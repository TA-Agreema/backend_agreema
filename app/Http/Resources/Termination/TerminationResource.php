<?php

namespace App\Http\Resources\Termination;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class TerminationResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id'                        => $this->id,
            'contract_id'               => $this->contract_id,
            'termination_number'        => $this->termination_number,
            'title'                     => $this->title,
            'termination_reason'        => $this->termination_reason,
            'termination_note'          => $this->termination_note,
            'termination_document_path' => $this->termination_document_path ? asset('storage/' . $this->termination_document_path) : null,
            'effective_date'            => $this->effective_date,
            'created_at'                => $this->created_at,
            'updated_at'                => $this->updated_at,
        ];
    }
}
