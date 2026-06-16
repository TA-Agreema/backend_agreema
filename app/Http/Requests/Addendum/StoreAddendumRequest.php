<?php

namespace App\Http\Requests\Addendum;

use Illuminate\Foundation\Http\FormRequest;

class StoreAddendumRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true; // Sesuaikan dengan logika otorisasi Anda jika diperlukan
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'title'           => 'required|string|max:255',
            'addendum_number' => 'required|string|max:100',
            'description'     => 'nullable|string|max:5000',
            'document'        => 'nullable|file|mimes:pdf|max:10240',
            'effective_date'  => 'nullable|date',
        ];
    }
}
