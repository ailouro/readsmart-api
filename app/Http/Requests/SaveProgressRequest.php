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
            'quiz_score' => 'required|numeric',
            'total_questions' => 'required|numeric',
            'oral_fluency_accuracy' => 'required|numeric',
            'time_on_task' => 'required|integer',
            'test_type' => 'nullable|in:pre_test,post_test',
            'self_corrected_words' => 'nullable|array',
            'self_corrected_words.*.word' => 'required_with:self_corrected_words|string',
            'self_corrected_words.*.total_attempts' => 'nullable|integer',
        ];
    }
}