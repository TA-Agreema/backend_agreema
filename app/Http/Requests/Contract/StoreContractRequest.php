<?php

namespace App\Http\Requests\Contract;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class StoreContractRequest extends FormRequest
{
    private const ALLOWED_STATUSES = 'draft,review,revision,approved,signed,active,expired,terminated,rejected';

    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'contract_number'          => 'nullable|string|unique:contracts,contract_number',
            'external_contract_number' => 'nullable|string|unique:contracts,external_contract_number',
            'title'       => 'required|string',
            'paper_size'   => 'nullable|in:a4,f4',
            'start_date'  => 'nullable|date',
            'end_date'    => 'nullable|date|after_or_equal:start_date',
            'status'      => 'nullable|in:' . self::ALLOWED_STATUSES,
            'template_id' => 'nullable|exists:templates,id',
            'partner_name' => 'nullable|string|max:255',
            'parent_contract_id' => 'nullable|exists:contracts,id',
            'content'     => 'nullable|string',
            'field_values' => 'nullable|array',
            'field_values.*.field_definition_id' => 'required|exists:fields_definitions,id',
            'field_values.*.value' => 'nullable|string',
            'signers' => 'nullable|array|max:2',
            'signers.*.type' => 'required|in:internal,external',
            'signers.*.name' => 'nullable|string',
            'signers.*.title' => 'nullable|string',
            'signers.*.email' => 'nullable|email',
        ];
    }

    public function after(): array
    {
        return [
            function (Validator $validator) {
                $signers = collect($this->input('signers', []))
                    ->filter(fn ($signer) => filled($signer['name'] ?? null))
                    ->values();

                if ($signers->isEmpty()) {
                    return;
                }

                if ($signers->count() > 2) {
                    $validator->errors()->add(
                        'signers',
                        'Kontrak hanya boleh memiliki maksimal 2 penandatangan.'
                    );
                    return;
                }

                $internalCount = $signers->where('type', 'internal')->count();
                $externalCount = $signers->where('type', 'external')->count();

                if ($externalCount > 1 || $internalCount === 0) {
                    $validator->errors()->add(
                        'signers',
                        'Kombinasi penandatangan hanya boleh 2 internal atau 1 internal dan 1 eksternal.'
                    );
                }
            },
        ];
    }

    public function messages(): array
    {
        return [
            'end_date.after_or_equal' => 'Tanggal selesai tidak boleh sebelum tanggal mulai.',
            'status.in' => 'Status kontrak tidak valid.',
            'signers.max' => 'Kontrak hanya boleh memiliki maksimal 2 penandatangan.',
        ];
    }
}
