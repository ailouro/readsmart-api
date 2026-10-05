<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class SaveProgressRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'user_id' => 'required|exists:users,id',
            'story_id' => 'required|exists:stories,id',
            'test_type'             => 'required|in:pre_test,post_test',
            'quiz_score'            => 'required|numeric|min:0|lte:total_questions',
            'total_questions'       => 'required|integer|min:1',
            'oral_fluency_accuracy' => 'required|numeric|between:0,100',
            'time_on_task'          => 'required|integer|min:1',
            'self_corrected_words' => 'nullable|array',
            'self_corrected_words.*.word' => 'required_with:self_corrected_words|string',
            'self_corrected_words.*.total_attempts' => 'nullable|integer',
        ];
    }
}