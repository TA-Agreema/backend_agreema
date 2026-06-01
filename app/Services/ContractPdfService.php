<?php

namespace App\Services;

use App\Models\Contract;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Facades\Storage;

class ContractPdfService
{
    /**
     * Generate PDF kontrak beserta semua tanda tangan digital,
     * simpan ke storage/app/public/contracts/pdf/, kembalikan path relatif.
     */
    public function generateSignedPdf(Contract $contract): string
    {
        $contract->loadMissing([
            'latestVersion',
            'signers.signatures',
            'signers.user',
            'creator',
        ]);

        $content = $contract->latestVersion?->content
            ?? '<p>Konten kontrak tidak tersedia.</p>';

        // Siapkan gambar TTD per signer (base64)
        $signatureImages = []; // [signer_id => 'data:image/png;base64,...']
        $signatureDates  = []; // [signer_id => 'D MMMM YYYY, HH:mm']

        foreach ($contract->signers as $signer) {
            $lastSig = $signer->signatures->sortByDesc('signed_at')->first();
            if (!$lastSig || !$lastSig->signature_path) continue;

            $fullPath = Storage::disk('public')->path($lastSig->signature_path);
            if (!file_exists($fullPath)) continue;

            $mime = mime_content_type($fullPath);
            $b64  = base64_encode(file_get_contents($fullPath));

            $signatureImages[$signer->id] = "data:{$mime};base64,{$b64}";
            $signatureDates[$signer->id]  = $lastSig->signed_at
                ? $lastSig->signed_at->locale('id')->isoFormat('D MMMM YYYY, HH:mm')
                : null;
        }

        // Generate PDF via Blade template yang sudah ada
        $pdf = Pdf::loadView('pdf.contract', [
            'contract'        => $contract,
            'content'         => $content,
            'signatureImages' => $signatureImages,
            'signatureDates'  => $signatureDates,
        ])
        ->setPaper('a4', 'portrait')
        ->setOption('isRemoteEnabled', false)
        ->setOption('defaultFont', 'DejaVu Sans');

        // Simpan ke storage
        $safeNumber = preg_replace('/[\/\\\\ ]/', '_', $contract->contract_number ?? 'contract-' . $contract->id);
        $filename   = "contracts/pdf/{$safeNumber}.pdf";

        Storage::disk('public')->put($filename, $pdf->output());

        return $filename;
    }
}
