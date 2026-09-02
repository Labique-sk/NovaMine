<?php

namespace App\Services\Export;

use App\Models\Candidate;
use App\Models\ExportRecord;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

class CandidateExportService
{
    public function __construct(private readonly XlsxWriter $xlsx)
    {
    }

    /**
     * @param  array<string, mixed>  $filters
     */
    public function create(User $user, string $format, array $filters = [], ?int $shortlistId = null): ExportRecord
    {
        $format = strtolower($format);
        if (! in_array($format, ['csv', 'xlsx', 'json'], true)) {
            throw new \InvalidArgumentException('Unsupported export format.');
        }

        $export = ExportRecord::query()->create([
            'user_id' => $user->id,
            'shortlist_id' => $shortlistId,
            'format' => $format,
            'filters' => $filters,
            'disk' => config('filesystems.default', 'local'),
            'status' => 'processing',
        ]);

        $rows = $this->rows($user, $filters, $shortlistId);
        $payload = $this->serialize($format, $rows);
        $path = sprintf('exports/%d/%s-%s.%s', $user->id, $export->id, now()->format('YmdHis'), $format);

        Storage::disk($export->disk)->put($path, $payload);

        $export->update([
            'file_path' => $path,
            'record_count' => count($rows),
            'status' => 'completed',
        ]);

        return $export->fresh();
    }

    public function download(ExportRecord $export): StreamedResponse
    {
        $disk = Storage::disk($export->disk);
        $name = 'novamine-candidates.'.$export->format;
        $mime = match ($export->format) {
            'csv' => 'text/csv',
            'json' => 'application/json',
            default => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        };

        return $disk->download($export->file_path, $name, ['Content-Type' => $mime]);
    }

    /**
     * @param  array<string, mixed>  $filters
     * @return list<array<string, mixed>>
     */
    public function rows(User $user, array $filters = [], ?int $shortlistId = null): array
    {
        $query = Candidate::query()->ownedBy($user)->with('skills');

        if ($shortlistId) {
            $query->whereHas('shortlists', fn (Builder $q) => $q->where('shortlists.id', $shortlistId)->where('shortlists.user_id', $user->id));
        }

        $this->applyFilters($query, $filters);

        $out = [];
        $query->orderByDesc('id')->chunk(200, function ($chunk) use (&$out) {
            foreach ($chunk as $candidate) {
                $out[] = [
                    'name' => $candidate->name,
                    'role' => $candidate->designation,
                    'company' => $candidate->company,
                    'location' => $candidate->location,
                    'experience' => $candidate->years_experience,
                    'score' => $candidate->match_score,
                    'status' => $candidate->verification_status,
                    'email' => $candidate->professional_email,
                    'phone' => $candidate->phone,
                    'skills' => $candidate->skills->pluck('name')->implode(', '),
                    'source' => $candidate->source_name,
                    'is_synthetic' => $candidate->is_synthetic ? 'yes' : 'no',
                ];
            }
        });

        return $out;
    }

    /**
     * @param  Builder<Candidate>  $query
     * @param  array<string, mixed>  $filters
     */
    private function applyFilters(Builder $query, array $filters): void
    {
        if (! empty($filters['q'])) {
            $q = $filters['q'];
            $query->where(function (Builder $inner) use ($q) {
                $inner->where('name', 'like', "%{$q}%")
                    ->orWhere('designation', 'like', "%{$q}%")
                    ->orWhere('company', 'like', "%{$q}%")
                    ->orWhere('location', 'like', "%{$q}%")
                    ->orWhere('professional_email', 'like', "%{$q}%")
                    ->orWhereHas('skills', fn (Builder $s) => $s->where('name', 'like', "%{$q}%"));
            });
        }
        if (isset($filters['min_score'])) {
            $query->where('match_score', '>=', (int) $filters['min_score']);
        }
        if (! empty($filters['ids']) && is_array($filters['ids'])) {
            $query->whereIn('id', $filters['ids']);
        }
        if (! empty($filters['verification_status'])) {
            $query->where('verification_status', $filters['verification_status']);
        }
    }

    /**
     * @param  list<array<string, mixed>>  $rows
     */
    private function serialize(string $format, array $rows): string
    {
        if ($format === 'json') {
            return json_encode($rows, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) ?: '[]';
        }

        $headers = ['name', 'role', 'company', 'location', 'experience', 'score', 'status', 'email', 'phone', 'skills', 'source', 'is_synthetic'];

        if ($format === 'csv') {
            $fh = fopen('php://temp', 'r+');
            fputcsv($fh, $headers);
            foreach ($rows as $row) {
                fputcsv($fh, array_map(fn ($h) => $row[$h] ?? '', $headers));
            }
            rewind($fh);

            return stream_get_contents($fh) ?: '';
        }

        $data = [];
        foreach ($rows as $row) {
            $data[] = array_map(fn ($h) => $row[$h] ?? '', $headers);
        }

        return $this->xlsx->build($headers, $data);
    }
}
