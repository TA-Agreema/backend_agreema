<?php

namespace App\Http\Resources\Contract;

use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\Storage;

class ContractSignerResource extends JsonResource
{
    public function toArray($request): array
    {
        $latestSignature = $this->relationLoaded('signatures')
            ? $this->signatures->filter(fn($s) => ($s->iteration ?? 1) > 0)->sortByDesc('iteration')->first()
            : null;

        return [
            'id'             => $this->id,
            'user_id'        => $this->user_id,
            'signer_type'    => $this->signer_type,
            'signer_name'    => $this->signer_name,
            'signer_role'    => $this->signer_role,
            'external_email' => $this->external_email,
            'sequence'       => $this->sequence,

            'user' => $this->whenLoaded('user', fn() => [
                'id'        => $this->user->id,
                'name'      => $this->user->name,
                'email'     => $this->user->email,
                'job_title' => $this->user->job_title,
            ]),

            // All signatures (for frontend to show each signer's TTD)
            'signatures' => $this->whenLoaded('signatures', fn() =>
                $this->signatures->map(fn($sig) => [
                    'id'             => $sig->id,
                    'iteration'      => $sig->iteration,
                    'signature_type' => $sig->signature_type,
                    'signature_path' => $sig->signature_path
                        ? Storage::disk('public')->url($sig->signature_path)
                        : null,
                    'signed_at'      => $sig->signed_at?->toDateTimeString(),
                ])->values()
            ),

            'signature_image' => $latestSignature?->signature_path
                ? Storage::disk('public')->url($latestSignature->signature_path)
                : null,

            'signed_at' => $latestSignature?->signed_at
                ? $latestSignature->signed_at->format('d/m/Y')
                : null,

            'reviews' => $this->whenLoaded('reviews',
                fn() => ContractSignerReviewResource::collection($this->reviews)
            ),
        ];
    }
}
