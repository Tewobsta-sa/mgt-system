<?php

namespace App\Services;

use App\Models\Assignment;
use App\Models\Student;

/**
 * Shared promotion-eligibility evaluation.
 * Used by StudentPromotionController (candidate list + stats) and
 * StatisticService (dashboard KPIs) so every surface counts the same way.
 */
class PromotionEvaluator
{
    public static function thresholds(): array
    {
        return [
            'min_grade' => (float) config('academic.promotion_min_grade', 50.0),
            'min_attendance' => (float) config('academic.promotion_min_attendance', 60.0),
        ];
    }

    /**
     * Map of section_id => [course_id => true] for Course-type assignments,
     * used to scope grade evaluation to the student's current section only.
     */
    public static function sectionCourseMap($sectionIds): array
    {
        $map = [];
        $ids = collect($sectionIds)->filter()->unique()->values();
        if ($ids->isEmpty()) {
            return $map;
        }

        $assignments = Assignment::whereIn('section_id', $ids)
            ->where('type', 'Course')
            ->with('assignmentCourses')
            ->get();

        foreach ($assignments as $asn) {
            foreach ($asn->assignmentCourses as $ac) {
                $map[$asn->section_id][$ac->course_id] = true;
            }
        }

        return $map;
    }

    /**
     * Evaluate a student's academic & attendance performance.
     * $student must have 'grades.assessment.course' and 'attendances.assignment' loaded.
     * Returns the full metric payload used by the promotion UI.
     */
    public static function evaluate(Student $student, array $sectionCourseMap): array
    {
        $t = self::thresholds();

        $currentSectionCourses = $student->section_id && isset($sectionCourseMap[$student->section_id])
            ? array_keys($sectionCourseMap[$student->section_id])
            : null;

        $courseScores = [];
        foreach ($student->grades as $grade) {
            $assessment = $grade->assessment;
            if (!$assessment || !$assessment->course) continue;

            $courseId = $assessment->course_id;

            // Only evaluate courses assigned to the student's current section if assignments exist
            if ($currentSectionCourses !== null && !empty($currentSectionCourses) && !in_array($courseId, $currentSectionCourses)) {
                continue;
            }

            if (!isset($courseScores[$courseId])) {
                $courseScores[$courseId] = [
                    'course_id' => $courseId,
                    'course_name' => $assessment->course->name,
                    'sum_weighted' => 0,
                    'sum_weights' => 0,
                    'assessments' => [],
                ];
            }

            $rawScore = (float) $grade->score;
            $maxScore = (float) $assessment->max_score;
            $weight = (float) ($assessment->weight ?: 100);
            $contribution = $maxScore > 0 ? ($rawScore / $maxScore) * $weight : 0;

            $courseScores[$courseId]['sum_weighted'] += $contribution;
            $courseScores[$courseId]['sum_weights'] += $weight;
            $courseScores[$courseId]['assessments'][] = [
                'assessment_id' => $assessment->id,
                'title' => $assessment->title,
                'raw_score' => round($rawScore, 1),
                'max_score' => round($maxScore, 1),
                'weight' => round($weight, 1),
                'percentage' => $maxScore > 0 ? round(($rawScore / $maxScore) * 100, 1) : 0,
            ];
        }

        $coursesBreakdown = [];
        $overallPercentages = [];
        foreach ($courseScores as $cId => $cData) {
            $cPercent = $cData['sum_weights'] > 0
                ? round(($cData['sum_weighted'] / $cData['sum_weights']) * 100, 1)
                : 0;
            $overallPercentages[] = $cPercent;
            $coursesBreakdown[] = [
                'course_id' => $cId,
                'course_name' => $cData['course_name'],
                'percentage' => $cPercent,
                'assessments' => $cData['assessments'],
            ];
        }

        $overallGradeAvg = count($overallPercentages) > 0
            ? round(array_sum($overallPercentages) / count($overallPercentages), 1)
            : null;

        // Attendance performance
        $courseSessions = ['total' => 0, 'present' => 0, 'absent' => 0, 'excused' => 0];
        $mezmurSessions = ['total' => 0, 'present' => 0, 'absent' => 0, 'excused' => 0];
        $sessionLogs = [];

        foreach ($student->attendances as $att) {
            $type = $att->assignment?->type ?? 'Course';
            $status = $att->status;

            $sessionLogs[] = [
                'id' => $att->id,
                'type' => $type,
                'status' => $status,
                'marked_at' => $att->marked_at ? date('Y-m-d H:i', strtotime($att->marked_at)) : null,
                'date' => $att->assignment?->scheduled_date ?? ($att->marked_at ? date('Y-m-d', strtotime($att->marked_at)) : '-'),
            ];

            $bucket = $type === 'MezmurTraining' ? 'mezmurSessions' : 'courseSessions';
            ${$bucket}['total']++;
            if ($status === 'Present' || $status === 'Late') ${$bucket}['present']++;
            elseif ($status === 'Excused') ${$bucket}['excused']++;
            else ${$bucket}['absent']++;
        }

        $attendanceAvg = fn ($b) => $b['total'] > 0
            ? round((($b['present'] + ($b['excused'] * 0.5)) / $b['total']) * 100, 1)
            : null;

        $totalSessions = $courseSessions['total'] + $mezmurSessions['total'];
        $overallAttendanceAvg = $totalSessions > 0
            ? round((($courseSessions['present'] + $mezmurSessions['present']
                + ($courseSessions['excused'] + $mezmurSessions['excused']) * 0.5) / $totalSessions) * 100, 1)
            : null;

        $isEligible = $overallGradeAvg !== null
            && $overallGradeAvg >= $t['min_grade']
            && $overallAttendanceAvg !== null
            && $overallAttendanceAvg >= $t['min_attendance'];

        return [
            'overall_grade_avg' => $overallGradeAvg,
            'courses_breakdown' => $coursesBreakdown,
            'has_grades' => count($coursesBreakdown) > 0,
            'is_eligible' => $isEligible,
            'eligibility_reasons' => [
                'has_results' => $overallGradeAvg !== null,
                'grade_passed' => $overallGradeAvg !== null && $overallGradeAvg >= $t['min_grade'],
                'has_attendance' => $overallAttendanceAvg !== null,
                'attendance_passed' => $overallAttendanceAvg !== null && $overallAttendanceAvg >= $t['min_attendance'],
            ],
            'overall_attendance_avg' => $overallAttendanceAvg,
            'course_attendance_avg' => $attendanceAvg($courseSessions),
            'mezmur_attendance_avg' => $attendanceAvg($mezmurSessions),
            'attendance_summary' => [
                'total' => $totalSessions,
                'present' => $courseSessions['present'] + $mezmurSessions['present'],
                'absent' => $courseSessions['absent'] + $mezmurSessions['absent'],
                'excused' => $courseSessions['excused'] + $mezmurSessions['excused'],
                'course_total' => $courseSessions['total'],
                'mezmur_total' => $mezmurSessions['total'],
            ],
            'session_logs' => array_slice(array_reverse($sessionLogs), 0, 10),
        ];
    }

    /**
     * Count of students who actually meet the promotion thresholds.
     * Pool = students whose promotion_status is null/'eligible' and who are
     * not Graduated/Inactive.
     */
    public static function countEligible(): int
    {
        $pool = Student::with(['grades.assessment.course', 'attendances.assignment', 'section'])
            ->where(fn ($q) => $q->where('promotion_status', 'eligible')->orWhereNull('promotion_status'))
            ->whereNotIn('status', ['Graduated', 'Inactive'])
            ->where('is_flagged', false)
            ->get(['id', 'section_id', 'promotion_status', 'status']);

        $map = self::sectionCourseMap($pool->pluck('section_id'));

        return $pool->filter(
            fn ($s) => self::evaluate($s, $map)['is_eligible']
        )->count();
    }
}
