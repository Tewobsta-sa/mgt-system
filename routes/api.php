<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Auth;

Route::post('/login', [App\Http\Controllers\Auth\AuthenticatedSessionController::class, 'store'])->name('login');
Route::post('/forgot-password', [App\Http\Controllers\Auth\RegisteredUserController::class, 'forgotPassword']);

Route::middleware('auth:sanctum')->get('/whoami', function () {
    return response()->json(Auth::user());
});

Route::middleware(['auth:sanctum'])->group(function () {
    Route::post('/register', [App\Http\Controllers\Auth\RegisteredUserController::class, 'store']); // Authenticated registration
    Route::put('/user/update', [App\Http\Controllers\Auth\RegisteredUserController::class, 'update']); // Self-update
    Route::put('/admin/users/{id}', [App\Http\Controllers\Auth\RegisteredUserController::class, 'adminUpdate']); // Super admin update
    Route::delete('/admin/users/{id}', [App\Http\Controllers\Auth\RegisteredUserController::class, 'destroy']); // Super admin delete
    Route::get('/users', [App\Http\Controllers\Auth\RegisteredUserController::class, 'index']); // Authenticated users can view
    Route::post('/logout', [App\Http\Controllers\Auth\AuthenticatedSessionController::class, 'destroy']);// Logout route
});