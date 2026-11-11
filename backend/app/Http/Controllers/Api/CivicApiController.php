<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Modules\Customer\Models\CivicItem;
use Illuminate\Http\Request;

class CivicApiController extends Controller
{
    public function index(Request $request)
    {
        $query = CivicItem::query()->latest('published_date');

        if ($type = $request->query('type')) {
            $query->where('type', $type);
        }

        if ($request->boolean('featured')) {
            $query->where('is_featured', true);
        }

        $items = $query->limit(50)->get()->map(fn (CivicItem $item) => [
            'id' => $item->id,
            'type' => $item->type,
            'title' => $item->title,
            'category' => $item->category,
            'summary' => $item->summary,
            'content' => $item->content,
            'author_role' => $item->author_role,
            'published_date' => $item->published_date?->toDateString(),
            'event_date' => $item->event_date?->toDateString(),
            'venue' => $item->venue,
            'download_url' => $item->download_url,
            'resolution_number' => $item->resolution_number,
            'is_featured' => $item->is_featured,
        ]);

        return response()->json(['data' => $items]);
    }

    public function show(string $id)
    {
        $item = CivicItem::findOrFail($id);

        return response()->json([
            'data' => [
                'id' => $item->id,
                'type' => $item->type,
                'title' => $item->title,
                'category' => $item->category,
                'summary' => $item->summary,
                'content' => $item->content,
                'author_role' => $item->author_role,
                'published_date' => $item->published_date?->toDateString(),
                'event_date' => $item->event_date?->toDateString(),
                'venue' => $item->venue,
                'download_url' => $item->download_url,
                'resolution_number' => $item->resolution_number,
                'is_featured' => $item->is_featured,
            ],
        ]);
    }
}

