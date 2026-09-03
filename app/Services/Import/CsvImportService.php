<?php

namespace App\Services\Import;

use App\Models\Candidate;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class CsvImportService
{
    /**
     * @var array<string, list<string>>
     */
    private const COLUMN_MAPPINGS = [
        'name' => ['name', 'full_name', 'candidate_name', 'first_name'],
        'email' => ['email', 'professional_email', 'email_address'],
        'phone' => ['phone', 'phone_number', 'mobile', 'contact'],
        'designation' => ['designation', 'title', 'role', 'job_title', 'position'],
        'company' => ['company', 'organization', 'employer'],
        'years_experience' => ['years_experience', 'experience', 'exp', 'years_of_experience'],
        'skills' => ['skills', 'skill', 'tech_stack', 'technologies'],
        'education' => ['education', 'degree', 'qualification'],
        'location' => ['location', 'city', 'region', 'area'],
        'industry' => ['industry', 'sector', 'field'],
        'profile_url' => ['profile_url', 'linkedin_url', 'website', 'portfolio', 'github'],
        'source_name' => ['source', 'source_name', 'origin'],
        'source_url' => ['source_url', 'url', 'link'],
        'summary' => ['summary', 'description', 'bio', 'about'],
    ];

    /**
     * @var array<string, mixed>
     */
    private array $results = [
        'total_rows' => 0,
        'imported' => 0,
        'duplicates' => 0,
        'invalid' => 0,
        'errors' => [],
    ];

    /**
     * @var array<int, array<string, mixed>>
     */
    private array $errorRows = [];

    /**
     * @var array<string, string>
     */
    private array $columnMap = [];

    /**
     * @var array<string, int>
     */
    private array $existingFingerprints = [];

    private User $user;

    /**
     * @return array<string, mixed>
     */
    public function import(string $filePath, User $user): array
    {
        if (! Storage::exists($filePath)) {
            throw new \RuntimeException('File not found.');
        }

        $fileContent = Storage::get($filePath);
        $lines = explode("\n", $fileContent);
        
        if (empty($lines)) {
            return [
                ...$this->results,
                'message' => 'CSV file is empty.',
            ];
        }

        // Load existing candidate fingerprints for duplicate detection
        $this->user = $user;
        $this->loadExistingFingerprints();

        // Detect column headers
        $headers = $this->parseHeaders($lines[0]);
        $this->mapColumns($headers);

        // Process data rows
        $dataRows = array_slice($lines, 1);
        $this->results['total_rows'] = count($dataRows);

        DB::transaction(function () use ($dataRows) {
            foreach ($dataRows as $rowIndex => $line) {
                $this->processRow($line, $rowIndex + 2); // +2 because header is row 1
            }
        });

        // Clean up uploaded file
        Storage::delete($filePath);

        return [
            ...$this->results,
            'message' => sprintf(
                'Import complete: %d imported, %d duplicates, %d invalid.',
                $this->results['imported'],
                $this->results['duplicates'],
                $this->results['invalid']
            ),
        ];
    }

    /**
     * @return array<string, string>
     */
    private function parseHeaders(string $headerLine): array
    {
        $headers = str_getcsv($headerLine);
        return array_map('trim', $headers);
    }

    /**
     * @param  array<string, string>  $headers
     */
    private function mapColumns(array $headers): void
    {
        $this->columnMap = [];
        
        foreach ($headers as $index => $header) {
            $normalizedHeader = strtolower(str_replace([' ', '_', '-'], '', $header));
            
            foreach (self::COLUMN_MAPPINGS as $targetField => $possibleNames) {
                foreach ($possibleNames as $name) {
                    if ($normalizedHeader === strtolower(str_replace([' ', '_', '-'], '', $name))) {
                        $this->columnMap[$targetField] = $index;
                        break 2;
                    }
                }
            }
        }
    }

    /**
     * @param  string  $line
     * @param  int  $rowNumber
     */
    private function processRow(string $line, int $rowNumber): void
    {
        $values = str_getcsv($line);
        
        if (count($values) < 2) {
            $this->results['invalid']++;
            $this->addError($rowNumber, 'Row has insufficient columns');
            return;
        }

        $candidateData = $this->extractCandidateData($values);

        // Validate required fields
        if (empty($candidateData['name'])) {
            $this->results['invalid']++;
            $this->addError($rowNumber, 'Name is required');
            return;
        }

        // Validate email format if provided
        if (!empty($candidateData['email']) && !filter_var($candidateData['email'], FILTER_VALIDATE_EMAIL)) {
            $this->results['invalid']++;
            $this->addError($rowNumber, 'Invalid email format');
            return;
        }

        // Normalize data
        $candidateData = $this->normalizeData($candidateData);

        // Check for duplicates
        $fingerprint = $this->generateFingerprint($candidateData);
        if (in_array($fingerprint, $this->existingFingerprints, true)) {
            $this->results['duplicates']++;
            $this->addError($rowNumber, 'Duplicate candidate (email, profile URL, or name+company)');
            return;
        }

        // Insert candidate
        try {
            $candidate = $this->createCandidate($candidateData);
            $this->existingFingerprints[] = $fingerprint;
            $this->results['imported']++;
        } catch (\Exception $e) {
            $this->results['invalid']++;
            $this->addError($rowNumber, 'Database error: ' . $e->getMessage());
        }
    }

    /**
     * @param  array<string, string>  $values
     * @return array<string, mixed>
     */
    private function extractCandidateData(array $values): array
    {
        $data = array_fill_keys(array_keys(self::COLUMN_MAPPINGS), null);

        foreach ($this->columnMap as $field => $index) {
            if (isset($values[$index])) {
                $data[$field] = trim($values[$index]);
            }
        }

        return $data;
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private function normalizeData(array $data): array
    {
        // Normalize phone number
        if (!empty($data['phone'])) {
            $data['phone'] = preg_replace('/[^\+]?(\d{10,15})$/', '+$1', preg_replace('/\D/', '', $data['phone']));
        }

        // Normalize experience to integer
        if (!empty($data['years_experience'])) {
            $data['years_experience'] = (int) preg_replace('/[^0-9]/', '', $data['years_experience']);
        }

        // Normalize skills - split by common delimiters
        if (!empty($data['skills'])) {
            $skills = preg_split('/[,;|]/', $data['skills']);
            $data['skills'] = array_map('trim', array_filter($skills));
        }

        // Trim all text fields
        foreach ($data as $key => $value) {
            if (is_string($value)) {
                $data[$key] = trim($value);
            }
        }

        // Prevent formula injection
        foreach ($data as $key => $value) {
            if (is_string($value) && in_array(substr($value, 0, 1), ['=', '+', '-', '@'])) {
                $data[$key] = "'" . $value;
            }
        }

        return $data;
    }

    /**
     * @param  array<string, mixed>  $data
     * @return string
     */
    private function generateFingerprint(array $data): string
    {
        $parts = [];
        
        if (!empty($data['email'])) {
            $parts[] = 'email:' . strtolower(trim($data['email']));
        }
        
        if (!empty($data['profile_url'])) {
            $parts[] = 'url:' . strtolower(rtrim(trim($data['profile_url']), '/'));
        }
        
        if (!empty($data['name'])) {
            $nameKey = strtolower(preg_replace('/\s+/', '', trim($data['name'])));
            if (!empty($data['company'])) {
                $parts[] = 'name_company:' . $nameKey . ':' . strtolower(trim($data['company']));
            } elseif (!empty($data['location'])) {
                $parts[] = 'name_location:' . $nameKey . ':' . strtolower(trim($data['location']));
            }
        }

        return implode('|', $parts);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function createCandidate(array $data): Candidate
    {
        $candidate = Candidate::query()->create([
            'user_id' => $this->user->id,
            'mining_job_id' => null,
            'name' => $data['name'],
            'designation' => $data['designation'] ?? null,
            'company' => $data['company'] ?? null,
            'years_experience' => $data['years_experience'] ?? null,
            'industry' => $data['industry'] ?? null,
            'location' => $data['location'] ?? null,
            'professional_email' => $data['email'] ?? null,
            'phone' => $data['phone'] ?? null,
            'profile_url' => $data['profile_url'] ?? null,
            'portfolio_url' => null,
            'source_url' => $data['source_url'] ?? null,
            'source_name' => $data['source_name'] ?? 'CSV Import',
            'candidate_type' => null,
            'verification_status' => 'unverified',
            'email_validation_status' => !empty($data['email']) ? 'unverified' : null,
            'phone_validation_status' => !empty($data['phone']) ? 'unverified' : null,
            'profile_validation_status' => !empty($data['profile_url']) ? 'unverified' : null,
            'duplicate_status' => 'unique',
            'match_score' => null,
            'score_breakdown' => null,
            'is_synthetic' => false,
        ]);

        // Add skills
        if (!empty($data['skills']) && is_array($data['skills'])) {
            foreach ($data['skills'] as $skill) {
                if (!empty($skill)) {
                    $candidate->skills()->create(['name' => $skill, 'is_match' => false]);
                }
            }
        }

        // Add source information
        $candidate->sources()->create([
            'source_name' => $data['source_name'] ?? 'CSV Import',
            'source_url' => $data['source_url'] ?? null,
            'collected_at' => now(),
        ]);

        return $candidate;
    }

    private function loadExistingFingerprints(): void
    {
        $candidates = Candidate::query()->ownedBy($this->user)
            ->select(['id', 'professional_email', 'profile_url', 'name', 'company', 'location'])
            ->get();

        foreach ($candidates as $candidate) {
            $this->existingFingerprints[] = $this->generateFingerprint([
                'email' => $candidate->professional_email,
                'profile_url' => $candidate->profile_url,
                'name' => $candidate->name,
                'company' => $candidate->company,
                'location' => $candidate->location,
            ]);
        }
    }

    /**
     * @param  int  $rowNumber
     * @param  string  $message
     */
    private function addError(int $rowNumber, string $message): void
    {
        $this->results['errors'][] = [
            'row' => $rowNumber,
            'message' => $message,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function getResults(): array
    {
        return $this->results;
    }

    /**
     * @return string
     */
    public function generateErrorReport(): string
    {
        $lines = ['Row,Error'];
        foreach ($this->results['errors'] as $error) {
            $lines[] = sprintf('%d,"%s"', $error['row'], $error['message']);
        }
        
        return implode("\n", $lines);
    }
}
