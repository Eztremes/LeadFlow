<?php

namespace App\Jobs;

use App\Mail\FailedRecordsMail;
use App\Models\Import;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Mail;

class SendFailedRecordsEmail implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public int $backoff = 30;

    public function __construct(
        public int $importId
    ) {}

    public function handle(): void
    {
        $import = Import::findOrFail($this->importId);

        if (
            $import->status !== 'completed' ||
            empty($import->failed_csv_path)
        ) {
            return;
        }

        Mail::to($import->notification_email)->send(
            new FailedRecordsMail(
                $import->id,
                $import->failed_csv_path
            )
        );
    }
}
