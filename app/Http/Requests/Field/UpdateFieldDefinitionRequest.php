<?php

namespace App\Http\Requests\Field;

use Illuminate\Foundation\Http\FormRequest;

class UpdateFieldDefinitionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $id = $this->route('id');

        return [
            'field_key'   => "sometimes|required|string|unique:fields_definitions,field_key,{$id}",
            'field_label' => 'sometimes|required|string',
            'field_type'  => 'sometimes|required|string',
            'is_required' => 'boolean',
            'is_active'   => 'boolean',
        ];
    }
}
