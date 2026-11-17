<?php

namespace App\Http\Controllers;

use App\Models\Employee;
use App\Models\LeadFollowup;
use App\Models\SystemSetting;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\View\View;

class GuideController extends Controller
{
    /**
     * Role keys as stored on employees, with the name shown in the guide.
     */
    private const ROLE_LABELS = [
        'Sales Executive' => 'Sales executive',
        'Team Leader' => 'Team leader',
        'Supervisor' => 'Supervisor',
        'MarketingExecutive' => 'Marketer',
        'Accounts' => 'Accounts',
        'Admin' => 'Administrator',
        'Developer' => 'Developer',
    ];

    public function index(Request $request): View
    {
        $user = $request->user();
        $employee = $user instanceof User ? $user->employee : null;
        abort_unless($employee instanceof Employee, 403);

        $manuals = $this->manuals();
        $chosen = $employee->isAdmin() ? (string) $request->query('view', 'rules') : $employee->role;
        if ($chosen !== 'rules' && ! isset($manuals[$chosen])) {
            $chosen = $employee->isAdmin() ? 'rules' : 'Sales Executive';
        }

        return view('guide.index', [
            'employee' => $employee,
            'chosen' => $chosen,
            'roleLabels' => self::ROLE_LABELS,
            'manual' => $chosen === 'rules' ? null : $manuals[$chosen],
            'chapters' => $chosen === 'rules' ? $this->systemRules() : [],
            'settings' => collect(SystemSetting::DEFINITIONS)->map(fn (array $definition, string $key): array => $definition + ['value' => SystemSetting::valueOf($key)]),
        ]);
    }

    /**
     * The full rule book, shown to administrators only.
     *
     * @return list<array{id: string, title: string, intro: string, points: list<string>}>
     */
    private function systemRules(): array
    {
        $n = SystemSetting::integer('lead_batch_size');
        $hot = SystemSetting::integer('hot_lead_minutes');
        $warm = SystemSetting::integer('warm_lead_hours');
        $cold = SystemSetting::integer('cold_lead_hours');
        $cutoff = SystemSetting::integer('assignment_cutoff_minutes');
        $lazy = SystemSetting::integer('lazy_threshold_hours');
        $transferDays = SystemSetting::integer('transfer_auto_approve_days');
        $reminder = SystemSetting::integer('installment_reminder_days');

        return [
            [
                'id' => 'lifecycle',
                'title' => 'Lead life cycle',
                'intro' => 'Every lead has exactly one status and at most one owner at a time.',
                'points' => [
                    'New — received, waiting in the central queue with no owner.',
                    'Assigned — given to a sales executive (by the engine or by hand) and not yet called.',
                    'Follow-up — called; a call-back is scheduled. Interested — the customer wants the course.',
                    'Enrolled - Pending Payment, then Payment Under Review — an enrollment exists and money is awaited or being checked.',
                    'Converted — Accounts verified a payment. Not Interested, Unreachable and Lost are closed outcomes.',
                    'Returning a lead to the queue makes it New again and remembers the previous owner.',
                ],
            ],
            [
                'id' => 'intake',
                'title' => 'Lead intake and duplicates',
                'intro' => 'Leads arrive by CSV upload, single entry or quick-create, always under a campaign.',
                'points' => [
                    'A campaign has a channel, an optional course and a distribution mode: Auto (the engine may take its leads) or Manual (only you assign them).',
                    'A new lead whose phone number already exists is not created. It goes to Duplicate review, where an admin approves it as new, merges it or discards it.',
                    'Channels where the customer contacted us first (phone, reference, WhatsApp, website, Google form) count as high intent and are served first inside the same temperature.',
                ],
            ],
            [
                'id' => 'priority',
                'title' => 'Lead priority: hot, warm, cool, cold',
                'intro' => 'The chance of reaching and enrolling a person falls quickly with every hour that passes after they ask. The system therefore ranks leads by freshness.',
                'points' => [
                    "Hot — received within {$hot} minutes. Warm — within {$warm} hours. Cool — within {$cold} hours. Cold — older than that.",
                    'Distribution order: hot first, then warm, newest first; high-intent channels go ahead inside the same temperature.',
                    'One slot in every four of a cycle is reserved for the longest-waiting cool or cold lead, so that old leads are still called and never wait forever.',
                    'On an executive’s task list the order is: hot new leads, promised call-backs that are due (interested customers first), then warm, cool and cold new leads.',
                    'Team leaders see “Assigned but not yet called”, longest wait first, so they can chase a first call before a lead cools.',
                ],
            ],
            [
                'id' => 'engine',
                'title' => 'Distribution engine',
                'intro' => "Leads are given in batches of up to {$n}. An executive receives a new batch only after finishing the current one.",
                'points' => [
                    'Who can receive: active sales executives and team leaders whose status is Available right now — on shift, outside the last '.$cutoff.' minutes of the shift, with no open batch and not flagged Lazy.',
                    "How many: with L auto-queue leads and S available executives, each gets min({$n}, L ÷ S rounded down). If that is less than {$n}, the remainder goes one each to the executives idle longest.",
                    'Dealing is one lead at a time in rounds, so the hottest leads are spread across executives instead of all going to one person.',
                    'Automatic distribution runs every minute when the engine switch is ON. When it is OFF, leads move only when you press “Run distribution now” or assign by hand.',
                    'Manual assignment from the central queue adds the leads to the executive’s open batch, or opens one.',
                ],
            ],
            [
                'id' => 'working',
                'title' => 'Working a lead',
                'intro' => 'Only the owner records calls. Every call needs a result, an interest level, a note and a next action.',
                'points' => [
                    'Next actions: Follow-up (a call-back time is required), Interested, Not Interested, Unreachable, Lost. The lead’s status follows the next action.',
                    'Saving feedback closes any earlier open follow-up for that lead, schedules the new one, and frees the batch slot.',
                    'A lead in enrollment (pending payment or under review) holds its batch slot until Accounts verifies the payment, and cannot take further call feedback.',
                    'Team leaders and supervisors can read a lead’s history but cannot record calls for it.',
                ],
            ],
            [
                'id' => 'repeat',
                'title' => 'Repeat inquiries and duplicate numbers',
                'intro' => 'The same customer often asks more than once, and often from a different number. The system matches them and decides for itself.',
                'points' => [
                    'A lead can have extra numbers (a parent, a sibling). A call, upload or paste from any of them finds the same lead instead of creating a second one. Use Number check to look a number up before adding it.',
                    'Every inquiry is logged on the lead with its campaign. A repeat inquiry on a lead that was closed (not interested, unreachable, lost, invalid) reopens it and gives it back to its previous owner, outside their daily limit. If that person has left, it returns to the queue.',
                    'A repeat inquiry on an open lead sets a follow-up for its owner, but only when it is a new day. On the same day it is only logged. An enrolled or paying student is only logged.',
                    'A wrong-number call closes the lead as Invalid/Junk. A call that connected and left the lead open needs a call-back time.',
                    'Administrators can merge two records of one customer. Calls, follow-ups, numbers and inquiries all move to the kept lead.',
                ],
            ],
            [
                'id' => 'assignment',
                'title' => 'Who receives leads automatically',
                'intro' => 'Each sales person is either Auto or Manual, with an optional daily limit.',
                'points' => [
                    'Auto people receive leads from the engine. Manual people never do: only a person can hand them a lead.',
                    'The daily limit caps what the engine gives someone in a day. Leads handed over by a person, and returning customers, do not count. Set both under Assignment controls.',
                    'Data health lists leads that have quietly got stuck: held by someone who has left, engaged with no follow-up, waiting too long in the queue, or with odd phone numbers. The safe fixes are one click.',
                ],
            ],
            [
                'id' => 'conversion',
                'title' => 'Converting a lead into a student',
                'intro' => 'A lead is converted only when money is verified, never because someone says so.',
                'points' => [
                    'On the lead page, the owner presses Enroll this student: picks the course, gives a discount (up to '.SystemSetting::integer('max_sales_discount_percent').'%) and sets 1 to '.SystemSetting::integer('max_installments').' installments, 30 days apart. A seat is reserved in the first batch with room. The lead becomes Enrolled - Pending Payment and its open follow-ups close.',
                    'The owner collects an installment and presses Send to Accounts with the amount, the method, the transaction ID and the slip. The amount must equal the installment. The lead becomes Payment Under Review and Accounts is notified.',
                    'Accounts verify it against the bank or wallet record. Verified: the installment is Paid, the enrollment is Enrolled, the lead is Converted and the batch slot is released. Rejected: the reason goes back to the owner, who submits again.',
                    'Later installments are submitted the same way and verified the same way.',
                ],
            ],
            [
                'id' => 'followups',
                'title' => 'Follow-ups and the Lazy rule',
                'intro' => 'A follow-up is a promise to call back at a set time. Keeping it is the strongest habit behind conversions.',
                'points' => [
                    'A follow-up has two stages. Coming up: from '.LeadFollowup::noticeHours().' hours before its time until the exact time. The owner gets a reminder when that window opens, and it shows on the dashboard. Overdue: from the exact time onward, until the sales person logs a new follow-up, a conversion or another outcome. The owner is told at the exact time and again every '.SystemSetting::integer('followup_overdue_repeat_hours').' hours.',
                    "An executive with a follow-up overdue for more than {$lazy} hours is flagged Lazy, and so is reported to the team leader, the supervisors and the administrators.",
                    'A Lazy executive receives no new batch until the overdue follow-ups are handled.',
                    'An admin can move an open follow-up, with its lead, to another executive or back to the central queue.',
                ],
            ],
            [
                'id' => 'notifications',
                'title' => 'Notifications',
                'intro' => 'The bell at the top right and the Notifications page tell each role what it can act on. Opening a notification takes you to the work and marks it read.',
                'points' => [
                    'Sales executives and team leaders: new leads in a batch, follow-ups coming up, overdue follow-ups, transfer outcomes, payment results, conversions and leave decisions.',
                    'Team leaders and supervisors: transfers waiting for them, team members flagged Lazy, leave to approve and team conversions.',
                    'Accounts: payments waiting to be verified. Marketing: conversions that came from a campaign. Administrators and developers: duplicate leads, profile changes, payments, Lazy people and leave to approve.',
                    'A reminder disappears from the unread list by itself once the work is done, for example when the follow-up is called and an outcome is logged.',
                    'Notifications are created by the scheduler, so it must be running (php artisan schedule:work).',
                ],
            ],
            [
                'id' => 'transfers',
                'title' => 'Transfers',
                'intro' => 'An executive may ask to hand a lead to an executive in a team. The lead stays with its owner until the transfer is approved.',
                'points' => [
                    'The leader of the sending team and the leader of the receiving team must both approve. A team without a leader needs no approval from that side.',
                    "A request still pending after {$transferDays} days is approved automatically.",
                    'An admin can approve or reject any request at once. Rejection always needs a reason.',
                    'On approval the lead, its open follow-ups and its team move to the new owner, and the old owner’s batch slot is freed.',
                ],
            ],
            [
                'id' => 'performance',
                'title' => 'Performance and KPIs',
                'intro' => 'Four KPIs measure how well each sales person works the leads they are given. Targets are the same for everyone and are set in Settings.',
                'points' => [
                    'Calls per working day: logged calls divided by the days in the period up to today, leaving out Fridays. Target '.SystemSetting::integer('kpi_calls_per_day').'.',
                    'First-call time: the median wait between a lead being assigned and its first logged call. Target '.SystemSetting::integer('kpi_first_call_minutes').' minutes or less.',
                    'Follow-ups kept on time: share of promised call-backs done within an hour of the promised time. Target '.SystemSetting::integer('kpi_followup_on_time_percent').'%.',
                    'Conversion rate: assigned leads that have since converted. Target '.SystemSetting::integer('kpi_conversion_percent').'%.',
                    'A figure is green when it meets its target, amber when within a fifth of it, red when further away.',
                    'Sales executives and team leaders see their own KPIs and a six-week trend. Team leaders and supervisors also see their people; administrators see everyone, plus marketing and Accounts figures.',
                ],
            ],
            [
                'id' => 'leave',
                'title' => 'Leave',
                'intro' => 'Everyone except developers can apply for leave. A request names the type, the dates and a reason.',
                'points' => [
                    'Approval follows the hierarchy. A sales executive needs two approvals: the team leader first, then a supervisor or administrator. A team leader, marketer or accounts person needs one, from a supervisor or administrator. A supervisor needs one, from an administrator. An administrator needs none: the leave is approved automatically. Nobody decides their own.',
                    'An administrator can settle any request at once, even before the team leader. A rejection at any step ends the request and always needs a reason.',
                    'Leave follows the Bangladesh Labour Act 2006: Casual '.SystemSetting::integer('leave_casual_days').' days a year (section 115), Sick '.SystemSetting::integer('leave_sick_days').' days a year (section 116, a registered doctor’s certificate for more than '.SystemSetting::integer('leave_sick_certificate_days').' days), Annual 1 day for every '.SystemSetting::integer('leave_earned_work_days').' days worked (section 117, usable after one year of service) and Maternity '.SystemSetting::integer('leave_maternity_days').' days for women (section 46, after 6 months of service). Unpaid leave has no limit.',
                    'Friday, the weekly holiday, is not counted as leave (maternity leave counts every day). A request cannot start in the past or overlap another, and it cannot be longer than the days left; ask for the rest as unpaid leave. Festival holidays are not leave, so do not apply for them.',
                    'While approved leave covers today the person’s status is On Leave: they receive no new leads, and appear on their manager’s dashboard.',
                ],
            ],
            [
                'id' => 'finance',
                'title' => 'Enrollment, payments and installments',
                'intro' => 'Money is confirmed by Accounts, never by sales.',
                'points' => [
                    'Verifying a payment marks the installment Paid, the enrollment Enrolled and the lead Converted, and frees the executive’s batch slot.',
                    'Rejecting a payment needs a reason and puts the installment, enrollment and lead back to awaiting payment.',
                    "Installments due within {$reminder} days are listed as Due soon; unpaid installments past their date are Overdue.",
                ],
            ],
            [
                'id' => 'availability',
                'title' => 'Shifts and availability',
                'intro' => 'Availability is worked out from the shift and the clock, with date overrides on top.',
                'points' => [
                    'Shift order: a roster override for the date, then the team’s shift, then the employee’s own shift.',
                    'Statuses: Available, Busy (open batch), Cut Off (last '.$cutoff.' minutes of the shift), Lazy, On Leave (approved leave), Off Duty (outside the shift), Holiday, Unavailable, Resigned.',
                    'Inactive and suspended employees are signed out on their next click and receive nothing.',
                ],
            ],
            [
                'id' => 'access',
                'title' => 'Access and data protection',
                'intro' => 'Each role sees only what it needs.',
                'points' => [
                    'Sales executives see their own leads; team leaders their team’s (and their own); supervisors their teams’; marketers, admins and developers all leads.',
                    'Team leaders and supervisors can change only their own teams. Only an admin creates, deletes or deactivates a team.',
                    'Nobody can request a role or status change for themselves, and the last active administrator cannot be demoted or deactivated.',
                    'Staff passwords are stored in a form an admin can read back. Reading or resetting one requires the admin’s own password and a reason, and is written to a log that cannot be edited.',
                ],
            ],
        ];
    }

    /**
     * A training manual for each role.
     *
     * @return array<string, array{summary: string, can: list<string>, cannot: list<string>, tour: list<array{0: string, 1: string, 2: ?string}>, rules: list<string>, habits: list<string>}>
     */
    private function manuals(): array
    {
        $n = SystemSetting::integer('lead_batch_size');
        $hot = SystemSetting::integer('hot_lead_minutes');
        $lazy = SystemSetting::integer('lazy_threshold_hours');
        $cutoff = SystemSetting::integer('assignment_cutoff_minutes');
        $transferDays = SystemSetting::integer('transfer_auto_approve_days');

        return [
            'Sales Executive' => [
                'summary' => 'You call the leads the system gives you, record what happened, and keep every call-back you promise.',
                'can' => [
                    'See and work only the leads you own.',
                    'Record call feedback and schedule follow-ups.',
                    'Ask to transfer one of your leads to a sales person in a team.',
                    'Apply for leave and watch it being decided.',
                    'See your own KPIs against the company targets.',
                    'Update your own profile; job details go to your manager for approval.',
                ],
                'cannot' => [
                    'See or export other people’s leads.',
                    'Choose which leads you receive, or change a lead’s owner yourself.',
                    'Confirm a payment — only Accounts does that.',
                ],
                'tour' => [
                    ['Dashboard', 'Your status, batch progress, calls today, overdue follow-ups, the next people to call and your KPIs for the month.', 'dashboard'],
                    ['My tasks', 'One ranked list: start at number 1 and work down. Press “Call now”, make the call, save feedback.', 'sales.tasks'],
                    ['Lead page', 'Opened from any list. Tap the number to call, read the history, then fill in the feedback form.', null],
                    ['My follow-ups', 'Every call-back you promised: overdue, coming up, scheduled later and completed.', 'follow-ups.index'],
                    ['Notifications', 'The bell shows reminders for follow-ups coming up and overdue, new leads and other updates.', 'notifications.index'],
                    ['My leads', 'Search and filter everything you own.', 'leads.index'],
                    ['Performance', 'Your KPIs: calls a day, first-call time, follow-ups kept on time, conversion and revenue, with a six-week trend.', 'performance.index'],
                    ['Leave', 'Apply for leave, see your balance and cancel a request you no longer need.', 'leave.index'],
                ],
                'rules' => [
                    "You receive up to {$n} leads at a time. The next batch comes only after you give feedback on every lead in the current one.",
                    "A lead received in the last {$hot} minutes is Hot and sits at the top of your list. Call it first — the chance of reaching the person drops with every minute.",
                    "A follow-up overdue for more than {$lazy} hours flags you Lazy, and you stop receiving new leads until you clear it.",
                    "No new leads arrive in the last {$cutoff} minutes of your shift, while you are off shift, or while your approved leave covers today.",
                    'Choosing Follow-up requires a call-back date and time. Saving feedback frees the batch slot.',
                    "A transfer needs both team leaders to approve; after {$transferDays} days without a decision it is approved automatically.",
                    'Your KPIs are measured against the same targets for everyone; an administrator sets them in Settings.',
                    'Leave needs two approvals: your team leader first, then a supervisor or administrator. Balances follow the Labour Act: casual, sick, annual and maternity leave each have their own days.',
                ],
                'habits' => [
                    'Call hot leads within five minutes of seeing them.',
                    'Call back at the exact time you promised — being on time builds trust.',
                    'If nobody answers, schedule a follow-up at a different time of day instead of closing the lead.',
                    'Write notes the next person could act on: what the customer wants, what worries them, what you agreed.',
                    'Check your KPIs weekly; the slowest first call is usually the easiest one to improve.',
                ],
            ],
            'Team Leader' => [
                'summary' => 'You sell and you lead. You work leads like any sales executive, and you keep your team calling on time.',
                'can' => [
                    'Receive leads in batches, call them, record feedback and keep your own follow-ups — exactly like a sales executive.',
                    'Ask to transfer one of your leads to another sales person.',
                    'See your team’s leads, follow-ups, KPIs and each person’s status and batch progress.',
                    'Open your team page and update its members and duty time.',
                    'Approve or reject transfers that involve your team, and decide leave for your executives.',
                    'Apply for leave yourself; one approval from your supervisor or an administrator settles it. As the team leader you also give the first approval for your executives.',
                ],
                'cannot' => [
                    'See or change other teams.',
                    'Create, delete or deactivate a team, or assign leads — an admin does that.',
                    'Record calls on another person’s lead, or reassign a follow-up.',
                ],
                'tour' => [
                    ['Dashboard', 'Two parts. “My work”: your status, batch, call order and KPIs. “My team”: team KPIs, who is on leave, leads not yet called, and the roster with those who need attention first.', 'dashboard'],
                    ['My tasks', 'Your own ranked call list, the same as a sales executive’s.', 'sales.tasks'],
                    ['My follow-ups', 'The call-backs you promised yourself.', 'follow-ups.mine'],
                    ['Team follow-ups', 'Your whole team’s call-backs, yours included. Filter by person to coach one.', 'follow-ups.index'],
                    ['Transfers', 'Requests involving your team, with the day counter. Approve, or reject with a reason.', 'lead-transfers.index'],
                    ['All leads', 'Every lead in your team, with filters.', 'leads.index'],
                    ['Teams & roster', 'Your team’s members, shift and date overrides.', 'teams.index'],
                    ['Performance', 'Your KPIs and a table of everyone in your team against the targets.', 'performance.index'],
                    ['Leave and Leave requests', 'Your own leave, and the leave your executives are waiting on.', 'leave.index'],
                ],
                'rules' => [
                    'You are in the distribution like everyone else: you receive batches when you are Available, and a lead you hold counts toward the Lazy rule.',
                    'The roster is sorted by need: Lazy first, then most overdue follow-ups, then most uncalled leads.',
                    "An executive with a follow-up overdue more than {$lazy} hours is Lazy and receives no new leads.",
                    "A transfer moves the lead only when both team leaders approve, or automatically after {$transferDays} days. If you lead both teams, one approval is enough.",
                    'You give the first approval to your executives’ leave, then a supervisor or administrator decides. Yours needs one approval from your supervisor or an administrator.',
                    'Leads are distributed hot first, in batches of up to '.$n.'; a person must finish a batch to get the next.',
                ],
                'habits' => [
                    'Start the day with “Assigned but not yet called” and ask for those first calls.',
                    'Do your own first calls before coaching; your numbers set the example.',
                    'Check overdue follow-ups before lunch and before the shift ends.',
                    'Decide transfers and leave the same day — a waiting lead cools and a waiting request frustrates.',
                ],
            ],
            'Supervisor' => [
                'summary' => 'You watch several teams, compare them, and step in where follow-up discipline slips.',
                'can' => [
                    'See the leads, follow-ups, KPIs and people of every team you supervise, team leaders included.',
                    'Compare your teams on open leads, assignments and conversions this month.',
                    'Decide leave for the team leaders you supervise and for the executives of your teams.',
                    'Update the teams you supervise and review change requests from team leaders and executives.',
                    'Apply for leave; one approval from a supervisor or administrator settles it.',
                ],
                'cannot' => [
                    'See teams you do not supervise.',
                    'Create or delete teams, assign leads or decide transfers.',
                    'Record calls on a lead.',
                ],
                'tour' => [
                    ['Dashboard', 'Team KPIs, leave today, a comparison table, leads not yet called and the roster of your sales people.', 'dashboard'],
                    ['Follow-ups', 'Overdue and upcoming call-backs across your teams, with the Lazy list.', 'follow-ups.index'],
                    ['All leads', 'Every lead in your teams.', 'leads.index'],
                    ['Teams & roster', 'Open any of your teams.', 'teams.index'],
                    ['Performance', 'Every person in your teams against the KPI targets.', 'performance.index'],
                    ['Leave and Leave requests', 'Your own leave, and the requests you decide.', 'leave.index'],
                ],
                'rules' => [
                    "Lazy means a follow-up overdue for more than {$lazy} hours; it blocks new leads for that person.",
                    'Conversion rate on your dashboard is converted ÷ assigned for leads assigned this month.',
                    'Green on the performance table meets the target, amber is within a fifth of it, red is further away.',
                    'You see a team only after an admin lists you as its supervisor.',
                ],
                'habits' => [
                    'Compare teams weekly, not daily — small numbers mislead.',
                    'When a team’s overdue count rises, look at the roster for who is overloaded before blaming effort.',
                    'Decide leave early so the team leader can plan the batch load.',
                ],
            ],
            'MarketingExecutive' => [
                'summary' => 'You bring leads in: create campaigns, upload leads, and watch which campaigns turn into enrollments.',
                'can' => [
                    'Create and edit campaigns.',
                    'Upload leads by CSV or add one by hand.',
                    'See all leads and how each campaign performs.',
                ],
                'cannot' => [
                    'Decide duplicate reviews — an admin does that.',
                    'Run distribution or see the staff screens.',
                ],
                'tour' => [
                    ['Dashboard', 'Leads received, conversion rate, leads still waiting for an owner, leads by channel and campaign performance for the period you pick.', 'dashboard'],
                    ['Campaigns', 'One campaign per source of leads. Set the channel, the course and whether distribution is Auto or Manual.', 'campaigns.index'],
                    ['Upload leads', 'Pick the campaign, download the template, fill it in and upload.', 'leads.upload'],
                    ['All leads', 'Check what happened to the leads you brought in.', 'leads.index'],
                    ['Performance', 'Leads received, duplicate share, leads still waiting and the campaign table.', 'performance.index'],
                    ['Leave', 'Apply for leave; one approval from a supervisor or administrator settles it.', 'leave.index'],
                ],
                'rules' => [
                    'A phone number that already exists is held in duplicate review instead of becoming a second lead.',
                    "Leads are freshest in their first {$hot} minutes. Upload as soon as leads arrive — a batch uploaded a day late starts warm or cool.",
                    'Leads of a Manual campaign wait until an admin assigns them; Auto campaigns go to the engine.',
                    'High-intent channels (phone, reference, WhatsApp, website, Google form) are served ahead of ad leads of the same age, so pick the channel accurately.',
                ],
                'habits' => [
                    'Upload during working hours, when executives are available to call at once.',
                    'Judge a campaign by conversion rate and by leads still waiting, not by lead count alone.',
                ],
            ],
            'Accounts' => [
                'summary' => 'You confirm the money. A lead is converted only when you verify a payment.',
                'can' => [
                    'See payments waiting for review and verify or reject them.',
                    'See every installment: due soon, overdue, upcoming, under review and paid.',
                ],
                'cannot' => [
                    'See leads, teams or sales screens.',
                    'Change an enrollment’s amounts.',
                ],
                'tour' => [
                    ['Payments review', 'Oldest first. Check the amount and transaction ID against the statement, then verify, or reject with a reason.', 'payments.index'],
                    ['Installments', 'Five views with counts and amounts. Start with Overdue and Due soon.', 'installments.index'],
                    ['Performance', 'Payments verified, how fast you decide them, and the rejection rate.', 'performance.index'],
                    ['Leave', 'Apply for leave; one approval from a supervisor or administrator settles it.', 'leave.index'],
                ],
                'rules' => [
                    'Verifying marks the installment Paid, enrolls the student, converts the lead and frees the executive’s batch slot.',
                    'Rejecting needs a reason and returns the enrollment to awaiting payment.',
                    'A decision cannot be made twice on the same payment.',
                ],
                'habits' => [
                    'Review payments at fixed times each day — executives cannot take new leads while their slot waits on you.',
                ],
            ],
            'Developer' => [
                'summary' => 'You hold every administrator permission and can open any employee’s panel to test and observe the system.',
                'can' => [
                    'Everything an administrator can do, including settings, staff passwords and the engine switch.',
                    'Open the system as any active employee and switch between panels from the banner.',
                    'Run maintenance tools and read recent errors from the developer panel.',
                    'Give the Developer role to another employee.',
                ],
                'cannot' => [
                    'Open another developer’s panel.',
                    'Apply for leave or have KPIs — open a sales person’s panel to see theirs.',
                ],
                'tour' => [
                    ['Developer panel', 'Switch panels, check the environment, scheduled commands and record counts, run tools, read errors and the activity log.', 'developer.index'],
                    ['Developer guide', 'Architecture, where each rule lives, recipes, testing and troubleshooting.', 'developer.guide'],
                    ['Everything else', 'The full administrator sidebar is available to you.', 'dashboard'],
                ],
                'rules' => [
                    'Every panel switch and tool run is recorded in the developer activity log.',
                    'While viewing as someone, what you save is saved under their name.',
                    'The developer panel is on by default outside production; DEVELOPER_PANEL controls it.',
                ],
                'habits' => [
                    'Test on a copy of the data when you can.',
                    'Return to your own account before ending the session.',
                ],
            ],
            'Admin' => [
                'summary' => 'You keep leads flowing and people set up correctly, and you decide what nobody else can.',
                'can' => [
                    'Everything in the system, including settings, staff passwords and every queue.',
                ],
                'cannot' => [
                    'Remove the last active administrator.',
                    'Read a password without giving your own password and a reason.',
                ],
                'tour' => [
                    ['Dashboard', 'Live queue, batches, overdue follow-ups, payments, transfers, the next distribution cycle and today’s roster.', 'dashboard'],
                    ['Central queue', 'Unowned leads, freshest first, with temperature. Run a cycle or assign by hand.', 'central-queue.index'],
                    ['Duplicate review', 'Decide leads held because the phone number already exists.', 'duplicate-lead-reviews.index'],
                    ['Follow-ups', 'All call-backs and the Lazy list; reassign or return a lead to the queue.', 'follow-ups.index'],
                    ['Transfers', 'Approve or reject any request.', 'lead-transfers.index'],
                    ['Payments review and Installments', 'The finance queues, shared with Accounts.', 'payments.index'],
                    ['Reports', 'Leads by executive, team and campaign for a period.', 'reports.index'],
                    ['Performance', 'KPIs of every sales person against the targets, plus marketing and Accounts figures.', 'performance.index'],
                    ['Leave requests', 'Decide leave for supervisors, marketers, accounts and other administrators.', 'leave.requests'],
                    ['Settings', 'Batch size, priority windows, cutoff, Lazy threshold, transfer and reminder days, leave allowance, KPI targets and the engine switch.', 'settings.index'],
                    ['Staff passwords', 'Read or reset a password; every use is logged.', 'staff-passwords.index'],
                ],
                'rules' => [
                    'Keep the scheduler running on the server, or automatic distribution and transfer auto-approval do not happen.',
                    'Turn the engine ON only when executives are on shift; when OFF, leads wait.',
                    'Set a shift on every team, otherwise its executives are always Off Duty and get nothing.',
                ],
                'habits' => [
                    'Clear duplicate review daily so genuine repeat inquiries are not left waiting.',
                    'Watch “Unassigned queue” against “executives available”: a growing queue with nobody available is a staffing problem, not a system one.',
                ],
            ],
        ];
    }
}
