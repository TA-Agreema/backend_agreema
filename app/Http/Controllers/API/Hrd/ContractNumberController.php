<?php

namespace App\Http\Controllers\API\Hrd;

use App\Http\Controllers\Controller;
use App\Models\Contract;
use App\Models\ContractCategory;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ContractNumberController extends Controller
{
    public function generateNumber(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'category_id' => 'nullable|integer|exists:contract_categories,id',
        ]);

        return response()->json([
            'contract_number' => $this->generateContractNumber(
                isset($validated['category_id'])
                    ? (int) $validated['category_id']
                    : null,
            ),
        ]);
    }

    public function generateContractNumber(?int $categoryId = null): string
    {
        $prefix = $this->resolveContractNumberPrefix($categoryId);
        $year = now()->year;
        $month = now()->month;
        $romanMonth = $this->getRomanMonth($month);
        $sequence = $this->getNextContractSequence($prefix, $year, $month);
        $candidate = $this->buildContractNumber($prefix, $sequence, $romanMonth, $year);

        while (Contract::where('contract_number', $candidate)->exists()) {
            $sequence++;
            $candidate = $this->buildContractNumber($prefix, $sequence, $romanMonth, $year);
        }

        return $candidate;
    }

    private function resolveContractNumberPrefix(?int $categoryId): string
    {
        if (!$categoryId) {
            return 'SPK';
        }

        $category = ContractCategory::find($categoryId);
        if (!$category) {
            return 'SPK';
        }

        if (!empty($category->number_prefix)) {
            return strtoupper(trim($category->number_prefix));
        }

        return $this->buildPrefixFromCategoryName($category->name);
    }

    private function buildPrefixFromCategoryName(?string $categoryName): string
    {
        if (empty($categoryName)) {
            return 'SPK';
        }

        $words = preg_split('/[^\p{L}\p{N}]+/u', trim($categoryName)) ?: [];
        $letters = [];

        foreach ($words as $word) {
            $word = trim($word);
            if ($word === '') {
                continue;
            }

            $letters[] = mb_substr($word, 0, 1, 'UTF-8');
        }

        return count($letters) > 0 ? strtoupper(implode('', $letters)) : 'SPK';
    }

    private function getRomanMonth(int $month): string
    {
        $romanMonths = [
            1 => 'I',
            2 => 'II',
            3 => 'III',
            4 => 'IV',
            5 => 'V',
            6 => 'VI',
            7 => 'VII',
            8 => 'VIII',
            9 => 'IX',
            10 => 'X',
            11 => 'XI',
            12 => 'XII',
        ];

        return $romanMonths[$month] ?? 'I';
    }

    private function getNextContractSequence(string $prefix, int $year, int $month): int
    {
        $count = Contract::whereYear('created_at', $year)
            ->whereMonth('created_at', $month)
            ->where('contract_number', 'like', "{$prefix}-%")
            ->count();

        return $count + 1;
    }

    private function buildContractNumber(string $prefix, int $sequence, string $romanMonth, int $year): string
    {
        $sequenceNumber = str_pad($sequence, 3, '0', STR_PAD_LEFT);

        return "{$prefix}-{$sequenceNumber}/SLAB/{$romanMonth}/{$year}";
    }
}
