<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\ActivityLog;
use App\Services\Export\CandidateExportService;
use App\Services\Export\GoogleSheetsService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Throwable;

class GoogleController extends Controller
{
    public function connect(Request $request, GoogleSheetsService $google): JsonResponse
    {
        if (! $google->isConfigured()) {
            return response()->json([
                'configured' => false,
                'connected' => false,
                'message' => 'Google Sheets is not configured. Add GOOGLE_CLIENT_ID and GOOGLE_CLIENT_SECRET.',
            ], 422);
        }

        return response()->json([
            'configured' => true,
            'connected' => $request->user()->googleConnected(),
            'url' => $google->authorizationUrl($request->user()),
        ]);
    }

    public function callback(Request $request, GoogleSheetsService $google): RedirectResponse
    {
        $request->validate([
            'code' => ['required', 'string'],
            'state' => ['required', 'string'],
        ]);

        try {
            $google->handleCallback($request->string('code'), $request->string('state'), $request->user());
        } catch (Throwable $e) {
            return redirect('/?google=error');
        }

        return redirect('/?google=connected');
    }

    public function export(Request $request, GoogleSheetsService $google, CandidateExportService $exports): JsonResponse
    {
        if (! $google->isConfigured()) {
            return response()->json(['message' => 'Google Sheets is not configured.'], 422);
        }
        if (! $request->user()->googleConnected()) {
            return response()->json(['message' => 'Google Sheets authorization required.'], 409);
        }

        try {
            $rows = $exports->rows($request->user(), $request->all(), $request->integer('shortlist_id') ?: null);
            $id = $google->exportRows($request->user(), $rows);
        } catch (Throwable $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        ActivityLog::query()->create([
            'user_id' => $request->user()->id,
            'action' => 'google.export',
            'properties' => ['spreadsheet_id' => $id, 'count' => count($rows)],
        ]);

        return response()->json(['spreadsheet_id' => $id, 'count' => count($rows)]);
    }

    public function status(Request $request, GoogleSheetsService $google): JsonResponse
    {
        return response()->json([
            'configured' => $google->isConfigured(),
            'connected' => $request->user()->googleConnected(),
        ]);
    }
}
