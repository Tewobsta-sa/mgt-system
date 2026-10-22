<?php 
return [
    'super_admin' => ['*'],
    'yesew_habt' => [
        'student_registration',
        'attendance_taking',
        'ministry_assignment',
    ],
    'mereja_kfl' => [
        'view_only',
    ],
    'mezmur_kfl' => [
        'view_students',
        'mezmur_schedules',
        'mezmur_exams',
        'send_passed_to_yesew_habt',
    ],
    'tmhrt_kfl' => [
        'tmhrt_schedules',
        'courses_manage',
        'grades_manage',
        'teachers_manage',
    ],
];
