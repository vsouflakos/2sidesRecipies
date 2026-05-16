<?php

use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;

beforeEach(function () {
    $this->seed(RolesAndPermissionsSeeder::class);
});

test('deactivated user cannot log in', function () {
    $user = User::factory()->create([
        'account_status' => 'deactivated',
        'password' => bcrypt('password'),
    ]);
    $user->assignRole('User');

    $this->post(route('login'), [
        'email' => $user->email,
        'password' => 'password',
    ]);

    $this->assertGuest();
    $this->followingRedirects()
        ->post(route('login'), [
            'email' => $user->email,
            'password' => 'password',
        ])
        ->assertSeeText('deactivated');
});
