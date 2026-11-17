<?php

/*
|--------------------------------------------------------------------------
| Glossary
|--------------------------------------------------------------------------
|
| Plain-language explanations shown beside the (i) icon on figures, cards
| and table headings, and as hover text on status pills. The key is the
| exact label printed on the screen. Settings are named, not quoted, so a
| changed setting never makes this text wrong.
|
*/

return [
    'terms' => [
        // Figures
        'Unassigned queue' => 'New leads that nobody owns yet. They wait here until the engine or an admin gives them to a sales executive.',
        'Active batches' => 'Batches being worked right now. An executive holds one batch at a time and receives the next only after giving feedback on every lead in it.',
        'Overdue follow-ups' => 'Call-backs whose exact time has passed. They stay overdue until a new follow-up, a conversion or another outcome is logged. The owner is notified at once and again every few hours.',
        'Payments under review' => 'Payments students have submitted that Accounts has not yet verified. Each one holds the executive’s batch slot until it is decided.',
        'Pending transfers' => 'Requests to move a lead to another executive that still need a decision. They approve themselves after the waiting period in Settings.',
        'Converted today' => 'Enrollments whose payment Accounts verified today, with the money confirmed alongside.',
        'Leads in queue' => 'Every new lead with no owner, from both Auto and Manual campaigns.',
        'Auto-distributed' => 'Queue leads from campaigns set to Auto. Only these are handed out by the engine.',
        'Manual campaigns' => 'Queue leads from campaigns set to Manual. They stay in the queue until an admin assigns them.',
        'Available executives' => 'Sales executives who can receive leads right now: active, on shift, outside the last minutes of the shift, with no unfinished batch and not flagged Lazy. The engine divides the queue among them.',
        'Waiting for review' => 'Payments submitted and not yet verified or rejected.',
        'Amount waiting' => 'Total taka in the payments that are waiting for review.',
        'Verified today' => 'Taka that Accounts confirmed today.',
        'Rejected' => 'Payments Accounts refused, usually because the reference or the amount did not match the statement.',
        'Leads received' => 'Leads created in the chosen period, whatever their status now.',
        'Assigned' => 'Of the leads received, how many have an owner now.',
        'Converted' => 'Leads that became paying students, after Accounts verified the payment.',
        'Conversion rate' => 'Converted leads divided by leads received in the period.',
        'Collected' => 'Taka in payments verified during the period.',
        'Current batch' => 'Your leads for this round. Give feedback on every one to finish the batch and receive more.',
        'Follow-ups coming up' => 'Call-backs inside the reminder window: from a set number of hours before their time (30 by default) until the exact time. The owner is reminded once. After the exact time they become overdue.',
        'Open leads' => 'Leads still in progress: assigned, contacted, in follow-up or interested.',
        'Awaiting payment' => 'Leads that are enrolled but not yet confirmed by Accounts. They count as converted only after verification.',
        'Executives' => 'Active sales executives in the teams you oversee.',
        'Converted this month' => 'Leads assigned this month that have become students.',
        'Transfers to decide' => 'Transfer requests involving your team that wait for a team leader’s decision.',
        'Teams' => 'The teams you supervise.',
        'Waiting for an owner' => 'New leads that have not been given to any executive yet.',
        'In duplicate review' => 'Leads held because the phone number already exists. An admin decides whether each is new, merged or discarded.',
        'Active campaigns' => 'Campaigns that are open for new leads.',

        // Cards
        'Distribution engine — next cycle' => 'A preview of what the next cycle will do with the queue and the executives available now. Nothing is assigned until the cycle runs.',
        'Next distribution cycle' => 'A preview of what the next cycle will do with the queue and the executives available now. Nothing is assigned until the cycle runs.',
        'Lead pipeline' => 'How many leads received in the period sit at each status, from New to Converted.',
        'Pipeline by status' => 'How many leads received in the period sit at each status, from New to Converted.',
        'Executive roster — today' => 'Each active sales executive with today’s shift, availability, batch progress and overdue follow-ups. Those needing attention come first.',
        'Needs your attention' => 'Work that is waiting for an admin: payments to verify, transfers to decide and installments falling due.',
        'Executives flagged Lazy' => 'Executives with a follow-up overdue for longer than the Lazy threshold in Settings. They receive no new leads until it is handled.',
        'Leads waiting' => 'Queue leads, freshest first. Hot and warm leads are distributed before cool and cold ones.',
        'Call next' => 'Your calling order: hot new leads, due call-backs, then the rest by freshness. Start at number 1.',
        'Assigned but not yet called' => 'Leads given to an executive that have had no first call, longest wait first. A lead cools with every hour it waits.',
        'Executives — today' => 'Today’s status of each executive, with those who need attention first: Lazy, then most overdue, then most uncalled leads.',
        'Leads by channel' => 'Where the leads of this period came from.',
        'Campaign performance' => 'For each campaign: leads received, leads still waiting for an owner, conversions and conversion rate.',
        'Leads by status' => 'How many leads received in the period sit at each status.',
        'Sales executives' => 'Leads assigned in the period and what became of them, per executive.',
        'Access log' => 'Every time a password was shown or reset: who, whom, why and when. Entries cannot be edited.',
        'Environment' => 'The settings of the server this copy of the system is running on.',
        'Record counts' => 'Rows in the main tables of the current database.',
        'Developer activity' => 'Every panel switch and tool run by a developer.',
        'Call feedback' => 'Record what happened on the call. Saving it sets the lead’s status, schedules the next call-back and frees your batch slot.',
        'Transfer this lead' => 'Ask for the lead to go to an executive in another team. It stays with you until the team leaders approve.',
        'History' => 'Every call logged on this lead and every follow-up scheduled for it.',

        // Performance
        'Calls per working day' => 'Calls logged divided by the days in the period up to today, leaving out Fridays. Compared with the daily target set in Settings.',
        'First-call time (median)' => 'The typical wait between a lead being assigned and its first logged call. Half of the leads were called faster than this. Lower is better.',
        'Follow-ups kept on time' => 'Share of promised call-backs that were done within an hour of the promised time.',
        'Revenue collected' => 'Taka in verified payments for enrollments sold by this person.',
        'Sales people' => 'Sales executives and team leaders. Team leaders call leads too.',
        'Duplicate share' => 'Leads sent to duplicate review as a share of all leads that arrived, counting the duplicates.',
        'Assigned, never called' => 'Leads that have an owner but no logged call yet. They cool with every hour.',
        'Payments verified' => 'Payments Accounts confirmed in the period, with the taka they total.',
        'Median decision time' => 'The typical time from a payment being submitted to Accounts verifying or rejecting it.',
        'Rejection rate' => 'Rejected payments as a share of all payments decided in the period.',
        'Calls today' => 'Calls you have logged today.',
        'Last 6 weeks' => 'Calls logged and leads received, week by week.',
        'Performance by person' => 'Each sales person against the targets. Green meets the target, amber is within a fifth of it, red is further away.',
        'Leave' => 'Who is on leave today and how many leave requests wait for your decision.',
        'On leave today' => 'Employees whose approved leave covers today. They receive no new leads.',

        // Sharing-rule terms
        'Queue leads (L)' => 'L: how many auto-distributed leads are waiting in the queue.',
        'Available (S)' => 'S: how many executives can receive leads right now.',
        'Rule' => 'Which case of the sharing rule applies, from how L compares with S and the batch size. “2S ≤ L < 3S” means every executive gets two leads and the rest go one each to the executives idle longest.',

        // Table headings
        'Priority' => 'How fresh the lead is: Hot, Warm, Cool or Cold. Fresher leads are distributed first.',
        'Distribution' => 'Auto: the engine hands the lead out. Manual: only an admin assigns it.',
        'Waiting' => 'How long the lead has been in the queue.',
        'Batch' => 'Leads finished out of the leads in the executive’s current batch.',
    ],

    'statuses' => [
        'Available' => 'Can receive new leads now.',
        'Busy' => 'Has an unfinished batch, so receives nothing new until it is finished.',
        'Cut Off' => 'In the last minutes of the shift. No new leads are given.',
        'Manual' => 'Never receives leads from the engine. Only a person can hand over a lead.',
        'Lazy' => 'Has a follow-up overdue beyond the Lazy threshold. Receives no new leads until it is handled.',
        'Off Duty' => 'Outside shift hours, or no shift set.',
        'Holiday' => 'Marked as a day off for this date.',
        'On Leave' => 'Has approved leave today. Receives no new leads.',
        'Unavailable' => 'Marked unavailable for this date.',
        'Resigned' => 'No longer with the company.',
        'Hot' => 'Received within the hot window. Called before anything else.',
        'Warm' => 'Still fresh. Distributed right after the hot leads.',
        'Cool' => 'Getting stale. Distributed after fresh leads.',
        'Cold' => 'Waited too long. Called through the slot reserved for the longest-waiting lead.',
        'Auto' => 'The engine may hand out this campaign’s leads.',
        'Manual' => 'Only an admin assigns this campaign’s leads.',
        'New' => 'In the queue with no owner.',
        'Assigned' => 'With an executive, not yet called.',
        'Follow-up' => 'Called; a call-back is scheduled.',
        'Interested' => 'The customer wants the course.',
        'Enrolled - Pending Payment' => 'Enrollment created; payment awaited.',
        'Payment Under Review' => 'Payment submitted; Accounts is checking it.',
        'Converted' => 'Payment verified. The lead became a student.',
        'Not Interested' => 'Closed: the customer declined.',
        'Unreachable' => 'Closed: the customer could not be reached.',
        'Lost' => 'Closed: the lead is lost.',
        'Invalid/Junk' => 'Closed: a wrong number or a fake inquiry. A wrong-number call closes the lead this way.',
        'Pending' => 'Waiting for a decision.',
        'Verified' => 'Confirmed by Accounts.',
        'Approved' => 'Approved and done.',
        'Rejected' => 'Refused. A reason is recorded.',
        'Paid' => 'Installment paid and verified.',
        'Overdue' => 'Past its due time and not done.',
        'Under Review' => 'Submitted; waiting for Accounts to check.',
        'Pending Payment' => 'Waiting for the student to pay.',
        'Enrolled' => 'The student is enrolled.',
    ],
];
