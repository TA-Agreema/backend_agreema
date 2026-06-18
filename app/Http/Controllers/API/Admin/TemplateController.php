<?php

namespace App\Http\Controllers\API\Admin;

use Exception;
use App\Models\Template;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Auth;
use App\Http\Controllers\Controller;
use Barryvdh\DomPDF\Facade\Pdf;
use App\Http\Requests\Template\StoreTemplateRequest;
use App\Http\Requests\Template\UpdateTemplateRequest;

class TemplateController extends Controller
{
    public function index(): JsonResponse
    {
        try {
            $templates = Template::query()
                ->with(['category:id,name', 'creator:id,name'])
                ->orderByDesc('created_at')
                ->get()
                ->map(fn(Template $t) => $this->formatTemplate($t));

            return response()->json([
                'message' => 'Templates retrieved successfully',
                'data'    => $templates,
            ]);
        } catch (Exception $e) {
            Log::error('Error retrieving templates', ['error' => $e->getMessage()]);

            return response()->json([
                'message' => 'An error occurred while retrieving templates',
                'error'   => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * POST /api/templates
     * 
     */
    public function store(StoreTemplateRequest $request): JsonResponse
    {
        try {
            $template = Template::create([
                'name'        => $request->name,
                'content'     => $request->content,
                'paper_size'   => $request->input('paper_size') ?? 'a4',
                'category_id' => $request->category_id,
                'created_by'  => Auth::id(),
                'is_active'   => $request->boolean('is_active', true),
            ]);

            $template->load(['category:id,name', 'creator:id,name']);

            return response()->json([
                'message' => 'Template created successfully',
                'data'    => $this->formatTemplate($template),
            ], 201);
        } catch (Exception $e) {
            Log::error('Error creating template', [
                'error' => $e->getMessage(),
                'input' => $request->all(),
            ]);

            return response()->json([
                'message' => 'An error occurred while creating template',
                'error'   => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * GET /api/templates/{id}
     * Dapat diakses oleh admin dan hrd.
     */
    public function show(int $id): JsonResponse
    {
        try {
            $template = Template::with(['category:id,name', 'creator:id,name'])
                ->findOrFail($id);

            return response()->json([
                'message' => 'Template retrieved successfully',
                'data'    => $this->formatTemplate($template),
            ]);
        } catch (Exception $e) {
            Log::error('Error retrieving template', [
                'template_id' => $id,
                'error'       => $e->getMessage(),
            ]);

            return response()->json([
                'message' => 'Template not found',
                'error'   => $e->getMessage(),
            ], 404);
        }
    }

    /**
     * PATCH /api/templates/{id}
     * Hanya admin.
     */
    public function update(UpdateTemplateRequest $request, int $id): JsonResponse
    {
        try {
            $template = Template::findOrFail($id);

            $template->update($request->only([
                'name',
                'content',
                'paper_size',
                'category_id',
                'is_active',
            ]));

            $template->load(['category:id,name', 'creator:id,name']);

            return response()->json([
                'message' => 'Template updated successfully',
                'data'    => $this->formatTemplate($template),
            ]);
        } catch (Exception $e) {
            Log::error('Error updating template', [
                'template_id' => $id,
                'error'       => $e->getMessage(),
                'input'       => $request->all(),
            ]);

            return response()->json([
                'message' => 'An error occurred while updating template',
                'error'   => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * DELETE /api/templates/{id}
     * Hanya admin.
     */
    public function destroy(int $id): JsonResponse
    {
        try {
            $template = Template::findOrFail($id);

            if ($template->contracts()->exists()) {
                return response()->json([
                    'message' => 'Template cannot be deleted because it is used by existing contracts',
                ], 422);
            }

            $template->delete();

            return response()->json([
                'message' => 'Template deleted successfully',
            ]);
        } catch (Exception $e) {
            Log::error('Error deleting template', [
                'template_id' => $id,
                'error'       => $e->getMessage(),
            ]);

            return response()->json([
                'message' => 'An error occurred while deleting template',
                'error'   => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * PATCH /api/templates/{id}/toggle-status
     * Hanya admin.
     */
    public function toggleStatus(int $id): JsonResponse
    {
        try {
            $template = Template::findOrFail($id);

            $template->update(['is_active' => !$template->is_active]);

            $template->load(['category:id,name', 'creator:id,name']);

            return response()->json([
                'message' => 'Template status updated successfully',
                'data'    => $this->formatTemplate($template),
            ]);
        } catch (Exception $e) {
            Log::error('Error toggling template status', [
                'template_id' => $id,
                'error'       => $e->getMessage(),
            ]);

            return response()->json([
                'message' => 'An error occurred while updating template status',
                'error'   => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * GET /api/templates/{id}/download
     * Generate dan download template sebagai PDF.
     */
    public function download(int $id)
    {
        try {
            $template = Template::with(['category:id,name', 'creator:id,name'])->findOrFail($id);
            $content = $template->content ?? '<p>Konten template tidak tersedia.</p>';

            $html = view('pdf.template', [
                'template' => $template,
                'content'  => $content,
            ])->render();

            $pdf = Pdf::loadHTML($html)
                ->setPaper(($template->paper_size ?? 'f4') === 'f4' ? [0, 0, 609.45, 935.43] : 'a4', 'portrait')
                ->setOptions([
                    'defaultFont' => 'sans-serif',
                    'isRemoteEnabled' => false,
                    'isHtml5ParserEnabled' => true,
                ]);

            $filename = ($template->name ?: 'template-' . $id) . '.pdf';
            $filename = str_replace(['/','\\'], '-', $filename);

            return $pdf->download($filename);
        } catch (Exception $e) {
            Log::error('Error downloading template PDF', [
                'template_id' => $id,
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'message' => 'Gagal mengunduh template.',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Format template data agar konsisten dengan kontrak frontend.
     * Status frontend: "Aktif" | "Nonaktif"
     */
    private function formatTemplate(Template $template): array
    {
        return [
            'id'         => $template->id,
            'name'       => $template->name,
            'content'    => $template->content,
            'paper_size' => $template->paper_size ?? 'f4',
            'is_active'  => $template->is_active,
            'status'     => $template->is_active ? 'Aktif' : 'Nonaktif',
            'category'   => $template->category?->name ?? '-',
            'category_id' => $template->category_id,
            'createdBy'  => $template->creator?->name ?? '-',
            'createdAt'  => $template->created_at?->format('d-m-Y'),
            'created_at' => $template->created_at,
            'updated_at' => $template->updated_at,
        ];
    }
}


