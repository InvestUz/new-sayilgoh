<?php

namespace App\Http\Controllers;

use App\Services\ExcelParserService;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\View\View;

/**
 * Dashboard Controller - Single Responsibility: Handle dashboard requests
 */
class DashboardController extends Controller
{
    private ExcelParserService $excelParser;

    public function __construct(ExcelParserService $excelParser)
    {
        $this->excelParser = $excelParser;
    }

    /**
     * Display the main dashboard view
     */
    public function index(): View
    {
        return view('dashboard');
    }

    /**
     * Get all dashboard data (KPIs, summaries, charts)
     */
    public function getData(Request $request): JsonResponse
    {
        try {
            $data = $this->excelParser->getDashboardData();

            return response()->json([
                'success' => true,
                'data' => $data,
                'timestamp' => now()->toIso8601String()
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get rent penalty data with optional filtering
     */
    public function getRentPenalty(Request $request): JsonResponse
    {
        try {
            $data = $this->excelParser->parseRentPenalty();
            $filtered = $this->applyFilters($data['data'] ?? [], $request);

            return response()->json([
                'success' => true,
                'data' => $filtered,
                'total' => count($filtered),
                'headers' => $data['headers'] ?? []
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get market penalty data with optional filtering
     */
    public function getMarketPenalty(Request $request): JsonResponse
    {
        try {
            $data = $this->excelParser->parseMarketPenalty();
            $filtered = $this->applyFilters($data['data'] ?? [], $request);

            return response()->json([
                'success' => true,
                'data' => $filtered,
                'total' => count($filtered),
                'headers' => $data['headers'] ?? []
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get tenants list data
     */
    public function getTenants(Request $request): JsonResponse
    {
        try {
            $data = $this->excelParser->parseTenantsList();
            $filtered = $this->applyFilters($data['data'] ?? [], $request);

            return response()->json([
                'success' => true,
                'data' => $filtered,
                'total' => count($filtered),
                'headers' => $data['headers'] ?? []
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get invoice analysis data
     */
    public function getInvoices(Request $request): JsonResponse
    {
        try {
            $data = $this->excelParser->parseInvoiceAnalysis();
            $filtered = $this->applyFilters($data['data'] ?? [], $request);

            return response()->json([
                'success' => true,
                'data' => $filtered,
                'total' => count($filtered),
                'headers' => $data['headers'] ?? []
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get summary/KPI data only
     */
    public function getSummary(): JsonResponse
    {
        try {
            $dashboardData = $this->excelParser->getDashboardData();

            return response()->json([
                'success' => true,
                'summary' => $dashboardData['summary']
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get chart data
     */
    public function getChartData(): JsonResponse
    {
        try {
            $dashboardData = $this->excelParser->getDashboardData();

            return response()->json([
                'success' => true,
                'charts' => $dashboardData['charts']
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Export data to Excel format
     */
    public function exportData(Request $request): JsonResponse
    {
        $type = $request->get('type', 'all');

        try {
            $data = match($type) {
                'rent_penalty' => $this->excelParser->parseRentPenalty(),
                'market_penalty' => $this->excelParser->parseMarketPenalty(),
                'tenants' => $this->excelParser->parseTenantsList(),
                'invoices' => $this->excelParser->parseInvoiceAnalysis(),
                default => $this->excelParser->getDashboardData()
            };

            return response()->json([
                'success' => true,
                'data' => $data,
                'export_type' => $type
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get available files info
     */
    public function getFiles(): JsonResponse
    {
        try {
            $files = $this->excelParser->getAvailableFiles();

            return response()->json([
                'success' => true,
                'files' => $files
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Apply search and filter to data array
     */
    private function applyFilters(array $data, Request $request): array
    {
        $search = $request->get('search');
        $sortBy = $request->get('sort_by');
        $sortOrder = $request->get('sort_order', 'asc');

        // Apply search filter
        if ($search) {
            $data = array_filter($data, function ($row) use ($search) {
                foreach ($row as $value) {
                    if (is_string($value) && mb_stripos($value, $search) !== false) {
                        return true;
                    }
                    if (is_numeric($value) && strpos((string)$value, $search) !== false) {
                        return true;
                    }
                }
                return false;
            });
        }

        // Apply sorting
        if ($sortBy && !empty($data)) {
            usort($data, function ($a, $b) use ($sortBy, $sortOrder) {
                $aVal = $a[$sortBy] ?? '';
                $bVal = $b[$sortBy] ?? '';

                if (is_numeric($aVal) && is_numeric($bVal)) {
                    $result = $aVal <=> $bVal;
                } else {
                    $result = strcasecmp((string)$aVal, (string)$bVal);
                }

                return $sortOrder === 'desc' ? -$result : $result;
            });
        }

        return array_values($data);
    }
}
