<?php

use App\Http\Controllers\Auth\SessionController;
use Illuminate\Support\Facades\Route;

Route::view('/', 'app')->name('home');
Route::view('/issues/new', 'app');
Route::view('/issues/{issue}', 'app')->whereNumber('issue');

Route::post('/login', [SessionController::class, 'store'])->middleware('throttle:5,1');
Route::post('/logout', [SessionController::class, 'destroy'])->middleware('auth');
