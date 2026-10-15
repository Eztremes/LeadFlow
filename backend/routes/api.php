<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\ImportController;

Route::post('/imports', [ImportController::class, 'store']);
Route::get('/imports/{import}', [ImportController::class, 'show']);
Route::get(
    '/imports/{import}/failed-csv',
    [ImportController::class, 'downloadFailed']
);