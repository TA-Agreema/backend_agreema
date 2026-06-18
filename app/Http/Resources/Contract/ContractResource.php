<?php

namespace App\Http\Resources\Contract;

use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Http\Resources\Json\JsonResource;

class ContractResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $partnerParty = $this->resolvePartner();

        // Ambil signature type dari internal signer terakhir yang TTD
        $lastInternalSignature = $this->signers
            ->where('signer_type', 'internal')
            ->flatMap(fn($s) => $s->signatures ?? collect())
            ->sortByDesc('signed_at')
            ->first();

        return [
            'id' => $this->id,
            'contract_number' => $this->contract_number,
            'external_contract_number' => $this->external_contract_number,
            'title' => $this->title,
            'paper_size' => $this->paper_size ?? $this->template?->paper_size ?? 'f4',
            'partner' => $partnerParty,
            'category' => $this->template?->category?->name ?? ($this->category_id ? 'Kategori' : '-'),
            'category_id' => $this->category_id ?? $this->template?->category_id,
            'template_id' => $this->template_id,
            'partner_id' => $this->parties?->firstWhere('party_order', 2)?->party_id ?? null,
            'content' => $this->latestVersion?->content ?? $this->template?->content ?? '',
            'status' => $this->mapStatus($this->status),
            'start_date' => $this->start_date?->format('d-m-Y'),
            'end_date' => $this->end_date?->format('d-m-Y'),
            'created_by' => $this->creator?->name ?? '-',
            'sign_method' => $lastInternalSignature?->signature_type ?? null,
            'signed_document_url' => ($this->signed_document_path && $lastInternalSignature?->signature_type === 'upload')
                ? asset('storage/' . $this->signed_document_path)
                : null,
            'addendums' => $this->addendums
                ->map(function ($addendum) {
                    $title = $addendum->title
                        ?: ($addendum->description
                            ? Str::limit($addendum->description, 60)
                            : "Addendum {$addendum->addendum_number}");

                    $documentUrl = null;
                    if (!empty($addendum->document_path)) {
                        $documentUrl = Str::startsWith($addendum->document_path, ['http://', 'https://'])
                            ? $addendum->document_path
                            : asset('storage/' . ltrim($addendum->document_path, '/'));
                    }

                    return [
                        'id' => $addendum->id,
                        'addendum_number' => $addendum->addendum_number,
                        'title' => $title,
                        'description' => $addendum->description ?? '-',
                        'created_at' => $addendum->created_at?->format('d-m-Y'),
                        'effective_date' => $addendum->effective_date?->format('d-m-Y'),
                        'document_path' => $documentUrl,
                    ];
                })
                ->values(),
            'terminations' => $this->termination ? [
                [
                    'id' => $this->termination->id,
                    'contract_id' => $this->termination->contract_id,
                    'termination_number' => $this->termination->termination_number,
                    'title' => $this->termination->title ?: "Terminasi {$this->termination->termination_number}",
                    'termination_reason' => $this->termination->termination_reason,
                    'termination_note' => $this->termination->termination_note ?? '-',
                    'created_at' => $this->termination->created_at?->format('d-m-Y'),
                    'effective_date' => $this->termination->effective_date?->format('d-m-Y'),
                    'termination_document_path' => $this->termination->termination_document_path
                        ? (Str::startsWith($this->termination->termination_document_path, ['http://', 'https://'])
                            ? $this->termination->termination_document_path
                            : asset('storage/' . ltrim($this->termination->termination_document_path, '/')))
                        : null,
                    'document_path' => $this->termination->termination_document_path
                        ? (Str::startsWith($this->termination->termination_document_path, ['http://', 'https://'])
                            ? $this->termination->termination_document_path
                            : asset('storage/' . ltrim($this->termination->termination_document_path, '/')))
                        : null,
                ]
            ] : [],
            'field_values' => $this->latestVersion?->fieldValues
                ->map(function ($fv) {
                    return [
                        'id' => $fv->id,
                        'field_definition_id' => $fv->field_definition_id,
                        'field_label' => $fv->fieldDefinition?->field_label,
                        'field_key' => $fv->fieldDefinition?->field_key,
                        'value' => $fv->value,
                    ];
                }) ?? [],
            'signers' => $this->whenLoaded('signers', function () {
                return $this->signers->map(function ($s) {
                    return [
                        'id' => $s->id,
                        'user_id' => $s->user_id,
                        'signer_type' => $s->signer_type,
                        'signer_name' => $s->signer_name,
                        'signer_role' => $s->signer_role,
                        'external_email' => $s->external_email,
                        'user' => $s->user ? [
                            'name' => $s->user->name,
                            'job_title' => $s->user->job_title,
                        ] : null,
                        'signatures' => $s->relationLoaded('signatures')
                            ? $s->signatures->map(function ($sig) {
                                return [
                                    'id'             => $sig->id,
                                    'signature_type' => $sig->signature_type,
                                    'signature_path' => $sig->signature_path
                                        ? asset('storage/' . $sig->signature_path)
                                        : null,
                                    'signed_at'      => $sig->signed_at,
                                    'iteration'      => $sig->iteration,
                                ];
                            })
                            : [],
                        'reviews' => $s->relationLoaded('reviews') ? $s->reviews->map(function ($r) {
                            return [
                                'id' => $r->id,
                                'status' => $r->status,
                                'notes' => $r->notes,
                                'reviewed_at' => $r->reviewed_at?->format('d M Y, H:i'),
                            ];
                        })->values() : [],
                    ];
                });
            }),

            'status_logs' => $this->statusLogs->map(fn($log) => [
                'id'         => $log->id,
                'old_status' => $log->old_status,
                'new_status' => $log->new_status,
                'changed_by' => $log->changedBy?->name ?? 'System',
                'created_at' => $log->created_at?->format('d M Y, H:i'),
            ]),

            'versions' => $this->whenLoaded('versions', function () {
                return $this->versions->map(function ($v) {
                    return [
                        'id' => $v->id,
                        'version_number' => $v->version_number,
                        'content' => $v->content,
                        'created_at' => $v->created_at?->format('d-m-Y H:i:s'),
                        'created_by' => $v->creator?->name ?? 'System',
                    ];
                });
            }),

        ];
    }

    private function resolvePartner(): string
    {
        $parties = $this->parties?->sortBy('party_order');
        $partner = $parties?->firstWhere('party_order', 2) ?? $parties?->first();

        return $partner?->party?->display_name ?? '-';
    }

    private function mapStatus(string $status): string
    {
        return match ($status) {
            'amended' => 'revision',
            default => $status,
        };
    }
}
