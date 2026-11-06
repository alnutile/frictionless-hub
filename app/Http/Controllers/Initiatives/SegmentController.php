<?php

namespace App\Http\Controllers\Initiatives;

use App\Http\Controllers\Controller;
use App\Models\Initiative;
use App\Models\SegmentCategory;
use App\Models\SegmentValue;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * Groups used for coverage: categories (e.g. Experience) and their values (e.g. Senior).
 */
class SegmentController extends Controller
{
    public function storeCategory(Request $request, Initiative $initiative): RedirectResponse
    {
        $this->authorize('update', $initiative);

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:100', Rule::unique('segment_categories')->where('initiative_id', $initiative->id)],
        ]);

        $initiative->segmentCategories()->create([
            ...$validated,
            'position' => (int) $initiative->segmentCategories()->max('position') + 1,
        ]);

        return back();
    }

    public function destroyCategory(Initiative $initiative, SegmentCategory $segmentCategory): RedirectResponse
    {
        $this->authorize('update', $initiative);
        abort_if($segmentCategory->is_area, 422, 'The work-area category cannot be removed.');

        $segmentCategory->delete();

        return back();
    }

    public function storeValue(Request $request, Initiative $initiative, SegmentCategory $segmentCategory): RedirectResponse
    {
        $this->authorize('update', $initiative);

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:100', Rule::unique('segment_values')->where('segment_category_id', $segmentCategory->id)],
        ]);

        $segmentCategory->values()->create([
            ...$validated,
            'position' => (int) $segmentCategory->values()->max('position') + 1,
        ]);

        return back();
    }

    public function destroyValue(Initiative $initiative, SegmentValue $segmentValue): RedirectResponse
    {
        $this->authorize('update', $initiative);

        $segmentValue->delete();

        return back();
    }
}
