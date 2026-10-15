<?php

namespace App\Jobs;

use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Support\Facades\Cache;
use App\Models\Import;
use App\Models\Lead;
use App\Models\FailedRecord;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use RuntimeException;
use Throwable;

class ProcessLeadImport implements ShouldQueue, ShouldBeUnique
{
    use Queueable;

    public int $timeout = 3600;
    public int $tries = 3;
public int $backoff = 10;

    public function __construct(
        public int $importId
    ) {}


public function uniqueId(): string
{
    return "lead-import-{$this->importId}";
}

public function uniqueVia()
{
    return Cache::store('database');
}


    public function handle(): void
    {
         $lock = Cache::store('database')
        ->lock("processing-import-{$this->importId}", 3700);

    if (!$lock->get()) {
        $this->release(10);
        return;
    }

    try {
        $this->processImport();
    } finally {
        $lock->release();
    }
    }

    private function processImport(): void
{
     $import = Import::findOrFail($this->importId);

        if ($import->status === 'completed') {
            return;
        }

        $import->update([
            'status' => 'processing',
            'error_message' => null,
        ]);

        $path = Storage::disk('local')->path($import->file_path);
        $handle = fopen($path, 'r');

        if ($handle === false) {
            throw new RuntimeException('Unable to open CSV file.');
        }

        try {
            // Read and validate CSV header.
            $header = fgetcsv($handle, 0, ',', '"', '');

            if ($header === false) {
                throw new RuntimeException('CSV file is empty.');
            }

            $header = array_map(
                fn ($column) => Str::lower(
                    trim(str_replace("\xEF\xBB\xBF", '', $column))
                ),
                $header
            );

            $required = ['name', 'email', 'phone', 'company'];

            if (
                count($header) !== count(array_unique($header)) ||
                array_diff($required, $header)
            ) {
                throw new RuntimeException(
                    'Invalid CSV header. Required: name,email,phone,company'
                );
            }

            // First pass: count rows without loading the file.
            $total = 0;

            while (fgetcsv($handle, 0, ',', '"', '') !== false) {
                $total++;
            }

            $import->update(['total_records' => $total]);

            // Return to beginning for actual processing.
            rewind($handle);
            fgetcsv($handle, 0, ',', '"', '');

           $batch = [];
$success = (int) $import->success_count;
$failed = (int) $import->failed_count;
$rowNumber = 0;
$checkpoint = (int) $import->last_processed_row;

            while (($values = fgetcsv($handle, 0, ',', '"', '')) !== false) {

            $rowNumber++;

if ($rowNumber <= $checkpoint) {
    continue;
}

                $row = [];

                foreach ($header as $index => $column) {
                    $row[$column] = trim($values[$index] ?? '');
                }

                // A malformed row must not silently lose extra columns.
                if (count($values) !== count($header)) {
                    $row['_error'] = 'Incorrect number of CSV columns';
                }

                $batch[] = $row;


if (count($batch) >= 500) {
    $this->processBatch(
        $batch,
        $import->id,
        $rowNumber
    );

    $batch = [];
}

            }


if (!empty($batch)) {
    $this->processBatch(
        $batch,
        $import->id,
        $rowNumber
    );
}






$import->refresh();

$failedCsvPath = $this->generateFailedCsv($import->id);

$import->update([
    'failed_csv_path' => $failedCsvPath,
    'status' => 'completed',
]);

if ($failedCsvPath !== null) {
    SendFailedRecordsEmail::dispatch($import->id);
}


        } finally {
            fclose($handle);
        }
    }

    private function processBatch(array $rows, int $importId, int $lastRowNumber): array
    {
        $valid = [];
        $failures = [];
        $seenInBatch = [];

        foreach ($rows as $row) {

            $data = [
                'name' => $row['name'] ?? '',
                'email' => Str::lower($row['email'] ?? ''),
                'phone' => $row['phone'] ?? '',
                'company' => $row['company'] ?? '',
            ];

            $validator = Validator::make($data, [
                'name' => 'required|string|max:255',
                'email' => 'required|email|max:255',
                'phone' => 'required|string|min:7|max:30',
                'company' => 'required|string|max:255',
            ]);

            $reason = null;

            if (isset($row['_error'])) {
                $reason = $row['_error'];
            } elseif ($validator->fails()) {
                $reason = implode(
                    '; ',
                    $validator->errors()->all()
                );
            } elseif (isset($seenInBatch[$data['email']])) {
                $reason = 'Duplicate email within CSV batch';
            }

            if ($reason !== null) {
                $failures[] = $this->failedRow(
                    $importId,
                    $data,
                    $reason
                );
                continue;
            }

            $seenInBatch[$data['email']] = true;
            $valid[] = $data;
        }

        // Check existing emails using one query per batch.
        $emails = array_column($valid, 'email');

        $existing = Lead::whereIn('email', $emails)
            ->pluck('email')
            ->mapWithKeys(fn ($email) => [$email => true])
            ->all();

        $inserts = [];
        $now = now();

        foreach ($valid as $data) {
            if (isset($existing[$data['email']])) {
                $failures[] = $this->failedRow(
                    $importId,
                    $data,
                    'Duplicate email already exists'
                );
            } else {
                $inserts[] = [
                    ...$data,
                    'created_at' => $now,
                    'updated_at' => $now,
                ];
            }
        }

        // Insert the entire batch atomically.

DB::transaction(function () use (
    $inserts,
    $failures,
    $importId,
    $lastRowNumber
) {
    if (!empty($inserts)) {
        Lead::insert($inserts);
    }

    if (!empty($failures)) {
        FailedRecord::insert($failures);
    }

    Import::whereKey($importId)->update([
        'success_count' => DB::raw(
            'success_count + ' . count($inserts)
        ),
        'failed_count' => DB::raw(
            'failed_count + ' . count($failures)
        ),
        'last_processed_row' => $lastRowNumber,
    ]);
});

return [count($inserts), count($failures)];
    }



private function generateFailedCsv(int $importId): ?string
{
    $hasFailures = FailedRecord::where('import_id', $importId)
        ->exists();

    if (!$hasFailures) {
        return null;
    }

    $directory = 'failed-imports';
    $path = "{$directory}/failed_records_{$importId}.csv";

    Storage::disk('local')->makeDirectory($directory);

    $handle = fopen(
        Storage::disk('local')->path($path),
        'w'
    );

    if ($handle === false) {
        throw new RuntimeException('Cannot create failed CSV.');
    }

    try {
        fputcsv($handle, [
            'name',
            'email',
            'phone',
            'company',
            'reason',
        ]);

        FailedRecord::where('import_id', $importId)
            ->chunkById(500, function ($records) use ($handle) {
                foreach ($records as $record) {
                    fputcsv($handle, [
                        $record->name,
                        $record->email,
                        $record->phone,
                        $record->company,
                        $record->reason,
                    ]);
                }
            });
    } finally {
        fclose($handle);
    }

    return $path;
}


    private function failedRow(
        int $importId,
        array $data,
        string $reason
    ): array {
        return [
            'import_id' => $importId,
            ...$data,
            'reason' => $reason,
            'created_at' => now(),
            'updated_at' => now(),
        ];
    }

    public function failed(?Throwable $exception): void
    {
        Import::whereKey($this->importId)->update([
            'status' => 'failed',
            'error_message' => $exception?->getMessage(),
        ]);
    }
}
