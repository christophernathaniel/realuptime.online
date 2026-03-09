<?php

namespace App\Http\Controllers\Monitoring;

use App\Http\Controllers\Controller;
use App\Models\StatusPage;
use App\Services\Monitoring\MonitorPresenter;
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

        return Inertia::render('monitoring/public-status', $this->presenter->publicStatusPage($page));
    }
}
