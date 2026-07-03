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

        // Ambil signature type dari internal signer terakhir yang TTD
        $lastInternalSignature = $this->signers
            ->where('signer_type', 'internal')
            ->flatMap(fn($s) => $s->signatures ?? collect())
            ->sortByDesc('signed_at')
            ->first();

        return [
            'id' => $this->id,
            'contract_number' => $this->contract_number,
            'contract_type' => $this->contract_type,
            'external_contract_number' => $this->external_contract_number,
            'title' => $this->title,
            'paper_size' => $this->paper_size ?? $this->template?->paper_size ?? 'f4',
            'partner' => $this->partner_name ?: '-',
            'category' => $this->resolveCategory(),
            'category_id' => $this->template?->category_id,
            'template_id' => $this->template_id,
            'partner_id' => null,
            'parent_contract' => $this->whenLoaded('parentContract', function () {
                return $this->parentContract ? [
                    'id' => $this->parentContract->id,
                    'contract_number' => $this->parentContract->contract_number,
                    'title' => $this->parentContract->title,
                ] : null;
            }),
            'renewal_count' => $this->child_contracts_count ?? 0,
            'has_open_renewal' => ($this->open_renewal_count ?? 0) > 0,
            'content' => $this->latestVersion?->content ?? $this->template?->content ?? '',
            'status' => $this->mapStatus($this->status),
            'start_date' => $this->start_date?->format('d-m-Y'),
            'end_date' => $this->end_date?->format('d-m-Y'),
            'created_by' => $this->creator?->name ?? '-',
            'uploaded_by' => $this->contract_type === 'external' ? ($this->creator?->name ?? '-') : null,
            'sign_method' => $lastInternalSignature?->signature_type ?? null,
            'signed_document_url' => $this->resolveSignedDocumentUrl($lastInternalSignature),
            'has_expired_token' => $this->signers
                ->where('signer_type', 'external')
                ->flatMap(fn($s) => $s->signatureTokens?? collect())
                ->filter(fn($t) => $t->expired_at && $t->expired_at->isPast() && !$t->used_at)
                ->isNotEmpty(),
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
                                'review_document_url' => $r->review_document_path
                                    ? asset('storage/' . ltrim($r->review_document_path, '/'))
                                    : null,
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

    private function resolveSignedDocumentUrl($lastInternalSignature): ?string
    {
        if (!$this->signed_document_path) {
            return null;
        }

        // Kontrak mitra: dokumen yang diupload HRD selalu dianggap dokumen final
        if ($this->contract_type === 'external') {
            return asset('storage/' . $this->signed_document_path);
        }

        // Kontrak internal: hanya jika signer terakhir upload manual
        if ($lastInternalSignature?->signature_type === 'upload') {
            return asset('storage/' . $this->signed_document_path);
        }

        return null;
    }

    private function resolveCategory(): string
    {
        if ($this->template?->category?->name) {
            return $this->template->category->name;
        }

        if ($this->contract_type === 'external') {
            return 'Kontrak Mitra';
        }

        return '-';
    }

    private function mapStatus(string $status): string
    {
        return match ($status) {
            'amended' => 'revision',
            default => $status,
        };
    }
}
