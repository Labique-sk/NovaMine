<?php

namespace App\Http\Controllers;

use App\Models\ActivityLog;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class GoogleAuthController extends Controller
{
    public function redirect(Request $request): RedirectResponse
    {
        if (! $this->isConfigured()) {
            return redirect('/?auth=google_not_configured');
        }

        $state = Str::random(64);
        $request->session()->put('google_signin_state', $state);

        $query = http_build_query([
            'client_id' => config('services.google.client_id'),
            'redirect_uri' => $this->redirectUri(),
            'response_type' => 'code',
            'scope' => 'openid email profile',
            'state' => $state,
            'prompt' => 'select_account',
        ]);

        return redirect()->away('https://accounts.google.com/o/oauth2/v2/auth?'.$query);
    }

    public function callback(Request $request): RedirectResponse
    {
        if ($request->filled('error')) {
            return redirect('/?auth=google_cancelled');
        }

        $state = (string) $request->query('state', '');
        $expectedState = (string) $request->session()->pull('google_signin_state', '');
        if ($state === '' || $expectedState === '' || ! hash_equals($expectedState, $state)) {
            return redirect('/?auth=google_failed');
        }

        $code = (string) $request->query('code', '');
        if ($code === '' || ! $this->isConfigured()) {
            return redirect('/?auth=google_failed');
        }

        try {
            $tokenResponse = Http::asForm()->timeout(12)->post('https://oauth2.googleapis.com/token', [
                'code' => $code,
                'client_id' => config('services.google.client_id'),
                'client_secret' => config('services.google.client_secret'),
                'redirect_uri' => $this->redirectUri(),
                'grant_type' => 'authorization_code',
            ]);

            if (! $tokenResponse->successful() || ! $tokenResponse->json('access_token')) {
                Log::warning('Google sign-in token exchange failed', ['status' => $tokenResponse->status()]);

                return redirect('/?auth=google_failed');
            }

            $profileResponse = Http::withToken((string) $tokenResponse->json('access_token'))
                ->timeout(12)
                ->get('https://openidconnect.googleapis.com/v1/userinfo');

            if (! $profileResponse->successful()) {
                Log::warning('Google sign-in profile request failed', ['status' => $profileResponse->status()]);

                return redirect('/?auth=google_failed');
            }

            $profile = $profileResponse->json();
            $googleId = (string) ($profile['sub'] ?? '');
            $email = strtolower(trim((string) ($profile['email'] ?? '')));
            if ($googleId === '' || ! filter_var($email, FILTER_VALIDATE_EMAIL) || ($profile['email_verified'] ?? false) !== true) {
                return redirect('/?auth=google_email_unverified');
            }

            $user = User::query()->where('google_id', $googleId)->first();
            if (! $user) {
                $user = User::query()->where('email', $email)->first();
                if ($user && filled($user->google_id) && $user->google_id !== $googleId) {
                    return redirect('/?auth=google_account_conflict');
                }

                if (! $user) {
                    $user = User::query()->create([
                        'name' => trim((string) ($profile['name'] ?? '')) ?: Str::before($email, '@'),
                        'email' => $email,
                        'password' => Str::random(64),
                    ]);
                }

                $user->forceFill([
                    'google_id' => $googleId,
                    'email_verified_at' => $user->email_verified_at ?: now(),
                ])->save();
            }

            Auth::login($user);
            $request->session()->regenerate();

            ActivityLog::query()->create([
                'user_id' => $user->id,
                'action' => 'auth.google_login',
            ]);

            return redirect('/?auth=google_success');
        } catch (\Throwable $exception) {
            Log::warning('Google sign-in failed', ['exception' => $exception::class]);

            return redirect('/?auth=google_failed');
        }
    }

    private function isConfigured(): bool
    {
        return filled(config('services.google.client_id')) && filled(config('services.google.client_secret'));
    }

    private function redirectUri(): string
    {
        return (string) (config('services.google.auth_redirect') ?: route('auth.google.callback'));
    }
}
