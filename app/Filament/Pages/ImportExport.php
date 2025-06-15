<?php

namespace App\Filament\Pages;

use App\Services\ImportExportManager;
use Filament\Actions\Action;
use Filament\Forms\Components\CheckboxList;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Toggle;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Illuminate\Support\Facades\Storage;

class ImportExport extends Page implements HasForms
{
    use InteractsWithForms;

    protected static ?string $title = 'Import & Export';

    protected static ?string $navigationLabel = 'Import & Export';

    protected static ?int $navigationSort = 6;

    public function getView(): string
    {
        return 'filament.pages.import-export';
    }

    public ?array $exportData = [];

    public ?array $importData = [];

    public array $recentExports = [];

    public function mount(): void
    {
        $this->exportForm->fill([
            'types' => ['datasets', 'documents', 'prompt_templates'],
            'format' => 'json',
            'include_content' => true,
            'include_keys' => false,
        ]);

        $this->importForm->fill([
            'skip_duplicates' => true,
            'make_private' => true,
        ]);

        $this->loadRecentExports();
    }

    protected function getExportFormSchema(): array
    {
        $importExportManager = app(ImportExportManager::class);

        return [
            Section::make('Export Selection')
                ->description('Choose what data to export')
                ->schema([
                    CheckboxList::make('types')
                        ->label('Data Types to Export')
                        ->options($importExportManager->getExportableTypes())
                        ->required()
                        ->columns(2),

                    Select::make('format')
                        ->label('Export Format')
                        ->options($importExportManager->getExportFormats())
                        ->required()
                        ->default('json'),
                ])
                ->columnSpanFull(),

            Section::make('Export Options')
                ->description('Configure export settings')
                ->schema([
                    DatePicker::make('start_date')
                        ->label('Start Date (Optional)')
                        ->helperText('Only export data created after this date'),

                    DatePicker::make('end_date')
                        ->label('End Date (Optional)')
                        ->helperText('Only export data created before this date'),

                    Toggle::make('include_content')
                        ->label('Include Document Content')
                        ->helperText('Include full document content (larger file size)')
                        ->default(true),

                    Toggle::make('include_keys')
                        ->label('Include API Keys')
                        ->helperText('Include actual API key values (security risk)')
                        ->default(false),

                    Toggle::make('public_only')
                        ->label('Public Templates Only')
                        ->helperText('For prompt templates, only export public ones')
                        ->default(false),

                    Toggle::make('clear_auth_data')
                        ->label('Clear Authentication Data')
                        ->helperText('Remove sensitive authentication information from connections')
                        ->default(true),
                ])
                ->columns(2)
                ->columnSpanFull(),
        ];
    }

    protected function getImportFormSchema(): array
    {
        return [
            Section::make('Import File')
                ->description('Upload your export file')
                ->schema([
                    FileUpload::make('import_file')
                        ->label('Select Import File')
                        ->acceptedFileTypes(['application/json', 'application/zip'])
                        ->maxSize(50 * 1024) // 50MB
                        ->required()
                        ->helperText('Upload a JSON or ZIP file exported from MCPman')
                        ->columnSpanFull(),
                ])
                ->columnSpanFull(),

            Section::make('Import Options')
                ->description('Configure import behavior')
                ->schema([
                    Toggle::make('skip_duplicates')
                        ->label('Skip Duplicates')
                        ->helperText('Skip items that already exist (based on name/slug)')
                        ->default(true),

                    Toggle::make('skip_api_keys')
                        ->label('Skip API Keys')
                        ->helperText('Do not import API keys for security')
                        ->default(false),

                    Toggle::make('make_private')
                        ->label('Make Templates Private')
                        ->helperText('Import all prompt templates as private')
                        ->default(true),

                    Toggle::make('clear_auth_data')
                        ->label('Clear Connection Auth Data')
                        ->helperText('Remove authentication data from connections')
                        ->default(true),
                ])
                ->columns(2)
                ->columnSpanFull(),
        ];
    }

    public function exportForm(Schema $schema): Schema
    {
        return $schema
            ->components($this->getExportFormSchema())
            ->statePath('exportData')
            ->columns(2);
    }

    public function importForm(Schema $schema): Schema
    {
        return $schema
            ->components($this->getImportFormSchema())
            ->statePath('importData')
            ->columns(2);
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('cleanupOldExports')
                ->label('Cleanup Old Exports')
                ->icon('heroicon-o-trash')
                ->action('cleanupOldExports')
                ->color('warning')
                ->requiresConfirmation()
                ->modalDescription('This will remove export files older than 30 days to free up storage space.'),
        ];
    }

    public function exportData(): void
    {
        try {
            $this->exportForm->getState();

            $importExportManager = app(ImportExportManager::class);
            $user = auth()->user();

            $types = $this->exportData['types'] ?? [];
            $format = $this->exportData['format'] ?? 'json';
            $options = [
                'start_date' => $this->exportData['start_date'] ?? null,
                'end_date' => $this->exportData['end_date'] ?? null,
                'include_content' => $this->exportData['include_content'] ?? true,
                'include_keys' => $this->exportData['include_keys'] ?? false,
                'public_only' => $this->exportData['public_only'] ?? false,
                'clear_auth_data' => $this->exportData['clear_auth_data'] ?? true,
            ];

            $filePath = $importExportManager->createExportFile($user, $types, $options, $format);

            $this->loadRecentExports();

            Notification::make()
                ->title('Export Successful')
                ->body('Your data has been exported successfully. Download will start automatically.')
                ->success()
                ->persistent()
                ->actions([
                    \Filament\Notifications\Actions\Action::make('download')
                        ->label('Download Now')
                        ->url(Storage::disk('local')->url($filePath))
                        ->openUrlInNewTab(),
                ])
                ->send();

        } catch (\Exception $e) {
            Notification::make()
                ->title('Export Failed')
                ->body('Failed to export data: '.$e->getMessage())
                ->danger()
                ->send();
        }
    }

    public function importData(): void
    {
        try {
            $this->importForm->getState();

            $importFile = $this->importData['import_file'] ?? null;
            if (! $importFile) {
                throw new \Exception('No import file selected.');
            }

            $importExportManager = app(ImportExportManager::class);
            $user = auth()->user();

            $options = [
                'skip_duplicates' => $this->importData['skip_duplicates'] ?? true,
                'skip_api_keys' => $this->importData['skip_api_keys'] ?? false,
                'make_private' => $this->importData['make_private'] ?? true,
                'clear_auth_data' => $this->importData['clear_auth_data'] ?? true,
            ];

            $results = $importExportManager->importData($user, $importFile, $options);

            if ($results['success']) {
                $summary = $this->generateImportSummaryMessage($results['summary']);

                Notification::make()
                    ->title('Import Successful')
                    ->body($summary)
                    ->success()
                    ->persistent()
                    ->send();

                // Clear the form
                $this->importForm->fill([
                    'import_file' => null,
                    'skip_duplicates' => true,
                    'make_private' => true,
                ]);

            } else {
                $errorMessage = 'Import failed: '.implode(', ', $results['errors']['general'] ?? ['Unknown error']);

                Notification::make()
                    ->title('Import Failed')
                    ->body($errorMessage)
                    ->danger()
                    ->persistent()
                    ->send();
            }

        } catch (\Exception $e) {
            Notification::make()
                ->title('Import Failed')
                ->body('Failed to import data: '.$e->getMessage())
                ->danger()
                ->send();
        }
    }

    public function cleanupOldExports(): void
    {
        try {
            $importExportManager = app(ImportExportManager::class);
            $deletedCount = $importExportManager->cleanupOldExports(30);

            Notification::make()
                ->title('Cleanup Complete')
                ->body("Removed {$deletedCount} old export files.")
                ->success()
                ->send();

            $this->loadRecentExports();

        } catch (\Exception $e) {
            Notification::make()
                ->title('Cleanup Failed')
                ->body('Failed to cleanup old exports: '.$e->getMessage())
                ->danger()
                ->send();
        }
    }

    protected function loadRecentExports(): void
    {
        try {
            $files = Storage::disk('local')->files('exports');
            $exports = [];

            foreach ($files as $file) {
                $exports[] = [
                    'name' => basename($file),
                    'size' => Storage::disk('local')->size($file),
                    'created' => \Carbon\Carbon::createFromTimestamp(Storage::disk('local')->lastModified($file)),
                    'url' => Storage::disk('local')->url($file),
                ];
            }

            // Sort by creation date (newest first)
            usort($exports, function ($a, $b) {
                return $b['created']->timestamp - $a['created']->timestamp;
            });

            $this->recentExports = array_slice($exports, 0, 10);

        } catch (\Exception $e) {
            $this->recentExports = [];
        }
    }

    protected function generateImportSummaryMessage(array $summary): string
    {
        $messages = [];

        foreach ($summary as $type => $stats) {
            $typeName = ucfirst(str_replace('_', ' ', $type));
            $messages[] = "{$typeName}: {$stats['imported']} imported, {$stats['skipped']} skipped, {$stats['errors']} errors";
        }

        return implode(' | ', $messages);
    }

    public function downloadExport(string $filename)
    {
        $path = "exports/{$filename}";

        if (Storage::disk('local')->exists($path)) {
            return Storage::disk('local')->download($path);
        }

        Notification::make()
            ->title('File Not Found')
            ->body('The requested export file could not be found.')
            ->warning()
            ->send();
    }

    public function deleteExport(string $filename): void
    {
        $path = "exports/{$filename}";

        if (Storage::disk('local')->exists($path)) {
            Storage::disk('local')->delete($path);
            $this->loadRecentExports();

            Notification::make()
                ->title('Export Deleted')
                ->body('Export file has been deleted successfully.')
                ->success()
                ->send();
        }
    }
}
