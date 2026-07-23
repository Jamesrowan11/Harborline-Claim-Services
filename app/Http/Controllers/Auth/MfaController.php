<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\AuditEvent;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use PragmaRX\Google2FA\Google2FA;

class MfaController extends Controller
{
    public function enroll(Request $request, Google2FA $google2fa)
    {
        $user = $request->user();

        if (! $request->session()->has('mfa_enroll_secret')) {
            $request->session()->put('mfa_enroll_secret', $google2fa->generateSecretKey());
        }

        $secret = $request->session()->get('mfa_enroll_secret');
        $otpauthUrl = $google2fa->getQRCodeUrl(config('branding.name'), $user->email, $secret);

        return view('auth.mfa-enroll', compact('secret', 'otpauthUrl'));
    }

    public function confirmEnrollment(Request $request, Google2FA $google2fa)
    {
        $request->validate(['code' => ['required', 'digits:6']]);
        $secret = $request->session()->get('mfa_enroll_secret');
        abort_unless($secret, 400);

        if (! $google2fa->verifyKey($secret, $request->input('code'))) {
            throw ValidationException::withMessages(['code' => __('That code is not valid. Please try again.')]);
        }

        $recoveryCodes = collect(range(1, 8))->map(fn () => Str::upper(Str::random(10)))->all();

        $request->user()->forceFill([
            'mfa_secret' => $secret,
            'mfa_enabled_at' => now(),
            'mfa_recovery_codes' => $recoveryCodes,
        ])->save();

        $request->session()->forget('mfa_enroll_secret');
        $request->session()->put('mfa_passed', true);
        AuditEvent::record('mfa_enrolled', $request->user());

        return view('auth.mfa-recovery-codes', compact('recoveryCodes'));
    }

    public function challenge()
    {
        return view('auth.mfa-challenge');
    }

    public function verify(Request $request, Google2FA $google2fa)
    {
        $request->validate(['code' => ['required', 'string']]);
        $user = $request->user();
        $code = trim($request->input('code'));

        $throttleKey = 'mfa|'.$user->id;
        if (RateLimiter::tooManyAttempts($throttleKey, 5)) {
            throw ValidationException::withMessages(['code' => __('Too many attempts. Please wait and try again.')]);
        }

        $valid = strlen($code) === 6 && ctype_digit($code)
            ? $google2fa->verifyKey($user->mfa_secret, $code)
            : $this->consumeRecoveryCode($user, $code);

        if (! $valid) {
            RateLimiter::hit($throttleKey, 300);
            AuditEvent::record('mfa_failed', $user);
            throw ValidationException::withMessages(['code' => __('That code is not valid.')]);
        }

        RateLimiter::clear($throttleKey);
        $request->session()->put('mfa_passed', true);
        AuditEvent::record('mfa_passed', $user);

        return redirect()->intended($user->isClient() ? route('client.dashboard') : route('portal.dashboard'));
    }

    private function consumeRecoveryCode($user, string $code): bool
    {
        $codes = $user->mfa_recovery_codes ?? [];
        $index = array_search(strtoupper($code), $codes, true);

        if ($index === false) {
            return false;
        }

        unset($codes[$index]);
        $user->forceFill(['mfa_recovery_codes' => array_values($codes)])->save();

        return true;
    }
}
