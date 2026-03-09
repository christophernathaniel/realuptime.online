<?php

use App\Http\Controllers\Auth\OAuthController;
use App\Http\Controllers\Admin\AdminUserController;
use App\Http\Controllers\MarketingPageController;
use App\Http\Controllers\Monitoring\HeartbeatController;
use App\Http\Controllers\Monitoring\IncidentController;
use App\Http\Controllers\Monitoring\MaintenanceWindowController;
use App\Http\Controllers\Monitoring\ApiTokenController;
use App\Http\Controllers\Monitoring\MonitorController;
use App\Http\Controllers\Monitoring\MonitoringSectionController;
use App\Http\Controllers\Monitoring\NotificationContactController;
use App\Http\Controllers\Monitoring\PublicStatusPageController;
use App\Http\Controllers\Monitoring\StatusPageController;
use App\Http\Controllers\Monitoring\StatusPageIncidentController;
use App\Http\Controllers\Monitoring\WorkspaceMembershipController;
use Illuminate\Support\Facades\Route;

Route::get('/', [MarketingPageController::class, 'home'])->name('home');
Route::get('/features', [MarketingPageController::class, 'features'])->name('features.index');
Route::get('/features/{slug}', [MarketingPageController::class, 'feature'])->name('features.show');
Route::get('/pricing', [MarketingPageController::class, 'pricing'])->name('pricing');
Route::get('/plans', [MarketingPageController::class, 'plans'])->name('plans');
Route::get('/about', [MarketingPageController::class, 'about'])->name('about');
Route::get('/careers', [MarketingPageController::class, 'careers'])->name('careers');
Route::get('/privacy-policy', [MarketingPageController::class, 'privacy'])->name('privacy-policy');
Route::get('/terms-and-conditions', [MarketingPageController::class, 'terms'])->name('terms-and-conditions');

Route::post('heartbeat/{token}', [HeartbeatController::class, 'store'])->name('heartbeat.store');
Route::get('auth/{provider}/redirect', [OAuthController::class, 'redirect'])->name('oauth.redirect');
Route::get('auth/{provider}/callback', [OAuthController::class, 'callback'])->name('oauth.callback');
Route::get('status/{user}/{statusPage:slug}', [PublicStatusPageController::class, 'show'])
    ->scopeBindings()
    ->name('public-status-pages.show');

Route::middleware(['auth'])->group(function () {
    Route::get('workspace-invitations/{token}', [WorkspaceMembershipController::class, 'accept'])->name('workspace-invitations.accept');
    Route::post('workspaces/switch', [WorkspaceMembershipController::class, 'switch'])->name('workspaces.switch');
});

Route::middleware(['auth', 'verified'])->group(function () {
    Route::get('dashboard', [MonitorController::class, 'index'])->name('dashboard');
    Route::get('monitors', [MonitorController::class, 'index'])->name('monitors.index');
    Route::get('monitors/create', [MonitorController::class, 'create'])->name('monitors.create');
    Route::post('monitors', [MonitorController::class, 'store'])->name('monitors.store');
    Route::get('monitors/{monitor}', [MonitorController::class, 'show'])->name('monitors.show');
    Route::get('monitors/{monitor}/edit', [MonitorController::class, 'edit'])->name('monitors.edit');
    Route::put('monitors/{monitor}', [MonitorController::class, 'update'])->name('monitors.update');
    Route::post('monitors/{monitor}/toggle', [MonitorController::class, 'toggle'])->name('monitors.toggle');
    Route::post('monitors/{monitor}/run-now', [MonitorController::class, 'runNow'])->name('monitors.run-now');
    Route::post('monitors/{monitor}/test-notification', [MonitorController::class, 'testNotification'])->name('monitors.test-notification');
    Route::delete('monitors/{monitor}', [MonitorController::class, 'destroy'])->name('monitors.destroy');

    Route::middleware('workspace.feature:advanced')->group(function () {
        Route::post('notification-contacts', [NotificationContactController::class, 'store'])->name('notification-contacts.store');
        Route::put('notification-contacts/{notificationContact}', [NotificationContactController::class, 'update'])->name('notification-contacts.update');
        Route::delete('notification-contacts/{notificationContact}', [NotificationContactController::class, 'destroy'])->name('notification-contacts.destroy');
        Route::post('api-tokens', [ApiTokenController::class, 'store'])->name('api-tokens.store');
        Route::delete('api-tokens/{apiToken}', [ApiTokenController::class, 'destroy'])->name('api-tokens.destroy');

        Route::post('status-pages', [StatusPageController::class, 'store'])->name('status-pages.store');
        Route::put('status-pages/{statusPage}', [StatusPageController::class, 'update'])->name('status-pages.update');
        Route::delete('status-pages/{statusPage}', [StatusPageController::class, 'destroy'])->name('status-pages.destroy');
        Route::post('status-pages/{statusPage}/incidents', [StatusPageIncidentController::class, 'store'])->name('status-pages.incidents.store');
        Route::post('status-page-incidents/{statusPageIncident}/updates', [StatusPageIncidentController::class, 'storeUpdate'])->name('status-page-incidents.updates.store');
        Route::delete('status-page-incidents/{statusPageIncident}', [StatusPageIncidentController::class, 'destroy'])->name('status-page-incidents.destroy');

        Route::post('maintenance-windows', [MaintenanceWindowController::class, 'store'])->name('maintenance-windows.store');
        Route::put('maintenance-windows/{maintenanceWindow}', [MaintenanceWindowController::class, 'update'])->name('maintenance-windows.update');
        Route::delete('maintenance-windows/{maintenanceWindow}', [MaintenanceWindowController::class, 'destroy'])->name('maintenance-windows.destroy');

        Route::get('incidents', [MonitoringSectionController::class, 'incidents'])->name('incidents.index');
        Route::get('incidents/{incident}', [IncidentController::class, 'show'])->name('incidents.show');
        Route::put('incidents/{incident}', [IncidentController::class, 'update'])->name('incidents.update');
        Route::get('status-pages', [MonitoringSectionController::class, 'statusPages'])->name('status-pages.index');
        Route::get('maintenance', [MonitoringSectionController::class, 'maintenance'])->name('maintenance.index');
        Route::get('team-members', [MonitoringSectionController::class, 'team'])->name('team-members.index');
        Route::post('team-members/invitations', [WorkspaceMembershipController::class, 'store'])->name('team-members.invitations.store');
        Route::delete('team-members/invitations/{workspaceMembership}', [WorkspaceMembershipController::class, 'destroy'])->name('team-members.invitations.destroy');
        Route::get('integrations', [MonitoringSectionController::class, 'integrations'])->name('integrations.index');
    });
});

Route::middleware(['auth', 'verified', 'admin'])->prefix('admin')->name('admin.')->group(function () {
    Route::get('users', [AdminUserController::class, 'index'])->name('users.index');
    Route::post('users', [AdminUserController::class, 'store'])->name('users.store');
    Route::patch('users/{user}', [AdminUserController::class, 'update'])->name('users.update');
    Route::patch('users/{user}/membership', [AdminUserController::class, 'updateMembership'])->name('users.membership.update');
    Route::delete('users/{user}', [AdminUserController::class, 'destroy'])->name('users.destroy');
});

require __DIR__.'/settings.php';
