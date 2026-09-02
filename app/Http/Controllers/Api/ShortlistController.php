<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\ShortlistRequest;
use App\Models\Shortlist;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ShortlistController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $lists = Shortlist::query()
            ->where('user_id', $request->user()->id)
            ->with(['candidates.skills'])
            ->latest()
            ->get()
            ->map(fn (Shortlist $list) => [
                'id' => $list->id,
                'name' => $list->name,
                'description' => $list->description,
                'candidates' => $list->candidates->map->toSearchArray(),
            ]);

        return response()->json(['data' => $lists]);
    }

    public function store(ShortlistRequest $request): JsonResponse
    {
        $list = Shortlist::query()->create([
            'user_id' => $request->user()->id,
            'name' => $request->string('name'),
            'description' => $request->input('description'),
        ]);

        return response()->json(['shortlist' => $list], 201);
    }

    public function update(ShortlistRequest $request, Shortlist $shortlist): JsonResponse
    {
        $this->authorize('update', $shortlist);
        $shortlist->update($request->validated());

        return response()->json(['shortlist' => $shortlist]);
    }

    public function destroy(Request $request, Shortlist $shortlist): JsonResponse
    {
        $this->authorize('delete', $shortlist);
        $shortlist->delete();

        return response()->json(['ok' => true]);
    }
}
