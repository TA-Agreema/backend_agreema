<?php

namespace App\Http\Requests\Contract;

use Illuminate\Foundation\Http\FormRequest;

class UpdateContractRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $id = $this->route('id');

        return [
            'contract_number' => "sometimes|required|string|unique:contracts,contract_number,{$id}",
            'external_contract_number' => "nullable|string|unique:contracts,external_contract_number,{$id}",
            'title' => 'sometimes|required|string',
            'start_date' => 'nullable|date',
            'end_date' => 'nullable|date',
            'status' => 'nullable|string',
            'template_id' => 'nullable|exists:templates,id',
            'parent_contract_id' => 'nullable|exists:contracts,id',
            'content' => 'nullable|string',
        ];
    }
}
