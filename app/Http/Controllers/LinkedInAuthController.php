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

class LinkedInAuthController extends Controller
{
    public function redirect(Request $request): RedirectResponse
    {
        if (! $this->isConfigured()) {
            return redirect('/?auth=linkedin_not_configured');
        }

        $state = Str::random(64);
        $request->session()->put('linkedin_signin_state', $state);

        $query = http_build_query([
            'client_id' => config('services.linkedin.client_id'),
            'redirect_uri' => $this->redirectUri(),
            'response_type' => 'code',
            'scope' => 'openid profile email',
            'state' => $state,
        ]);

        return redirect()->away('https://www.linkedin.com/oauth/v2/authorization?'.$query);
    }

    public function callback(Request $request): RedirectResponse
    {
        if ($request->filled('error')) {
            return redirect('/?auth=linkedin_cancelled');
        }

        $state = (string) $request->query('state', '');
        $expectedState = (string) $request->session()->pull('linkedin_signin_state', '');
        if ($state === '' || $expectedState === '' || ! hash_equals($expectedState, $state)) {
            return redirect('/?auth=linkedin_failed');
        }

        $code = (string) $request->query('code', '');
        if ($code === '' || ! $this->isConfigured()) {
            return redirect('/?auth=linkedin_failed');
        }

        try {
            $tokenResponse = Http::asForm()->timeout(12)->post('https://www.linkedin.com/oauth/v2/accessToken', [
                'grant_type' => 'authorization_code',
                'code' => $code,
                'client_id' => config('services.linkedin.client_id'),
                'client_secret' => config('services.linkedin.client_secret'),
                'redirect_uri' => $this->redirectUri(),
            ]);

            if (! $tokenResponse->successful() || ! $tokenResponse->json('access_token')) {
                Log::warning('LinkedIn sign-in token exchange failed', ['status' => $tokenResponse->status()]);

                return redirect('/?auth=linkedin_failed');
            }

            $profileResponse = Http::withToken((string) $tokenResponse->json('access_token'))
                ->timeout(12)
                ->get('https://api.linkedin.com/v2/userinfo');

            if (! $profileResponse->successful()) {
                Log::warning('LinkedIn sign-in profile request failed', ['status' => $profileResponse->status()]);

                return redirect('/?auth=linkedin_failed');
            }

            $profile = $profileResponse->json();
            $linkedinId = (string) ($profile['sub'] ?? '');
            $email = strtolower(trim((string) ($profile['email'] ?? '')));
            if ($linkedinId === '' || ! filter_var($email, FILTER_VALIDATE_EMAIL) || ($profile['email_verified'] ?? false) !== true) {
                return redirect('/?auth=linkedin_email_unverified');
            }

            $user = User::query()->where('linkedin_id', $linkedinId)->first();
            if (! $user) {
                $user = User::query()->where('email', $email)->first();
                if ($user && filled($user->linkedin_id) && $user->linkedin_id !== $linkedinId) {
                    return redirect('/?auth=linkedin_account_conflict');
                }

                if (! $user) {
                    $user = User::query()->create([
                        'name' => trim((string) ($profile['name'] ?? '')) ?: Str::before($email, '@'),
                        'email' => $email,
                        'password' => Str::random(64),
                    ]);
                }

                $user->forceFill([
                    'linkedin_id' => $linkedinId,
                    'email_verified_at' => $user->email_verified_at ?: now(),
                ])->save();
            }

            Auth::login($user);
            $request->session()->regenerate();

            ActivityLog::query()->create([
                'user_id' => $user->id,
                'action' => 'auth.linkedin_login',
            ]);

            return redirect('/?auth=linkedin_success');
        } catch (\Throwable $exception) {
            Log::warning('LinkedIn sign-in failed', ['exception' => $exception::class]);

            return redirect('/?auth=linkedin_failed');
        }
    }

    private function isConfigured(): bool
    {
        return filled(config('services.linkedin.client_id')) && filled(config('services.linkedin.client_secret'));
    }

    private function redirectUri(): string
    {
        return (string) (config('services.linkedin.redirect') ?: route('auth.linkedin.callback'));
    }
}
