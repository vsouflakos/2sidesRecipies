<?php

use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;

beforeEach(function () {
    $this->seed(RolesAndPermissionsSeeder::class);
});

test('moderator can reach the ingredient review queue placeholder', function () {
    $moderator = User::factory()->create();
    $moderator->assignRole('Moderator');

    $this->actingAs($moderator)
        ->get('/admin/ingredients')
        ->assertOk();
});

test('plain user gets 403 on the ingredient review queue', function () {
    $user = User::factory()->create();
    $user->assignRole('User');

    $this->actingAs($user)
        ->get('/admin/ingredients')
        ->assertForbidden();
});

test('plain user gets 403 on every admin route', function () {
    $user = User::factory()->create();
    $user->assignRole('User');

    $this->actingAs($user)
        ->get('/admin/users')
        ->assertForbidden();
});
