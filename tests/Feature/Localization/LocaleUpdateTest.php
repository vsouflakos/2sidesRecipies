<?php

use App\Models\User;

test('locale switcher persists the choice to the user record', function () {
    $user = User::factory()->create(['locale' => 'en']);

    $this->actingAs($user)
        ->put('/locale', ['locale' => 'el'])
        ->assertNoContent();

    expect($user->fresh()->locale)->toBe('el');
});

test('locale update rejects an unsupported locale', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->put('/locale', ['locale' => 'fr'])
        ->assertRedirect()
        ->assertSessionHasErrors('locale');
});
