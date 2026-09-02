<?php

namespace App\Services\Export;

use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class GoogleSheetsService
{
    public function isConfigured(): bool
    {
        return filled(config('services.google.client_id')) && filled(config('services.google.client_secret'));
    }

    public function authorizationUrl(User $user): string
    {
        $state = Str::random(40);
        session(['google_oauth_state' => $state, 'google_oauth_user' => $user->id]);

        $query = http_build_query([
            'client_id' => config('services.google.client_id'),
            'redirect_uri' => config('services.google.redirect'),
            'response_type' => 'code',
            'scope' => 'https://www.googleapis.com/auth/spreadsheets https://www.googleapis.com/auth/drive.file',
            'access_type' => 'offline',
            'prompt' => 'consent',
            'state' => $state,
        ]);

        return 'https://accounts.google.com/o/oauth2/v2/auth?'.$query;
    }

    public function handleCallback(string $code, string $state, User $user): void
    {
        if ($state !== session('google_oauth_state') || (int) session('google_oauth_user') !== $user->id) {
            throw new \RuntimeException('Invalid Google OAuth state.');
        }

        $response = Http::asForm()->post('https://oauth2.googleapis.com/token', [
            'code' => $code,
            'client_id' => config('services.google.client_id'),
            'client_secret' => config('services.google.client_secret'),
            'redirect_uri' => config('services.google.redirect'),
            'grant_type' => 'authorization_code',
        ]);

        if (! $response->successful()) {
            Log::error('Google OAuth token exchange failed', ['body' => $response->body()]);
            throw new \RuntimeException('Google Sheets authorization failed.');
        }

        $user->forceFill([
            'google_access_token' => $response->json('access_token'),
            'google_refresh_token' => $response->json('refresh_token') ?? $user->google_refresh_token,
            'google_connected_at' => now(),
        ])->save();
    }

    /**
     * @param  list<array<string, mixed>>  $rows
     */
    public function exportRows(User $user, array $rows): string
    {
        if (! $this->isConfigured()) {
            throw new \RuntimeException('Google Sheets is not configured.');
        }
        if (! $user->googleConnected()) {
            throw new \RuntimeException('Google Sheets authorization required.');
        }

        $token = $this->accessToken($user);
        $spreadsheetId = $user->google_spreadsheet_id;

        if (! $spreadsheetId) {
            $created = Http::withToken($token)->post('https://sheets.googleapis.com/v4/spreadsheets', [
                'properties' => ['title' => 'NovaMine Candidates'],
            ]);
            if (! $created->successful()) {
                throw new \RuntimeException('Unable to create Google spreadsheet.');
            }
            $spreadsheetId = $created->json('spreadsheetId');
            $user->forceFill(['google_spreadsheet_id' => $spreadsheetId])->save();
        }

        $values = [['name', 'role', 'company', 'location', 'experience', 'score', 'status', 'email', 'phone', 'skills', 'source', 'is_synthetic']];
        foreach ($rows as $row) {
            $values[] = array_values($row);
        }

        $write = Http::withToken($token)->post(
            "https://sheets.googleapis.com/v4/spreadsheets/{$spreadsheetId}/values/Candidates!A1:append?valueInputOption=RAW",
            ['values' => $values]
        );

        if (! $write->successful()) {
            Log::error('Google Sheets write failed', ['body' => $write->body()]);
            throw new \RuntimeException('Unable to write to Google Sheets.');
        }

        return (string) $spreadsheetId;
    }

    private function accessToken(User $user): string
    {
        if ($user->google_access_token) {
            return (string) $user->google_access_token;
        }

        $refresh = Http::asForm()->post('https://oauth2.googleapis.com/token', [
            'client_id' => config('services.google.client_id'),
            'client_secret' => config('services.google.client_secret'),
            'refresh_token' => $user->google_refresh_token,
            'grant_type' => 'refresh_token',
        ]);

        if (! $refresh->successful()) {
            throw new \RuntimeException('Google Sheets authorization required.');
        }

        $token = (string) $refresh->json('access_token');
        $user->forceFill(['google_access_token' => $token])->save();

        return $token;
    }
}
