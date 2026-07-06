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
            'termination_number' => 'required|string|max:100|unique:contract_terminations,termination_number',
            'termination_reason' => 'required|string|max:5000',
            'termination_note' => 'nullable|string|max:5000',
            'document' => 'required|file|mimes:pdf|max:10240',
            'effective_date' => 'required|date',
        ];
    }

    public function messages(): array
    {
        return [
            'title.required' => 'Judul terminasi wajib diisi.',
            'termination_number.required' => 'Nomor terminasi wajib diisi.',
            'termination_number.unique' => 'Nomor terminasi sudah digunakan.',
            'termination_reason.required' => 'Alasan terminasi wajib diisi.',
            'document.required' => 'Dokumen wajib diisi.',
            'document.mimes' => 'Dokumen harus berupa file dengan tipe: pdf.',
            'document.max' => 'Ukuran dokumen tidak boleh lebih dari 10MB.',
            'effective_date.required' => 'Tanggal efektif wajib diisi.',
        ];
    }
}
