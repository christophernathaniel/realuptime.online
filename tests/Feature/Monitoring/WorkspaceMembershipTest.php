<?php

use App\Models\Monitor;
use App\Models\User;
use App\Models\WorkspaceMembership;
use App\Notifications\WorkspaceInvitationNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;

uses(RefreshDatabase::class);

it('invites a teammate and grants them access to the shared workspace after acceptance', function () {
    Notification::fake();

    $owner = User::factory()->premium()->create([
        'email_verified_at' => now(),
    ]);
    $member = User::factory()->create([
        'email' => 'member@example.com',
        'email_verified_at' => now(),
    ]);

    Monitor::query()->create([
        'user_id' => $owner->id,
        'name' => 'Primary API',
        'type' => Monitor::TYPE_HTTP,
        'status' => Monitor::STATUS_UP,
        'target' => 'https://example.com/health',
        'request_method' => 'GET',
        'interval_seconds' => 300,
        'timeout_seconds' => 30,
        'retry_limit' => 2,
        'region' => 'North America',
        'next_check_at' => now()->subMinute(),
    ]);

    $this->actingAs($owner)
        ->post('/team-members/invitations', [
            'email' => $member->email,
        ])
        ->assertRedirect();

    $membership = WorkspaceMembership::query()->first();

    expect($membership)->not->toBeNull();
    expect($membership->invited_email)->toBe('member@example.com');
    expect($membership->accepted_at)->toBeNull();

    Notification::assertSentOnDemand(WorkspaceInvitationNotification::class);

    $this->actingAs($member)
        ->get("/workspace-invitations/{$membership->token}")
        ->assertRedirect(route('team-members.index'));

    $membership->refresh();

    expect($membership->member_user_id)->toBe($member->id);
    expect($membership->accepted_at)->not->toBeNull();

    $this->get('/monitors')
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('monitors/index')
            ->where('summary.total', 1)
            ->where('monitors.0.name', 'Primary API'));
});
