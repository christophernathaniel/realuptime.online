<?php

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;

uses(RefreshDatabase::class);

it('renders the membership settings page', function () {
    $user = User::factory()->premium()->create();

    $this->actingAs($user)
        ->get('/settings/membership')
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('settings/membership')
            ->where('membership.currentPlan.value', 'premium')
            ->where('membership.currentPlan.monitorLimit', 50)
            ->where('membership.currentPlan.advancedFeaturesUnlocked', true));
});

it('renders the actual membership plan for platform admins', function () {
    $user = User::factory()->admin()->create();

    $this->actingAs($user)
        ->get('/settings/membership')
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('settings/membership')
            ->where('membership.currentPlan.label', 'Free')
            ->where('membership.currentPlan.monitorLimitLabel', '10')
            ->where('membership.currentPlan.priceLabel', 'Free')
            ->where('membership.currentPlan.isAdmin', true));
});
