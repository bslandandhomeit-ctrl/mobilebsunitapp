<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\UnifiedCatalogRepository;
use Illuminate\Http\Request;

class UnifiedCatalogController extends Controller
{
    protected function enabled()
    {
        abort_unless(config('unified_catalog.enabled'), 404);
    }

    public function units(Request $request, UnifiedCatalogRepository $catalog)
    {
        $this->enabled();
        $filters = $request->validate([
            'project_id' => 'nullable|integer|min:1',
            'unit_type_id' => 'nullable|integer|min:1',
            'term' => 'nullable|string|max:100',
            'status' => 'nullable|string|max:100',
            'per_page' => 'nullable|integer|min:1|max:100',
            'page' => 'nullable|integer|min:1',
        ]);
        $page = $catalog->units($filters);
        return response()->json([
            'data' => $page->items(), 'code' => 200,
            'meta' => ['current_page' => $page->currentPage(), 'last_page' => $page->lastPage(),
                'per_page' => $page->perPage(), 'total' => $page->total()],
            'links' => ['next' => $page->nextPageUrl(), 'prev' => $page->previousPageUrl()],
        ]);
    }

    public function show($id, UnifiedCatalogRepository $catalog)
    {
        $this->enabled();
        $unit = $catalog->unit($id);
        abort_unless($unit, 404);
        return response()->json(['data' => $unit, 'code' => 200]);
    }

    public function projects(UnifiedCatalogRepository $catalog)
    {
        $this->enabled();
        return response()->json(['data' => $catalog->projects(), 'code' => 200]);
    }

    public function unitTypes(Request $request, UnifiedCatalogRepository $catalog)
    {
        $this->enabled();
        $filters = $request->validate([
            'project_id' => 'nullable|integer|min:1',
            'per_page' => 'nullable|integer|min:1|max:100',
            'page' => 'nullable|integer|min:1',
        ]);
        $page = $catalog->unitTypes($filters);
        return response()->json(['data' => $page->items(), 'code' => 200,
            'meta' => ['current_page' => $page->currentPage(), 'last_page' => $page->lastPage(),
                'per_page' => $page->perPage(), 'total' => $page->total()],
            'links' => ['next' => $page->nextPageUrl(), 'prev' => $page->previousPageUrl()]]);
    }
}
