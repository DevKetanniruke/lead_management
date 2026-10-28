<?php

use App\Http\Controllers\ActivityController;
use App\Http\Controllers\AuditLogController;
use App\Http\Controllers\ClientActivityController;
use App\Http\Controllers\ClientController;
use App\Http\Controllers\ConversionController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\FollowUpController;
use App\Http\Controllers\InvoiceController;
use App\Http\Controllers\LeadController;
use App\Http\Controllers\NotificationController;
use App\Http\Controllers\PaymentController;
use App\Http\Controllers\PipelineController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\ReportController;
use App\Http\Controllers\RoleController;
use App\Http\Controllers\SettingsController;
use App\Http\Controllers\UserController;
use Illuminate\Support\Facades\Route;

// No public landing page: guests get the login form, signed-in users the dashboard.
Route::redirect('/', '/login');

Route::middleware(['auth', 'verified'])->group(function () {
    Route::get('/dashboard', DashboardController::class)->name('dashboard');
    Route::get('/dashboard/stats', [DashboardController::class, 'stats'])->name('dashboard.stats');
});

Route::middleware('auth')->group(function () {
    Route::post('leads/bulk-assign', [LeadController::class, 'bulkAssign'])->name('leads.bulk-assign');
    Route::resource('leads', LeadController::class);

    // Lead → client conversion (eligibility enforced by LeadPolicy::convert)
    Route::get('leads/{lead}/convert', [ConversionController::class, 'create'])->name('conversions.create');
    Route::post('leads/{lead}/convert', [ConversionController::class, 'store'])->name('conversions.store');

    // Clients (scoped per user by ClientPolicy / Client::visibleTo)
    Route::get('clients/export', [ClientController::class, 'export'])->name('clients.export');
    Route::resource('clients', ClientController::class)->except('destroy');
    Route::patch('clients/{client}/status', [ClientController::class, 'updateStatus'])->name('clients.status');
    Route::post('clients/{client}/activities', [ClientActivityController::class, 'store'])->name('clients.activities.store');
    Route::post('clients/{client}/deals', [ClientActivityController::class, 'storeDeal'])->name('clients.deals.store');
    Route::delete('clients/{client}/deals/{deal}', [ClientActivityController::class, 'destroyDeal'])->name('clients.deals.destroy');

    // Invoices (InvoicePolicy: drafts editable, issued frozen, void/reissue audited)
    Route::get('invoices/export', [InvoiceController::class, 'export'])->name('invoices.export');
    Route::resource('invoices', InvoiceController::class);
    Route::post('invoices/{invoice}/issue', [InvoiceController::class, 'issue'])->name('invoices.issue');
    Route::post('invoices/{invoice}/void', [InvoiceController::class, 'void'])->name('invoices.void');
    Route::post('invoices/{invoice}/reissue', [InvoiceController::class, 'reissue'])->name('invoices.reissue');
    Route::post('invoices/{invoice}/duplicate', [InvoiceController::class, 'duplicate'])->name('invoices.duplicate');
    Route::post('invoices/{invoice}/send', [InvoiceController::class, 'send'])->middleware('throttle:10,1')->name('invoices.send');
    Route::get('invoices/{invoice}/pdf', [InvoiceController::class, 'pdf'])->name('invoices.pdf');
    Route::get('invoices/{invoice}/print', [InvoiceController::class, 'print'])->name('invoices.print');

    // Payments (never deleted; reversals and corrections are audited)
    Route::get('payments/export', [PaymentController::class, 'export'])->name('payments.export');
    Route::resource('payments', PaymentController::class)->only(['index', 'create', 'store', 'show']);
    Route::post('payments/{payment}/reverse', [PaymentController::class, 'reverse'])->name('payments.reverse');
    Route::post('payments/{payment}/correct', [PaymentController::class, 'correct'])->name('payments.correct');

    Route::middleware('permission:reports.view')->group(function () {
        Route::get('reports', [ReportController::class, 'index'])->name('reports.index');
        Route::get('reports/export/{report}', [ReportController::class, 'export'])->whereIn('report', ['sales-performance', 'aging', 'monthly'])->name('reports.export');
    });

    Route::middleware('permission:settings.manage')->group(function () {
        Route::get('settings', [SettingsController::class, 'edit'])->name('settings.edit');
        Route::put('settings', [SettingsController::class, 'update'])->name('settings.update');
    });
    Route::get('settings/branding/{type}', [SettingsController::class, 'branding'])->whereIn('type', ['logo', 'signature'])->name('settings.branding');

    // User management (admin by default; granted via the users.manage permission)
    Route::middleware('permission:users.manage')->group(function () {
        Route::resource('users', UserController::class);
        Route::patch('users/{user}/activate', [UserController::class, 'activate'])->name('users.activate');
        Route::patch('users/{user}/deactivate', [UserController::class, 'deactivate'])->name('users.deactivate');
        Route::post('users/{user}/password-reset', [UserController::class, 'sendPasswordReset'])->name('users.password-reset');
        Route::post('users/{user}/reset-access', [UserController::class, 'resetAccess'])->name('users.reset-access');
        Route::post('users/{user}/reassign', [UserController::class, 'reassign'])->name('users.reassign');

        Route::get('roles', [RoleController::class, 'index'])->name('roles.index');
        Route::put('roles/{role}', [RoleController::class, 'update'])->name('roles.update');
    });
    Route::get('users/{user}/photo', [UserController::class, 'photo'])->name('users.photo');

    Route::get('activity-log', [AuditLogController::class, 'index'])->middleware('permission:audit.view')->name('audit.index');

    Route::get('notifications', [NotificationController::class, 'index'])->name('notifications.index');
    Route::get('notifications/{id}', [NotificationController::class, 'open'])->name('notifications.open');
    Route::post('notifications/read-all', [NotificationController::class, 'markAllRead'])->name('notifications.read-all');

    Route::post('leads/{lead}/activities', [ActivityController::class, 'store'])->name('leads.activities.store');
    Route::patch('activities/{activity}/complete', [ActivityController::class, 'complete'])->name('activities.complete');
    Route::delete('activities/{activity}', [ActivityController::class, 'destroy'])->name('activities.destroy');

    Route::get('follow-ups', [FollowUpController::class, 'index'])->name('follow-ups.index');

    Route::get('pipeline', [PipelineController::class, 'index'])->name('pipeline.index');
    Route::get('pipeline/columns/{status}', [PipelineController::class, 'column'])->name('pipeline.column');
    Route::patch('leads/{lead}/status', [PipelineController::class, 'move'])->name('leads.status');

    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::patch('/profile/notifications', [ProfileController::class, 'updateNotifications'])->name('profile.notifications');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');
});

require __DIR__.'/auth.php';
