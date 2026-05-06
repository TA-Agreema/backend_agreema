<?php

namespace App\Http\Requests\Template;

use Illuminate\Validation\Rule;
use Illuminate\Foundation\Http\FormRequest;

class UpdateTemplateRequest extends FormRequest
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
        $templateId = $this->route('id');

        return [
            'name' => [
                'sometimes',
                'required',
                'string',
                'max:255',
                Rule::unique('templates', 'name')->ignore($templateId),
            ],
            'content'     => 'sometimes|required|string',
            'category_id' => 'sometimes|required|integer|exists:contract_categories,id',
            'is_active'   => 'sometimes|boolean',
        ];
    }

    public function messages(): array
    {
        return [
            'name.unique'          => 'Nama template sudah digunakan.',
            'content.required'     => 'Konten template wajib diisi.',
            'category_id.exists'   => 'Kategori yang dipilih tidak valid.',
        ];
    }
}
