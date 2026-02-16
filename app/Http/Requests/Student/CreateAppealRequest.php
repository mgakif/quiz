<?php

declare(strict_types=1);

namespace App\Http\Requests\Student;

use Illuminate\Foundation\Http\FormRequest;

class CreateAppealRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    public function rules(): array
    {
        return [
            'reason_text' => ['required', 'string', 'min:10', 'max:4000'],
        ];
    }

    public function messages(): array
    {
        return [
            'reason_text.required' => 'Please explain why you are appealing this score.',
            'reason_text.min' => 'Appeal reason should be at least 10 characters.',
        ];
    }
}
