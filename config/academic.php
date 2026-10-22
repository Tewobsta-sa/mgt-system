<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Student Promotion Thresholds
    |--------------------------------------------------------------------------
    | Minimum grade percentage and attendance percentage required for a
    | student to be considered eligible for promotion.
    */
    'promotion_min_grade' => (float) env('PROMOTION_MIN_GRADE', 50.0),
    'promotion_min_attendance' => (float) env('PROMOTION_MIN_ATTENDANCE', 60.0),
];
