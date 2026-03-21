<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class SaveJambAnswerRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'attemptQuestionId' => ['required', 'integer', 'exists:jamb_attempt_questions,attemptQuestionId'],
            'selectedOption' => ['nullable', 'in:A,B,C,D'],
            'isFlagged' => ['nullable', 'boolean'],
            'timeSpentSeconds' => ['nullable', 'integer', 'min:0', 'max:7200'],
            'currentQuestionOrder' => ['nullable', 'integer', 'min:1'],
        ];
    }
}