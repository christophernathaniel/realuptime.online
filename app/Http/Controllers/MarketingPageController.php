<?php

namespace App\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;
use Laravel\Fortify\Features;

class MarketingPageController extends Controller
{
    /**
     * @var array<int, string>
     */
    private const FEATURE_SLUGS = [
        'website-monitoring',
        'port-monitoring',
        'ping-monitoring',
        'incidents-management',
        'public-status-pages',
        'it-alerting-software',
    ];

    public function home(): Response
    {
        return Inertia::render('marketing/home', $this->sharedProps());
    }

    public function features(): Response
    {
        return Inertia::render('marketing/features', $this->sharedProps());
    }

    public function feature(string $slug): Response
    {
        abort_unless(in_array($slug, self::FEATURE_SLUGS, true), 404);

        return Inertia::render('marketing/feature-show', [
            ...$this->sharedProps(),
            'slug' => $slug,
        ]);
    }

    public function pricing(): Response
    {
        return Inertia::render('marketing/pricing', $this->sharedProps());
    }

    public function plans(): RedirectResponse
    {
        return redirect()->route('pricing');
    }

    public function about(): Response
    {
        return Inertia::render('marketing/about', $this->sharedProps());
    }

    public function careers(): Response
    {
        return Inertia::render('marketing/careers', $this->sharedProps());
    }

    public function privacy(): Response
    {
        return Inertia::render('marketing/privacy', $this->sharedProps());
    }

    public function terms(): Response
    {
        return Inertia::render('marketing/terms', $this->sharedProps());
    }

    /**
     * @return array<string, bool>
     */
    private function sharedProps(): array
    {
        return [
            'canRegister' => Features::enabled(Features::registration()),
        ];
    }
}
