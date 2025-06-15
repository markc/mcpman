<?php

namespace App\Services;

use App\Models\ApiKey;
use App\Models\Dataset;
use App\Models\Document;
use App\Models\McpConnection;
use App\Models\PromptTemplate;
use App\Models\Tool;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use ZipArchive;

class ImportExportManager
{
    protected array $exportableModels = [
        'datasets' => Dataset::class,
        'documents' => Document::class,
        'connections' => McpConnection::class,
        'api_keys' => ApiKey::class,
        'prompt_templates' => PromptTemplate::class,
        'tools' => Tool::class,
    ];

    /**
     * Export data for a user
     */
    public function exportUserData(User $user, array $types = [], array $options = []): array
    {
        $exportData = [
            'metadata' => [
                'export_date' => now()->toISOString(),
                'user_id' => $user->id,
                'user_email' => $user->email,
                'version' => '1.0',
                'exported_types' => $types,
            ],
            'data' => [],
        ];

        // If no types specified, export all
        if (empty($types)) {
            $types = array_keys($this->exportableModels);
        }

        foreach ($types as $type) {
            if (isset($this->exportableModels[$type])) {
                $exportData['data'][$type] = $this->exportModelData($type, $user, $options);
            }
        }

        return $exportData;
    }

    /**
     * Export model data
     */
    protected function exportModelData(string $type, User $user, array $options = []): array
    {
        $modelClass = $this->exportableModels[$type];
        $query = $modelClass::where('user_id', $user->id);

        // Apply date filters if specified
        if (isset($options['start_date'])) {
            $query->where('created_at', '>=', $options['start_date']);
        }
        if (isset($options['end_date'])) {
            $query->where('created_at', '<=', $options['end_date']);
        }

        // Apply additional filters based on type
        switch ($type) {
            case 'datasets':
                if (isset($options['status'])) {
                    $query->where('status', $options['status']);
                }
                break;
            case 'documents':
                if (isset($options['document_type'])) {
                    $query->where('type', $options['document_type']);
                }
                if (isset($options['include_content']) && ! $options['include_content']) {
                    $query->select(['id', 'title', 'slug', 'type', 'status', 'dataset_id', 'metadata', 'created_at', 'updated_at']);
                }
                break;
            case 'connections':
                if (isset($options['connection_status'])) {
                    $query->where('status', $options['connection_status']);
                }
                // Remove sensitive data
                $query->select(['id', 'name', 'endpoint_url', 'transport_type', 'status', 'capabilities', 'metadata', 'created_at', 'updated_at']);
                break;
            case 'api_keys':
                if (isset($options['include_keys']) && ! $options['include_keys']) {
                    $query->select(['id', 'name', 'permissions', 'rate_limits', 'is_active', 'expires_at', 'created_at', 'updated_at']);
                }
                break;
            case 'prompt_templates':
                if (isset($options['category'])) {
                    $query->where('category', $options['category']);
                }
                if (isset($options['public_only']) && $options['public_only']) {
                    $query->where('is_public', true);
                }
                break;
        }

        return $query->get()->toArray();
    }

    /**
     * Create export file
     */
    public function createExportFile(User $user, array $types = [], array $options = [], string $format = 'json'): string
    {
        $exportData = $this->exportUserData($user, $types, $options);
        $timestamp = now()->format('Y-m-d_H-i-s');
        $filename = "mcp_export_{$user->id}_{$timestamp}";

        switch ($format) {
            case 'json':
                return $this->createJsonExport($exportData, $filename);
            case 'zip':
                return $this->createZipExport($exportData, $filename);
            case 'csv':
                return $this->createCsvExport($exportData, $filename);
            default:
                throw new \InvalidArgumentException("Unsupported export format: {$format}");
        }
    }

    /**
     * Create JSON export
     */
    protected function createJsonExport(array $data, string $filename): string
    {
        $jsonData = json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
        $path = "exports/{$filename}.json";

        Storage::disk('local')->put($path, $jsonData);

        return $path;
    }

    /**
     * Create ZIP export with separate files
     */
    protected function createZipExport(array $data, string $filename): string
    {
        $tempDir = storage_path("app/temp/{$filename}");
        $zipPath = "exports/{$filename}.zip";

        // Create temporary directory
        if (! is_dir($tempDir)) {
            mkdir($tempDir, 0755, true);
        }

        // Create metadata file
        file_put_contents("{$tempDir}/metadata.json", json_encode($data['metadata'], JSON_PRETTY_PRINT));

        // Create individual files for each data type
        foreach ($data['data'] as $type => $records) {
            if (! empty($records)) {
                file_put_contents(
                    "{$tempDir}/{$type}.json",
                    json_encode($records, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE)
                );
            }
        }

        // Create ZIP file
        $zip = new ZipArchive;
        if ($zip->open(storage_path("app/{$zipPath}"), ZipArchive::CREATE) === true) {
            $files = glob("{$tempDir}/*");
            foreach ($files as $file) {
                $zip->addFile($file, basename($file));
            }
            $zip->close();
        }

        // Clean up temporary directory
        array_map('unlink', glob("{$tempDir}/*"));
        rmdir($tempDir);

        return $zipPath;
    }

    /**
     * Create CSV export (only for simple data types)
     */
    protected function createCsvExport(array $data, string $filename): string
    {
        $tempDir = storage_path("app/temp/{$filename}");
        $zipPath = "exports/{$filename}_csv.zip";

        if (! is_dir($tempDir)) {
            mkdir($tempDir, 0755, true);
        }

        foreach ($data['data'] as $type => $records) {
            if (! empty($records)) {
                $csvPath = "{$tempDir}/{$type}.csv";
                $file = fopen($csvPath, 'w');

                // Write headers
                if (! empty($records[0])) {
                    fputcsv($file, array_keys($records[0]));
                }

                // Write data
                foreach ($records as $record) {
                    fputcsv($file, $this->flattenArray($record));
                }

                fclose($file);
            }
        }

        // Create ZIP of CSV files
        $zip = new ZipArchive;
        if ($zip->open(storage_path("app/{$zipPath}"), ZipArchive::CREATE) === true) {
            $files = glob("{$tempDir}/*");
            foreach ($files as $file) {
                $zip->addFile($file, basename($file));
            }
            $zip->close();
        }

        // Clean up
        array_map('unlink', glob("{$tempDir}/*"));
        rmdir($tempDir);

        return $zipPath;
    }

    /**
     * Import data from file
     */
    public function importData(User $user, string $filePath, array $options = []): array
    {
        $results = [
            'success' => true,
            'imported' => [],
            'skipped' => [],
            'errors' => [],
            'summary' => [],
        ];

        try {
            $data = $this->parseImportFile($filePath);

            // Validate import data
            $validation = $this->validateImportData($data);
            if (! $validation['valid']) {
                throw new \Exception('Invalid import data: '.implode(', ', $validation['errors']));
            }

            DB::beginTransaction();

            foreach ($data['data'] as $type => $records) {
                if (isset($this->exportableModels[$type])) {
                    $typeResults = $this->importModelData($type, $records, $user, $options);
                    $results['imported'][$type] = $typeResults['imported'];
                    $results['skipped'][$type] = $typeResults['skipped'];
                    $results['errors'][$type] = $typeResults['errors'];
                }
            }

            DB::commit();

            // Generate summary
            $results['summary'] = $this->generateImportSummary($results);

        } catch (\Exception $e) {
            DB::rollBack();
            $results['success'] = false;
            $results['errors']['general'] = [$e->getMessage()];
        }

        return $results;
    }

    /**
     * Parse import file
     */
    protected function parseImportFile(string $filePath): array
    {
        $fullPath = Storage::disk('local')->path($filePath);

        if (! file_exists($fullPath)) {
            throw new \Exception("Import file not found: {$filePath}");
        }

        $extension = pathinfo($fullPath, PATHINFO_EXTENSION);

        switch ($extension) {
            case 'json':
                $content = file_get_contents($fullPath);

                return json_decode($content, true);

            case 'zip':
                return $this->parseZipImport($fullPath);

            default:
                throw new \Exception("Unsupported import format: {$extension}");
        }
    }

    /**
     * Parse ZIP import
     */
    protected function parseZipImport(string $zipPath): array
    {
        $zip = new ZipArchive;
        $tempDir = storage_path('app/temp/import_'.uniqid());

        if (! is_dir($tempDir)) {
            mkdir($tempDir, 0755, true);
        }

        if ($zip->open($zipPath) === true) {
            $zip->extractTo($tempDir);
            $zip->close();
        } else {
            throw new \Exception('Unable to extract ZIP file');
        }

        // Read metadata
        $metadataPath = "{$tempDir}/metadata.json";
        if (! file_exists($metadataPath)) {
            throw new \Exception('Metadata file not found in ZIP');
        }

        $metadata = json_decode(file_get_contents($metadataPath), true);
        $data = ['metadata' => $metadata, 'data' => []];

        // Read data files
        foreach ($this->exportableModels as $type => $modelClass) {
            $dataPath = "{$tempDir}/{$type}.json";
            if (file_exists($dataPath)) {
                $data['data'][$type] = json_decode(file_get_contents($dataPath), true);
            }
        }

        // Clean up
        array_map('unlink', glob("{$tempDir}/*"));
        rmdir($tempDir);

        return $data;
    }

    /**
     * Validate import data
     */
    protected function validateImportData(array $data): array
    {
        $errors = [];

        if (! isset($data['metadata'])) {
            $errors[] = 'Missing metadata section';
        }

        if (! isset($data['data'])) {
            $errors[] = 'Missing data section';
        }

        if (isset($data['metadata']['version']) && version_compare($data['metadata']['version'], '1.0', '>')) {
            $errors[] = 'Unsupported export version: '.$data['metadata']['version'];
        }

        return [
            'valid' => empty($errors),
            'errors' => $errors,
        ];
    }

    /**
     * Import model data
     */
    protected function importModelData(string $type, array $records, User $user, array $options = []): array
    {
        $imported = 0;
        $skipped = 0;
        $errors = [];
        $modelClass = $this->exportableModels[$type];

        foreach ($records as $record) {
            try {
                // Remove ID and timestamps for new records
                unset($record['id'], $record['created_at'], $record['updated_at']);

                // Set the user ID
                $record['user_id'] = $user->id;

                // Apply type-specific processing
                $processedRecord = $this->processRecordForImport($type, $record, $options);

                if ($processedRecord === null) {
                    $skipped++;

                    continue;
                }

                // Check for duplicates if specified
                if (isset($options['skip_duplicates']) && $options['skip_duplicates']) {
                    if ($this->isDuplicate($type, $processedRecord, $user)) {
                        $skipped++;

                        continue;
                    }
                }

                $modelClass::create($processedRecord);
                $imported++;

            } catch (\Exception $e) {
                $errors[] = "Failed to import {$type} record: ".$e->getMessage();
            }
        }

        return [
            'imported' => $imported,
            'skipped' => $skipped,
            'errors' => $errors,
        ];
    }

    /**
     * Process record for import
     */
    protected function processRecordForImport(string $type, array $record, array $options = []): ?array
    {
        switch ($type) {
            case 'api_keys':
                // Skip API keys if not including sensitive data
                if (isset($options['skip_api_keys']) && $options['skip_api_keys']) {
                    return null;
                }
                // Generate new API key if not provided
                if (! isset($record['key'])) {
                    $record['key'] = 'mcp_'.\Illuminate\Support\Str::random(32);
                }
                break;

            case 'connections':
                // Reset connection status
                $record['status'] = 'inactive';
                // Clear sensitive auth data if specified
                if (isset($options['clear_auth_data']) && $options['clear_auth_data']) {
                    $record['auth_config'] = [];
                }
                break;

            case 'prompt_templates':
                // Make templates private by default on import
                if (isset($options['make_private']) && $options['make_private']) {
                    $record['is_public'] = false;
                }
                // Reset usage statistics
                $record['usage_count'] = 0;
                $record['average_rating'] = 0;
                break;
        }

        return $record;
    }

    /**
     * Check for duplicates
     */
    protected function isDuplicate(string $type, array $record, User $user): bool
    {
        $modelClass = $this->exportableModels[$type];

        switch ($type) {
            case 'datasets':
            case 'documents':
            case 'prompt_templates':
                return $modelClass::where('user_id', $user->id)
                    ->where('slug', $record['slug'])
                    ->exists();

            case 'connections':
                return $modelClass::where('user_id', $user->id)
                    ->where('name', $record['name'])
                    ->exists();

            case 'api_keys':
                return $modelClass::where('user_id', $user->id)
                    ->where('name', $record['name'])
                    ->exists();

            default:
                return false;
        }
    }

    /**
     * Generate import summary
     */
    protected function generateImportSummary(array $results): array
    {
        $summary = [];

        foreach ($results['imported'] as $type => $count) {
            $summary[$type] = [
                'imported' => $count,
                'skipped' => $results['skipped'][$type] ?? 0,
                'errors' => count($results['errors'][$type] ?? []),
            ];
        }

        return $summary;
    }

    /**
     * Flatten array for CSV export
     */
    protected function flattenArray(array $array, string $prefix = ''): array
    {
        $result = [];

        foreach ($array as $key => $value) {
            $newKey = $prefix === '' ? $key : $prefix.'.'.$key;

            if (is_array($value)) {
                $result[$newKey] = json_encode($value);
            } else {
                $result[$newKey] = $value;
            }
        }

        return $result;
    }

    /**
     * Get export formats
     */
    public function getExportFormats(): array
    {
        return [
            'json' => 'JSON (Single file)',
            'zip' => 'ZIP (Multiple files)',
            'csv' => 'CSV (Spreadsheet compatible)',
        ];
    }

    /**
     * Get exportable types
     */
    public function getExportableTypes(): array
    {
        return [
            'datasets' => 'Datasets',
            'documents' => 'Documents',
            'connections' => 'MCP Connections',
            'api_keys' => 'API Keys',
            'prompt_templates' => 'Prompt Templates',
            'tools' => 'Tools',
        ];
    }

    /**
     * Clean up old export files
     */
    public function cleanupOldExports(int $daysOld = 30): int
    {
        $files = Storage::disk('local')->files('exports');
        $deleted = 0;
        $cutoff = now()->subDays($daysOld);

        foreach ($files as $file) {
            $lastModified = Carbon::createFromTimestamp(Storage::disk('local')->lastModified($file));
            if ($lastModified->lt($cutoff)) {
                Storage::disk('local')->delete($file);
                $deleted++;
            }
        }

        return $deleted;
    }
}
