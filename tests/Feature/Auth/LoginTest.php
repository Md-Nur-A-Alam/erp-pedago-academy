<?php

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('renders the branded phone and password login form', function () {
    $this->get(route('login'))
        ->assertOk()
        ->assertSee('Login please')
        ->assertSee('name="phone_number"', false)
        ->assertSee('name="password"', false)
        ->assertSee('minlength="6" maxlength="12"', false)
        ->assertSee('name="remember" type="checkbox" value="1" checked', false)
        ->assertSee(route('login.store'), false)
        ->assertSee(asset('user_defined_assets/img/Welcome%20to%20Pedago%20Academy%20ERP%20System.png'), false)
        ->assertSee('Forgot Password?')
        ->assertSee('min-[861px]:aspect-[1465/974]', false);
});

it('shows validation errors and retains the phone number after an unsuccessful login', function () {
    $this->from(route('login'))->post(route('login.store'), [
        'phone_number' => 'invalid',
        'password' => 'Wrong12!',
    ])->assertRedirect(route('login'))
        ->assertSessionHasErrors('phone_number')
        ->assertSessionHas('_old_input.phone_number', 'invalid');

    $this->from(route('login'))->followingRedirects()->post(route('login.store'), [
        'phone_number' => '01712345678',
        'password' => 'Wrong12!',
    ])->assertOk()
        ->assertSee('The phone number or password is incorrect.')
        ->assertSee('value="01712345678"', false);
});

it('normalizes international dialing prefixes before attempting login', function () {
    User::factory()->create([
        'phone_number' => '+442079460958',
        'password' => 'SecurePass12',
    ]);

    $this->from(route('login'))->post(route('login.store'), [
        'phone_number' => '0044 (20) 7946-0958',
        'password' => 'Wrong12!',
    ])->assertRedirect(route('login'))
        ->assertSessionHasErrors([
            'phone_number' => 'The phone number or password is incorrect.',
        ])
        ->assertSessionHas('_old_input.phone_number', '+442079460958');
});

it('rejects international phone numbers longer than E.164 allows on login', function () {
    $this->from(route('login'))->post(route('login.store'), [
        'phone_number' => '+1234567890123456',
        'password' => 'Wrong12!',
    ])->assertRedirect(route('login'))
        ->assertSessionHasErrors('phone_number');
});

it('accepts a phone number at the international 15 digit limit', function () {
    $this->from(route('login'))->post(route('login.store'), [
        'phone_number' => '+123456789012345',
        'password' => 'Wrong12!',
    ])->assertRedirect(route('login'))
        ->assertSessionHasErrors([
            'phone_number' => 'The phone number or password is incorrect.',
        ]);
});

it('rejects login passwords shorter than 6 or longer than 12 characters', function (string $password) {
    $this->from(route('login'))->post(route('login.store'), [
        'phone_number' => '01712345678',
        'password' => $password,
    ])->assertRedirect(route('login'))
        ->assertSessionHasErrors('password');
})->with([
    'below the minimum' => '12345',
    'above the maximum' => '1234567890123',
]);
