<?php

namespace App\Http\Requests\Initiatives;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class DimensionRequest extends FormRequest
{
    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $initiative = $this->route('initiative');

        return [
            'name' => [
                'required',
                'string',
                'max:255',
                Rule::unique('dimensions')
                    ->where('initiative_id', $initiative->id)
                    ->ignore($this->route('dimension')),
            ],
            'description' => ['nullable', 'string', 'max:255'],
        ];
    }
}
