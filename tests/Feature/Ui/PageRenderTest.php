<?php

use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;

beforeEach(function () {
    $this->seed(RolesAndPermissionsSeeder::class);
});

test('dashboard renders for an authenticated user', function () {
    $user = User::factory()->create();
    $user->assignRole('User');

    $this->actingAs($user)
        ->get(route('dashboard'))
        ->assertOk();
});

test('admin users page renders for an admin', function () {
    $admin = User::factory()->create();
    $admin->assignRole('Admin');

    $this->actingAs($admin)
        ->get('/admin/users')
        ->assertOk();
});

test('settings profile renders for an authenticated user', function () {
    $user = User::factory()->create();
    $user->assignRole('User');

    $this->actingAs($user)
        ->get(route('profile.edit'))
        ->assertOk();
});
