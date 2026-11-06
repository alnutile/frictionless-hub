<?php

namespace App\Http\Requests\Initiatives;

use App\Enums\ParticipantStatus;
use App\Models\Initiative;
use App\Models\SegmentValue;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class ParticipantRequest extends FormRequest
{
    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'role' => ['nullable', 'string', 'max:255'],
            'status' => ['required', Rule::enum(ParticipantStatus::class)],
            'scheduled_at' => ['nullable', 'date'],
            'invite_reason' => ['nullable', 'string', 'max:255'],
            'notes' => ['nullable', 'string', 'max:5000'],
            'area_value_id' => ['nullable', 'integer'],
            'value_ids' => ['array'],
            'value_ids.*' => ['integer', 'distinct'],
        ];
    }

    /**
     * The area must be one of this initiative's work areas; other values must belong to this
     * initiative's non-area categories, with at most one value per category.
     *
     * @return array<int, callable(Validator): void>
     */
    public function after(): array
    {
        return [function (Validator $validator) {
            if ($validator->errors()->isNotEmpty()) {
                return;
            }

            /** @var Initiative $initiative */
            $initiative = $this->route('initiative');
            $ids = array_filter([$this->integer('area_value_id') ?: null, ...array_map('intval', $this->input('value_ids', []))]);
            $values = SegmentValue::with('category')->whereIn('id', $ids)->get()->keyBy('id');

            if ($this->filled('area_value_id')) {
                $area = $values->get($this->integer('area_value_id'));

                if (! $area || $area->category->initiative_id !== $initiative->id || ! $area->category->is_area) {
                    $validator->errors()->add('area_value_id', 'Choose one of this initiative\'s work areas.');
                }
            }

            $seenCategories = [];
            foreach ($this->input('value_ids', []) as $index => $id) {
                $value = $values->get((int) $id);

                if (! $value || $value->category->initiative_id !== $initiative->id || $value->category->is_area) {
                    $validator->errors()->add("value_ids.{$index}", 'That group value does not belong to this initiative.');

                    continue;
                }

                if (isset($seenCategories[$value->segment_category_id])) {
                    $validator->errors()->add("value_ids.{$index}", "Choose only one {$value->category->name} value per person.");
                }

                $seenCategories[$value->segment_category_id] = true;
            }
        }];
    }
}
