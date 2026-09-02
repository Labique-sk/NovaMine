<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\UpdateProfileRequest;
use App\Models\ActivityLog;
use App\Models\ExportRecord;
use App\Models\Search;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;

class SettingsController extends Controller
{
    public function show(Request $request): JsonResponse
    {
        $user = $request->user();

        return response()->json([
            'user' => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'initials' => $user->initials(),
                'google_connected' => $user->googleConnected(),
                'preferences' => $user->preferences ?? [],
            ],
            'searches' => Search::query()->where('user_id', $user->id)->latest()->limit(20)->get(),
            'exports' => ExportRecord::query()->where('user_id', $user->id)->latest()->limit(20)->get(),
        ]);
    }

    public function update(UpdateProfileRequest $request): JsonResponse
    {
        $user = $request->user();
        $data = $request->safe()->only(['name', 'email', 'preferences']);
        if ($request->filled('password')) {
            $data['password'] = $request->string('password');
        }
        $user->fill($data)->save();

        ActivityLog::query()->create([
            'user_id' => $user->id,
            'action' => 'settings.updated',
        ]);

        return response()->json([
            'user' => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'initials' => $user->initials(),
                'google_connected' => $user->googleConnected(),
                'preferences' => $user->preferences ?? [],
            ],
        ]);
    }
}
