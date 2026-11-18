<?php

use App\Http\Controllers\Api\IssueCommentController;
use App\Http\Controllers\Api\IssueController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::get('/me', function (Request $request): array {
    return $request->user()->only(['id', 'name', 'email']);
})->middleware('auth:web');


Route::prefix('/issues')->middleware('auth:web')->group(function() {
    // Issues routes
    Route::post('/', [IssueController::class, 'store'])->name('issues.store');
    Route::patch('/{issue}', [IssueController::class, 'update'])->name('issues.update');
    Route::get('/{issue}', [IssueController::class, 'show'])->name('issues.show');
    Route::get('/', [IssueController::class, 'index'])->name('issues.index');

    // Issue comments routes
    Route::post('/{issue}/comments', [IssueCommentController::class, 'store'])->name('issues.comments.store');
});