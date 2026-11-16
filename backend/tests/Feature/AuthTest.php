<?php

use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;

/**
 * The SPA runs on a different origin than the API, so a browser request carries
 * an Origin header and Sanctum attaches the session middleware. Tests that
 * exercise the cookie flow must send it, or `hasSession()` is false and the
 * controller correctly takes the stateless path instead.
 */
function spaHeaders(): array
{
    return ['Origin' => 'http://localhost:5173'];
}

test('a guest cannot reach any protected endpoint', function (string $method, string $uri) {
    $this->json($method, $uri)->assertUnauthorized();
})->with([
    ['GET', '/api/user'],
    ['POST', '/api/logout'],
    ['GET', '/api/admin/overview'],
    ['GET', '/api/wali/santri'],
]);

test('registration creates a wali account and signs it in', function () {
    $response = $this->postJson('/api/auth/register', [
        'name' => 'Ahmad Fauzi',
        'email' => 'fauzi@example.com',
        'password' => 'rahasia123',
        'password_confirmation' => 'rahasia123',
    ], spaHeaders());

    $response
        ->assertCreated()
        ->assertJsonPath('success', true)
        ->assertJsonPath('data.user.email', 'fauzi@example.com')
        ->assertJsonPath('data.user.role', 'wali')
        ->assertJsonPath('data.user.role_label', 'Wali Santri');

    $this->assertAuthenticated();

    $user = User::where('email', 'fauzi@example.com')->sole();
    expect($user->isWali())->toBeTrue();
});

test('a stateful registration never hands a bearer token to the browser', function () {
    $this->postJson('/api/auth/register', [
        'name' => 'Ahmad Fauzi',
        'email' => 'fauzi@example.com',
        'password' => 'rahasia123',
        'password_confirmation' => 'rahasia123',
    ], spaHeaders())
        ->assertCreated()
        ->assertJsonPath('data.token', null);

    expect(User::where('email', 'fauzi@example.com')->sole()->tokens()->count())->toBe(1);
});

test('a stateless registration returns a usable bearer token', function () {
    $token = $this->postJson('/api/auth/register', [
        'name' => 'Tanpa Origin',
        'email' => 'tanpa-origin@example.com',
        'password' => 'rahasia123',
        'password_confirmation' => 'rahasia123',
    ])
        ->assertCreated()
        ->assertJsonPath('data.token', fn (mixed $value): bool => is_string($value) && $value !== '')
        ->json('data.token');

    $this->withHeader('Authorization', 'Bearer '.$token)
        ->getJson('/api/user')
        ->assertOk()
        ->assertJsonPath('data.email', 'tanpa-origin@example.com');
});

test('registration never lets the caller choose the role', function () {
    $this->postJson('/api/auth/register', [
        'name' => 'Calon Admin',
        'email' => 'calon@example.com',
        'password' => 'rahasia123',
        'password_confirmation' => 'rahasia123',
        'role' => 'admin',
    ])->assertCreated();

    expect(User::where('email', 'calon@example.com')->sole()->isWali())->toBeTrue();
});

test('registration stores a hash and never the plaintext password', function () {
    $this->postJson('/api/auth/register', [
        'name' => 'Ahmad Fauzi',
        'email' => 'fauzi@example.com',
        'password' => 'rahasia123',
        'password_confirmation' => 'rahasia123',
    ])->assertCreated();

    $user = User::where('email', 'fauzi@example.com')->sole();

    expect($user->password)->not->toBe('rahasia123')
        ->and($user->password)->toStartWith('$')
        ->and(Hash::check('rahasia123', $user->password))->toBeTrue();
});

test('registration rejects an email that is already taken', function () {
    User::factory()->create(['email' => 'ada@example.com']);

    $this->postJson('/api/auth/register', [
        'name' => 'Duplikat',
        'email' => 'ada@example.com',
        'password' => 'rahasia123',
        'password_confirmation' => 'rahasia123',
    ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('email');
});

test('registration rejects a password that is too weak', function () {
    $this->postJson('/api/auth/register', [
        'name' => 'Ahmad',
        'email' => 'fauzi@example.com',
        'password' => 'pendek',
        'password_confirmation' => 'pendek',
    ])->assertJsonValidationErrors('password');
});

test('registration rejects a mismatched confirmation', function () {
    $this->postJson('/api/auth/register', [
        'name' => 'Ahmad',
        'email' => 'fauzi@example.com',
        'password' => 'rahasia123',
        'password_confirmation' => 'berbeda123',
    ])->assertJsonValidationErrors('password');
});

test('login succeeds with correct credentials', function () {
    $user = User::factory()->wali()->create([
        'email' => 'wali@example.com',
        'password' => 'rahasia123',
    ]);

    $this->postJson('/api/auth/login', [
        'email' => 'wali@example.com',
        'password' => 'rahasia123',
    ], spaHeaders())
        ->assertOk()
        ->assertJsonPath('data.user.id', $user->id)
        ->assertJsonPath('data.user.role', 'wali')
        ->assertJsonPath('data.token', null);

    $this->assertAuthenticatedAs($user);
});

test('login is case insensitive on the email address', function () {
    User::factory()->create([
        'email' => 'wali@example.com',
        'password' => 'rahasia123',
    ]);

    $this->postJson('/api/auth/login', [
        'email' => 'WALI@Example.com',
        'password' => 'rahasia123',
    ], spaHeaders())->assertOk();

    $this->assertAuthenticated();
});

test('login fails on a wrong password without revealing that the account exists', function () {
    User::factory()->create([
        'email' => 'wali@example.com',
        'password' => 'rahasia123',
    ]);

    $this->postJson('/api/auth/login', [
        'email' => 'wali@example.com',
        'password' => 'salah-total',
    ])->assertUnauthorized()->assertJsonPath('message', 'Email atau kata sandi salah.');

    $this->assertGuest();
});

test('login fails identically for an unknown email', function () {
    $this->postJson('/api/auth/login', [
        'email' => 'tidak-ada@example.com',
        'password' => 'rahasia123',
    ])->assertUnauthorized()->assertJsonPath('message', 'Email atau kata sandi salah.');

    $this->assertGuest();
});

test('logout clears the session and revokes issued tokens', function () {
    $user = User::factory()->create(['email' => 'wali@example.com']);
    $user->createToken('login');

    $this->actingAs($user)
        ->postJson('/api/logout', [], spaHeaders())
        ->assertOk()
        ->assertJsonPath('success', true);

    expect($user->tokens()->count())->toBe(0);

    // The auth manager and its guards live for the whole test method, and the
    // RequestGuard caches whichever user it resolved. Real requests each get a
    // fresh guard, so drop them before asserting the caller is anonymous.
    $this->app['auth']->forgetGuards();
    $this->assertGuest();
});

test('the profile endpoint returns the signed in user', function () {
    $user = User::factory()->admin()->create(['name' => 'Bapak Admin']);

    $this->actingAs($user)
        ->getJson('/api/user')
        ->assertOk()
        ->assertJsonPath('data.id', $user->id)
        ->assertJsonPath('data.name', 'Bapak Admin')
        ->assertJsonPath('data.role', 'admin')
        ->assertJsonPath('data.role_label', 'Admin / Tata Usaha');
});

test('the profile endpoint never leaks the password or remember token', function () {
    $user = User::factory()->create();

    $response = $this->actingAs($user)->getJson('/api/user')->assertOk();

    $response->assertJsonMissingPath('data.password')
        ->assertJsonMissingPath('data.remember_token');
});

test('a personal access token authenticates like the session cookie', function () {
    $user = User::factory()->admin()->create();

    $token = $user->createToken('uji')->plainTextToken;

    $this->withHeader('Authorization', 'Bearer '.$token)
        ->getJson('/api/user')
        ->assertOk()
        ->assertJsonPath('data.id', $user->id);
});

test('a tampered token is rejected', function () {
    $user = User::factory()->create();
    $token = $user->createToken('uji')->plainTextToken;

    $this->withHeader('Authorization', 'Bearer '.substr($token, 0, -4).'zzzz')
        ->getJson('/api/user')
        ->assertUnauthorized();
});

test('a bearer token stops working after logout', function () {
    $user = User::factory()->create();

    $token = $user->createToken('uji')->plainTextToken;

    $this->withHeader('Authorization', 'Bearer '.$token)
        ->getJson('/api/user')
        ->assertOk();

    $this->withHeader('Authorization', 'Bearer '.$token)
        ->postJson('/api/logout')
        ->assertOk();

    // Same guard-caching caveat as the session test above.
    $this->app['auth']->forgetGuards();

    $this->withHeader('Authorization', 'Bearer '.$token)
        ->getJson('/api/user')
        ->assertUnauthorized();
});

test('forgot password emails a reset link to a known address', function () {
    Notification::fake();

    $user = User::factory()->create(['email' => 'wali@example.com']);

    $this->postJson('/api/auth/forgot-password', ['email' => 'wali@example.com'])
        ->assertOk()
        ->assertJsonPath('success', true);

    Notification::assertSentTo($user, ResetPassword::class);
});

test('forgot password answers the same way for an unknown address', function () {
    Notification::fake();

    $this->postJson('/api/auth/forgot-password', ['email' => 'hantu@example.com'])
        ->assertOk()
        ->assertJsonPath('success', true);

    Notification::assertNothingSent();
});

/**
 * The real defence against account enumeration: a known address and an unknown
 * one must be indistinguishable.
 *
 * Only `message` and `success` are compared. The body echoes back the submitted
 * address, which leaks nothing — it is the caller's own input, and the UI needs
 * it to show which address a link was supposedly sent to.
 */
test('forgot password response does not reveal whether the address is registered', function () {
    Notification::fake();

    User::factory()->create(['email' => 'wali@example.com']);

    $known = $this->postJson('/api/auth/forgot-password', ['email' => 'wali@example.com']);
    $unknown = $this->postJson('/api/auth/forgot-password', ['email' => 'hantu@example.com']);

    $known->assertOk();
    $unknown->assertOk();

    expect($known->json('message'))->toBe($unknown->json('message'))
        ->and($known->json('success'))->toBe($unknown->json('success'))
        ->and($known->json('message'))->toBe('Jika email tersebut terdaftar, tautan reset kata sandi sudah dikirim.');

    // The submitted address is echoed back so the UI can echo it, but the
    // response must not grow a second field that hints at the outcome.
    expect(array_keys($known->json('data')))->toBe(['email'])
        ->and($known->json('data'))->toBe(['email' => 'wali@example.com']);
});

/**
 * The stock `ResetPassword` notification resolves its button URL through
 * `route('password.reset', ...)`, which does not exist in this API-only app.
 * `Notification::fake()` never renders the mail, so without this test a real
 * forgot-password request would pass CI and then 500 in production.
 */
test('the reset email points at the SPA reset route', function () {
    $user = User::factory()->create(['email' => 'wali@example.com']);

    $url = (new ResetPassword('token-abc'))
        ->toMail($user)
        ->actionUrl;

    expect($url)->toStartWith('http://localhost:5173/reset-password?')
        ->and($url)->toContain('token=token-abc')
        ->and($url)->toContain('email=wali%40example.com');
});

/**
 * Laravel renders validation failures as `{ message, errors }`, which would be
 * the only response in the app that skips the `{ success, message, data }`
 * envelope. The SPA reads field errors from `data.errors`, so the shape is part
 * of the contract, not an implementation detail. The top-level `errors` key
 * stays too, because `assertJsonValidationErrors()` elsewhere relies on it.
 */
test('validation failures still use the standard envelope', function () {
    $response = $this->postJson('/api/auth/register', [
        'name' => 'A',
        'email' => 'bukan-email',
        'password' => '123',
        'password_confirmation' => '456',
    ]);

    $response
        ->assertStatus(422)
        ->assertJsonPath('success', false)
        ->assertJsonPath('data.errors.email.0', 'Format email tidak valid.')
        ->assertJsonPath('errors.email.0', 'Format email tidak valid.');

    // The message must not carry Laravel's developer-oriented
    // "(and N more errors)" suffix, since it is rendered in the form.
    expect($response->json('message'))->not->toContain('more errors');
});

/**
 * Drive a real reset: request the link, pull the token out of the notification,
 * and post it to the reset endpoint.
 */
function resetPasswordFor(User $user, string $newPassword): void
{
    Notification::fake();

    test()->postJson('/api/auth/forgot-password', ['email' => $user->email])->assertOk();

    Notification::assertSentTo(
        $user,
        ResetPassword::class,
        function (ResetPassword $notification) use ($user, $newPassword): bool {
            test()->postJson('/api/auth/reset-password', [
                'token' => $notification->token,
                'email' => $user->email,
                'password' => $newPassword,
                'password_confirmation' => $newPassword,
            ])->assertOk();

            return true;
        },
    );
}

test('reset password stores a new hash and drops existing tokens', function () {
    $user = User::factory()->create([
        'email' => 'wali@example.com',
        'password' => 'rahasia123',
    ]);
    $user->createToken('lama');

    resetPasswordFor($user, 'katasandia456');

    $user->refresh();

    expect(Hash::check('katasandia456', $user->password))->toBeTrue()
        ->and(Hash::check('rahasia123', $user->password))->toBeFalse()
        ->and($user->tokens()->count())->toBe(0);
});

test('reset password rejects a bad token and leaves the password alone', function () {
    $user = User::factory()->create([
        'email' => 'wali@example.com',
        'password' => 'rahasia123',
    ]);

    $this->postJson('/api/auth/reset-password', [
        'token' => 'token-palsu',
        'email' => 'wali@example.com',
        'password' => 'katasandia456',
        'password_confirmation' => 'katasandia456',
    ])->assertUnprocessable();

    expect(Hash::check('rahasia123', $user->fresh()->password))->toBeTrue();
});

test('the new password works on the next login', function () {
    $user = User::factory()->create([
        'email' => 'wali@example.com',
        'password' => 'rahasia123',
    ]);

    resetPasswordFor($user, 'katasandia456');

    $this->postJson('/api/auth/login', [
        'email' => 'wali@example.com',
        'password' => 'katasandia456',
    ], spaHeaders())->assertOk();
});

test('the role enum still backs the auth layer', function () {
    expect(UserRole::Wali->label())->toBe('Wali Santri')
        ->and(UserRole::Admin->label())->toBe('Admin / Tata Usaha');
});
