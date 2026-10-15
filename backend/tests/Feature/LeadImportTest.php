<?php

namespace Tests\Feature;

use App\Models\Import;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class LeadImportTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('local');
        Queue::fake();
    }

    private function csvFile(): UploadedFile
    {
        return UploadedFile::fake()->createWithContent(
            'leads.csv',
            "name,email,phone,company\n"
            . "Aarav,aarav@example.com,9876543210,TechNova\n"
            . "Priya,priya@example.com,9876543211,CloudWorks\n"
        );
    }

    public function test_valid_csv_can_be_uploaded(): void
    {
        $response = $this->post('/api/imports', [
            'file' => $this->csvFile(),
            'email' => 'reviewer@example.com',
        ]);

        $this->assertContains(
            $response->status(),
            [200, 201, 202]
        );

        $this->assertDatabaseHas('imports', [
            'filename' => 'leads.csv',
            'notification_email' => 'reviewer@example.com',
        ]);

        $import = Import::latest('id')->first();

        $this->assertNotNull($import);

        Storage::disk('local')->assertExists(
            $import->file_path
        );
    }

    public function test_csv_file_is_required(): void
    {
        $response = $this->post('/api/imports', [
            'email' => 'reviewer@example.com',
        ]);

       $response->assertStatus(422)
         ->assertJsonValidationErrors('file');

        $this->assertDatabaseCount('imports', 0);
    }

    public function test_valid_notification_email_is_required(): void
    {
        $response = $this->post('/api/imports', [
            'file' => $this->csvFile(),
            'email' => 'invalid-email',
        ]);

       $response->assertStatus(422)
         ->assertJsonValidationErrors('email');

        $this->assertDatabaseCount('imports', 0);
    }

    public function test_import_status_can_be_retrieved(): void
    {
        $import = Import::create([
            'filename' => 'leads.csv',
            'file_path' => 'imports/leads.csv',
            'notification_email' => 'reviewer@example.com',
            'status' => 'processing',
            'total_records' => 100,
            'success_count' => 60,
            'failed_count' => 10,
            'last_processed_row' => 70,
        ]);

        $response = $this->getJson(
            "/api/imports/{$import->id}"
        );

        $response->assertOk();

        $response->assertJsonPath(
            'status',
            'processing'
        );
    }
}
