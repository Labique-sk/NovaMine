<?php

namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Throwable;

class HealthController extends Controller
{
    public function __invoke(): JsonResponse
    {
        $database = 'ok';
        try {
            DB::select('select 1');
        } catch (Throwable) {
            $database = 'error';
        }

        $ok = $database === 'ok';

        return response()->json([
            'status' => $ok ? 'ok' : 'degraded',
            'app' => 'NovaMine',
            'database' => $database,
        ], $ok ? 200 : 503);
    }
}
