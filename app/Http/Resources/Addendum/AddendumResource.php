<?php

namespace App\Http\Resources\Addendum;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class AddendumResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id'              => $this->id,
            'contract_id'     => $this->contract_id,
            'addendum_number' => $this->addendum_number,
            'title'           => $this->title,
            'description'     => $this->description,
            'document_path'   => $this->document_path ? asset('storage/' . $this->document_path) : null,
            'effective_date'  => $this->effective_date,
            'created_at'      => $this->created_at,
            'updated_at'      => $this->updated_at,
        ];
    }
}
