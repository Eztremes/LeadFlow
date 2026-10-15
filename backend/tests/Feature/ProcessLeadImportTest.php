<?php

namespace Tests\Feature;

use App\Jobs\ProcessLeadImport;
use App\Models\FailedRecord;
use App\Models\Import;
use App\Models\Lead;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class ProcessLeadImportTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('local');
        Mail::fake();

        // The processing job uses a database-backed lock.
        Cache::store('database')->clear();
    }

    private function createImport(array $rows): Import
    {
        $path = 'imports/test-' . uniqid() . '.csv';

        Storage::disk('local')->makeDirectory('imports');

        $handle = fopen(
            Storage::disk('local')->path($path),
            'w'
        );

        fputcsv($handle, ['name', 'email', 'phone', 'company']);

        foreach ($rows as $row) {
            fputcsv($handle, $row);
        }

        fclose($handle);

        return Import::create([
            'filename' => 'test.csv',
            'file_path' => $path,
            'notification_email' => 'reviewer@example.com',
            'status' => 'pending',
        ]);
    }

    public function test_valid_leads_are_imported(): void
    {
        $import = $this->createImport([
            ['Aarav', 'aarav@example.com', '9876543210', 'TechNova'],
            ['Priya', 'priya@example.com', '9876543211', 'CloudWorks'],
        ]);

        (new ProcessLeadImport($import->id))->handle();

        $import->refresh();

        $this->assertSame('completed', $import->status);
        $this->assertEquals(2, $import->success_count);
        $this->assertEquals(0, $import->failed_count);
        $this->assertEquals(2, $import->last_processed_row);

        $this->assertDatabaseHas('leads', [
            'email' => 'aarav@example.com',
        ]);
    }

    public function test_invalid_and_duplicate_rows_are_recorded(): void
    {
        $import = $this->createImport([
            ['Aarav', 'aarav@example.com', '9876543210', 'TechNova'],
            ['Duplicate', 'aarav@example.com', '9876543211', 'Another Co'],
            ['Invalid', 'not-an-email', '9876543212', 'Test Co'],
            ['Missing Company', 'missing@example.com', '9876543213', ''],
        ]);

        (new ProcessLeadImport($import->id))->handle();

        $import->refresh();

        $this->assertEquals(4, $import->total_records);
        $this->assertEquals(1, $import->success_count);
        $this->assertEquals(3, $import->failed_count);
        $this->assertEquals(4, $import->last_processed_row);

        $this->assertEquals(
            3,
            FailedRecord::where('import_id', $import->id)->count()
        );

        $this->assertNotNull($import->failed_csv_path);

        Storage::disk('local')->assertExists(
            $import->failed_csv_path
        );
    }

    public function test_completed_import_is_not_processed_again(): void
    {
        $import = $this->createImport([
            ['Aarav', 'aarav@example.com', '9876543210', 'TechNova'],
        ]);

        (new ProcessLeadImport($import->id))->handle();
        (new ProcessLeadImport($import->id))->handle();

        $import->refresh();

        $this->assertEquals(1, $import->success_count);
        $this->assertEquals(0, $import->failed_count);
        $this->assertEquals(1, $import->last_processed_row);

        $this->assertEquals(
            1,
            Lead::where('email', 'aarav@example.com')->count()
        );
    }
}
