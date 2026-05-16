<?php

use App\Models\User;

test('styleguide page renders in the local environment', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->get('/dev/styleguide')
        ->assertOk();
});
