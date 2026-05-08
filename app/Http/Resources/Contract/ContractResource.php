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

        return [
            'id' => $this->id,
            'contract_number' => $this->contract_number,
            'external_contract_number' => $this->external_contract_number,
            'title' => $this->title,
            'partner' => $partnerParty,
            'category' => $this->template?->category?->name ?? ($this->category_id ? 'Kategori' : '-'),
            'category_id' => $this->category_id ?? $this->template?->category_id,
            'template_id' => $this->template_id,
            'content' => $this->latestVersion?->content ?? $this->template?->content ?? '',
            'status' => $this->mapStatus($this->status),
            'start_date' => $this->start_date?->format('d-m-Y'),
            'end_date' => $this->end_date?->format('d-m-Y'),
            'created_by' => $this->creator?->name ?? '-',
            'addendums' => $this->addendums
                ->map(function ($addendum) {
                    $title = $addendum->description
                        ? Str::limit($addendum->description, 60)
                        : "Addendum {$addendum->addendum_number}";

                    return [
                        'id' => $addendum->id,
                        'addendum_number' => $addendum->addendum_number,
                        'title' => $title,
                        'description' => $addendum->description ?? '-',
                        'created_at' => $addendum->created_at?->format('d-m-Y'),
                        'effective_date' => $addendum->effective_date?->format('d-m-Y'),
                    ];
                })
                ->values(),
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
