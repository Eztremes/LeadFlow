<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Attachment;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class FailedRecordsMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public int $importId,
        public string $failedCsvPath
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: "Failed Records - Import #{$this->importId}"
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.failed-records'
        );
    }

    public function attachments(): array
    {
        return [
            Attachment::fromStorageDisk(
                'local',
                $this->failedCsvPath
            )->as("failed_records_{$this->importId}.csv")
             ->withMime('text/csv'),
        ];
    }
}
