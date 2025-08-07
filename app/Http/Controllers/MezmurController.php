<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Mezmur;

class MezmurController extends Controller {
    public function index(Request $r){
        $q = Mezmur::with('category.type');
        if ($r->filled('category_type')) {
            $q->whereHas('category.type', fn($w) => $w->where('name',$r->category_type));
        }
        if ($r->filled('category_id')) $q->where('category_id',$r->category_id);
        if ($r->filled('q')) $q->where('title','like','%'.$r->q.'%');
        return $q->orderByDesc('id')->paginate(10);
    }
    public function store(Request $r){
        $data = $r->validate([
            'title'=>'required|string',
            'lyrics'=>'nullable|string',
            'audio_url'=>'nullable|url',
            'category_id'=>'required|exists:mezmur_categories,id'
        ]);
        return response()->json(Mezmur::create($data),201);
    }
    public function show($id){ return Mezmur::with('category.type')->findOrFail($id); }
    public function update(Request $r,$id){
        $m = Mezmur::findOrFail($id);
        $data = $r->validate([
            'title'=>'sometimes|string',
            'lyrics'=>'nullable|string',
            'audio_url'=>'nullable|url',
            'category_id'=>'sometimes|exists:mezmur_categories,id'
        ]);
        $m->update($data);
        return $m->load('category.type');
    }
    public function destroy($id){ Mezmur::findOrFail($id)->delete(); return response()->json(null,204); }
}

