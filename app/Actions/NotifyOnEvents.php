<?php

namespace App\Actions;

use App\Models\DuplicateLeadReview;
use App\Models\Employee;
use App\Models\EmployeeChangeRequest;
use App\Models\Lead;
use App\Models\LeadTransfer;
use App\Models\Payment;
use App\Models\Team;

/**
 * Who hears about what. Each role is told only what it can act on or needs to know:
 *
 * - Sales executives and team leaders: new leads, follow-ups (see SendFollowUpNotices), transfer outcomes,
 *   payment outcomes, conversions, leave decisions.
 * - Team leaders and supervisors: transfers waiting for them, Lazy team members, leave to approve, conversions.
 * - Accounts: payments waiting to be verified.
 * - Marketing: conversions that came from a campaign, leave decisions.
 * - Administrators and developers: duplicate leads to review, change requests, payments, Lazy people, leave to approve.
 */
class NotifyOnEvents
{
    public static function register(): void
    {
        LeadTransfer::created(fn (LeadTransfer $transfer) => rescue(fn () => self::transferRequested($transfer)));
        LeadTransfer::updated(fn (LeadTransfer $transfer) => rescue(fn () => self::transferDecided($transfer)));
        Payment::created(fn (Payment $payment) => rescue(fn () => self::paymentSubmitted($payment)));
        Payment::updated(fn (Payment $payment) => rescue(fn () => self::paymentReviewed($payment)));
        DuplicateLeadReview::created(fn (DuplicateLeadReview $review) => rescue(fn () => self::duplicateHeld($review)));
        EmployeeChangeRequest::created(fn (EmployeeChangeRequest $request) => rescue(fn () => self::changeRequested($request)));
        EmployeeChangeRequest::updated(fn (EmployeeChangeRequest $request) => rescue(fn () => self::changeDecided($request)));
    }

    private static function notifier(): Notifier
    {
        return app(Notifier::class);
    }

    private static function transferRequested(LeadTransfer $transfer): void
    {
        $notifier = self::notifier();
        $teams = Team::query()->with('teamLeader.user')->whereIn('id', array_filter([$transfer->from_team_id, $transfer->to_team_id]))->get();
        $approvers = $teams->pluck('teamLeader')->filter()->reject(fn (Employee $leader): bool => $leader->id === $transfer->from_employee_id);

        if ($approvers->isEmpty()) {
            $approvers = $notifier->admins();
        }

        $from = Employee::query()->find($transfer->from_employee_id)?->name ?? 'A sales person';
        $to = Employee::query()->find($transfer->to_employee_id)?->name ?? 'another executive';

        $notifier->send($approvers, 'transfer.requested', 'Lead transfer waiting for you', "{$from} asks to move lead L-{$transfer->lead_id} to {$to}. Reason: {$transfer->reason}", route('lead-transfers.index'), 'amber', "transfer:{$transfer->id}");
    }

    private static function transferDecided(LeadTransfer $transfer): void
    {
        if (! $transfer->wasChanged('status') || ! in_array($transfer->status, ['Approved', 'Rejected'], true)) {
            return;
        }

        $notifier = self::notifier();
        $notifier->resolve("transfer:{$transfer->id}");
        $approved = $transfer->status === 'Approved';
        $people = Employee::query()->with('user')->whereIn('id', array_filter([$transfer->from_employee_id, $transfer->to_employee_id]))->get();

        foreach ($people as $person) {
            $received = $approved && $person->id === $transfer->to_employee_id;
            $notifier->send(
                $person,
                'transfer.decided',
                $received ? 'A lead was transferred to you' : ($approved ? 'Your transfer was approved' : 'Your transfer was rejected'),
                $received
                    ? "Lead L-{$transfer->lead_id} is now yours, with its follow-ups."
                    : "Lead L-{$transfer->lead_id}: ".($approved ? 'it moved to the new owner.' : ($transfer->decision_note ?: 'no reason was given.')),
                $received ? route('leads.workspace', $transfer->lead_id) : route('sales.tasks'),
                $approved ? 'green' : 'red',
            );
        }
    }

    private static function paymentSubmitted(Payment $payment): void
    {
        if ($payment->status !== 'Pending') {
            return;
        }

        $notifier = self::notifier();
        $notifier->send(
            $notifier->withRole('Accounts')->merge($notifier->admins()),
            'payment.submitted',
            'Payment waiting for verification',
            'A payment of '.number_format((float) $payment->amount).' needs to be checked against the bank or wallet record.',
            route('payments.index'),
            'amber',
            "payment:{$payment->id}",
        );
    }

    private static function paymentReviewed(Payment $payment): void
    {
        if (! $payment->wasChanged('status') || ! in_array($payment->status, ['Verified', 'Rejected'], true)) {
            return;
        }

        $notifier = self::notifier();
        $notifier->resolve("payment:{$payment->id}");
        $lead = $payment->enrollment?->lead;

        if ($lead === null) {
            return;
        }

        $owner = $lead->currentOwner;
        $verified = $payment->status === 'Verified';

        $notifier->send(
            $owner,
            'payment.reviewed',
            $verified ? 'Payment verified, lead converted' : 'Payment rejected',
            $verified
                ? "{$lead->student_name} is enrolled. The payment of ".number_format((float) $payment->amount).' was verified.'
                : "The payment for {$lead->student_name} was rejected: ".($payment->rejection_reason ?: 'no reason given.').' Ask the student to pay again.',
            route('leads.workspace', $lead->id),
            $verified ? 'green' : 'red',
        );

        if ($verified) {
            self::leadConverted($lead);
        }
    }

    /**
     * A converted lead is good news for the owner, their managers and the marketer whose campaign brought it in.
     */
    private static function leadConverted(Lead $lead): void
    {
        $notifier = self::notifier();
        $owner = $lead->currentOwner;
        $campaign = $lead->source?->campaign_name;
        $title = "Converted: {$lead->student_name}";

        $notifier->send($owner, 'lead.converted', $title, 'Well done. The payment was verified and the student is enrolled.', route('leads.workspace', $lead->id), 'green');

        if ($owner !== null) {
            $notifier->send($notifier->managersOf($owner, withAdmins: false), 'lead.converted_team', $title, "{$owner->name} converted this lead.", route('leads.index', ['search' => 'L-'.$lead->id]), 'green');
        }

        if ($campaign !== null) {
            $notifier->send($notifier->withRole('MarketingExecutive'), 'lead.converted_campaign', "Campaign conversion: {$campaign}", "{$lead->student_name} enrolled from this campaign.", route('leads.index', ['search' => 'L-'.$lead->id]), 'green');
        }
    }

    private static function duplicateHeld(DuplicateLeadReview $review): void
    {
        $notifier = self::notifier();
        $notifier->send($notifier->admins(), 'duplicate.held', 'Duplicate lead needs a decision', 'A new inquiry uses a phone number that already exists. '.($review->match_reason ?? ''), route('duplicate-lead-reviews.index'), 'amber', "duplicate:{$review->id}");
    }

    private static function changeRequested(EmployeeChangeRequest $request): void
    {
        $notifier = self::notifier();
        $name = Employee::query()->find($request->employee_id)?->name ?? 'An employee';

        $notifier->send($notifier->admins()->reject(fn (Employee $admin): bool => $admin->id === $request->requested_by_employee_id), 'change.requested', 'Profile change waiting for approval', "A change to {$name}'s essential details was requested. Reason: {$request->reason}", route('employee-change-requests.index'), 'amber', "change:{$request->id}");
    }

    private static function changeDecided(EmployeeChangeRequest $request): void
    {
        if (! $request->wasChanged('status') || $request->status === 'Pending') {
            return;
        }

        $notifier = self::notifier();
        $notifier->resolve("change:{$request->id}");
        $notifier->send(
            Employee::query()->with('user')->find($request->requested_by_employee_id),
            'change.decided',
            'Profile change '.strtolower($request->status),
            $request->review_note ?: 'The administrator decided your request.',
            route('employee-change-requests.index'),
            $request->status === 'Approved' ? 'green' : 'red',
        );
    }
}
