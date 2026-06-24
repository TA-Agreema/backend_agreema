<?php

namespace App\Http\Requests\Termination;

use Illuminate\Foundation\Http\FormRequest;

class StoreTerminationRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'title' => 'required|string|max:255',
            'termination_number' => 'required|string|max:100',
            'termination_reason' => 'required|string|max:5000',
            'termination_note' => 'nullable|string|max:5000',
            'document' => 'nullable|file|mimes:pdf|max:10240',
            'effective_date' => 'required|date',
        ];
    }
}
