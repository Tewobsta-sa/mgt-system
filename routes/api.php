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
use App\Http\Controllers\StudentPromotionController;
use App\Http\Controllers\SystemInitializationController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\ReportController;
use App\Http\Controllers\Auth\RegisteredUserController;

// System initialization routes (no authentication required)
Route::get('/system/status', [SystemInitializationController::class, 'checkStatus']);
Route::post('/system/initialize', [SystemInitializationController::class, 'initialize']);
Route::get('/system/roles', [SystemInitializationController::class, 'getAvailableRoles']);

Route::post('/login', [App\Http\Controllers\Auth\AuthenticatedSessionController::class, 'store'])->name('login');
Route::post('/refresh', [App\Http\Controllers\Auth\AuthenticatedSessionController::class, 'refresh'])->name('refresh');
Route::post('/forgot-password', [App\Http\Controllers\Auth\RegisteredUserController::class, 'forgotPassword']);

Route::middleware('auth:sanctum')->get('/whoami', function () {
    return response()->json(Auth::user());
});

Route::middleware(['auth:sanctum', 'require.init'])->group(function () {

    Route::post('/logout', [App\Http\Controllers\Auth\AuthenticatedSessionController::class, 'destroy']);// Logout route
    Route::put('/user/update', [App\Http\Controllers\Auth\RegisteredUserController::class, 'update']); // Self-update 
    Route::get('/dashboard/stats', [DashboardController::class, 'getStats']); // Analytics Dashboard
    Route::get('/reports/export/{type}', [ReportController::class, 'export']);

    
    Route::middleware([RoleMiddleware::class . ':super_admin'])->group(function () {
        Route::put('/admin/users/{id}', [App\Http\Controllers\Auth\RegisteredUserController::class, 'adminUpdate']); // Super admin update
        Route::delete('/admin/users/{id}', [App\Http\Controllers\Auth\RegisteredUserController::class, 'destroy']); // Super admin delete
        Route::get('/admin/stats', [App\Http\Controllers\Auth\RegisteredUserController::class, 'adminStats']); // Admin statistics
        Route::get('/admin/logs', [\App\Http\Controllers\LogController::class, 'index']); // System logs
    });

    Route::middleware([RoleMiddleware::class . ':super_admin|mezmur_office_admin|tmhrt_office_admin|distance_admin|gngnunet_office_admin|young_gngnunet_admin'])->group(function () {
        Route::get('/users', [App\Http\Controllers\Auth\RegisteredUserController::class, 'index']); // List all users
        Route::post('/register', [App\Http\Controllers\Auth\RegisteredUserController::class, 'store']); // Authenticated registration
    });

    Route::prefix('students')->group(function () {

        Route::middleware([RoleMiddleware::class . ':super_admin|gngnunet_office_admin|young_gngnunet_admin|mezmur_office_admin|tmhrt_office_admin'])->group(function () {
            Route::get('young/{id}', [StudentController::class, 'showYoung']);
            Route::get('young', [StudentController::class, 'indexYoung']);
        });
        Route::middleware([RoleMiddleware::class . ':super_admin|gngnunet_office_admin|mezmur_office_admin|tmhrt_office_admin'])->group(function () {
            Route::get('regular/{id}', [StudentController::class, 'showRegular']);
            Route::get('regular', [StudentController::class, 'indexRegular']);
        });

        Route::middleware([RoleMiddleware::class . ':gngnunet_office_admin'])->group(function () {
            Route::post('regular', [StudentController::class, 'storeRegular']);
            Route::put('regular/{id}', [StudentController::class, 'updateRegular']);
            Route::delete('regular/{id}', [StudentController::class, 'destroyRegular']);
        });    
        Route::middleware([RoleMiddleware::class . ':gngnunet_office_admin|young_gngnunet_admin'])->group(function () {
            Route::post('young', [StudentController::class, 'storeYoung']);
            Route::put('young/{id}', [StudentController::class, 'updateYoung']);
            Route::delete('young/{id}', [StudentController::class, 'destroyYoung']);
        });
        Route::middleware([RoleMiddleware::class . ':super_admin|distance_admin'])->group(function () {
            Route::get('distance', [StudentController::class, 'indexDistance']);
            Route::get('distance/{id}', [StudentController::class, 'showDistance']);
        });
        Route::middleware([RoleMiddleware::class . ':distance_admin'])->group(function () {
            Route::post('distance', [StudentController::class, 'storeDistance']);
            Route::put('distance/{id}', [StudentController::class, 'updateDistance']);
            Route::delete('distance/{id}', [StudentController::class, 'destroyDistance']);
        });

    });

    Route::middleware([RoleMiddleware::class . ':tmhrt_office_admin|gngnunet_office_admin|super_admin|'])->group(function () {
        Route::post('students/{id}/verify', [StudentPromotionController::class, 'verifyStudent']);
        Route::post('students/bulk-verify', [StudentPromotionController::class, 'bulkVerify']);
        Route::post('promote/regular', [StudentPromotionController::class, 'promoteRegular']);
        Route::post('promote/young', [StudentPromotionController::class, 'promoteYoung']);
    });

    Route::middleware([RoleMiddleware::class . ":super_admin|distance_admin|tmhrt_office_admin"])->group(function () {
        Route::post('promote/distance', [StudentPromotionController::class, 'promoteDistance']);
    });

    
    Route::get('/program-types/{id}/sections', [ProgramTypeController::class, 'sections']);
    Route::get('/program-types/{id}/courses', [ProgramTypeController::class, 'courses']);
    Route::get('/program-types/{id}/teachers', [ProgramTypeController::class, 'teachers']);
    Route::get('/program-types/{id}/students', [ProgramTypeController::class, 'students']);
    
    // Read-only access to these resources for all authenticated admins/teachers
    Route::get('program-types', [ProgramTypeController::class, 'index']);
    Route::get('program-types/{program_type}', [ProgramTypeController::class, 'show']);
    Route::get('sections', [SectionController::class, 'index']);
    Route::get('sections/{section}', [SectionController::class, 'show']);
    Route::get('courses', [CourseController::class, 'index']);
    Route::get('courses/{course}', [CourseController::class, 'show']);

    Route::get('sections/{id}/courses', [SectionController::class, 'courses']);
    Route::get('sections/{id}/students', [SectionController::class, 'students']);
    Route::get('sections/{id}/teachers', [SectionController::class, 'teachers']);
    Route::get('schedule', [AssignmentController::class, 'schedule']);
    Route::get('attendance', [AttendanceController::class, 'getAttendance']);

    Route::middleware([RoleMiddleware::class . ":super_admin|distance_admin|tmhrt_office_admin"])->group(function () {
        Route::post('program-types', [ProgramTypeController::class, 'store']);
        Route::put('program-types/{program_type}', [ProgramTypeController::class, 'update']);
        Route::delete('program-types/{program_type}', [ProgramTypeController::class, 'destroy']);

        Route::post('sections', [SectionController::class, 'store']);
        Route::put('sections/{section}', [SectionController::class, 'update']);
        Route::delete('sections/{section}', [SectionController::class, 'destroy']);
        Route::post('sections/{id}/assign-course', [SectionController::class, 'assignCourse']);

        Route::post('courses', [CourseController::class, 'store']);
        Route::put('courses/{course}', [CourseController::class, 'update']);
        Route::delete('courses/{course}', [CourseController::class, 'destroy']);
    });

    // Teachers Read Access (Super Admin + Tmhrt Admin)
    Route::middleware([RoleMiddleware::class . ':super_admin|tmhrt_office_admin'])->group(function () {
        Route::get('teachers', [RegisteredUserController::class, 'index']);
        Route::get('teachers/{id}', [RegisteredUserController::class, 'show']);
    });

    // Teachers Write Access (ONLY Tmhrt Admin)
    Route::middleware([RoleMiddleware::class . ':tmhrt_office_admin'])->group(function () {
        Route::post('teachers', [RegisteredUserController::class, 'store']);
        Route::put('teachers/{id}', [RegisteredUserController::class, 'adminUpdate']);
        Route::delete('teachers/{id}', [RegisteredUserController::class, 'destroy']);
    });

    Route::middleware([RoleMiddleware::class . ':super_admin|distance_admin|tmhrt_office_admin|teacher'])->group(function () {
        Route::apiResource('assessments', AssessmentController::class);
        Route::get('grades', [GradeController::class, 'index']);
        Route::post('grades', [GradeController::class, 'store']);
        Route::delete('grades/{id}', [GradeController::class, 'destroy']);

        Route::get('students/{id}/totals', [StudentGradeController::class, 'totals']);
        Route::get('sections/{id}/rankings', [StudentGradeController::class, 'sectionRankings']);
        Route::get('courses/{courseId}/grades', [GradeController::class, 'gradesForCourse']);
    });


    Route::middleware([RoleMiddleware::class . ':super_admin|mezmur_office_admin'])->group(function () {
        Route::apiResource('trainers', TrainerController::class);
        Route::apiResource('mezmur-category-types', MezmurCategoryTypeController::class);
        Route::apiResource('mezmur-categories', MezmurCategoryController::class);
        Route::apiResource('mezmurs', MezmurController::class);
        Route::post('/students/mezmur/assign', [StudentController::class, 'assignMezmur']);
        Route::post('/students/mezmur/unassign', [StudentController::class, 'unassignMezmur']);
        Route::get('/students/mezmur', [StudentController::class, 'indexMezmur']);

        Route::get('/ministry-assignments', [MinistryController::class, 'index']);
        Route::get('/ministry-assignments/{id}', [MinistryController::class, 'show']);
        Route::post('/ministry-assignments', [MinistryController::class, 'store']);
        Route::put('/ministry-assignments/{id}', [MinistryController::class, 'update']);
        Route::delete('/ministry-assignments/{id}', [MinistryController::class, 'destroy']);

        // Manual add/remove students
        Route::post('/ministry-assignments/{id}/students/add', [MinistryController::class, 'addStudents']);
        Route::post('/ministry-assignments/{id}/students/remove', [MinistryController::class, 'removeStudents']);

        // Re-run auto assignment
        Route::post('/ministry-assignments/{id}/auto-assign', [MinistryController::class, 'rerunAutoAssign']);
    });

    Route::middleware([RoleMiddleware::class . ':super_admin|mezmur_office_admin|tmhrt_office_admin|teacher'])->group(function () {
        Route::apiResource('assignments', AssignmentController::class);
        Route::post('attendance/mark', [AttendanceController::class, 'markAttendance']);
    });
});
