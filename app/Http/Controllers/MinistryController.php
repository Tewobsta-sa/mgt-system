<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreMinistryAssignmentRequest;
use App\Models\{Ministry, MinistryAssignment, MinistryAssignmentStudent};
use App\Services\MinistryService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class MinistryController extends Controller
{
    public function index(){ return Ministry::orderByDesc('ministry_date')->paginate(10); }

    public function store(Request $r){
        $data = $r->validate([
            'name'=>'required|string',
            'ministry_date'=>'required|date',
            'location'=>'nullable|string',
            'notes'=>'nullable|string'
        ]);
        return response()->json(Ministry::create($data),201);
    }

    public function addAssignment(StoreMinistryAssignmentRequest $r, MinistryService $svc)
    {
        $data = $r->validated();
        $ma = null;

        DB::transaction(function() use ($data, &$ma) {
            $ma = MinistryAssignment::create([
                'ministry_id' => $data['ministry_id'],
                'duration_start_date' => $data['duration_start_date'],
                'duration_end_date' => $data['duration_end_date'],
                'created_by_user_id' => Auth::id(),
            ]);
            $ma->mezmurs()->sync($data['mezmur_ids']);
        });

        if ($ma) {
            return response()->json($ma->load('mezmurs'), 201);
        } else {
            return response()->json(['error' => 'Assignment could not be created'], 500);
        }
    }

    public function autoSelectStudents($id, MinistryService $svc)
    {
        $ma = MinistryAssignment::findOrFail($id);
        $studentIds = $svc->autoSelectStudents($ma);

        DB::transaction(function() use ($ma,$studentIds){
            foreach ($studentIds as $sid) {
                MinistryAssignmentStudent::firstOrCreate(
                    ['ministry_assignment_id'=>$ma->id, 'student_id'=>$sid],
                    ['source'=>'Auto']
                );
            }
        });

        return response()->json([
            'selected_count' => count($studentIds),
            'students' => MinistryAssignmentStudent::with('student')->where('ministry_assignment_id',$ma->id)->get()
        ]);
    }

    public function addStudentManually(Request $r, $id){
        $data = $r->validate(['student_id'=>'required|exists:students,id']);
        $ma = MinistryAssignment::findOrFail($id);
        MinistryAssignmentStudent::firstOrCreate(
            ['ministry_assignment_id'=>$ma->id,'student_id'=>$data['student_id']],
            ['source'=>'Manual']
        );
        return response()->json(['message'=>'Added']);
    }

    public function removeStudent($id,$studentId){
        MinistryAssignmentStudent::where('ministry_assignment_id',$id)->where('student_id',$studentId)->delete();
        return response()->json(null,204);
    }
}

