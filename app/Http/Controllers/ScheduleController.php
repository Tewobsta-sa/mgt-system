<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreScheduleBlockRequest;
use App\Models\{ScheduleBlock, Assignment};
use App\Services\ScheduleService;
use Illuminate\Http\Request;

class ScheduleController extends Controller
{
    public function index(Request $req)
    {
        $q = ScheduleBlock::with(['assignment','items.teacher','items.course','items.mezmur']);
        if ($req->filled('date_from') && $req->filled('date_to')) {
            $q->whereBetween('date', [$req->date_from, $req->date_to]);
        }
        if ($req->filled('assignment_id')) $q->where('assignment_id', $req->assignment_id);
        return $q->orderBy('date')->paginate(10);
    }

    public function store(StoreScheduleBlockRequest $request, ScheduleService $service)
    {
        $block = $service->createBlockWithItems($request->validated());
        return response()->json($block, 201);
    }

    public function show($id)
    {
        return ScheduleBlock::with(['assignment','items.teacher','items.course','items.mezmur','attendance'])->findOrFail($id);
    }

    public function destroy($id)
    {
        $b = ScheduleBlock::withCount('attendance')->findOrFail($id);
        if ($b->attendance_count > 0) return response()->json(['error'=>'Has attendance; cannot delete'], 422);
        $b->delete();
        return response()->json(null, 204);
    }
}

