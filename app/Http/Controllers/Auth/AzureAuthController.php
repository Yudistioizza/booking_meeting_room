<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use Laravel\Socialite\Facades\Socialite;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use App\Models\User;
use Illuminate\Support\Str;

class AzureAuthController extends Controller
{
    public function redirectToProvider()
    {
        return Socialite::driver('azure')->redirect();
    }

    public function handleProviderCallback()
    {
        try {
            $azureUser = Socialite::driver('azure')->user();
        } catch (\Exception $e) {
            $errorMessage = $e->getMessage();

            // Tangani error khusus jika user tidak diassign di Azure Enterprise App
            if (str_contains($errorMessage, 'AADSTS50105')) {
                return redirect()->route('login')->withErrors([
                    'azure' => 'Akun Anda belum diundang untuk menggunakan aplikasi ini. Hubungi administrator.',
                ]);
            }

            // Log error umum
            Log::error('Azure SSO login error: '.$errorMessage, [
                'exception' => $e,
                'trace' => $e->getTraceAsString(),
            ]);

            return redirect()->route('login')->withErrors([
                'azure' => 'Gagal login dengan Azure. Silakan coba lagi.',
            ]);
        }

        // Tambahan filter: cek domain email jika mau batasi hanya domain tertentu
        $allowedDomains = ['perusahaan.com']; // ubah sesuai domain perusahaan
        $emailDomain = substr(strrchr($azureUser->getEmail(), "@"), 1);

        if (!in_array(strtolower($emailDomain), $allowedDomains)) {
            return redirect()->route('login')->withErrors([
                'azure' => 'Akun email Anda tidak diizinkan untuk login.',
            ]);
        }

        // Simpan atau update user di database
        $user = User::updateOrCreate(
            ['email' => $azureUser->getEmail()],
            [
                'name' => $azureUser->getName() ?? 'Azure User',
                'sso_id' => $azureUser->getId(),
                'password' => bcrypt(Str::random(32)), // password random untuk keamanan
            ]
        );

        Auth::login($user, true);

        return redirect()->intended('dashboard');
    }
}
