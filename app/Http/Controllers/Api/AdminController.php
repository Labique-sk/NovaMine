<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\MiningJob;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class AdminController extends Controller
{
    private const SESSION_KEY = 'admin_dashboard_authorized_until';

    public function unlock(Request $request): JsonResponse
    {
        abort_unless($this->isAdminAccount($request), 403, 'This account is not allowed to access administration.');

        $password = (string) config('services.admin.password');
        abort_if($password === '', 503, 'Admin dashboard password is not configured on the server.');

        $credentials = $request->validate(['password' => ['required', 'string', 'max:255']]);
        if (! hash_equals($password, $credentials['password'])) {
            throw ValidationException::withMessages(['password' => 'The admin password is incorrect.']);
        }

        $request->session()->put(self::SESSION_KEY, now()->addMinutes(20)->timestamp);

        return $this->users($request);
    }

    public function index(Request $request): JsonResponse
    {
        $this->authorizeAdminSession($request);

        return $this->users($request);
    }

    public function setStatus(Request $request, User $user): JsonResponse
    {
        $this->authorizeAdminSession($request);
        $data = $request->validate(['active' => ['required', 'boolean']]);

        abort_if($request->user()->is($user) && ! $data['active'], 422, 'You cannot deactivate the admin account you are using.');

        $user->forceFill(['is_active' => $data['active']])->save();

        return $this->users($request);
    }

    public function lock(Request $request): JsonResponse
    {
        $request->session()->forget(self::SESSION_KEY);

        return response()->json(['ok' => true]);
    }

    private function users(Request $request): JsonResponse
    {
        $users = User::query()
            ->withCount(['miningJobs', 'candidates'])
            ->orderByDesc('created_at')
            ->get(['id', 'name', 'email', 'is_active', 'created_at']);

        return response()->json([
            'summary' => [
                'users' => $users->count(),
                'active_users' => $users->where('is_active', true)->count(),
                'deactivated_users' => $users->where('is_active', false)->count(),
                'mining_runs' => MiningJob::query()->count(),
                'candidates_mined' => $users->sum('candidates_count'),
            ],
            'users' => $users->map(fn (User $user) => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'active' => $user->is_active,
                'active_since' => $user->created_at?->toIso8601String(),
                'mining_runs' => $user->mining_jobs_count,
                'candidates_mined' => $user->candidates_count,
            ]),
            'admin_access_expires_at' => $request->session()->get(self::SESSION_KEY),
        ]);
    }

    private function authorizeAdminSession(Request $request): void
    {
        abort_unless($this->isAdminAccount($request), 403, 'This account is not allowed to access administration.');
        $expiresAt = (int) $request->session()->get(self::SESSION_KEY, 0);
        abort_if($expiresAt < now()->timestamp, 423, 'Admin session expired. Unlock the admin dashboard again.');
    }

    private function isAdminAccount(Request $request): bool
    {
        $adminEmail = strtolower(trim((string) config('services.admin.email')));

        return $adminEmail !== '' && strtolower((string) $request->user()?->email) === $adminEmail;
    }
}
