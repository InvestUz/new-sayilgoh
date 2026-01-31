<?php

namespace App\Services;

use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Spreadsheet;

/**
 * Excel Parser Service - Single Responsibility: Parse Excel files and extract data
 */
class ExcelParserService
{
    private string $datasetPath;

    public function __construct()
    {
        $this->datasetPath = public_path('dataset');
    }

    /**
     * Get all available data files info
     */
    public function getAvailableFiles(): array
    {
        $files = [];
        $xlsxFiles = glob($this->datasetPath . '/*.xlsx');

        foreach ($xlsxFiles as $file) {
            $files[] = [
                'name' => basename($file),
                'path' => $file,
                'size' => filesize($file),
                'modified' => date('Y-m-d H:i:s', filemtime($file))
            ];
        }

        return $files;
    }

    /**
     * Parse Rent Penalty data (Ижара Пеня)
     */
    public function parseRentPenalty(): array
    {
        $filePath = $this->findFile('Ижара Пеня');
        if (!$filePath) {
            return ['error' => 'Rent penalty file not found', 'data' => []];
        }

        return $this->parseExcelFile($filePath, 'rent_penalty');
    }

    /**
     * Parse Market Penalty data (Бозорлар Пения)
     */
    public function parseMarketPenalty(): array
    {
        $filePath = $this->findFile('Бозорлар Пения');
        if (!$filePath) {
            return ['error' => 'Market penalty file not found', 'data' => []];
        }

        return $this->parseExcelFile($filePath, 'market_penalty');
    }

    /**
     * Parse Tenants List (Ижарачилар)
     */
    public function parseTenantsList(): array
    {
        $filePath = $this->findFile('ИЖАРАЧИЛАР');
        if (!$filePath) {
            return ['error' => 'Tenants list file not found', 'data' => []];
        }

        return $this->parseExcelFile($filePath, 'tenants');
    }

    /**
     * Parse Invoice Analysis (Анализ сч.ф)
     */
    public function parseInvoiceAnalysis(): array
    {
        $filePath = $this->findFile('Анализ сч.ф');
        if (!$filePath) {
            return ['error' => 'Invoice analysis file not found', 'data' => []];
        }

        return $this->parseExcelFile($filePath, 'invoices');
    }

    /**
     * Get aggregated dashboard data
     */
    public function getDashboardData(): array
    {
        $rentPenalty = $this->parseRentPenalty();
        $marketPenalty = $this->parseMarketPenalty();
        $tenants = $this->parseTenantsList();
        $invoices = $this->parseInvoiceAnalysis();

        return [
            'summary' => $this->calculateSummary($rentPenalty, $marketPenalty, $tenants, $invoices),
            'rent_penalty' => $rentPenalty,
            'market_penalty' => $marketPenalty,
            'tenants' => $tenants,
            'invoices' => $invoices,
            'charts' => $this->prepareChartData($rentPenalty, $marketPenalty)
        ];
    }

    /**
     * Find file by partial name match
     */
    private function findFile(string $partialName): ?string
    {
        $files = glob($this->datasetPath . '/*.xlsx');

        foreach ($files as $file) {
            if (mb_stripos(basename($file), $partialName) !== false) {
                return $file;
            }
        }

        return null;
    }

    /**
     * Parse Excel file and return structured data
     */
    private function parseExcelFile(string $filePath, string $type): array
    {
        try {
            $spreadsheet = IOFactory::load($filePath);
            $worksheet = $spreadsheet->getActiveSheet();
            $data = $worksheet->toArray(null, true, true, true);

            // Remove empty rows
            $data = array_filter($data, fn($row) => !empty(array_filter($row)));

            // Get headers from first row
            $headers = array_shift($data);
            $headers = $this->normalizeHeaders($headers);

            // Map data to headers
            $result = [];
            foreach ($data as $rowIndex => $row) {
                $mappedRow = [];
                foreach ($headers as $colKey => $header) {
                    if (!empty($header)) {
                        $value = $row[$colKey] ?? null;
                        $mappedRow[$header] = $this->cleanValue($value);
                    }
                }
                if (!empty(array_filter($mappedRow))) {
                    $mappedRow['_row_id'] = $rowIndex;
                    $result[] = $mappedRow;
                }
            }

            return [
                'success' => true,
                'file' => basename($filePath),
                'type' => $type,
                'count' => count($result),
                'headers' => array_values(array_filter($headers)),
                'data' => $result
            ];

        } catch (\Exception $e) {
            return [
                'success' => false,
                'error' => $e->getMessage(),
                'file' => basename($filePath),
                'type' => $type,
                'data' => []
            ];
        }
    }

    /**
     * Normalize header names (remove special chars, create consistent keys)
     */
    private function normalizeHeaders(array $headers): array
    {
        $normalized = [];
        $counter = [];

        foreach ($headers as $key => $header) {
            if (empty($header)) {
                $normalized[$key] = null;
                continue;
            }

            $clean = trim($header);
            $slug = mb_strtolower($clean);
            $slug = preg_replace('/[^\w\s\p{Cyrillic}]/u', '', $slug);
            $slug = preg_replace('/\s+/', '_', $slug);

            // Handle duplicates
            if (isset($counter[$slug])) {
                $counter[$slug]++;
                $slug .= '_' . $counter[$slug];
            } else {
                $counter[$slug] = 0;
            }

            $normalized[$key] = $slug ?: 'column_' . $key;
        }

        return $normalized;
    }

    /**
     * Clean and format cell values
     */
    private function cleanValue($value)
    {
        if ($value === null || $value === '') {
            return null;
        }

        if (is_string($value)) {
            $value = trim($value);

            // Try to convert numeric strings
            if (is_numeric(str_replace([' ', ','], ['', '.'], $value))) {
                $numericValue = str_replace([' ', ','], ['', '.'], $value);
                return floatval($numericValue);
            }
        }

        return $value;
    }

    /**
     * Calculate summary statistics for dashboard KPIs
     */
    private function calculateSummary(array $rentPenalty, array $marketPenalty, array $tenants, array $invoices): array
    {
        $totalTenants = $tenants['count'] ?? 0;

        // Calculate totals from rent penalty data
        $totalRentDebt = 0;
        $totalRentPaid = 0;
        $totalRentPenalty = 0;

        if (!empty($rentPenalty['data'])) {
            foreach ($rentPenalty['data'] as $row) {
                $totalRentDebt += $this->extractNumeric($row, ['қарздорлик', 'долг', 'debt', 'qarzdorlik']);
                $totalRentPaid += $this->extractNumeric($row, ['тўланган', 'оплачено', 'paid', 'tolangan']);
                $totalRentPenalty += $this->extractNumeric($row, ['пеня', 'penya', 'penalty']);
            }
        }

        // Calculate totals from market penalty data
        $totalMarketDebt = 0;
        $totalMarketPaid = 0;
        $totalMarketPenalty = 0;

        if (!empty($marketPenalty['data'])) {
            foreach ($marketPenalty['data'] as $row) {
                $totalMarketDebt += $this->extractNumeric($row, ['қарздорлик', 'долг', 'debt', 'qarzdorlik']);
                $totalMarketPaid += $this->extractNumeric($row, ['тўланган', 'оплачено', 'paid', 'tolangan']);
                $totalMarketPenalty += $this->extractNumeric($row, ['пеня', 'penya', 'penalty']);
            }
        }

        return [
            'total_tenants' => $totalTenants,
            'total_debt' => $totalRentDebt + $totalMarketDebt,
            'total_paid' => $totalRentPaid + $totalMarketPaid,
            'total_penalty' => $totalRentPenalty + $totalMarketPenalty,
            'rent_summary' => [
                'debt' => $totalRentDebt,
                'paid' => $totalRentPaid,
                'penalty' => $totalRentPenalty
            ],
            'market_summary' => [
                'debt' => $totalMarketDebt,
                'paid' => $totalMarketPaid,
                'penalty' => $totalMarketPenalty
            ],
            'collection_rate' => ($totalRentDebt + $totalMarketDebt) > 0
                ? round(($totalRentPaid + $totalMarketPaid) / ($totalRentDebt + $totalMarketDebt + $totalRentPaid + $totalMarketPaid) * 100, 2)
                : 0
        ];
    }

    /**
     * Extract numeric value from row by checking multiple possible column names
     */
    private function extractNumeric(array $row, array $possibleKeys): float
    {
        foreach ($row as $key => $value) {
            foreach ($possibleKeys as $searchKey) {
                if (mb_stripos($key, $searchKey) !== false && is_numeric($value)) {
                    return floatval($value);
                }
            }
        }
        return 0;
    }

    /**
     * Prepare chart data from parsed data
     */
    private function prepareChartData(array $rentPenalty, array $marketPenalty): array
    {
        return [
            'penalty_comparison' => [
                'labels' => ['Rent Penalty', 'Market Penalty'],
                'values' => [
                    array_sum(array_column($rentPenalty['data'] ?? [], 'penya') ?: [0]),
                    array_sum(array_column($marketPenalty['data'] ?? [], 'penya') ?: [0])
                ]
            ],
            'status_distribution' => $this->calculateStatusDistribution($rentPenalty, $marketPenalty)
        ];
    }

    /**
     * Calculate payment status distribution
     */
    private function calculateStatusDistribution(array $rentPenalty, array $marketPenalty): array
    {
        $paid = 0;
        $pending = 0;
        $overdue = 0;

        $allData = array_merge($rentPenalty['data'] ?? [], $marketPenalty['data'] ?? []);

        foreach ($allData as $row) {
            // Simple logic based on presence of penalty
            $hasPenalty = false;
            foreach ($row as $key => $value) {
                if (mb_stripos($key, 'пеня') !== false || mb_stripos($key, 'penalty') !== false) {
                    $hasPenalty = $value > 0;
                    break;
                }
            }

            if ($hasPenalty) {
                $overdue++;
            } else {
                $paid++;
            }
        }

        return [
            'labels' => ['Paid', 'Pending', 'Overdue'],
            'values' => [$paid, $pending, $overdue]
        ];
    }
}
