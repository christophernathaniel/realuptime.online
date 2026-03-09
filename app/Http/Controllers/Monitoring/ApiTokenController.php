<?php

namespace App\Http\Controllers\Monitoring;

use App\Http\Controllers\Controller;
use App\Models\ApiToken;
use App\Support\WorkspaceResolver;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class ApiTokenController extends Controller
{
    public function __construct(protected WorkspaceResolver $workspaces) {}

    public function store(Request $request): RedirectResponse
    {
        $workspace = $this->workspaces->current($request);

        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
        ]);

        $plainTextToken = 'rtu_'.Str::random(48);

        $token = $workspace->apiTokens()->create([
            'name' => $data['name'],
            'token_hash' => hash('sha256', $plainTextToken),
        ]);

        return back()->with('success', sprintf('API token "%s" created.', $token->name))
            ->with('api_token', [
                'name' => $token->name,
                'token' => $plainTextToken,
            ]);
    }

    public function destroy(Request $request, ApiToken $apiToken): RedirectResponse
    {
        abort_unless($apiToken->user_id === $this->workspaces->current($request)->id, 404);

        $apiToken->delete();

        return back()->with('success', 'API token revoked.');
    }
}
