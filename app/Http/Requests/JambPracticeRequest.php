<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StartJambPracticeRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'subjectId' => ['required', 'integer', 'exists:jamb_subjects,subjectId'],
            'topicId' => ['nullable', 'integer', 'exists:jamb_topics,topicId'],
            'questionCount' => ['required', 'integer', 'min:5', 'max:100'],
            'difficulty' => ['nullable', 'in:easy,medium,hard'],
            'timed' => ['nullable', 'boolean'],
            'durationMinutes' => ['nullable', 'integer', 'min:1', 'max:300'],
            'year' => ['nullable', 'integer', 'min:1978', 'max:' . now()->year],
        ];
    }
}