<?php

namespace App\Http\Requests\Initiatives;

use App\Enums\NoteKind;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class InterviewNoteRequest extends FormRequest
{
    /**
     * Stage and code ids must belong to the initiative in the URL.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $initiativeId = $this->route('initiative')->id;

        return [
            'kind' => [$this->isMethod('post') ? 'required' : 'sometimes', Rule::enum(NoteKind::class)],
            'body' => ['required', 'string', 'max:5000'],
            'workflow_stage_id' => ['nullable', 'integer', Rule::exists('workflow_stages', 'id')->where('initiative_id', $initiativeId)],
            'owner' => ['nullable', 'string', 'max:255'],
            'done' => ['boolean'],
            'code_ids' => ['array'],
            'code_ids.*' => ['integer', Rule::exists('codes', 'id')->where('initiative_id', $initiativeId)],
        ];
    }
}
