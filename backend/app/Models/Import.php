<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Import extends Model
{
    protected $fillable = [
        'filename',
        'file_path',
        'notification_email',
        'total_records',
        'success_count',
        'failed_count',
        'status',
        'failed_csv_path',
        'error_message',
        'last_processed_row',
    ];
}
