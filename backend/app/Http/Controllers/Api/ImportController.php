<?php

namespace App\Http\Controllers\Api;

use App\Jobs\ProcessLeadImport;
use App\Http\Controllers\Controller;
use App\Models\Import;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class ImportController extends Controller
{
    public function store(Request $request)
    {
        $validated = $request->validate([
            'file' => 'required|file|mimes:csv,txt|max:102400',
            'email' => 'required|email',
        ]);

        // Save the uploaded CSV without reading it into PHP memory.
        $file = $request->file('file');

        $path = $file->store('imports', 'local');

        // Create an import tracking record.
        $import = Import::create([
            'filename' => $file->getClientOriginalName(),
            'file_path' => $path,
            'notification_email' => $validated['email'],
            'status' => 'pending',
        ]);
        ProcessLeadImport::dispatch($import->id);

        return response()->json([
            'message' => 'CSV uploaded successfully.',
            'import_id' => $import->id,
            'status' => $import->status,
        ], 202);
    }

    public function show(Import $import)
    {
        $processed = $import->success_count + $import->failed_count;

        return response()->json([
            'id' => $import->id,
            'filename' => $import->filename,
            'status' => $import->status,
            'total_records' => $import->total_records,
            'success_count' => $import->success_count,
            'failed_count' => $import->failed_count,
            'processed_records' => $processed,
            'progress' => $import->total_records > 0
                ? round(($processed / $import->total_records) * 100, 2)
                : 0,
            'error_message' => $import->error_message,
        ]);
    }


public function downloadFailed(Import $import)
{
    if (!$import->failed_csv_path) {
        return response()->json([
            'message' => 'No failed CSV available.',
        ], 404);
    }

    if (!Storage::disk('local')->exists(
        $import->failed_csv_path
    )) {
        return response()->json([
            'message' => 'Failed CSV file not found.',
        ], 404);
    }

    return Storage::disk('local')->download(
        $import->failed_csv_path,
        "failed_records_{$import->id}.csv"
    );
}

}
