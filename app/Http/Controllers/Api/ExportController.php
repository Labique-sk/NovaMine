<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\ExportRequest;
use App\Models\ActivityLog;
use App\Models\ExportRecord;
use App\Models\Shortlist;
use App\Services\Export\CandidateExportService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ExportController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $exports = ExportRecord::query()
            ->where('user_id', $request->user()->id)
            ->latest()
            ->paginate(20);

        return response()->json($exports);
    }

    public function store(ExportRequest $request, CandidateExportService $exports): JsonResponse
    {
        $shortlistId = $request->integer('shortlist_id') ?: null;
        if ($shortlistId) {
            $shortlist = Shortlist::query()->findOrFail($shortlistId);
            $this->authorize('view', $shortlist);
        }

        $export = $exports->create(
            $request->user(),
            $request->string('format'),
            $request->safe()->except(['format', 'shortlist_id']),
            $shortlistId
        );

        ActivityLog::query()->create([
            'user_id' => $request->user()->id,
            'action' => 'export.created',
            'subject_type' => ExportRecord::class,
            'subject_id' => $export->id,
            'properties' => ['format' => $export->format, 'count' => $export->record_count],
        ]);

        return response()->json(['export' => $export], 201);
    }

    public function download(Request $request, ExportRecord $export, CandidateExportService $exports): StreamedResponse
    {
        $this->authorize('view', $export);

        return $exports->download($export);
    }
}
