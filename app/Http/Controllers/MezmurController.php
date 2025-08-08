<?php 

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Mezmur;
use App\Models\MezmurLyricsPart;
use App\Models\MezmurCategory;

class MezmurController extends Controller
{
    public function index(Request $r)
    {
        $q = Mezmur::with(['category.type', 'lyricsParts']);

        if ($r->filled('category_type')) {
            $q->whereHas('category.type', fn($w) => $w->where('name', $r->category_type));
        }

        if ($r->filled('category_id')) {
            $q->where('category_id', $r->category_id);
        }

        if ($r->filled('q')) {
            $q->where('title', 'like', '%' . $r->q . '%');
        }

        return $q->orderByDesc('id')->paginate(10);
    }

    public function store(Request $r)
    {
        $data = $r->validate([
            'title' => 'required|string',
            'audio_url' => 'nullable|url',
            'category_name' => 'required|string|exists:mezmur_categories,name',
            'lyrics_parts' => 'required|array',
            'lyrics_parts.*.part_type' => 'required|string|in:chorus,verse,bridge,intro,outro',
            'lyrics_parts.*.content' => 'required|string',
            'lyrics_parts.*.order_no' => 'required|integer|min:1',
        ]);

        // Find the category by name
        $category = MezmurCategory::with('type')->where('name', $data['category_name'])->first();

        if (!$category) {
            return response()->json(['error' => 'Category not found'], 404);
        }

        // Category type is now accessible via $category->type
        // You can do any additional logic here if needed

        $mezmur = Mezmur::create([
            'title' => $data['title'],
            'audio_url' => $data['audio_url'] ?? null,
            'category_id' => $category->id,
        ]);

        // Insert lyrics parts
        foreach ($data['lyrics_parts'] as $part) {
            $mezmur->lyricsParts()->create([
                'part_type' => $part['part_type'],
                'content' => $part['content'],
                'order_no' => $part['order_no'],
            ]);
        }

        return response()->json($mezmur->load('category.type', 'lyricsParts'), 201);
    }

    public function show($id)
    {
        return Mezmur::with('category.type', 'lyricsParts')->findOrFail($id);
    }

    public function update(Request $r, $id)
    {
        $mezmur = Mezmur::findOrFail($id);

        $data = $r->validate([
            'title' => 'sometimes|string',
            'audio_url' => 'nullable|url',
            'category_name' => 'sometimes|string|exists:mezmur_categories,name',
            'lyrics_parts' => 'sometimes|array',
            'lyrics_parts.*.id' => 'sometimes|integer|exists:mezmur_lyrics_parts,id',
            'lyrics_parts.*.part_type' => 'required_with:lyrics_parts|string|in:chorus,verse,bridge,intro,outro',
            'lyrics_parts.*.content' => 'required_with:lyrics_parts|string',
            'lyrics_parts.*.order_no' => 'required_with:lyrics_parts|integer|min:1',
        ]);

        if (isset($data['category_name'])) {
            $category = MezmurCategory::with('type')->where('name', $data['category_name'])->first();

            if (!$category) {
                return response()->json(['error' => 'Category not found'], 404);
            }

            $mezmur->category_id = $category->id;
        }

        if (isset($data['title'])) {
            $mezmur->title = $data['title'];
        }

        if (array_key_exists('audio_url', $data)) {
            $mezmur->audio_url = $data['audio_url'];
        }

        $mezmur->save();

        // Update lyrics parts if provided
        if (isset($data['lyrics_parts'])) {
            foreach ($data['lyrics_parts'] as $part) {
                if (isset($part['id'])) {
                    // Update existing part
                    $lyricsPart = MezmurLyricsPart::find($part['id']);
                    if ($lyricsPart) {
                        $lyricsPart->update([
                            'part_type' => $part['part_type'],
                            'content' => $part['content'],
                            'order_no' => $part['order_no'],
                        ]);
                    }
                } else {
                    // Create new lyrics part
                    $mezmur->lyricsParts()->create([
                        'part_type' => $part['part_type'],
                        'content' => $part['content'],
                        'order_no' => $part['order_no'],
                    ]);
                }
            }
        }

        return $mezmur->load('category.type', 'lyricsParts');
    }

    public function destroy($id)
    {
        Mezmur::findOrFail($id)->delete();
        return response()->json(null, 204);
    }
}
