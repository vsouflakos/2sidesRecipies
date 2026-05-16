<?php

use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Laravel\Fortify\Features;
use Spatie\Permission\Traits\HasRoles;

beforeEach(function () {
    $this->seed(RolesAndPermissionsSeeder::class);
});

test('new registration is assigned the User role', function () {
    $this->skipUnlessFortifyHas(Features::registration());

    $this->post(route('register.store'), [
        'name' => 'New User',
        'email' => 'newuser@example.com',
        'password' => 'password',
        'password_confirmation' => 'password',
    ]);

    expect(User::where('email', 'newuser@example.com')->first()->hasRole('User'))->toBeTrue();
});

test('user model uses the HasRoles trait', function () {
    expect(
        in_array(HasRoles::class, class_uses_recursive(User::class))
    )->toBeTrue();
});
