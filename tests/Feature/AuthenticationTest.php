<?php

use App\Models\AuditEvent;
use App\Models\User;

beforeEach(fn () => seedCore());

it('logs a staff user in and requires MFA enrollment before the portal', function () {
    $user = staffUser('Case Manager', ['mfa_enabled_at' => null, 'password' => 'secret-password-123']);

    $response = $this->post('/login', ['email' => $user->email, 'password' => 'secret-password-123']);

    $response->assertRedirect(route('portal.dashboard'));
    // Without MFA enrolled, portal access forces enrollment.
    $this->get(route('portal.dashboard'))->assertRedirect(route('mfa.enroll'));
});

it('requires the MFA challenge each session for enrolled staff', function () {
    $user = staffUser();
    $this->actingAs($user);

    $this->get(route('portal.dashboard'))->assertRedirect(route('mfa.challenge'));
    $this->withSession(['mfa_passed' => true])->get(route('portal.dashboard'))->assertOk();
});

it('records failed logins in the audit log', function () {
    $user = staffUser();

    $this->post('/login', ['email' => $user->email, 'password' => 'wrong-password']);

    expect(AuditEvent::query()->where('event', 'login_failed')->exists())->toBeTrue();
});

it('blocks deactivated users', function () {
    $user = staffUser('Case Manager', ['password' => 'secret-password-123', 'deactivated_at' => now()]);

    $this->post('/login', ['email' => $user->email, 'password' => 'secret-password-123'])
        ->assertSessionHasErrors('email');
    $this->assertGuest();
});

it('does not let clients into the staff portal or staff into odd client routes', function () {
    $client = clientUser();
    $this->actingAs($client)->get(route('portal.dashboard'))->assertForbidden();

    $staff = actingAsStaff();
    $this->actingAs($staff)->get(route('client.dashboard'))->assertForbidden();
});
