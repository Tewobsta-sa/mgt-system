<?php

use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Route;
use Spatie\Permission\Middleware\RoleMiddleware;

use App\Http\Controllers\StudentController;
use App\Http\Controllers\ProgramTypeController;
use App\Http\Controllers\SectionController;
use App\Http\Controllers\AssignmentController;
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
use App\Http\Controllers\StudentImportController;
use App\Http\Controllers\SystemInitializationController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\ReportController;
use App\Http\Controllers\Auth\RegisteredUserController;
use App\Http\Controllers\TeacherController;
use App\Http\Controllers\MezmurExamController;
use App\Http\Controllers\LogController;
use App\Http\Controllers\Auth\AuthenticatedSessionController;

/*
|--------------------------------------------------------------------------
| System Initialization (Unauthenticated)
|--------------------------------------------------------------------------
*/
Route::get('/system/status', [SystemInitializationController::class, 'checkStatus']);
Route::post('/system/initialize', [SystemInitializationController::class, 'initialize']);
Route::get('/system/roles', [SystemInitializationController::class, 'getAvailableRoles']);

/*
|--------------------------------------------------------------------------
| Public Media (Secure against Path Traversal)
|--------------------------------------------------------------------------
*/
Route::get('/media/{path}', function (string $path) {
    $basePath = realpath(storage_path('app/public'));
    if (!$basePath) {
        abort(404);
    }
    
    // Normalize and resolve path
    $targetPath = realpath($basePath . DIRECTORY_SEPARATOR . str_replace(['/', '\\'], DIRECTORY_SEPARATOR, $path));

    if ($targetPath === false || !str_starts_with($targetPath, $basePath) || !is_file($targetPath)) {
        abort(404);
    }

    return response()->file($targetPath);
})->where('path', '.*');

/*
|--------------------------------------------------------------------------
| Authentication (Rate-Limited)
|--------------------------------------------------------------------------
*/
Route::post('/login', [AuthenticatedSessionController::class, 'store'])->middleware('throttle:6,1')->name('login');
Route::post('/refresh', [AuthenticatedSessionController::class, 'refresh'])->middleware('throttle:30,1')->name('refresh');
Route::post('/forgot-password', [RegisteredUserController::class, 'forgotPassword'])->middleware('throttle:6,1');
Route::get('/forgot-password/question', [RegisteredUserController::class, 'securityQuestion'])->middleware('throttle:10,1');

Route::middleware('auth:sanctum')->get('/whoami', function () {
    return response()->json(Auth::user()->load('roles'));
});

/*
|--------------------------------------------------------------------------
| Authenticated Core API (Protected by 5 Canonical Roles)
|--------------------------------------------------------------------------
*/
Route::middleware(['auth:sanctum', 'require.init'])->group(function () {

    Route::post('/logout', [AuthenticatedSessionController::class, 'destroy']);
    Route::put('/user/update', [RegisteredUserController::class, 'update']);
    Route::get('/dashboard/stats', [DashboardController::class, 'getStats']);
    Route::get('/reports/export/{type}', [ReportController::class, 'export']);

    // ── Super Admin Exclusive ─────────────────────────────────────────
    Route::middleware([RoleMiddleware::class . ':super_admin'])->group(function () {
        Route::put('/admin/users/{id}', [RegisteredUserController::class, 'adminUpdate']);
        Route::delete('/admin/users/{id}', [RegisteredUserController::class, 'destroy']);
        Route::get('/admin/stats', [RegisteredUserController::class, 'adminStats']);
        Route::get('/admin/logs', [LogController::class, 'index']);
        Route::post('/register', [RegisteredUserController::class, 'store']);
    });

    // ── User Management Directory (All staff roles) ───────────────────
    Route::middleware([RoleMiddleware::class . ':super_admin|yesew_habt|mereja_kfl|mezmur_kfl|tmhrt_kfl'])->group(function () {
        Route::get('/users', [RegisteredUserController::class, 'index']);
    });

    // =========================================================================
    // STUDENTS MODULE
    // =========================================================================
    Route::prefix('students')->group(function () {

        // Read (All canonical roles)
        Route::middleware([RoleMiddleware::class . ':super_admin|yesew_habt|mereja_kfl|mezmur_kfl|tmhrt_kfl'])->group(function () {
            Route::get('/all', [StudentController::class, 'indexAll']);
            Route::get('/prekg', [StudentController::class, 'indexPreKG']);
            Route::get('/prekg/{id}', [StudentController::class, 'showPreKG']);
            Route::get('/regular', [StudentController::class, 'indexRegular']);
            Route::get('/regular/{id}', [StudentController::class, 'showRegular']);
            Route::get('/young', [StudentController::class, 'indexYoung']);
            Route::get('/young/{id}', [StudentController::class, 'showYoung']);
            Route::get('/distance', [StudentController::class, 'indexDistance']);
            Route::get('/distance/{id}', [StudentController::class, 'showDistance']);
            Route::get('/{id}', [StudentController::class, 'showStudent']);
        });

        // Write (Yesew Habt & Super Admin)
        Route::middleware([RoleMiddleware::class . ':super_admin|yesew_habt'])->group(function () {
            Route::post('/unified', [StudentController::class, 'storeUnified']);
            Route::post('/unified/{id}', [StudentController::class, 'updateUnified']);
            Route::put('/unified/{id}', [StudentController::class, 'updateUnified']);
            Route::post('/bulk-status', [StudentController::class, 'bulkUpdateStatus']);
            Route::delete('/{id}', [StudentController::class, 'destroyStudent']);

            // Legacy endpoints
            Route::post('/regular', [StudentController::class, 'storeRegular']);
            Route::put('/regular/{id}', [StudentController::class, 'updateRegular']);
            Route::delete('/regular/{id}', [StudentController::class, 'destroyRegular']);
            Route::post('/young', [StudentController::class, 'storeYoung']);
            Route::put('/young/{id}', [StudentController::class, 'updateYoung']);
            Route::delete('/young/{id}', [StudentController::class, 'destroyYoung']);
            Route::post('/distance', [StudentController::class, 'storeDistance']);
            Route::put('/distance/{id}', [StudentController::class, 'updateDistance']);
            Route::delete('/distance/{id}', [StudentController::class, 'destroyDistance']);

            // Bulk import
            Route::get('/import/template', [StudentImportController::class, 'template']);
            Route::get('/import/template/{track}', [StudentImportController::class, 'template']);
            Route::post('/import', [StudentImportController::class, 'import']);
            Route::post('/import/regular', [StudentImportController::class, 'import']);
            Route::post('/import/young', [StudentImportController::class, 'import']);
            Route::post('/import/distance', [StudentImportController::class, 'import']);
        });

        // Flagging & ID-card export (Ye Sew Habt office + Super Admin)
        Route::middleware([RoleMiddleware::class . ':super_admin|yesew_habt|gngnunet_office_admin'])->group(function () {
            Route::post('/{id}/flag', [StudentController::class, 'flagStudent']);
            Route::post('/{id}/unflag', [StudentController::class, 'unflagStudent']);
            Route::get('/id-cards/export', [StudentController::class, 'getStudentsForIdCards']);
        });
    });

    // ── Verification & Promotion Workflows ───────────────────────────
    // Read-only candidate listing (includes Ye Sew Habt office admin & read-only Mereja)
    Route::middleware([RoleMiddleware::class . ':super_admin|yesew_habt|tmhrt_kfl|gngnunet_office_admin|mereja_kfl'])->group(function () {
        Route::get('/promotions/candidates', [StudentPromotionController::class, 'getCandidates']);
    });

    Route::middleware([RoleMiddleware::class . ':super_admin|yesew_habt|tmhrt_kfl|gngnunet_office_admin'])->group(function () {
        Route::post('/promotions/reject', [StudentPromotionController::class, 'reject']);
    });

    // Legacy verify/promote endpoints — kept for admin tooling only; they bypass the
    // nominate → endorse → approve workflow so they are restricted to Super Admin.
    Route::middleware([RoleMiddleware::class . ':super_admin'])->group(function () {
        Route::post('/students/{id}/verify', [StudentPromotionController::class, 'verifyStudent']);
        Route::post('/students/bulk-verify', [StudentPromotionController::class, 'bulkVerify']);
        Route::post('/promote/regular', [StudentPromotionController::class, 'promoteRegular']);
        Route::post('/promote/young', [StudentPromotionController::class, 'promoteYoung']);
        Route::post('/promote/distance', [StudentPromotionController::class, 'promoteDistance']);
    });

    // Level 1: Tmhrt nomination
    Route::middleware([RoleMiddleware::class . ':super_admin|tmhrt_kfl'])->group(function () {
        Route::post('/promotions/nominate', [StudentPromotionController::class, 'nominate']);
    });

    // Level 2: Ye Sew Habt endorsement
    Route::middleware([RoleMiddleware::class . ':super_admin|yesew_habt|gngnunet_office_admin'])->group(function () {
        Route::post('/promotions/endorse', [StudentPromotionController::class, 'endorse']);
    });

    // Level 3: Super Admin final approval
    Route::middleware([RoleMiddleware::class . ':super_admin'])->group(function () {
        Route::post('/promotions/approve', [StudentPromotionController::class, 'approve']);
    });

    // =========================================================================
    // PROGRAM TYPES & SECTIONS
    // =========================================================================
    Route::get('/program-types', [ProgramTypeController::class, 'index']);
    Route::get('/program-types/{program_type}', [ProgramTypeController::class, 'show']);
    Route::get('/program-types/{id}/sections', [ProgramTypeController::class, 'sections']);
    Route::get('/program-types/{id}/courses', [ProgramTypeController::class, 'courses']);
    Route::get('/program-types/{id}/teachers', [ProgramTypeController::class, 'teachers']);
    Route::get('/program-types/{id}/students', [ProgramTypeController::class, 'students']);

    Route::get('/sections', [SectionController::class, 'index']);
    Route::get('/sections/{section}', [SectionController::class, 'show']);
    Route::get('/sections/{id}/courses', [SectionController::class, 'courses']);
    Route::get('/sections/{id}/students', [SectionController::class, 'students']);
    Route::get('/sections/{id}/teachers', [SectionController::class, 'teachers']);

    Route::get('/courses', [CourseController::class, 'index']);
    Route::get('/courses/{course}', [CourseController::class, 'show']);

    Route::middleware([RoleMiddleware::class . ':super_admin|tmhrt_kfl'])->group(function () {
        Route::post('/program-types', [ProgramTypeController::class, 'store']);
        Route::put('/program-types/{program_type}', [ProgramTypeController::class, 'update']);
        Route::delete('/program-types/{program_type}', [ProgramTypeController::class, 'destroy']);

        Route::post('/sections', [SectionController::class, 'store']);
        Route::put('/sections/{section}', [SectionController::class, 'update']);
        Route::delete('/sections/{section}', [SectionController::class, 'destroy']);

        Route::post('/courses', [CourseController::class, 'store']);
        Route::put('/courses/{course}', [CourseController::class, 'update']);
        Route::delete('/courses/{course}', [CourseController::class, 'destroy']);
    });

    // =========================================================================
    // TEACHERS & INSTRUCTORS MANAGEMENT
    // =========================================================================
    Route::middleware([RoleMiddleware::class . ':super_admin|tmhrt_kfl|mereja_kfl'])->group(function () {
        Route::get('/teachers', [RegisteredUserController::class, 'index']);
        Route::get('/teachers/{id}', [RegisteredUserController::class, 'show']);
    });

    Route::middleware([RoleMiddleware::class . ':super_admin|tmhrt_kfl'])->group(function () {
        Route::post('/teachers', [RegisteredUserController::class, 'store']);
        Route::put('/teachers/{id}', [RegisteredUserController::class, 'adminUpdate']);
        Route::delete('/teachers/{id}', [RegisteredUserController::class, 'destroy']);
    });

    // =========================================================================
    // GRADING & ASSESSMENTS
    // =========================================================================
    Route::middleware([RoleMiddleware::class . ':super_admin|tmhrt_kfl|mereja_kfl|teacher'])->group(function () {
        Route::get('/assessments', [AssessmentController::class, 'index']);
        Route::get('/assessments/{assessment}', [AssessmentController::class, 'show']);
        Route::get('/grades', [GradeController::class, 'index']);
        Route::get('/students/{id}/totals', [StudentGradeController::class, 'totals']);
        Route::get('/sections/{id}/rankings', [StudentGradeController::class, 'sectionRankings']);
        Route::get('/sections/{id}/report-cards', [StudentGradeController::class, 'sectionReportCards']);
        Route::get('/courses/{courseId}/grades', [GradeController::class, 'gradesForCourse']);
        Route::get('/courses/{course}/assessments', [CourseController::class, 'assessments']);
        Route::get('/courses/{courseId}/students', [TeacherController::class, 'courseStudents']);
        Route::get('/teacher/my-courses', [TeacherController::class, 'myCourses']);
    });

    Route::middleware([RoleMiddleware::class . ':super_admin|tmhrt_kfl|teacher'])->group(function () {
        Route::post('/grades', [GradeController::class, 'store']);
        Route::post('/grades/bulk', [GradeController::class, 'bulkStore']);
        Route::delete('/grades/{id}', [GradeController::class, 'destroy']);
        Route::get('/courses/{course}/grades/template', [GradeController::class, 'downloadTemplate']);
        Route::post('/courses/{course}/grades/import', [GradeController::class, 'importGrades']);
    });

    Route::middleware([RoleMiddleware::class . ':super_admin|tmhrt_kfl'])->group(function () {
        Route::post('/assessments', [AssessmentController::class, 'store']);
        Route::put('/assessments/{assessment}', [AssessmentController::class, 'update']);
        Route::delete('/assessments/{assessment}', [AssessmentController::class, 'destroy']);
    });

    // =========================================================================
    // MEZMUR & TRAINERS & EXAMS
    // =========================================================================
    Route::middleware([RoleMiddleware::class . ':super_admin|yesew_habt|mezmur_kfl|mereja_kfl'])->group(function () {
        Route::get('/trainers', [TrainerController::class, 'index']);
        Route::get('/trainers/{trainer}', [TrainerController::class, 'show']);
        Route::get('/mezmur-category-types', [MezmurCategoryTypeController::class, 'index']);
        Route::get('/mezmur-categories', [MezmurCategoryController::class, 'index']);
        Route::get('/mezmurs', [MezmurController::class, 'index']);
        Route::get('/mezmurs/{mezmur}', [MezmurController::class, 'show']);
        Route::get('/students/mezmur', [StudentController::class, 'indexMezmur']);

        Route::get('/mezmur-exams', [MezmurExamController::class, 'index']);
        Route::get('/mezmur-exams/{id}', [MezmurExamController::class, 'show']);
        Route::get('/mezmur-exams-candidates', [MezmurExamController::class, 'getAttendees']);
    });

    Route::middleware([RoleMiddleware::class . ':super_admin|mezmur_kfl'])->group(function () {
        Route::post('/trainers', [TrainerController::class, 'store']);
        Route::put('/trainers/{trainer}', [TrainerController::class, 'update']);
        Route::delete('/trainers/{trainer}', [TrainerController::class, 'destroy']);

        Route::post('/mezmur-category-types', [MezmurCategoryTypeController::class, 'store']);
        Route::post('/mezmur-categories', [MezmurCategoryController::class, 'store']);
        Route::post('/mezmurs', [MezmurController::class, 'store']);
        Route::put('/mezmurs/{mezmur}', [MezmurController::class, 'update']);
        Route::delete('/mezmurs/{mezmur}', [MezmurController::class, 'destroy']);

        Route::post('/students/mezmur/assign', [StudentController::class, 'assignMezmur']);
        Route::post('/students/mezmur/unassign', [StudentController::class, 'unassignMezmur']);

        Route::post('/mezmur-exams', [MezmurExamController::class, 'store']);
        Route::post('/mezmur-exams/bulk-results', [MezmurExamController::class, 'bulkSaveResults']);
        Route::post('/mezmur-exams/send-passed', [MezmurExamController::class, 'bulkSendPassedToYesewHabt']);
    });

    // =========================================================================
    // MINISTRIES & ASSIGNMENTS
    // =========================================================================
    Route::middleware([RoleMiddleware::class . ':super_admin|yesew_habt|mezmur_kfl|mereja_kfl'])->group(function () {
        Route::get('/ministries', [MinistryController::class, 'getMinistries']);
        Route::get('/ministries/{id}/members', [MinistryController::class, 'getMinistryMembers']);
        Route::get('/ministry-assignments', [MinistryController::class, 'index']);
        Route::get('/ministry-assignments/{id}', [MinistryController::class, 'show']);
        Route::get('/mezmur/passed-for-ministry', [MezmurExamController::class, 'getPassedStudentsForYesewHabt']);
    });

    Route::middleware([RoleMiddleware::class . ':super_admin|mezmur_kfl'])->group(function () {
        Route::post('/ministries', [MinistryController::class, 'storeMinistry']);
        Route::put('/ministries/{id}', [MinistryController::class, 'updateMinistry']);
        Route::delete('/ministries/{id}', [MinistryController::class, 'deleteMinistry']);

        Route::post('/ministry-assignments', [MinistryController::class, 'store']);
        Route::put('/ministry-assignments/{id}', [MinistryController::class, 'update']);
        Route::delete('/ministry-assignments/{id}', [MinistryController::class, 'destroy']);
        Route::post('/ministry-assignments/{id}/students/add', [MinistryController::class, 'addStudents']);
        Route::post('/ministry-assignments/{id}/students/remove', [MinistryController::class, 'removeStudents']);
        Route::post('/ministry-assignments/{id}/auto-assign', [MinistryController::class, 'rerunAutoAssign']);
    });

    Route::middleware([RoleMiddleware::class . ':super_admin|yesew_habt'])->group(function () {
        Route::post('/ministries/bulk-assign', [MinistryController::class, 'bulkAssignStudents']);
    });

    // =========================================================================
    // SCHEDULES & ASSIGNMENTS & ATTENDANCE
    // =========================================================================
    Route::middleware([RoleMiddleware::class . ':super_admin|yesew_habt|mereja_kfl|mezmur_kfl|tmhrt_kfl|teacher'])->group(function () {
        Route::get('/assignments', [AssignmentController::class, 'index']);
        Route::get('/assignments/{assignment}', [AssignmentController::class, 'show']);
        Route::get('/schedule', [AssignmentController::class, 'getSchedule']);
        Route::get('/attendance', [AttendanceController::class, 'getAttendance']);
        Route::get('/attendance/session-students', [AttendanceController::class, 'getMobileSessionStudents']);
    });

    Route::middleware([RoleMiddleware::class . ':super_admin|mezmur_kfl|tmhrt_kfl'])->group(function () {
        Route::post('/assignments/end-semester', [AssignmentController::class, 'endSemester']);
        Route::post('/assignments', [AssignmentController::class, 'store']);
        Route::put('/assignments/{assignment}', [AssignmentController::class, 'update']);
        Route::patch('/assignments/{assignment}', [AssignmentController::class, 'update']);
        Route::delete('/assignments/{assignment}', [AssignmentController::class, 'destroy']);
    });

    // Attendance Taking: Yesew Habt, Tmhrt, Mezmur, Teacher, Super Admin
    Route::middleware([RoleMiddleware::class . ':super_admin|yesew_habt|mezmur_kfl|tmhrt_kfl|teacher'])->group(function () {
        Route::post('/attendance/mark', [AttendanceController::class, 'markAttendance']);
        Route::post('/attendance/bulk', [AttendanceController::class, 'bulkMark']);
        Route::post('/attendance/scan-mark', [AttendanceController::class, 'scanAndMark']);
    });

});
