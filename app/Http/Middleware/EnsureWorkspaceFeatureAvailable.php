<?php

namespace App\Http\Middleware;

use App\Support\WorkspaceResolver;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureWorkspaceFeatureAvailable
{
    public function __construct(protected WorkspaceResolver $workspaces) {}

    public function handle(Request $request, Closure $next, string $feature): Response
    {
        $actor = $request->user();
        abort_unless($actor !== null, 401);

        $workspace = $this->workspaces->current($request);

        if ($feature === 'advanced' && $workspace->allowsAdvancedWorkspaceFeatures()) {
            return $next($request);
        }

        $message = $workspace->id === $actor->id
            ? 'Upgrade to Premium or Ultra to unlock this section.'
            : sprintf('%s is on the Free plan, so this workspace section is locked.', $workspace->name);

        return redirect()
            ->route($workspace->id === $actor->id ? 'membership.show' : 'monitors.index')
            ->with('error', $message);
    }
}
