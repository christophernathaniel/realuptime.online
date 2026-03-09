<?php

namespace App\Http\Controllers\Monitoring;

use App\Http\Controllers\Controller;
use App\Models\StatusPage;
use App\Models\User;
use App\Services\Monitoring\MonitorPresenter;
use Inertia\Inertia;
use Inertia\Response;

class PublicStatusPageController extends Controller
{
    public function __construct(protected MonitorPresenter $presenter) {}

    public function show(User $user, StatusPage $statusPage): Response
    {
        abort_unless($statusPage->user_id === $user->id, 404);
        abort_unless($statusPage->published, 404);

        return Inertia::render('monitoring/public-status', $this->presenter->publicStatusPage($statusPage));
    }
}
