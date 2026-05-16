<?php

use App\Models\User;

test('set locale middleware applies the user locale', function () {
    $user = User::factory()->create(['locale' => 'el']);

    $this->actingAs($user)
        ->get(route('dashboard'));

    expect(app()->getLocale())->toBe('el');
});

test('locale shared prop is present in the inertia payload', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->get(route('dashboard'))
        ->assertInertia(fn ($page) => $page->has('locale'));
});
