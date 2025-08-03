<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Auth;

Route::middleware(['auth:sanctum'])->get('/user', function (Request $request) {
    return $request->user();
});

Route::post('/login', [App\Http\Controllers\Auth\AuthenticatedSessionController::class, 'store'])
    ->name('login');

Route::middleware('auth:sanctum')->get('/whoami', function () {
    return response()->json(Auth::user());
});

Route::middleware(['auth:sanctum'])->post('/register', [App\Http\Controllers\Auth\RegisteredUserController::class, 'store'])
    ->name('register');