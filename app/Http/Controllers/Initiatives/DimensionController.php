<?php

namespace App\Http\Controllers\Initiatives;

use App\Http\Controllers\Controller;
use App\Http\Requests\Initiatives\DimensionRequest;
use App\Models\Dimension;
use App\Models\Initiative;
use Illuminate\Http\RedirectResponse;

class DimensionController extends Controller
{
    public function store(DimensionRequest $request, Initiative $initiative): RedirectResponse
    {
        $this->authorize('update', $initiative);

        $initiative->dimensions()->create([
            ...$request->validated(),
            'position' => (int) $initiative->dimensions()->max('position') + 1,
        ]);

        return back()->with('success', 'Dimension added.');
    }

    public function update(DimensionRequest $request, Initiative $initiative, Dimension $dimension): RedirectResponse
    {
        $this->authorize('update', $initiative);

        $dimension->update($request->validated());

        return back()->with('success', 'Dimension updated.');
    }

    public function destroy(Initiative $initiative, Dimension $dimension): RedirectResponse
    {
        $this->authorize('update', $initiative);

        $dimension->delete();

        return back()->with('success', 'Dimension removed.');
    }
}
