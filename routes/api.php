<?php

use Illuminate\Support\Facades\Auth;
use App\Http\Controllers\StudentController;
use App\Http\Controllers\ProgramTypeController;
use App\Http\Controllers\SectionController;
use Illuminate\Support\Facades\Route;
use Spatie\Permission\Middleware\RoleMiddleware;
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

// System initialization routes (no authentication required)
Route::get('/system/status', [SystemInitializationController::class, 'checkStatus']);
Route::post('/system/initialize', [SystemInitializationController::class, 'initialize']);
Route::get('/system/roles', [SystemInitializationController::class, 'getAvailableRoles']);

// Public media (CORS-friendly) — used by ID card / report card PDF capture
Route::get('/media/{path}', function (string $path) {
    $path = ltrim(str_replace('..', '', $path), '/');
    if ($path === '' || !\Illuminate\Support\Facades\Storage::disk('public')->exists($path)) {
        abort(404);
    }

    return \Illuminate\Support\Facades\Storage::disk('public')->response($path);
})->where('path', '.*');

Route::post('/login', [App\Http\Controllers\Auth\AuthenticatedSessionController::class, 'store'])->name('login');
Route::post('/refresh', [App\Http\Controllers\Auth\AuthenticatedSessionController::class, 'refresh'])->name('refresh');
Route::post('/forgot-password', [App\Http\Controllers\Auth\RegisteredUserController::class, 'forgotPassword']);
Route::get('/forgot-password/question', [App\Http\Controllers\Auth\RegisteredUserController::class, 'securityQuestion']);

Route::middleware('auth:sanctum')->get('/whoami', function () {
    return response()->json(Auth::user()->load('roles'));
});

Route::middleware(['auth:sanctum', 'require.init'])->group(function () {

    Route::post('/logout', [App\Http\Controllers\Auth\AuthenticatedSessionController::class, 'destroy']);
    Route::put('/user/update', [App\Http\Controllers\Auth\RegisteredUserController::class, 'update']);
    Route::get('/dashboard/stats', [DashboardController::class, 'getStats']);
    Route::get('/reports/export/{type}', [ReportController::class, 'export']);

    // Super Admin Exclusive
    Route::middleware([RoleMiddleware::class . ':super_admin'])->group(function () {
        Route::put('/admin/users/{id}', [App\Http\Controllers\Auth\RegisteredUserController::class, 'adminUpdate']);
        Route::delete('/admin/users/{id}', [App\Http\Controllers\Auth\RegisteredUserController::class, 'destroy']);
        Route::get('/admin/stats', [App\Http\Controllers\Auth\RegisteredUserController::class, 'adminStats']);
        Route::get('/admin/logs', [\App\Http\Controllers\LogController::class, 'index']);
    });

    // Users listing & registration
    Route::middleware([RoleMiddleware::class . ':super_admin|yesew_habt|mereja_kfl|mezmur_kfl|tmhrt_kfl|gngnunet_office_admin|mezmur_office_admin|tmhrt_office_admin'])->group(function () {
        Route::get('/users', [App\Http\Controllers\Auth\RegisteredUserController::class, 'index']);
    });

    Route::middleware([RoleMiddleware::class . ':super_admin|tmhrt_office_admin'])->group(function () {
        Route::post('/register', [App\Http\Controllers\Auth\RegisteredUserController::class, 'store']);
    });

    // ==========================================
    // STUDENTS ROUTES
    // ==========================================
    Route::prefix('students')->group(function () {

        // READ (All roles have read/view access)
        Route::middleware([RoleMiddleware::class . ':super_admin|yesew_habt|mereja_kfl|mezmur_kfl|tmhrt_kfl|gngnunet_office_admin|mezmur_office_admin|tmhrt_office_admin'])->group(function () {
            Route::get('all', [StudentController::class, 'indexAll']);
            Route::get('prekg', [StudentController::class, 'indexPreKG']);
            Route::get('prekg/{id}', [StudentController::class, 'showPreKG']);
            Route::get('regular', [StudentController::class, 'indexRegular']);
            Route::get('regular/{id}', [StudentController::class, 'showRegular']);
            Route::get('young', [StudentController::class, 'indexYoung']);
            Route::get('young/{id}', [StudentController::class, 'showYoung']);
            Route::get('distance', [StudentController::class, 'indexDistance']);
            Route::get('distance/{id}', [StudentController::class, 'showDistance']);
            Route::get('{id}', [StudentController::class, 'showStudent']);
            Route::get('id-cards/export', [StudentController::class, 'getStudentsForIdCards']);
        });

        // WRITE (Yesew Habt & Super Admin)
        Route::middleware([RoleMiddleware::class . ':super_admin|yesew_habt|gngnunet_office_admin'])->group(function () {
            Route::post('unified', [StudentController::class, 'storeUnified']);
            Route::post('unified/{id}', [StudentController::class, 'updateUnified']); // support multipart update
            Route::put('unified/{id}', [StudentController::class, 'updateUnified']);
            Route::post('bulk-status', [StudentController::class, 'bulkUpdateStatus']);
            Route::delete('{id}', [StudentController::class, 'destroyStudent']);
            Route::post('{id}/flag', [StudentController::class, 'flagStudent']);
            Route::post('{id}/unflag', [StudentController::class, 'unflagStudent']);

            // Legacy endpoints
            Route::post('regular', [StudentController::class, 'storeRegular']);
            Route::put('regular/{id}', [StudentController::class, 'updateRegular']);
            Route::delete('regular/{id}', [StudentController::class, 'destroyRegular']);
            Route::post('young', [StudentController::class, 'storeYoung']);
            Route::put('young/{id}', [StudentController::class, 'updateYoung']);
            Route::delete('young/{id}', [StudentController::class, 'destroyYoung']);
            Route::post('distance', [StudentController::class, 'storeDistance']);
            Route::put('distance/{id}', [StudentController::class, 'updateDistance']);
            Route::delete('distance/{id}', [StudentController::class, 'destroyDistance']);

            // Bulk import
            Route::get('import/template', [StudentImportController::class, 'template']);
            Route::get('import/template/{track}', [StudentImportController::class, 'template']);
            Route::post('import', [StudentImportController::class, 'import']);
            Route::post('import/regular', [StudentImportController::class, 'import']);
            Route::post('import/young', [StudentImportController::class, 'import']);
            Route::post('import/distance', [StudentImportController::class, 'import']);
        });
    });

    // Verification & Promotion
    Route::middleware([RoleMiddleware::class . ':super_admin|yesew_habt|tmhrt_kfl|tmhrt_office_admin|gngnunet_office_admin'])->group(function () {
        Route::get('promotions/candidates', [StudentPromotionController::class, 'getCandidates']);
        Route::post('students/{id}/verify', [StudentPromotionController::class, 'verifyStudent']);
        Route::post('students/bulk-verify', [StudentPromotionController::class, 'bulkVerify']);
        Route::post('promote/regular', [StudentPromotionController::class, 'promoteRegular']);
        Route::post('promote/young', [StudentPromotionController::class, 'promoteYoung']);
        Route::post('promote/distance', [StudentPromotionController::class, 'promoteDistance']);
    });

    // Promotion Workflow - Level 1: Tmhrt Admin nomination (Requires Attendance >= 70%)
    Route::middleware([RoleMiddleware::class . ':super_admin|tmhrt_kfl|tmhrt_office_admin'])->group(function () {
        Route::post('promotions/nominate', [StudentPromotionController::class, 'nominate']);
    });

    // Promotion Workflow - Level 2: Ye Sew Habt endorsement
    Route::middleware([RoleMiddleware::class . ':super_admin|yesew_habt|gngnunet_office_admin'])->group(function () {
        Route::post('promotions/endorse', [StudentPromotionController::class, 'endorse']);
    });

    // Promotion Workflow - Level 3: Super Admin final approval & advancement execution
    Route::middleware([RoleMiddleware::class . ':super_admin'])->group(function () {
        Route::post('promotions/approve', [StudentPromotionController::class, 'approve']);
    });

    // Reject or reset nomination back to eligible
    Route::middleware([RoleMiddleware::class . ':super_admin|yesew_habt|gngnunet_office_admin|tmhrt_kfl|tmhrt_office_admin'])->group(function () {
        Route::post('promotions/reject', [StudentPromotionController::class, 'reject']);
    });

    // ==========================================
    // PROGRAM TYPES & SECTIONS
    // ==========================================
    Route::get('/program-types/{id}/sections', [ProgramTypeController::class, 'sections']);
    Route::get('/program-types/{id}/courses', [ProgramTypeController::class, 'courses']);
    Route::get('/program-types/{id}/teachers', [ProgramTypeController::class, 'teachers']);
    Route::get('/program-types/{id}/students', [ProgramTypeController::class, 'students']);
    Route::get('program-types', [ProgramTypeController::class, 'index']);
    Route::get('program-types/{program_type}', [ProgramTypeController::class, 'show']);
    Route::get('sections', [SectionController::class, 'index']);
    Route::get('sections/{section}', [SectionController::class, 'show']);
    Route::get('courses', [CourseController::class, 'index']);
    Route::get('courses/{course}', [CourseController::class, 'show']);
    Route::get('sections/{id}/courses', [SectionController::class, 'courses']);
    Route::get('sections/{id}/students', [SectionController::class, 'students']);
    Route::get('sections/{id}/teachers', [SectionController::class, 'teachers']);

    Route::middleware([RoleMiddleware::class . ':super_admin|tmhrt_kfl|tmhrt_office_admin'])->group(function () {
        Route::post('program-types', [ProgramTypeController::class, 'store']);
        Route::put('program-types/{program_type}', [ProgramTypeController::class, 'update']);
        Route::delete('program-types/{program_type}', [ProgramTypeController::class, 'destroy']);

        Route::post('sections', [SectionController::class, 'store']);
        Route::put('sections/{section}', [SectionController::class, 'update']);
        Route::delete('sections/{section}', [SectionController::class, 'destroy']);
        Route::post('sections/{id}/assign-course', [SectionController::class, 'assignCourse']);

    });

    Route::middleware([RoleMiddleware::class . ':super_admin|tmhrt_kfl'])->group(function () {
        Route::post('courses', [CourseController::class, 'store']);
        Route::put('courses/{course}', [CourseController::class, 'update']);
        Route::delete('courses/{course}', [CourseController::class, 'destroy']);
    });

    // ==========================================
    // TEACHERS MANAGEMENT
    // ==========================================
    Route::middleware([RoleMiddleware::class . ':super_admin|tmhrt_kfl|tmhrt_office_admin|mereja_kfl'])->group(function () {
        Route::get('teachers', [RegisteredUserController::class, 'index']);
        Route::get('teachers/{id}', [RegisteredUserController::class, 'show']);
    });

    Route::middleware([RoleMiddleware::class . ':super_admin|tmhrt_kfl'])->group(function () {
        Route::post('teachers', [RegisteredUserController::class, 'store']);
        Route::put('teachers/{id}', [RegisteredUserController::class, 'adminUpdate']);
        Route::delete('teachers/{id}', [RegisteredUserController::class, 'destroy']);
    });

    // ==========================================
    // GRADING & ASSESSMENTS
    // ==========================================
    Route::middleware([RoleMiddleware::class . ':super_admin|tmhrt_kfl|tmhrt_office_admin|teacher|mereja_kfl'])->group(function () {
        Route::get('assessments', [AssessmentController::class, 'index']);
        Route::get('assessments/{assessment}', [AssessmentController::class, 'show']);
        Route::get('grades', [GradeController::class, 'index']);
        Route::get('students/{id}/totals', [StudentGradeController::class, 'totals']);
        Route::get('sections/{id}/rankings', [StudentGradeController::class, 'sectionRankings']);
        Route::get('sections/{id}/report-cards', [StudentGradeController::class, 'sectionReportCards']);
        Route::get('courses/{courseId}/grades', [GradeController::class, 'gradesForCourse']);
        Route::get('courses/{course}/assessments', [CourseController::class, 'assessments']);
        Route::get('courses/{courseId}/students', [TeacherController::class, 'courseStudents']);
        Route::get('teacher/my-courses', [TeacherController::class, 'myCourses']);
    });

    Route::middleware([RoleMiddleware::class . ':super_admin|tmhrt_kfl'])->group(function () {
        Route::post('assessments', [AssessmentController::class, 'store']);
        Route::put('assessments/{assessment}', [AssessmentController::class, 'update']);
        Route::delete('assessments/{assessment}', [AssessmentController::class, 'destroy']);
    });

    Route::middleware([RoleMiddleware::class . ':super_admin|tmhrt_kfl|teacher'])->group(function () {
        Route::post('grades', [GradeController::class, 'store']);
        Route::post('grades/bulk', [GradeController::class, 'bulkStore']);
        Route::delete('grades/{id}', [GradeController::class, 'destroy']);
    });

    // ==========================================
    // MEZMUR & TRAINERS & EXAMS
    // ==========================================
    Route::middleware([RoleMiddleware::class . ':super_admin|yesew_habt|mezmur_kfl|mezmur_office_admin|mereja_kfl'])->group(function () {
        Route::get('trainers', [TrainerController::class, 'index']);
        Route::get('trainers/{trainer}', [TrainerController::class, 'show']);
        Route::get('mezmur-category-types', [MezmurCategoryTypeController::class, 'index']);
        Route::get('mezmur-categories', [MezmurCategoryController::class, 'index']);
        Route::get('mezmurs', [MezmurController::class, 'index']);
        Route::get('mezmurs/{mezmur}', [MezmurController::class, 'show']);
        Route::get('/students/mezmur', [StudentController::class, 'indexMezmur']);

        // Mezmur Exams Read
        Route::get('/mezmur-exams', [MezmurExamController::class, 'index']);
        Route::get('/mezmur-exams/{id}', [MezmurExamController::class, 'show']);
        Route::get('/mezmur-exams-candidates', [MezmurExamController::class, 'getAttendees']);
    });

    Route::middleware([RoleMiddleware::class . ':super_admin|mezmur_kfl|mezmur_office_admin'])->group(function () {
        Route::post('trainers', [TrainerController::class, 'store']);
        Route::put('trainers/{trainer}', [TrainerController::class, 'update']);
        Route::delete('trainers/{trainer}', [TrainerController::class, 'destroy']);

        Route::post('mezmur-category-types', [MezmurCategoryTypeController::class, 'store']);
        Route::post('mezmur-categories', [MezmurCategoryController::class, 'store']);
        Route::post('mezmurs', [MezmurController::class, 'store']);
        Route::put('mezmurs/{mezmur}', [MezmurController::class, 'update']);
        Route::delete('mezmurs/{mezmur}', [MezmurController::class, 'destroy']);
        Route::post('/students/mezmur/assign', [StudentController::class, 'assignMezmur']);
        Route::post('/students/mezmur/unassign', [StudentController::class, 'unassignMezmur']);

        // Mezmur Exams Write & Evaluation
        Route::post('/mezmur-exams', [MezmurExamController::class, 'store']);
        Route::post('/mezmur-exams/bulk-results', [MezmurExamController::class, 'bulkSaveResults']);
        Route::post('/mezmur-exams/send-passed', [MezmurExamController::class, 'bulkSendPassedToYesewHabt']);
    });

    // ==========================================
    // MINISTRIES & ASSIGNMENTS (Yesew Habt & Super Admin & Mezmur)
    // ==========================================
    Route::middleware([RoleMiddleware::class . ':super_admin|yesew_habt|gngnunet_office_admin|mezmur_kfl|mezmur_office_admin|mereja_kfl'])->group(function () {
        Route::get('/ministries', [MinistryController::class, 'getMinistries']);
        Route::get('/ministries/{id}/members', [MinistryController::class, 'getMinistryMembers']);
        Route::get('/ministry-assignments', [MinistryController::class, 'index']);
        Route::get('/ministry-assignments/{id}', [MinistryController::class, 'show']);
        Route::get('/mezmur/passed-for-ministry', [MezmurExamController::class, 'getPassedStudentsForYesewHabt']);
    });

    Route::middleware([RoleMiddleware::class . ':super_admin|mezmur_kfl|mezmur_office_admin'])->group(function () {
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

    // ==========================================
    // SCHEDULES & ASSIGNMENTS & ATTENDANCE
    // ==========================================
    Route::middleware([RoleMiddleware::class . ':super_admin|yesew_habt|mereja_kfl|mezmur_kfl|tmhrt_kfl|mezmur_office_admin|tmhrt_office_admin|teacher'])->group(function () {
        Route::get('assignments', [AssignmentController::class, 'index']);
        Route::get('assignments/{assignment}', [AssignmentController::class, 'show']);
        Route::get('schedule', [AssignmentController::class, 'schedule']);
        Route::get('attendance', [AttendanceController::class, 'getAttendance']);
        Route::get('attendance/session-students', [AttendanceController::class, 'getMobileSessionStudents']);
    });

    Route::middleware([RoleMiddleware::class . ':super_admin|mezmur_kfl|tmhrt_kfl|mezmur_office_admin|tmhrt_office_admin'])->group(function () {
        Route::post('assignments', [AssignmentController::class, 'store']);
        Route::put('assignments/{assignment}', [AssignmentController::class, 'update']);
        Route::patch('assignments/{assignment}', [AssignmentController::class, 'update']);
        Route::delete('assignments/{assignment}', [AssignmentController::class, 'destroy']);
    });

    // Attendance Taking: Yesew Habt, Tmhrt, Mezmur, Super Admin, Teacher
    Route::middleware([RoleMiddleware::class . ':super_admin|yesew_habt|gngnunet_office_admin|mezmur_kfl|tmhrt_kfl|mezmur_office_admin|tmhrt_office_admin|teacher'])->group(function () {
        Route::post('attendance/mark', [AttendanceController::class, 'markAttendance']);
        Route::post('attendance/scan-mark', [AttendanceController::class, 'scanAndMark']);
    });

});
