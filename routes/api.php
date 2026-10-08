<?php

declare(strict_types=1);

use App\Http\Controllers\Api\IngestController;
use App\Http\Middleware\AuthenticateSite;
use Illuminate\Support\Facades\Route;

Route::post('/v1/ingest', IngestController::class)
    ->middleware(['throttle:ingest', AuthenticateSite::class])
    ->name('api.ingest');
