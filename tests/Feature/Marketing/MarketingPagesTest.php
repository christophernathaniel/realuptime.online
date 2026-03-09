<?php

use Inertia\Testing\AssertableInertia as Assert;

test('marketing pages render successfully', function () {
    $this->get(route('home'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page->component('marketing/home')->has('canRegister'));

    $this->get(route('features.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page->component('marketing/features')->has('canRegister'));

    $this->get(route('features.show', ['slug' => 'website-monitoring']))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page->component('marketing/feature-show')->where('slug', 'website-monitoring')->has('canRegister'));

    $this->get(route('pricing'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page->component('marketing/pricing')->has('canRegister'));

    $this->get(route('about'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page->component('marketing/about')->has('canRegister'));

    $this->get(route('careers'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page->component('marketing/careers')->has('canRegister'));

    $this->get(route('privacy-policy'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page->component('marketing/privacy')->has('canRegister'));

    $this->get(route('terms-and-conditions'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page->component('marketing/terms')->has('canRegister'));
});

test('invalid feature pages return not found', function () {
    $this->get(route('features.show', ['slug' => 'not-a-feature']))
        ->assertNotFound()
        ->assertInertia(fn (Assert $page) => $page->component('errors/not-found')->has('canRegister'));
});

test('plans route redirects to pricing', function () {
    $this->get(route('plans'))
        ->assertRedirect(route('pricing'));
});

test('unknown web routes render the custom not found page', function () {
    $this->get('/this-page-does-not-exist')
        ->assertNotFound()
        ->assertInertia(fn (Assert $page) => $page->component('errors/not-found')->has('canRegister'));
});
