<?php

namespace App\Http\Resources\Field;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class FieldDefinitionResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id'          => $this->id,
            'field_key'   => $this->field_key,
            'field_label' => $this->field_label,
            'field_type'  => $this->field_type,
            'is_required' => $this->is_required,
            'is_active'   => $this->is_active,
            'created_at'  => $this->created_at?->format('d-m-Y H:i'),
            'updated_at'  => $this->updated_at?->format('d-m-Y H:i'),
        ];
    }
}
