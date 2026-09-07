<?php

namespace App\Http\Controllers;

use App\Models\Student;
use App\Models\Section;
use App\Models\ProgramType;
use App\Models\Grade;
use App\Models\Attendance;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;

class StudentPromotionController extends Controller
{
    /**
     * ✅ List candidates with live, interactive academic & attendance performance
     */
    public function getCandidates(Request $request)
    {
        $user = Auth::user();
        if (!$user) {
            return response()->json(['message' => 'Unauthorized'], 401);
        }

        $query = Student::with([
            'section.programType',
            'targetSection',
            'nominator:id,name,username',
            'endorser:id,name,username',
            'approver:id,name,username',
            'grades.assessment.course',
            'attendances.assignment',
        ]);

        // Filter by section if specified
        if ($request->filled('section_id')) {
            $query->where('section_id', $request->section_id);
        }

        // Filter by program type if specified
        if ($request->filled('program_type_id')) {
            $query->whereHas('section', function ($q) use ($request) {
                $q->where('program_type_id', $request->program_type_id);
            });
        }

        $isSuperAdmin = $user->hasRole('super_admin');
        $isTmhrt = $isSuperAdmin || $user->hasRole('tmhrt_kfl') || $user->hasRole('tmhrt_office_admin');
        $isYesew = $isSuperAdmin || $user->hasRole('yesew_habt') || $user->hasRole('gngnunet_office_admin');

        // Filter by promotion status tab (eligible, nominated_tmhrt, endorsed_yesew, promoted, all)
        if ($request->filled('promotion_status') && $request->promotion_status !== 'all') {
            $st = $request->promotion_status;
            if ($st === 'eligible') {
                $query->where(function ($q) {
                    $q->where('promotion_status', 'eligible')->orWhereNull('promotion_status');
                });
            } elseif ($st === 'nominated_tmhrt' || $st === 'nominated') {
                $query->whereIn('promotion_status', ['nominated_tmhrt', 'nominated']);
            } else {
                $query->where('promotion_status', $st);
            }
        } elseif (!$request->filled('promotion_status')) {
            // Yesew Habt without filter only sees candidates sent by Tmhrt Kfl
            if ($user->hasRole('yesew_habt') && !$isSuperAdmin && !$user->hasRole('tmhrt_kfl')) {
                $query->whereIn('promotion_status', ['nominated_tmhrt', 'nominated', 'endorsed_yesew']);
            }
        }

        // Search by name or student ID
        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                  ->orWhere('student_id', 'like', "%{$search}%")
                  ->orWhere('christian_name', 'like', "%{$search}%");
            });
        }

        $students = $query->orderBy('name', 'asc')->get();

        // Fetch all sections grouped by program type for next-section resolution
        $programTypes = ProgramType::with(['sections' => fn($q) => $q->orderBy('order_no')])->get()->keyBy('id');
        $programTypesByName = $programTypes->keyBy('name');

        $candidates = [];

        foreach ($students as $student) {
            // 1. Calculate Grade & Academic Performance
            $courseScores = [];
            $allAssessments = [];
            $totalEarnedPoints = 0;
            $totalMaxPoints = 0;

            foreach ($student->grades as $grade) {
                $assessment = $grade->assessment;
                if (!$assessment || !$assessment->course) continue;

                $courseId = $assessment->course_id;
                $courseName = $assessment->course->name;

                if (!isset($courseScores[$courseId])) {
                    $courseScores[$courseId] = [
                        'course_id' => $courseId,
                        'course_name' => $courseName,
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

                $totalEarnedPoints += $rawScore;
                $totalMaxPoints += $maxScore;
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

            // 2. Calculate Attendance Performance
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

                if ($type === 'MezmurTraining') {
                    $mezmurSessions['total']++;
                    if ($status === 'Present') $mezmurSessions['present']++;
                    elseif ($status === 'Excused') $mezmurSessions['excused']++;
                    else $mezmurSessions['absent']++;
                } else {
                    $courseSessions['total']++;
                    if ($status === 'Present') $courseSessions['present']++;
                    elseif ($status === 'Excused') $courseSessions['excused']++;
                    else $courseSessions['absent']++;
                }
            }

            $totalSessions = $courseSessions['total'] + $mezmurSessions['total'];
            $totalPresent = $courseSessions['present'] + $mezmurSessions['present'];
            $totalExcused = $courseSessions['excused'] + $mezmurSessions['excused'];
            $totalAbsent = $courseSessions['absent'] + $mezmurSessions['absent'];

            $overallAttendanceAvg = $totalSessions > 0
                ? round((($totalPresent + ($totalExcused * 0.5)) / $totalSessions) * 100, 1)
                : null;

            $courseAttendanceAvg = $courseSessions['total'] > 0
                ? round((($courseSessions['present'] + ($courseSessions['excused'] * 0.5)) / $courseSessions['total']) * 100, 1)
                : null;

            $mezmurAttendanceAvg = $mezmurSessions['total'] > 0
                ? round((($mezmurSessions['present'] + ($mezmurSessions['excused'] * 0.5)) / $mezmurSessions['total']) * 100, 1)
                : null;

            $isEligible = $overallGradeAvg !== null
                && $overallGradeAvg >= 50
                && $overallAttendanceAvg !== null
                && $overallAttendanceAvg >= 70;

            if ($request->input('promotion_status') === 'eligible' && !$isEligible) {
                continue;
            }

            // 3. Next Section Auto-Resolution
            $currentSection = $student->section;
            $nextSection = null;
            $isGraduating = false;

            if ($student->target_section_id && $student->targetSection) {
                $nextSection = [
                    'id' => $student->targetSection->id,
                    'name' => $student->targetSection->name,
                    'is_custom' => true,
                ];
            } elseif ($currentSection) {
                $program = $programTypes->get($currentSection->program_type_id);
                $sectionsInProgram = $program ? $program->sections : collect();
                
                // Look for next section in same program
                $nextSecModel = $sectionsInProgram->first(fn($s) => $s->order_no > $currentSection->order_no);

                if ($nextSecModel) {
                    $nextSection = [
                        'id' => $nextSecModel->id,
                        'name' => $nextSecModel->name,
                        'is_custom' => false,
                    ];
                } else {
                    // Reached end of current program
                    $progName = $program->name ?? '';
                    if (in_array($progName, ['PreKG', 'Young', 'Distance'])) {
                        $regularProg = $programTypesByName->get('Regular');
                        $firstRegular = $regularProg ? $regularProg->sections->first() : null;
                        if ($firstRegular) {
                            $nextSection = [
                                'id' => $firstRegular->id,
                                'name' => $firstRegular->name . ' (Transition to Regular)',
                                'is_custom' => false,
                            ];
                        }
                    } else {
                        $isGraduating = true;
                    }
                }
            }

            // Interactive filter checks
            if ($request->filled('min_grade') && $request->min_grade !== 'all') {
                $minG = (float) $request->min_grade;
                if ($overallGradeAvg === null || $overallGradeAvg < $minG) {
                    continue;
                }
            }

            if ($request->filled('min_attendance') && $request->min_attendance !== 'all') {
                $minA = (float) $request->min_attendance;
                if ($overallAttendanceAvg === null || $overallAttendanceAvg < $minA) {
                    continue;
                }
            }

            $promotionStatus = in_array($student->promotion_status, ['nominated_tmhrt', 'nominated'])
                ? 'nominated_tmhrt'
                : (in_array($student->promotion_status, ['endorsed_yesew', 'promoted'])
                    ? $student->promotion_status
                    : ($isEligible ? 'eligible' : 'ineligible'));

            $candidates[] = [
                'id' => $student->id,
                'student_id' => $student->student_id,
                'name' => $student->name,
                'christian_name' => $student->christian_name,
                'status' => $student->status,
                'section_id' => $student->section_id,
                'section_name' => $currentSection?->name ?? 'Unassigned',
                'program_name' => $currentSection?->programType?->name ?? 'None',
                'grade_level' => $student->grade_level,
                
                // Dynamic Academic Evaluation
                'overall_grade_avg' => $overallGradeAvg,
                'courses_breakdown' => $coursesBreakdown,
                'has_grades' => count($coursesBreakdown) > 0,
                'is_eligible' => $isEligible,
                'eligibility_reasons' => [
                    'has_results' => $overallGradeAvg !== null,
                    'grade_passed' => $overallGradeAvg !== null && $overallGradeAvg >= 50,
                    'has_attendance' => $overallAttendanceAvg !== null,
                    'attendance_passed' => $overallAttendanceAvg !== null && $overallAttendanceAvg >= 70,
                ],
                
                // Dynamic Attendance Evaluation
                'overall_attendance_avg' => $overallAttendanceAvg,
                'course_attendance_avg' => $courseAttendanceAvg,
                'mezmur_attendance_avg' => $mezmurAttendanceAvg,
                'attendance_summary' => [
                    'total' => $totalSessions,
                    'present' => $totalPresent,
                    'absent' => $totalAbsent,
                    'excused' => $totalExcused,
                    'course_total' => $courseSessions['total'],
                    'mezmur_total' => $mezmurSessions['total'],
                ],
                'session_logs' => array_slice(array_reverse($sessionLogs), 0, 10),
                
                // Promotion Workflow State
                'promotion_status' => $promotionStatus,
                'is_verified' => (bool) $student->is_verified,
                'target_section_id' => $student->target_section_id,
                'next_section' => $nextSection,
                'is_graduating' => $isGraduating,
                'promotion_notes' => $student->promotion_notes,
                'nominator' => $student->nominator ? $student->nominator->name : null,
                'nominated_at' => $student->nominated_at ? date('Y-m-d H:i', strtotime($student->nominated_at)) : null,
                'endorser' => $student->endorser ? $student->endorser->name : null,
                'endorsed_at' => $student->endorsed_at ? date('Y-m-d H:i', strtotime($student->endorsed_at)) : null,
                'endorsement_notes' => $student->endorsement_notes,
                'approver' => $student->approver ? $student->approver->name : null,
                'approved_at' => $student->approved_at ? date('Y-m-d H:i', strtotime($student->approved_at)) : null,
            ];
        }

        // Summary counts across all students
        $eligibleCount = $request->input('promotion_status') === 'eligible'
            ? count($candidates)
            : Student::where('promotion_status', 'eligible')->orWhereNull('promotion_status')->count();

        $stats = [
            'total_students' => Student::count(),
            'eligible_count' => $eligibleCount,
            'nominated_count' => Student::whereIn('promotion_status', ['nominated_tmhrt', 'nominated'])->count(),
            'endorsed_count' => Student::where('promotion_status', 'endorsed_yesew')->count(),
            'promoted_count' => Student::where('promotion_status', 'promoted')->count(),
        ];

        // All sections for dropdown selection
        $allSections = Section::with('programType')->orderBy('program_type_id')->orderBy('order_no')->get();

        return response()->json([
            'candidates' => $candidates,
            'stats' => $stats,
            'sections' => $allSections,
            'user_role' => [
                'is_super_admin' => $user->hasRole('super_admin'),
                'is_tmhrt' => $user->hasRole('super_admin') || $user->hasRole('tmhrt_kfl') || $user->hasRole('tmhrt_office_admin'),
                'is_yesew_habt' => $user->hasRole('super_admin') || $user->hasRole('yesew_habt') || $user->hasRole('gngnunet_office_admin'),
            ],
        ]);
    }

    /**
     * 🎓 Level 1: Nominate candidates for promotion (Exclusively for Tmhrt Admin / Super Admin)
     * Mandatory Attendance requirement: >= 70%
     */
    public function nominate(Request $request)
    {
        $user = Auth::user();
        if (!$user || !($user->hasRole('super_admin') || $user->hasRole('tmhrt_kfl') || $user->hasRole('tmhrt_office_admin'))) {
            return response()->json([
                'message' => 'Forbidden: Only Tmhrt Admin (ትምህርት ክፍል) or Super Admin can nominate students for promotion.'
            ], 403);
        }

        $request->validate([
            'student_ids' => 'required|array|min:1',
            'student_ids.*' => 'exists:students,id',
            'target_section_id' => 'nullable|exists:sections,id',
            'notes' => 'nullable|string|max:500',
        ]);

        $students = Student::with(['section', 'attendances', 'grades.assessment'])->whereIn('id', $request->student_ids)->get();
        $targetSectionId = $request->target_section_id;
        $allSections = Section::orderBy('order_no')->get();

        // Enforce the same live eligibility rule shown on the Tmhrt screen.
        foreach ($students as $student) {
            $totalAtt = $student->attendances->count();
            $attendancePct = $totalAtt > 0
                ? (($student->attendances->where('status', 'Present')->count() + ($student->attendances->where('status', 'Excused')->count() * 0.5)) / $totalAtt) * 100
                : null;

            $totalWeight = 0;
            $weightedScore = 0;
            foreach ($student->grades as $grade) {
                $assessment = $grade->assessment;
                if (!$assessment || (float) $assessment->max_score <= 0) continue;
                $weight = (float) ($assessment->weight ?: 100);
                $weightedScore += ((float) $grade->score / (float) $assessment->max_score) * $weight;
                $totalWeight += $weight;
            }
            $gradePct = $totalWeight > 0 ? ($weightedScore / $totalWeight) * 100 : null;

            if ($gradePct === null || $gradePct < 50 || $attendancePct === null || $attendancePct < 70) {
                return response()->json([
                    'message' => "Student {$student->name} must have results of at least 50% and attendance of at least 70% before nomination."
                ], 422);
            }
        }

        DB::transaction(function () use ($students, $targetSectionId, $allSections, $user, $request) {
            foreach ($students as $student) {
                $resolvedTargetId = $targetSectionId;

                if (!$resolvedTargetId && $student->section) {
                    $nextSec = $allSections
                        ->where('program_type_id', $student->section->program_type_id)
                        ->where('order_no', '>', $student->section->order_no)
                        ->first();
                    $resolvedTargetId = $nextSec?->id;
                }

                $student->promotion_status = 'nominated_tmhrt';
                $student->target_section_id = $resolvedTargetId;
                $student->nominated_by = $user->id;
                $student->nominated_at = now();
                if ($request->filled('notes')) {
                    $student->promotion_notes = $request->notes;
                }
                $student->save();
            }
        });

        return response()->json([
            'message' => count($students) . ' student(s) successfully nominated (Level 1) by Tmhrt Admin. Sent to Ye Sew Habt for review.',
            'count' => count($students),
        ]);
    }

    /**
     * 👥 Level 2: Endorse candidates (Exclusively for Ye Sew Habt / Super Admin)
     */
    public function endorse(Request $request)
    {
        $user = Auth::user();
        if (!$user || !($user->hasRole('super_admin') || $user->hasRole('yesew_habt') || $user->hasRole('gngnunet_office_admin'))) {
            return response()->json([
                'message' => 'Forbidden: Only Ye Sew Habt (የሰው ሀብት ክፍል) or Super Admin can endorse student promotions.'
            ], 403);
        }

        $request->validate([
            'student_ids' => 'required|array|min:1',
            'student_ids.*' => 'exists:students,id',
            'notes' => 'nullable|string|max:500',
        ]);

        $students = Student::whereIn('id', $request->student_ids)
            ->whereIn('promotion_status', ['nominated_tmhrt', 'nominated'])
            ->get();

        if ($students->isEmpty()) {
            return response()->json([
                'message' => 'No nominated candidates found for endorsement. Candidates must first be nominated by Tmhrt Admin.'
            ], 422);
        }

        DB::transaction(function () use ($students, $user, $request) {
            foreach ($students as $student) {
                $student->promotion_status = 'endorsed_yesew';
                $student->endorsed_by = $user->id;
                $student->endorsed_at = now();
                if ($request->filled('notes')) {
                    $student->endorsement_notes = $request->notes;
                }
                $student->save();
            }
        });

        return response()->json([
            'message' => count($students) . ' student(s) successfully endorsed (Level 2) by Ye Sew Habt. Sent to Super Admin for final approval.',
            'count' => count($students),
        ]);
    }

    /**
     * 👑 Level 3: Final Official Approval & Grade Advancement (Strictly for Super Admin!)
     */
    public function approve(Request $request)
    {
        $user = Auth::user();
        if (!$user || !$user->hasRole('super_admin')) {
            return response()->json([
                'message' => 'Forbidden: Only the Super Admin (የበላይ አስተዳዳሪ) has the authority to execute final promotion approval and section advancement.'
            ], 403);
        }

        $request->validate([
            'student_ids' => 'required|array|min:1',
            'student_ids.*' => 'exists:students,id',
            'notes' => 'nullable|string|max:500',
        ]);

        $students = Student::with(['section', 'targetSection'])
            ->whereIn('id', $request->student_ids)
            ->where('promotion_status', 'endorsed_yesew')
            ->get();

        if ($students->isEmpty()) {
            return response()->json([
                'message' => 'No endorsed candidates found for final approval. Candidates must first be nominated by Tmhrt Admin and endorsed by Ye Sew Habt.'
            ], 422);
        }

        DB::transaction(function () use ($students, $user, $request) {
            foreach ($students as $student) {
                if ($student->target_section_id) {
                    $student->section_id = $student->target_section_id;
                    if ($student->targetSection) {
                        $student->grade_level = $student->targetSection->name;
                    }
                } else {
                    $student->status = 'Graduated';
                    $student->section_id = null;
                }

                $student->promotion_status = 'promoted';
                $student->is_verified = true;
                $student->approved_by = $user->id;
                $student->approved_at = now();
                if ($request->filled('notes')) {
                    $student->promotion_notes = ($student->promotion_notes ? $student->promotion_notes . ' | ' : '') . 'Final Approval: ' . $request->notes;
                }
                $student->save();
            }
        });

        return response()->json([
            'message' => count($students) . ' student(s) officially approved and promoted to their next grade level by Super Admin!',
            'count' => count($students),
        ]);
    }

    /**
     * ↩️ Reject or return nomination back to eligible (Super Admin, Ye Sew Habt, or Tmhrt Admin)
     */
    public function reject(Request $request)
    {
        $user = Auth::user();
        if (!$user || !($user->hasRole('super_admin') || $user->hasRole('yesew_habt') || $user->hasRole('gngnunet_office_admin') || $user->hasRole('tmhrt_kfl') || $user->hasRole('tmhrt_office_admin'))) {
            return response()->json([
                'message' => 'Forbidden: Your role cannot reject or return nominations.'
            ], 403);
        }

        $request->validate([
            'student_ids' => 'required|array|min:1',
            'student_ids.*' => 'exists:students,id',
            'reason' => 'required|string|max:500',
        ]);

        $students = Student::whereIn('id', $request->student_ids)->get();

        DB::transaction(function () use ($students, $user, $request) {
            foreach ($students as $student) {
                $student->promotion_status = 'eligible';
                $student->target_section_id = null;
                $student->endorsed_by = null;
                $student->endorsed_at = null;
                $student->endorsement_notes = null;
                $student->promotion_notes = 'Reset by ' . $user->name . ': ' . $request->reason;
                $student->save();
            }
        });

        return response()->json([
            'message' => count($students) . ' candidate(s) reset back to eligible status.',
            'count' => count($students),
        ]);
    }

    /**
     * Legacy verify a single student
     */
    public function verifyStudent($studentId)
    {
        $student = Student::findOrFail($studentId);

        if ($student->is_verified) {
            return response()->json([
                'message' => 'Student is already verified.'
            ], 200);
        }

        $student->is_verified = true;
        $student->verified_by = Auth::id();
        $student->verified_at = now();
        $student->save();

        return response()->json([
            'message' => 'Student verified successfully',
            'student' => $student
        ]);
    }

    /**
     * Legacy bulk verify students
     */
    public function bulkVerify(Request $request)
    {
        $request->validate([
            'student_ids'   => 'required|array',
            'student_ids.*' => 'exists:students,id',
        ]);

        DB::transaction(function () use ($request) {
            Student::whereIn('id', $request->student_ids)
                ->update([
                    'is_verified' => true,
                    'verified_by' => Auth::id(),
                    'verified_at' => now(),
                ]);
        });

        return response()->json([
            'message' => 'Selected students have been verified successfully.',
        ]);
    }

    /**
     * Legacy promote regular
     */
    public function promoteRegular()
    {
        $students = Student::with('section.programType')
            ->whereHas('section.programType', fn($q) => $q->where('name', 'Regular'))
            ->where('is_verified', true)
            ->get();

        return $this->promoteLegacy($students, 'Regular');
    }

    /**
     * Legacy promote young
     */
    public function promoteYoung()
    {
        $students = Student::with('section.programType')
            ->whereHas('section.programType', fn($q) => $q->where('name', 'Young'))
            ->where('is_verified', true)
            ->get();

        return $this->promoteLegacy($students, 'Young');
    }

    /**
     * Legacy promote distance
     */
    public function promoteDistance()
    {
        $students = Student::with('section.programType')
            ->whereHas('section.programType', fn($q) => $q->where('name', 'Distance'))
            ->where('is_verified', true)
            ->get();

        return $this->promoteLegacy($students, 'Distance');
    }

    private function promoteLegacy($students, string $programName)
    {
        $programTypes = ProgramType::with(['sections' => function ($q) {
            $q->orderBy('order_no');
        }])->get()->keyBy('name');

        return DB::transaction(function () use ($students, $programTypes, $programName) {
            $updated = [];

            foreach ($students as $student) {
                $studentProgram = $student->section->programType->name ?? null;

                if ($studentProgram !== $programName) {
                    continue;
                }

                $currentProgram = $programTypes[$studentProgram] ?? null;
                $currentSections = $currentProgram ? $currentProgram->sections : collect();
                $lastSectionId = optional($currentSections->last())->id;

                if ($studentProgram === 'Young') {
                    if ($student->section_id !== $lastSectionId) {
                        $student->section_id = $this->getNextSectionIdByOrder($student->section_id, $currentSections);
                    } else {
                        $firstRegular = $programTypes['Regular']->sections->first();
                        if ($firstRegular) {
                            $student->section_id = $firstRegular->id;
                            $student->student_id = $this->generateStudentId('REG');
                        }
                    }
                } elseif ($studentProgram === 'Regular') {
                    if ($student->section_id !== $lastSectionId) {
                        $student->section_id = $this->getNextSectionIdByOrder($student->section_id, $currentSections);
                    } else {
                        $student->section_id = null;
                        $student->status = 'Graduated';
                    }
                } elseif ($studentProgram === 'Distance') {
                    $firstRegular = $programTypes['Regular']->sections->first();
                    if ($firstRegular) {
                        $student->section_id = $firstRegular->id;
                        $student->student_id = $this->generateStudentId('REG');
                    }
                }

                $student->is_verified = false;
                $student->save();
                $updated[] = $student;
            }

            return response()->json([
                'message' => count($updated) . " {$programName} student(s) promoted successfully",
                'students' => $updated
            ]);
        });
    }

    private function getNextSectionIdByOrder($currentSectionId, $orderedSections)
    {
        $index = $orderedSections->search(fn($s) => $s->id === $currentSectionId);
        if ($index !== false && isset($orderedSections[$index + 1])) {
            return $orderedSections[$index + 1]->id;
        }
        return $currentSectionId;
    }

    private function generateStudentId(string $prefix, ?string $round = null): string
    {
        return retry(3, function () use ($prefix, $round) {
            if ($prefix === 'DIS' && $round) {
                $count = Student::where('student_id', 'like', "{$prefix}/{$round}/%")->count() + 1;
                $candidate = "{$prefix}/{$round}/{$count}";
            } else {
                $count = Student::where('student_id', 'like', "{$prefix}/%")->count() + 1;
                $candidate = "{$prefix}/{$count}";
            }

            if (Student::where('student_id', $candidate)->exists()) {
                throw new \RuntimeException('collision');
            }
            return $candidate;
        }, 50);
    }
}
