<?php

use Illuminate\Support\Facades\Auth;
use App\Http\Controllers\StudentController;
use App\Http\Controllers\ProgramTypeController;
use App\Http\Controllers\SectionController;
use Illuminate\Support\Facades\Route;
use Spatie\Permission\Middleware\RoleMiddleware;

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

    

    Route::prefix('students')->middleware(['auth:sanctum'])->group(function () {

        Route::middleware([RoleMiddleware::class . ':gngnunet_office_admin|gngnunet_office_coordinator'])->group(function () {
            Route::get('regular', [StudentController::class, 'indexRegular']);
            Route::post('regular', [StudentController::class, 'storeRegular']);
            Route::get('regular/{id}', [StudentController::class, 'showRegular']);
            Route::put('regular/{id}', [StudentController::class, 'updateRegular']);
            Route::delete('regular/{id}', [StudentController::class, 'destroyRegular']);
        });

        Route::middleware([RoleMiddleware::class . ':super_admin'])->group(function () {
            Route::get('young', [StudentController::class, 'indexYoung']);
            Route::post('young', [StudentController::class, 'storeYoung']);
            Route::get('young/{id}', [StudentController::class, 'showYoung']);
            Route::put('young/{id}', [StudentController::class, 'updateYoung']);
            Route::delete('young/{id}', [StudentController::class, 'destroyYoung']);
        });

        Route::middleware([RoleMiddleware::class . ':distance_coordinator|distance_admin'])->group(function () {
            Route::get('distance', [StudentController::class, 'indexDistance']);
            Route::post('distance', [StudentController::class, 'storeDistance']);
            Route::get('distance/{id}', [StudentController::class, 'showDistance']);
            Route::put('distance/{id}', [StudentController::class, 'updateDistance']);
            Route::delete('distance/{id}', [StudentController::class, 'destroyDistance']);
        });

    });


    //section and program type
    Route::apiResource('program-types', ProgramTypeController::class);
    Route::apiResource('sections', SectionController::class);


});