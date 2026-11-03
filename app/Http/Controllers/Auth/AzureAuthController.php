<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\Role;
use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Laravel\Socialite\Facades\Socialite;

/**
 * Microsoft Entra ID (Azure AD) single sign-on.
 *
 * Accounts are provisioned on first sign-in with the default role. Admins
 * promote people afterwards. Deactivated users stay locked out even when
 * Microsoft authenticates them.
 */
class AzureAuthController extends Controller
{
    public function redirectToProvider()
    {
        if (blank(config('services.azure.client_id'))) {
            return $this->fail('Single sign-on belum dikonfigurasi. Hubungi administrator.');
        }

        return Socialite::driver('azure')->redirect();
    }

    public function handleProviderCallback()
    {
        try {
            $azureUser = Socialite::driver('azure')->user();
        } catch (\Throwable $e) {
            if (str_contains($e->getMessage(), 'AADSTS50105')) {
                return $this->fail('Akun Anda belum diundang untuk menggunakan aplikasi ini. Hubungi administrator.');
            }
            Log::error('Azure SSO login error: ' . $e->getMessage());

            return $this->fail('Gagal masuk dengan Microsoft. Silakan coba lagi.');
        }

        $email = strtolower((string) $azureUser->getEmail());
        if ($email === '') {
            return $this->fail('Akun Microsoft Anda tidak memiliki alamat email.');
        }

        $allowed = config('booking.sso_domains');
        if ($allowed && ! in_array(Str::after($email, '@'), array_map('strtolower', $allowed), true)) {
            return $this->fail('Domain email Anda tidak diizinkan untuk aplikasi ini.');
        }

        $user = User::withTrashed()->where('sso_id', $azureUser->getId())->orWhere('email', $email)->first();

        if ($user && ($user->trashed() || $user->is_active === false)) {
            return $this->fail('Akun Anda dinonaktifkan. Hubungi administrator.');
        }

        if (! $user) {
            $user = User::create([
                'name' => $azureUser->getName() ?: Str::before($email, '@'),
                'email' => $email,
                'sso_id' => $azureUser->getId(),
                'is_sso' => true,
                'password' => null,
                'email_verified_at' => now(),
                'role_id' => Role::where('name', config('booking.sso_default_role'))->value('id'),
                'created_by' => 'sso',
            ]);
            AuditLog::record('user.provisioned', $user, "Akun {$user->email} dibuat otomatis lewat SSO", [], $user);
        } else {
            $user->forceFill(['sso_id' => $azureUser->getId(), 'is_sso' => true, 'email_verified_at' => $user->email_verified_at ?? now()])->save();
        }

        $user->forceFill(['last_login_at' => now()])->saveQuietly();
        Auth::login($user, true);
        request()->session()->regenerate();

        return redirect()->intended(route('dashboard'));
    }

    private function fail(string $message)
    {
        return redirect()->route('login')->withErrors(['azure' => $message]);
    }
}
