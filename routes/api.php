<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::get('/me', function (Request $request): array {
    return $request->user()->only(['id', 'name', 'email']);
})->middleware('auth:web');
