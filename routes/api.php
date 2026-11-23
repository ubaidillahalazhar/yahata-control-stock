<?php

use App\Http\Controllers\Api\ToolingApiController;
use Illuminate\Support\Facades\Route;

// REST API read-only → /api/v1/...
Route::prefix('v1')->name('api.')->group(function (): void {
    Route::get('tools', [ToolingApiController::class, 'index'])->name('tools.index');
    Route::get('tools/summary', [ToolingApiController::class, 'summary'])->name('tools.summary');
    Route::get('tools/{tool}', [ToolingApiController::class, 'show'])->whereNumber('tool')->name('tools.show');
});
