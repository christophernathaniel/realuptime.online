<?php

use App\Models\Monitor;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('creates api tokens from the integrations area and uses them against the api', function () {
    $user = User::factory()->premium()->create();
    $monitor = Monitor::query()->create([
        'user_id' => $user->id,
        'name' => 'Primary API',
        'type' => Monitor::TYPE_HTTP,
        'status' => Monitor::STATUS_UP,
        'target' => 'https://example.com/health',
        'request_method' => 'GET',
        'interval_seconds' => 300,
        'timeout_seconds' => 30,
        'retry_limit' => 2,
        'region' => 'North America',
    ]);

    $response = $this->actingAs($user)
        ->post('/api-tokens', [
            'name' => 'CI pipeline',
        ]);

    $response->assertRedirect();
    $response->assertSessionHas('api_token.name', 'CI pipeline');
    $response->assertSessionHas('api_token.token');

    $token = session('api_token.token');

    $this->getJson('/api/v1/workspace', [
        'Authorization' => 'Bearer '.$token,
    ])->assertOk()->assertJsonPath('data.email', $user->email);

    $this->getJson('/api/v1/monitors', [
        'Authorization' => 'Bearer '.$token,
    ])->assertOk()->assertJsonCount(1, 'data')->assertJsonPath('data.0.id', $monitor->id);
});

it('rejects invalid api tokens', function () {
    $this->getJson('/api/v1/workspace', [
        'Authorization' => 'Bearer invalid-token',
    ])->assertUnauthorized()->assertJson([
        'message' => 'Invalid API token.',
    ]);
});
