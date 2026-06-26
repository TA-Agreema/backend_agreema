<?php

namespace App\Http\Requests\Field;

use App\Models\FieldDefinition;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

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

    protected function prepareForValidation(): void
    {
        $this->merge([
            'field_label' => $this->normalizeFieldLabel($this->input('field_label', '')),
            'field_key' => strtolower(trim((string) $this->input('field_key', ''))),
        ]);
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator) {
            if ($this->fieldLabelExists($this->input('field_label', ''))) {
                $validator->errors()->add('field_label', 'Nama field sudah digunakan.');
            }
        });
    }

    private function normalizeFieldLabel(string $label): string
    {
        return preg_replace('/\s+/', ' ', trim($label)) ?? '';
    }

    private function fieldLabelExists(string $label): bool
    {
        $normalizedLabel = strtolower($this->normalizeFieldLabel($label));

        return FieldDefinition::query()
            ->whereRaw('LOWER(TRIM(field_label)) = ?', [$normalizedLabel])
            ->exists();
    }
}
