<?php

namespace App\Http\Requests\Initiatives;

use App\Enums\Frequency;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class FrictionPointRequest extends FormRequest
{
    /**
     * Stage and dimension ids must belong to the initiative in the URL,
     * so a crafted request cannot attach another initiative's records.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $initiativeId = $this->route('initiative')->id;

        return [
            'title' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:5000'],
            'workflow_stage_id' => [
                'nullable',
                'integer',
                Rule::exists('workflow_stages', 'id')->where('initiative_id', $initiativeId),
            ],
            'frequency' => ['nullable', Rule::enum(Frequency::class)],
            'pain' => ['nullable', 'integer', 'between:1,5'],
            'affected' => ['nullable', 'string', 'max:255'],
            'dimension_ids' => ['array'],
            'dimension_ids.*' => [
                'integer',
                Rule::exists('dimensions', 'id')->where('initiative_id', $initiativeId),
            ],
        ];
    }
}
