<?php

use App\Http\Controllers\Api\IssueController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::get('/me', function (Request $request): array {
    return $request->user()->only(['id', 'name', 'email']);
})->middleware('auth:web');


Route::prefix('/issues')->middleware('auth:web')->group(function() {
    Route::post('/', [IssueController::class, 'store']);
});