<?php

use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;

beforeEach(function () {
    $this->seed(RolesAndPermissionsSeeder::class);
});

test('admin can list users', function () {
    $admin = User::factory()->create();
    $admin->assignRole('Admin');

    $this->actingAs($admin)
        ->get('/admin/users')
        ->assertOk();
});

test('admin can assign a role', function () {
    $admin = User::factory()->create();
    $admin->assignRole('Admin');

    $target = User::factory()->create();
    $target->assignRole('User');

    $this->actingAs($admin)
        ->put("/admin/users/{$target->id}/role", ['role' => 'Moderator']);

    expect($target->fresh()->hasRole('Moderator'))->toBeTrue();
});

test('admin can deactivate a user', function () {
    $admin = User::factory()->create();
    $admin->assignRole('Admin');

    $target = User::factory()->create();
    $target->assignRole('User');

    $this->actingAs($admin)
        ->put("/admin/users/{$target->id}/status");

    expect($target->fresh()->account_status->value)->toBe('deactivated');
});

test('admin can soft-delete a user', function () {
    $admin = User::factory()->create();
    $admin->assignRole('Admin');

    $target = User::factory()->create();
    $target->assignRole('User');

    $this->actingAs($admin)
        ->delete("/admin/users/{$target->id}");

    $this->assertSoftDeleted($target);
});
