<?php

use Illuminate\Support\Facades\Auth;
use App\Http\Controllers\StudentController;
use App\Http\Controllers\ProgramTypeController;
use App\Http\Controllers\SectionController;
use Illuminate\Support\Facades\Route;
use Spatie\Permission\Middleware\RoleMiddleware;
use App\Http\Controllers\AssignmentController ;
use App\Http\Controllers\AttendanceController;
use App\Http\Controllers\MezmurController;
use App\Http\Controllers\MinistryController;
use App\Http\Controllers\TrainerController;
use App\Http\Controllers\MezmurCategoryTypeController;
use App\Http\Controllers\MezmurCategoryController;
use App\Http\Controllers\CourseController;
use App\Http\Controllers\AssessmentController;
use App\Http\Controllers\GradeController;
use App\Http\Controllers\StudentGradeController;

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
    Route::get('/program-types/{id}/sections', [ProgramTypeController::class, 'sections']);
    Route::get('/program-types/{id}/courses', [ProgramTypeController::class, 'courses']);
    Route::get('/program-types/{id}/teachers', [ProgramTypeController::class, 'teachers']);
    Route::get('/program-types/{id}/students', [ProgramTypeController::class, 'students']);


    Route::apiResource('sections', SectionController::class);
    Route::get('sections/{id}/courses', [SectionController::class, 'courses']);
    Route::get('sections/{id}/students', [SectionController::class, 'students']);
    Route::get('sections/{id}/teachers', [SectionController::class, 'teachers']);
    Route::post('sections/{id}/assign-course', [SectionController::class, 'assignCourse']);

    Route::middleware([RoleMiddleware::class . ':mezmur_office_admin|mezmur_office_coordinator'])->group(function () {
        Route::apiResource('trainers', TrainerController::class);
        Route::apiResource('mezmur-category-types', MezmurCategoryTypeController::class);
        Route::apiResource('mezmur-categories', MezmurCategoryController::class);
        Route::apiResource('mezmurs', MezmurController::class);

    });

    Route::apiResource('assignments', AssignmentController::class);
    Route::get('schedule', [AssignmentController::class, 'schedule']);
    Route::apiResource('courses', CourseController::class);
    Route::post('attendance/mark', [AttendanceController::class, 'markAttendance']);
    Route::get('attendance', [AttendanceController::class, 'getAttendance']);

    Route::apiResource('assessments', AssessmentController::class);
    Route::get('grades', [GradeController::class, 'index']);
    Route::post('grades', [GradeController::class, 'store']);
    Route::delete('grades/{id}', [GradeController::class, 'destroy']);

    Route::get('students/{id}/totals', [StudentGradeController::class, 'totals']);
    Route::get('courses/{courseId}/grades', [GradeController::class, 'gradesForCourse']);


});

Route::get('/ministries', [MinistryController::class,'index']);
Route::post('/ministries', [MinistryController::class,'store']);
Route::post('/ministries/assignments', [MinistryController::class,'addAssignment']);
Route::post('/ministries/assignments/{id}/auto-select', [MinistryController::class,'autoSelectStudents']);
Route::post('/ministries/assignments/{id}/students', [MinistryController::class,'addStudentManually']);
Route::delete('/ministries/assignments/{id}/students/{studentId}', [MinistryController::class,'removeStudent']);
