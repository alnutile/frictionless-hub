<?php

namespace App\Http\Requests\Initiatives;

use App\Enums\Level;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ThemeRequest extends FormRequest
{
    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $initiativeId = $this->route('initiative')->id;

        return [
            'name' => ['required', 'string', 'max:255'],
            'summary' => ['nullable', 'string', 'max:5000'],
            'impact' => ['nullable', Rule::enum(Level::class)],
            'effort' => ['nullable', Rule::enum(Level::class)],
            'solutions' => ['nullable', 'string', 'max:5000'],
            'code_ids' => ['array'],
            'code_ids.*' => ['integer', Rule::exists('codes', 'id')->where('initiative_id', $initiativeId)],
        ];
    }
}
