<?php

use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;

beforeEach(function () {
    $this->seed(RolesAndPermissionsSeeder::class);
});

test('admin cannot change their own role', function () {
    $admin = User::factory()->create();
    $admin->assignRole('Admin');

    $this->actingAs($admin)
        ->put("/admin/users/{$admin->id}/role", ['role' => 'User'])
        ->assertUnprocessable();

    expect($admin->fresh()->hasRole('Admin'))->toBeTrue();
});

test('admin cannot deactivate themselves', function () {
    $admin = User::factory()->create();
    $admin->assignRole('Admin');

    $this->actingAs($admin)
        ->put("/admin/users/{$admin->id}/status")
        ->assertUnprocessable();
});

test('admin cannot delete themselves', function () {
    $admin = User::factory()->create();
    $admin->assignRole('Admin');

    $this->actingAs($admin)
        ->delete("/admin/users/{$admin->id}")
        ->assertUnprocessable();

    $this->assertNotSoftDeleted($admin);
});

test('system refuses to remove the last admin role', function () {
    $actingAdmin = User::factory()->create();
    $actingAdmin->assignRole('Admin');

    $targetAdmin = User::factory()->create();
    $targetAdmin->assignRole('Admin');

    // Acting admin successfully demotes the other admin (two admins exist)
    $this->actingAs($actingAdmin)
        ->put("/admin/users/{$targetAdmin->id}/role", ['role' => 'User'])
        ->assertSuccessful();

    // Now targetAdmin is the only remaining Admin — demoting the last Admin must fail
    $this->actingAs($targetAdmin->fresh())
        ->put("/admin/users/{$actingAdmin->id}/role", ['role' => 'User'])
        ->assertUnprocessable();
});
