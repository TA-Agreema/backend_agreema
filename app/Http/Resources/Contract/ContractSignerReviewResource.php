<?php

namespace App\Http\Resources\Contract;

use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\Storage;

class ContractSignerReviewResource extends JsonResource
{
    public function toArray($request): array
    {
        return [
            'id'                   => $this->id,
            'contract_signer_id'   => $this->contract_signer_id,
            'contract_version_id'  => $this->contract_version_id,
            'iteration'            => $this->iteration,
            'status'               => $this->status,
            'notes'                => $this->notes,
            'reviewed_at'          => $this->reviewed_at?->toDateTimeString(),
            'created_at'           => $this->created_at?->toDateTimeString(),

            // URL publik dokumen revisi (jika ada)
            'review_document_url'  => $this->review_document_path
                ? Storage::disk('public')->url($this->review_document_path)
                : null,
        ];
    }
}
