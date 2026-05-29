<?php

namespace App\Http\Requests\Template;

use Illuminate\Foundation\Http\FormRequest;

class StoreTemplateRequest extends FormRequest
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
            'name'        => 'required|string|max:255|unique:templates,name',
            'content'     => 'required|string',
            'category_id' => 'required|integer|exists:contract_categories,id',
            'is_active'   => 'nullable|boolean',
        ];
    }

    public function messages(): array
    {
        return [
            'name.required'        => 'Nama template wajib diisi.',
            'name.unique'          => 'Nama template sudah digunakan.',
            'content.required'     => 'Konten template wajib diisi.',
            'category_id.required' => 'Kategori template wajib dipilih.',
            'category_id.exists'   => 'Kategori yang dipilih tidak valid.',
        ];
    }
}
