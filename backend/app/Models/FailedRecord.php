<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class FailedRecord extends Model
{
    protected $fillable = [
        'import_id',
        'name',
        'email',
        'phone',
        'company',
        'reason',
    ];
}
