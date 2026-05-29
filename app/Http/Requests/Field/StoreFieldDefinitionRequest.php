<?php

namespace App\Http\Requests\Field;

use Illuminate\Foundation\Http\FormRequest;

class StoreFieldDefinitionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'field_key'   => 'required|string|unique:fields_definitions,field_key',
            'field_label' => 'required|string',
            'field_type'  => 'required|string',
            'is_required' => 'boolean',
            'is_active'   => 'boolean',
        ];
    }
}
