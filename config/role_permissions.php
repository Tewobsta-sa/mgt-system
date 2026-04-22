<?php 
return [
    'super_admin' => ['*'],
    'mezmur_office_admin' => [
        'mezmur_trainer',
        'wereb_trainer',
        'mezmur_office_coordinator'
    ],
    'tmhrt_office_admin' => [
        'regular_teacher',
        'tmhrt_office_coordinator'
    ],
    'distance_admin' => [
        'distance_teacher',
        'distance_coordinator'
    ],
    'gngnunet_office_admin' => [
        'gngnunet_office_coordinator',
        'student'
    ],
    // Generic instructor role used across program types. Controllers check
    // hasRole('teacher') directly (see AssignmentController, GradeController,
    // RegisteredUserController), so this role must exist after a fresh install.
    'teacher' => [],
];
