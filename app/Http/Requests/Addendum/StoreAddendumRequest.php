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
            'addendum_number' => 'required|string|max:100|unique:contract_addendums,addendum_number',
            'description'     => 'nullable|string|max:5000',
            'document'        => 'required|file|mimes:pdf|max:10240',
            'effective_date'  => 'required|date',
        ];
    }

    public function messages(): array
    {
        return [
            'title.required' => 'Judul addendum wajib diisi.',
            'addendum_number.required' => 'Nomor addendum wajib diisi.',
            'addendum_number.unique' => 'Nomor addendum sudah digunakan.',
            'document.required' => 'Dokumen wajib diisi.',
            'document.mimes' => 'Dokumen harus berupa file dengan tipe: pdf.',
            'document.max' => 'Ukuran dokumen tidak boleh lebih dari 10MB.',
            'effective_date.required' => 'Tanggal efektif wajib diisi.',
        ];
    }
}
