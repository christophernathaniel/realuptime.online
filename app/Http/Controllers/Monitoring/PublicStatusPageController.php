<?php

namespace App\Http\Controllers\Monitoring;

use App\Http\Controllers\Controller;
use App\Models\StatusPage;
use App\Services\Monitoring\MonitorPresenter;
use Illuminate\Support\Facades\Cache;
use Inertia\Inertia;
use Inertia\Response;

class PublicStatusPageController extends Controller
{
    public function __construct(protected MonitorPresenter $presenter) {}

    public function show(string $ownerKey, string $statusPage): Response
    {
        $page = StatusPage::query()
            ->where('slug', $statusPage)
            ->where('published', true)
            ->whereHas('user', fn ($query) => $query->where('public_status_key', $ownerKey))
            ->firstOrFail();
        $cacheSeconds = max(0, (int) config('realuptime.public_status.cache_seconds', 15));
        $props = $cacheSeconds > 0
            ? Cache::remember(
                sprintf('public-status-page:%s:%s', $ownerKey, $statusPage),
                now()->addSeconds($cacheSeconds),
                fn () => $this->presenter->publicStatusPage($page->fresh())
            )
            : $this->presenter->publicStatusPage($page);

        return Inertia::render('monitoring/public-status', $props);
    }
}
