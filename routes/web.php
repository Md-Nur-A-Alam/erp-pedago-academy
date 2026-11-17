<?php

use App\Http\Controllers\AssignmentControlController;
use App\Http\Controllers\Auth\AuthenticatedSessionController;
use App\Http\Controllers\Auth\PasswordController;
use App\Http\Controllers\CampaignController;
use App\Http\Controllers\CentralQueueController;
use App\Http\Controllers\CourseBatchController;
use App\Http\Controllers\CourseController;
use App\Http\Controllers\CourseTopicController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\DataHealthController;
use App\Http\Controllers\DeveloperController;
use App\Http\Controllers\DuplicateLeadReviewController;
use App\Http\Controllers\EmployeeChangeRequestController;
use App\Http\Controllers\EmployeeController;
use App\Http\Controllers\EmployeeProfileController;
use App\Http\Controllers\EnrollmentController;
use App\Http\Controllers\FollowUpController;
use App\Http\Controllers\GuideController;
use App\Http\Controllers\InstallmentController;
use App\Http\Controllers\LeadController;
use App\Http\Controllers\LeadMergeController;
use App\Http\Controllers\LeadPasteController;
use App\Http\Controllers\LeadPhoneController;
use App\Http\Controllers\LeadTransferController;
use App\Http\Controllers\LeadUploadController;
use App\Http\Controllers\LeaveController;
use App\Http\Controllers\NotificationController;
use App\Http\Controllers\NumberCheckController;
use App\Http\Controllers\PaymentReviewController;
use App\Http\Controllers\PerformanceController;
use App\Http\Controllers\ReportController;
use App\Http\Controllers\SalesWorkspaceController;
use App\Http\Controllers\StaffPasswordController;
use App\Http\Controllers\SubjectController;
use App\Http\Controllers\SystemSettingController;
use App\Http\Controllers\TeamController;
use Illuminate\Support\Facades\Route;

Route::redirect('/', '/login');

Route::middleware('guest')->group(function (): void {
    Route::get('/login', [AuthenticatedSessionController::class, 'create'])->name('login');
    Route::post('/login', [AuthenticatedSessionController::class, 'store'])->middleware('throttle:login')->name('login.store');
});

Route::middleware(['auth', 'employee.onboarding'])->group(function (): void {
    Route::post('/logout', [AuthenticatedSessionController::class, 'destroy'])->name('logout');
    Route::get('/password/change', [PasswordController::class, 'edit'])->name('password.change.edit');
    Route::put('/password/change', [PasswordController::class, 'update'])->name('password.change.update');
    Route::get('/profile', [EmployeeProfileController::class, 'edit'])->name('profile.edit');
    Route::put('/profile', [EmployeeProfileController::class, 'update'])->name('profile.update');

    Route::get('/dashboard', [DashboardController::class, 'index'])->name('dashboard');

    Route::get('/notifications', [NotificationController::class, 'index'])->name('notifications.index');
    Route::get('/notifications/count', [NotificationController::class, 'count'])->name('notifications.count');
    Route::post('/notifications/read-all', [NotificationController::class, 'readAll'])->name('notifications.read-all');
    Route::get('/notifications/{notification}/open', [NotificationController::class, 'open'])->name('notifications.open');

    Route::get('/developer', [DeveloperController::class, 'index'])->name('developer.index');
    Route::get('/developer/database', [DeveloperController::class, 'database'])->name('developer.database');
    Route::get('/developer/guide', [DeveloperController::class, 'guide'])->name('developer.guide');
    Route::post('/developer/view-as/{employee}', [DeveloperController::class, 'viewAs'])->name('developer.view-as');
    Route::post('/developer/return', [DeveloperController::class, 'return'])->name('developer.return');
    Route::post('/developer/tools/{tool}', [DeveloperController::class, 'tool'])->whereIn('tool', ['distribute', 'auto-approve', 'schedule-run', 'clear-views', 'clear-cache', 'engine-on', 'engine-off'])->name('developer.tools');
    Route::get('/guide', [GuideController::class, 'index'])->name('guide.index');
    Route::get('/reports', [ReportController::class, 'index'])->name('reports.index');

    Route::get('/my-tasks', [SalesWorkspaceController::class, 'tasks'])->name('sales.tasks');
    Route::get('/leads/{lead}/workspace', [SalesWorkspaceController::class, 'show'])->name('leads.workspace');
    Route::post('/leads/{lead}/feedback', [SalesWorkspaceController::class, 'feedback'])->name('leads.feedback');
    Route::post('/leads/{lead}/phones', [LeadPhoneController::class, 'store'])->name('lead-phones.store');
    Route::delete('/lead-phones/{leadPhone}', [LeadPhoneController::class, 'destroy'])->name('lead-phones.destroy');
    Route::post('/leads/{lead}/whatsapp-thanks', [SalesWorkspaceController::class, 'whatsappThanks'])->name('leads.whatsapp');
    Route::get('/number-check', [NumberCheckController::class, 'index'])->name('number-check.index');
    Route::get('/leads/{lead}/enroll', [EnrollmentController::class, 'create'])->name('enrollments.create');
    Route::post('/leads/{lead}/enroll', [EnrollmentController::class, 'store'])->name('enrollments.store');
    Route::post('/enrollments/{enrollment}/payments', [EnrollmentController::class, 'storePayment'])->name('enrollments.payments.store');
    Route::get('/payments/{payment}/slip', [EnrollmentController::class, 'slip'])->name('payments.slip');
    Route::post('/leads/{lead}/transfer-request', [SalesWorkspaceController::class, 'requestTransfer'])->name('leads.transfer-request');

    Route::get('/leads/paste', [LeadPasteController::class, 'create'])->name('leads.paste');
    Route::post('/leads/paste', [LeadPasteController::class, 'store'])->name('leads.paste.store');
    Route::get('/merge-duplicates', [LeadMergeController::class, 'index'])->name('leads.merge');
    Route::post('/merge-duplicates', [LeadMergeController::class, 'store'])->name('leads.merge.store');
    Route::get('/data-health', [DataHealthController::class, 'index'])->name('data-health.index');
    Route::post('/data-health', [DataHealthController::class, 'fix'])->name('data-health.fix');
    Route::get('/assignment-controls', [AssignmentControlController::class, 'index'])->name('assignment-controls.index');
    Route::put('/assignment-controls/{employee}', [AssignmentControlController::class, 'update'])->name('assignment-controls.update');

    Route::get('/central-queue', [CentralQueueController::class, 'index'])->name('central-queue.index');
    Route::post('/central-queue/distribute', [CentralQueueController::class, 'distribute'])->name('central-queue.distribute');
    Route::post('/central-queue/assign', [CentralQueueController::class, 'assign'])->name('central-queue.assign');

    Route::get('/follow-ups', [FollowUpController::class, 'index'])->name('follow-ups.index');
    Route::get('/my-follow-ups', [FollowUpController::class, 'index'])->name('follow-ups.mine');

    Route::get('/performance', [PerformanceController::class, 'index'])->name('performance.index');

    Route::get('/leave', [LeaveController::class, 'index'])->name('leave.index');
    Route::post('/leave', [LeaveController::class, 'store'])->name('leave.store');
    Route::post('/leave/{leaveRequest}/cancel', [LeaveController::class, 'cancel'])->name('leave.cancel');
    Route::get('/leave-requests', [LeaveController::class, 'requests'])->name('leave.requests');
    Route::put('/leave-requests/{leaveRequest}', [LeaveController::class, 'decide'])->name('leave.decide');
    Route::post('/follow-ups/{leadFollowup}/reassign', [FollowUpController::class, 'reassign'])->name('follow-ups.reassign');

    Route::get('/transfers', [LeadTransferController::class, 'index'])->name('lead-transfers.index');
    Route::put('/transfers/{leadTransfer}', [LeadTransferController::class, 'update'])->name('lead-transfers.update');

    Route::get('/payments', [PaymentReviewController::class, 'index'])->name('payments.index');
    Route::put('/payments/{payment}', [PaymentReviewController::class, 'update'])->name('payments.update');
    Route::get('/installments', [InstallmentController::class, 'index'])->name('installments.index');

    Route::get('/campaigns', [CampaignController::class, 'index'])->name('campaigns.index');
    Route::get('/campaigns/create', [CampaignController::class, 'create'])->name('campaigns.create');
    Route::post('/campaigns', [CampaignController::class, 'store'])->name('campaigns.store');
    Route::get('/campaigns/{campaign}/edit', [CampaignController::class, 'edit'])->name('campaigns.edit');
    Route::put('/campaigns/{campaign}', [CampaignController::class, 'update'])->name('campaigns.update');
    Route::delete('/campaigns/{campaign}', [CampaignController::class, 'destroy'])->name('campaigns.destroy');

    Route::get('/courses', [CourseController::class, 'index'])->name('courses.index');
    Route::post('/courses', [CourseController::class, 'store'])->name('courses.store');
    Route::get('/courses/{course}/edit', [CourseController::class, 'edit'])->name('courses.edit');
    Route::put('/courses/{course}', [CourseController::class, 'update'])->name('courses.update');
    Route::delete('/courses/{course}', [CourseController::class, 'destroy'])->name('courses.destroy');

    Route::get('/subjects', [SubjectController::class, 'index'])->name('subjects.index');
    Route::post('/subjects', [SubjectController::class, 'store'])->name('subjects.store');
    Route::put('/subjects/{subject}', [SubjectController::class, 'update'])->name('subjects.update');
    Route::delete('/subjects/{subject}', [SubjectController::class, 'destroy'])->name('subjects.destroy');

    Route::post('/course-topics', [CourseTopicController::class, 'store'])->name('course-topics.store');
    Route::put('/course-topics/{courseTopic}', [CourseTopicController::class, 'update'])->name('course-topics.update');
    Route::delete('/course-topics/{courseTopic}', [CourseTopicController::class, 'destroy'])->name('course-topics.destroy');

    Route::get('/course-batches', [CourseBatchController::class, 'index'])->name('course-batches.index');
    Route::post('/course-batches', [CourseBatchController::class, 'store'])->name('course-batches.store');
    Route::get('/course-batches/{courseBatch}/edit', [CourseBatchController::class, 'edit'])->name('course-batches.edit');
    Route::put('/course-batches/{courseBatch}', [CourseBatchController::class, 'update'])->name('course-batches.update');
    Route::delete('/course-batches/{courseBatch}', [CourseBatchController::class, 'destroy'])->name('course-batches.destroy');

    Route::get('/duplicate-lead-reviews', [DuplicateLeadReviewController::class, 'index'])
        ->name('duplicate-lead-reviews.index');
    Route::put('/duplicate-lead-reviews/{duplicateLeadReview}', [DuplicateLeadReviewController::class, 'update'])
        ->name('duplicate-lead-reviews.update');

    Route::get('/settings', [SystemSettingController::class, 'index'])->name('settings.index');
    Route::put('/settings', [SystemSettingController::class, 'update'])->name('settings.update');
    Route::post('/settings/distribution-engine', [SystemSettingController::class, 'toggleEngine'])->name('settings.engine');

    Route::get('/staff-passwords', [StaffPasswordController::class, 'index'])->name('staff-passwords.index');
    Route::redirect('/staff-passwords/{employee}/reveal', '/staff-passwords');
    Route::redirect('/staff-passwords/{employee}/reset', '/staff-passwords');
    Route::post('/staff-passwords/{employee}/reveal', [StaffPasswordController::class, 'reveal'])->middleware('throttle:10,1')->name('staff-passwords.reveal');
    Route::post('/staff-passwords/{employee}/reset', [StaffPasswordController::class, 'reset'])->middleware('throttle:10,1')->name('staff-passwords.reset');

    Route::get('/teams', [TeamController::class, 'index'])->name('teams.index');
    Route::get('/teams/create', [TeamController::class, 'create'])->name('teams.create');
    Route::post('/teams', [TeamController::class, 'store'])->name('teams.store');
    Route::get('/teams/{team}', [TeamController::class, 'show'])->name('teams.show');
    Route::get('/teams/{team}/edit', [TeamController::class, 'edit'])->name('teams.edit');
    Route::put('/teams/{team}', [TeamController::class, 'update'])->name('teams.update');
    Route::delete('/teams/{team}', [TeamController::class, 'destroy'])->name('teams.destroy');
    Route::post('/teams/{team}/toggle-status', [TeamController::class, 'toggleStatus'])->name('teams.toggle-status');
    Route::delete('/teams/{team}/members/{employee}', [TeamController::class, 'removeMember'])->name('teams.members.remove');

    Route::get('/employees', [EmployeeController::class, 'index'])->name('employees.index');
    Route::get('/employees/create', [EmployeeController::class, 'create'])->name('employees.create');
    Route::post('/employees', [EmployeeController::class, 'store'])->name('employees.store');
    Route::get('/employees/{employee}/edit', [EmployeeController::class, 'edit'])->name('employees.edit');
    Route::put('/employees/{employee}', [EmployeeController::class, 'update'])->name('employees.update');
    Route::post('/employees/{employee}/roster-override', [EmployeeController::class, 'storeRosterOverride'])->name('employees.roster-override.store');
    Route::delete('/employees/{employee}/roster-override/{roster}', [EmployeeController::class, 'destroyRosterOverride'])->name('employees.roster-override.destroy');

    Route::get('/employee-change-requests', [EmployeeChangeRequestController::class, 'index'])
        ->name('employee-change-requests.index');
    Route::put('/employee-change-requests/{employeeChangeRequest}', [EmployeeChangeRequestController::class, 'update'])
        ->name('employee-change-requests.update');

    Route::get('/leads', [LeadController::class, 'index'])->name('leads.index');
    Route::get('/leads/export', [LeadController::class, 'export'])->name('leads.export');
    Route::post('/leads/create', [LeadController::class, 'store'])->name('leads.quick-create');
    Route::post('/leads/{lead}/assign', [LeadController::class, 'assign'])->name('leads.assign');
    Route::get('/leads/upload', [LeadUploadController::class, 'index'])->name('leads.upload');
    Route::post('/leads/upload', [LeadUploadController::class, 'store'])->name('leads.upload.store');
    Route::post('/leads', [LeadUploadController::class, 'storeSingle'])->name('leads.store');
    Route::get('/leads/template', [LeadUploadController::class, 'template'])->name('leads.template');
});
