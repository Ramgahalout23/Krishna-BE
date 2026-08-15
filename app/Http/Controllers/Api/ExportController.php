<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Jobs\GenerateExportJob;
use App\Models\ExportJob;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class ExportController extends Controller
{
    // ── Async Export Jobs ──

    /**
     * Dispatch an async CSV export job.
     * POST /api/v1/admin/exports
     *
     * Body: {
     *   type: "products" | "orders" | "users",
     *   filters?: { search?: string, status?: string, ... },
     *   columns?: string[]
     * }
     */
    public function dispatchExport(Request $request): JsonResponse
    {
        try {
            $validated = $request->validate([
                'type' => 'required|string|in:products,orders,users',
                'filters' => 'nullable|array',
                'columns' => 'nullable|array',
                'columns.*' => 'string',
            ]);

            $exportJob = ExportJob::create([
                'user_id' => $request->user()->id,
                'type' => $validated['type'],
                'status' => 'pending',
                'filters' => $validated['filters'] ?? [],
                'columns' => $validated['columns'] ?? [],
            ]);

            GenerateExportJob::dispatch($exportJob->id);

            return response()->json([
                'success' => true,
                'data' => [
                    'id' => $exportJob->id,
                    'type' => $exportJob->type,
                    'status' => $exportJob->status,
                    'created_at' => $exportJob->created_at,
                ],
            ]);
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 500);
        }
    }

    /**
     * Check the status of an export job.
     * GET /api/v1/admin/exports/{id}
     */
    public function exportStatus(string $id): JsonResponse
    {
        try {
            $exportJob = ExportJob::findOrFail($id);

            return response()->json([
                'success' => true,
                'data' => [
                    'id' => $exportJob->id,
                    'type' => $exportJob->type,
                    'status' => $exportJob->status,
                    'file_name' => $exportJob->file_name,
                    'error_message' => $exportJob->error_message,
                    'created_at' => $exportJob->created_at,
                    'completed_at' => $exportJob->completed_at,
                ],
            ]);
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'message' => 'Export job not found'], 404);
        }
    }

    /**
     * Download a completed export file.
     * GET /api/v1/admin/exports/{id}/download
     */
    public function downloadExport(string $id)
    {
        try {
            $exportJob = ExportJob::findOrFail($id);

            if ($exportJob->status !== 'completed' || !$exportJob->file_path) {
                return response()->json(['success' => false, 'message' => 'Export not ready yet'], 400);
            }

            if (!Storage::disk('local')->exists($exportJob->file_path)) {
                return response()->json(['success' => false, 'message' => 'Export file not found'], 404);
            }

            return Storage::disk('local')->download(
                $exportJob->file_path,
                $exportJob->file_name ?? 'export.csv'
            );
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 404);
        }
    }

    /**
     * List recent export jobs for the authenticated user.
     * GET /api/v1/admin/exports
     */
    public function listExports(Request $request): JsonResponse
    {
        $exports = ExportJob::byUser($request->user()->id)
            ->latest()
            ->take(20)
            ->get(['id', 'type', 'status', 'file_name', 'error_message', 'created_at', 'completed_at']);

        return response()->json([
            'success' => true,
            'data' => $exports,
        ]);
    }

    // ── Export Orders ──

    /**
     * Export orders as CSV download.
     * GET /api/v1/admin/orders/export
     * Accepts optional `columns` query param: comma-separated column keys to include.
     */
    public function exportOrders(Request $request): \Illuminate\Http\Response
    {
        try {
            $exportJob = ExportJob::create([
                'user_id' => $request->user()->id,
                'type' => 'orders',
                'status' => 'pending',
                'filters' => $request->only(['status', 'start_date', 'end_date', 'search']),
                'columns' => $request->has('columns') ? array_map('trim', explode(',', $request->input('columns'))) : [],
            ]);
            GenerateExportJob::dispatch($exportJob->id);

            return response()->json([
                'success' => true,
                'message' => 'Export queued for processing',
                'data' => [
                    'id' => $exportJob->id,
                    'type' => $exportJob->type,
                    'status' => 'queued',
                ],
            ]);
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 500);
        }
    }

    // ── Export Products ──

    /**
     * Export products as CSV download.
     * GET /api/v1/admin/products/export
     * Accepts optional `columns` query param: comma-separated column keys to include.
     */
    public function exportProducts(Request $request): \Illuminate\Http\Response
    {
        try {
            $exportJob = ExportJob::create([
                'user_id' => $request->user()->id,
                'type' => 'products',
                'status' => 'pending',
                'filters' => $request->only(['search', 'status', 'category_id']),
                'columns' => $request->has('columns') ? array_map('trim', explode(',', $request->input('columns'))) : [],
            ]);
            GenerateExportJob::dispatch($exportJob->id);

            return response()->json([
                'success' => true,
                'message' => 'Export queued for processing',
                'data' => [
                    'id' => $exportJob->id,
                    'type' => $exportJob->type,
                    'status' => 'queued',
                ],
            ]);
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 500);
        }
    }

    /**
     * Convert array data to CSV string with UTF-8 BOM for Excel compatibility.
     */
    private function arrayToCsv(array $headers, array $rows): string
    {
        // UTF-8 BOM (\xEF\xBB\xBF) ensures Excel on Windows renders ₹ and other special characters correctly
        $csv = "\xEF\xBB\xBF";
        $csv .= implode(',', array_map(fn($h) => '"' . str_replace('"', '""', $h) . '"', $headers)) . "\n";
        foreach ($rows as $row) {
            $csv .= implode(',', array_map(fn($v) => '"' . str_replace('"', '""', $v ?? '') . '"', $row)) . "\n";
        }
        return $csv;
    }

    /**
     * Filter a CSV string by keeping only the requested columns.
     * Used for endpoints where the CSV is generated by a service method (e.g., generateUsersCsv).
     *
     * @param string $csv     The full CSV text
     * @param string $columns Comma-separated column keys to keep (e.g., "email,role,firstName")
     */
    private function filterCsvColumns(string $csv, string $columns): string
    {
        $lines = explode("\n", $csv);
        if (count($lines) < 2) {
            return $csv;
        }

        // Parse the header line (skip UTF-8 BOM prefix if present)
        $headerLine = $lines[0];
        $bom = "\xEF\xBB\xBF";
        if (str_starts_with($headerLine, $bom)) {
            $headerLine = substr($headerLine, strlen($bom));
        }

        // Simple CSV line parsing (headers are simple, no embedded commas expected)
        $allHeaders = str_getcsv($headerLine);
        if (empty($allHeaders)) {
            return $csv;
        }

        $columnKeys = array_map('trim', explode(',', $columns));
        $desiredIndices = [];
        $filteredHeaders = [];

        foreach ($columnKeys as $key) {
            // Try to find the header by fuzzy matching the key
            $lowerKey = strtolower(preg_replace('/([a-z])([A-Z])/', '$1 $2', $key));
            foreach ($allHeaders as $idx => $header) {
                $lowerHeader = strtolower(trim($header));
                if ($lowerHeader === $lowerKey || str_contains($lowerHeader, $lowerKey)) {
                    $desiredIndices[] = $idx;
                    $filteredHeaders[] = $header;
                    break;
                }
            }
        }

        if (empty($desiredIndices)) {
            return $csv;
        }

        $result = $bom . implode(',', array_map(fn($h) => '"' . str_replace('"', '""', $h) . '"', $filteredHeaders)) . "\n";
        for ($i = 1; $i < count($lines); $i++) {
            $line = trim($lines[$i]);
            if (empty($line)) continue;
            $cells = str_getcsv($line);
            $filteredCells = array_map(fn($idx) => $cells[$idx] ?? '', $desiredIndices);
            $result .= implode(',', array_map(fn($v) => '"' . str_replace('"', '""', $v) . '"', $filteredCells)) . "\n";
        }

        return $result;
    }
}
