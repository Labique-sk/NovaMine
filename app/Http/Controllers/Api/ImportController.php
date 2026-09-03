<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\ImportCsvRequest;
use App\Models\ActivityLog;
use App\Services\Import\CsvImportService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ImportController extends Controller
{
    public function downloadTemplate(): StreamedResponse
    {
        $template = implode("\n", [
            'name,email,phone,designation,company,years_experience,skills,education,location,industry,profile_url,source,source_url,summary',
            '"John Smith","john.smith@example.com","+14155551234","Software Engineer","Tech Corp",5,"Python,Java,SQL","BS Computer Science","San Francisco","Technology","https://linkedin.com/in/johnsmith","CSV Import","https://example.com","Senior software engineer with 5 years experience"',
            '"Jane Doe","jane.doe@example.com","+14155556789","Data Scientist","Data Inc",3,"Python,R,Machine Learning","MS Data Science","New York","Technology","https://linkedin.com/in/janedoe","CSV Import","","Data scientist specializing in ML"',
        ]);

        return response()->streamDownload(function () use ($template) {
            echo $template;
        }, 'novamine_import_template.csv', ['Content-Type' => 'text/csv']);
    }

    public function import(ImportCsvRequest $request, CsvImportService $importService): JsonResponse
    {
        $file = $request->file('csv_file');
        
        // Store the uploaded file temporarily
        $path = $file->store('temp/imports', 'local');
        
        try {
            $results = $importService->import($path, $request->user());
            
            // Log the import activity
            ActivityLog::query()->create([
                'user_id' => $request->user()->id,
                'action' => 'import.completed',
                'subject_type' => null,
                'subject_id' => null,
                'properties' => [
                    'total_rows' => $results['total_rows'],
                    'imported' => $results['imported'],
                    'duplicates' => $results['duplicates'],
                    'invalid' => $results['invalid'],
                ],
            ]);

            return response()->json([
                'success' => true,
                'message' => $results['message'],
                'results' => [
                    'total_rows' => $results['total_rows'],
                    'imported' => $results['imported'],
                    'duplicates' => $results['duplicates'],
                    'invalid' => $results['invalid'],
                    'errors' => $results['errors'],
                ],
            ]);
        } catch (\Exception $e) {
            // Clean up file on error
            if (Storage::exists($path)) {
                Storage::delete($path);
            }

            return response()->json([
                'success' => false,
                'message' => 'Import failed: ' . $e->getMessage(),
            ], 500);
        }
    }

    public function downloadErrorReport(Request $request): StreamedResponse
    {
        // This would typically be called with a specific import job ID
        // For now, we'll return a sample error report format
        $sampleReport = implode("\n", [
            'Row,Error',
            '2,"Name is required"',
            '5,"Invalid email format"',
            '8,"Duplicate candidate (email, profile URL, or name+company)"',
        ]);

        return response()->streamDownload(function () use ($sampleReport) {
            echo $sampleReport;
        }, 'import_errors.csv', ['Content-Type' => 'text/csv']);
    }
}
