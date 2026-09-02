<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\UpdateCandidateRequest;
use App\Models\Candidate;
use App\Models\Shortlist;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CandidateController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();
        $query = Candidate::query()->ownedBy($user)->with('skills');

        if ($q = $request->string('q')->toString()) {
            $query->where(function (Builder $inner) use ($q) {
                $inner->where('name', 'like', "%{$q}%")
                    ->orWhere('designation', 'like', "%{$q}%")
                    ->orWhere('company', 'like', "%{$q}%")
                    ->orWhere('location', 'like', "%{$q}%")
                    ->orWhere('professional_email', 'like', "%{$q}%")
                    ->orWhereHas('skills', fn (Builder $s) => $s->where('name', 'like', "%{$q}%"));
            });
        }

        foreach (['location', 'industry', 'source_name', 'verification_status', 'designation'] as $column) {
            if ($request->filled($column)) {
                $query->where($column, $request->string($column));
            }
        }
        if ($request->filled('skill')) {
            $skill = $request->string('skill');
            $query->whereHas('skills', fn (Builder $s) => $s->where('name', 'like', "%{$skill}%"));
        }
        if ($request->filled('role')) {
            $query->where('designation', 'like', '%'.$request->string('role').'%');
        }
        if ($request->filled('min_score')) {
            $query->where('match_score', '>=', $request->integer('min_score'));
        }
        if ($request->filled('experience_min')) {
            $query->where('years_experience', '>=', $request->integer('experience_min'));
        }
        if ($request->filled('experience_max')) {
            $query->where('years_experience', '<=', $request->integer('experience_max'));
        }
        if ($request->filled('from')) {
            $query->whereDate('created_at', '>=', $request->date('from'));
        }
        if ($request->filled('to')) {
            $query->whereDate('created_at', '<=', $request->date('to'));
        }

        $sort = $request->string('sort', 'match_score')->toString();
        $dir = $request->string('dir', 'desc')->toString() === 'asc' ? 'asc' : 'desc';
        $sortMap = [
            'name' => 'name',
            'role' => 'designation',
            'company' => 'company',
            'location' => 'location',
            'experience' => 'years_experience',
            'score' => 'match_score',
            'created_at' => 'created_at',
        ];
        $column = $sortMap[$sort] ?? 'match_score';
        $query->orderBy($column, $dir)->orderByDesc('id');

        $page = $query->paginate($request->integer('per_page', 10));

        return response()->json([
            'data' => collect($page->items())->map->toSearchArray()->values(),
            'meta' => [
                'current_page' => $page->currentPage(),
                'last_page' => $page->lastPage(),
                'per_page' => $page->perPage(),
                'total' => $page->total(),
            ],
        ]);
    }

    public function show(Request $request, Candidate $candidate): JsonResponse
    {
        $this->authorize('view', $candidate);
        $candidate->load(['skills', 'education', 'sources', 'validationResults', 'shortlists']);

        return response()->json(['candidate' => $this->detail($candidate)]);
    }

    public function update(UpdateCandidateRequest $request, Candidate $candidate): JsonResponse
    {
        $this->authorize('update', $candidate);
        $candidate->fill($request->validated())->save();

        return response()->json(['candidate' => $this->detail($candidate->fresh(['skills', 'education', 'sources', 'validationResults', 'shortlists']))]);
    }

    public function destroy(Request $request, Candidate $candidate): JsonResponse
    {
        $this->authorize('delete', $candidate);
        $candidate->delete();

        return response()->json(['ok' => true]);
    }

    public function addToShortlist(Request $request, Candidate $candidate): JsonResponse
    {
        $this->authorize('update', $candidate);
        $request->validate(['shortlist_id' => ['nullable', 'integer']]);
        $user = $request->user();

        $shortlist = $request->filled('shortlist_id')
            ? Shortlist::query()->where('user_id', $user->id)->findOrFail($request->integer('shortlist_id'))
            : Shortlist::query()->firstOrCreate(['user_id' => $user->id, 'name' => 'Saved candidates']);

        $this->authorize('update', $shortlist);
        $shortlist->candidates()->syncWithoutDetaching([$candidate->id]);

        return response()->json(['ok' => true, 'shortlist_id' => $shortlist->id]);
    }

    public function removeFromShortlist(Request $request, Candidate $candidate): JsonResponse
    {
        $this->authorize('update', $candidate);
        $user = $request->user();
        $query = Shortlist::query()->where('user_id', $user->id);
        if ($request->filled('shortlist_id')) {
            $query->where('id', $request->integer('shortlist_id'));
        }
        $query->get()->each(fn (Shortlist $list) => $list->candidates()->detach($candidate->id));

        return response()->json(['ok' => true]);
    }

    private function detail(Candidate $candidate): array
    {
        return [
            'id' => $candidate->id,
            'name' => $candidate->name,
            'designation' => $candidate->designation,
            'company' => $candidate->company,
            'location' => $candidate->location,
            'years_experience' => $candidate->years_experience,
            'industry' => $candidate->industry,
            'professional_email' => $candidate->professional_email,
            'phone' => $candidate->phone,
            'profile_url' => $candidate->profile_url,
            'portfolio_url' => $candidate->portfolio_url,
            'source_url' => $candidate->source_url,
            'source_name' => $candidate->source_name,
            'verification_status' => $candidate->verification_status,
            'email_validation_status' => $candidate->email_validation_status,
            'phone_validation_status' => $candidate->phone_validation_status,
            'profile_validation_status' => $candidate->profile_validation_status,
            'duplicate_status' => $candidate->duplicate_status,
            'match_score' => $candidate->match_score,
            'score_breakdown' => $candidate->score_breakdown,
            'is_synthetic' => $candidate->is_synthetic,
            'created_at' => $candidate->created_at,
            'skills' => $candidate->skills->pluck('name'),
            'education' => $candidate->education,
            'sources' => $candidate->sources,
            'validation' => $candidate->validationResults,
            'shortlist_ids' => $candidate->shortlists->pluck('id'),
        ];
    }
}
