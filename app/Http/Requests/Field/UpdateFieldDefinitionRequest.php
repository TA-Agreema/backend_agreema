<?php

namespace App\Http\Requests\Field;

use App\Models\FieldDefinition;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

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

    protected function prepareForValidation(): void
    {
        $normalized = [];

        if ($this->has('field_label')) {
            $normalized['field_label'] = $this->normalizeFieldLabel(
                $this->input('field_label', ''),
            );
        }

        if ($this->has('field_key')) {
            $normalized['field_key'] = strtolower(
                trim((string) $this->input('field_key', '')),
            );
        }

        if (!empty($normalized)) {
            $this->merge($normalized);
        }
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator) {
            if (
                $this->has('field_label') &&
                $this->fieldLabelExists($this->input('field_label', ''))
            ) {
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
        $id = $this->route('id');
        $normalizedLabel = strtolower($this->normalizeFieldLabel($label));

        return FieldDefinition::query()
            ->whereKeyNot($id)
            ->whereRaw('LOWER(TRIM(field_label)) = ?', [$normalizedLabel])
            ->exists();
    }
}
