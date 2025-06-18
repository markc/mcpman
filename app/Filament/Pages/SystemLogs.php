<?php

namespace App\Filament\Pages;

use Filament\Actions\Action;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Log;

class SystemLogs extends Page implements HasForms
{
    use InteractsWithForms;

    protected static ?string $title = 'System Logs & Debugging';

    protected static ?string $navigationLabel = 'System Logs';

    protected static ?int $navigationSort = 7;

    public function getView(): string
    {
        return 'filament.pages.system-logs';
    }

    public ?array $data = [];

    public array $logData = [];

    public array $availableLogFiles = [];

    public function mount(): void
    {
        $this->form->fill([
            'log_file' => 'laravel.log',
            'level' => 'all',
            'lines' => 100,
        ]);

        $this->loadAvailableLogFiles();
        $this->loadLogData();
    }

    protected function getFormSchema(): array
    {
        return [
            Select::make('log_file')
                ->label('Log File')
                ->options($this->getLogFileOptions())
                ->live()
                ->afterStateUpdated(fn () => $this->loadLogData()),

            Select::make('level')
                ->label('Log Level')
                ->options([
                    'all' => 'All Levels',
                    'emergency' => 'Emergency',
                    'alert' => 'Alert',
                    'critical' => 'Critical',
                    'error' => 'Error',
                    'warning' => 'Warning',
                    'notice' => 'Notice',
                    'info' => 'Info',
                    'debug' => 'Debug',
                ])
                ->live()
                ->afterStateUpdated(fn () => $this->loadLogData()),

            TextInput::make('lines')
                ->label('Number of Lines')
                ->numeric()
                ->minValue(10)
                ->maxValue(1000)
                ->default(100)
                ->live()
                ->afterStateUpdated(fn () => $this->loadLogData()),

            TextInput::make('search')
                ->label('Search Pattern')
                ->placeholder('Enter text to search for...')
                ->live(debounce: 500)
                ->afterStateUpdated(fn () => $this->loadLogData()),
        ];
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->components($this->getFormSchema())
            ->statePath('data')
            ->columns(4);
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('clearLogs')
                ->label('Clear Current Log')
                ->icon(Heroicon::OutlinedTrash)
                ->action('clearCurrentLog')
                ->color('danger')
                ->requiresConfirmation(),

            Action::make('downloadLog')
                ->label('Download Log')
                ->icon(Heroicon::OutlinedArrowDownTray)
                ->action('downloadCurrentLog'),

            Action::make('refreshLogs')
                ->label('Refresh')
                ->icon(Heroicon::OutlinedArrowPath)
                ->action('refreshLogs'),
        ];
    }

    public function loadAvailableLogFiles(): void
    {
        $logPath = storage_path('logs');
        if (File::exists($logPath)) {
            $files = File::files($logPath);
            $this->availableLogFiles = collect($files)
                ->map(fn ($file) => $file->getBasename())
                ->filter(fn ($file) => str_ends_with($file, '.log'))
                ->values()
                ->toArray();
        }
    }

    public function loadLogData(): void
    {
        try {
            $this->form->getState();

            $logFile = $this->data['log_file'] ?? 'laravel.log';
            $level = $this->data['level'] ?? 'all';
            $lines = (int) ($this->data['lines'] ?? 100);
            $search = $this->data['search'] ?? '';

            $logPath = storage_path("logs/{$logFile}");

            if (! File::exists($logPath)) {
                $this->logData = [];

                return;
            }

            $content = $this->readLogFile($logPath, $lines);
            $this->logData = $this->parseLogContent($content, $level, $search);

        } catch (\Exception $e) {
            $this->logData = [];
            Log::error('Failed to load log data', ['error' => $e->getMessage()]);
        }
    }

    protected function readLogFile(string $path, int $lines): string
    {
        // Read last N lines efficiently
        $file = new \SplFileObject($path, 'r');
        $file->seek(PHP_INT_MAX);
        $totalLines = $file->key();

        $startLine = max(0, $totalLines - $lines);
        $content = '';

        $file->seek($startLine);
        while (! $file->eof()) {
            $content .= $file->fgets();
        }

        return $content;
    }

    protected function parseLogContent(string $content, string $level, string $search): array
    {
        $entries = [];
        $lines = explode("\n", $content);
        $currentEntry = null;

        foreach ($lines as $line) {
            if (empty(trim($line))) {
                continue;
            }

            // Check if this is a new log entry (starts with timestamp)
            if (preg_match('/^\[(\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2})\].*?\.(\w+):/', $line, $matches)) {
                // Save previous entry
                if ($currentEntry) {
                    $entries[] = $currentEntry;
                }

                // Start new entry
                $currentEntry = [
                    'timestamp' => $matches[1],
                    'level' => strtolower($matches[2]),
                    'raw_line' => $line,
                    'message' => '',
                ];

                // Extract the message part
                $messagePart = preg_replace('/^\[.*?\].*?\.(\w+):\s*/', '', $line);
                $currentEntry['message'] = $messagePart;
            } else {
                // This is a continuation of the previous entry
                if ($currentEntry) {
                    $currentEntry['message'] .= "\n".$line;
                }
            }
        }

        // Add the last entry
        if ($currentEntry) {
            $entries[] = $currentEntry;
        }

        // Filter by level
        if ($level !== 'all') {
            $entries = array_filter($entries, fn ($entry) => $entry['level'] === $level);
        }

        // Filter by search term
        if (! empty($search)) {
            $entries = array_filter($entries, function ($entry) use ($search) {
                return stripos($entry['message'], $search) !== false ||
                       stripos($entry['level'], $search) !== false;
            });
        }

        return array_reverse($entries); // Show newest first
    }

    protected function getLogFileOptions(): array
    {
        $options = [];
        foreach ($this->availableLogFiles as $file) {
            $options[$file] = $file;
        }

        return $options;
    }

    public function refreshLogs(): void
    {
        $this->loadAvailableLogFiles();
        $this->loadLogData();

        Notification::make()
            ->title('Logs Refreshed')
            ->success()
            ->send();
    }

    public function clearCurrentLog(): void
    {
        try {
            $logFile = $this->data['log_file'] ?? 'laravel.log';
            $logPath = storage_path("logs/{$logFile}");

            if (File::exists($logPath)) {
                File::put($logPath, '');
                $this->loadLogData();

                Notification::make()
                    ->title('Log Cleared')
                    ->body("The {$logFile} file has been cleared.")
                    ->success()
                    ->send();
            }
        } catch (\Exception $e) {
            Notification::make()
                ->title('Clear Failed')
                ->body('Failed to clear log: '.$e->getMessage())
                ->danger()
                ->send();
        }
    }

    public function downloadCurrentLog()
    {
        try {
            $logFile = $this->data['log_file'] ?? 'laravel.log';
            $logPath = storage_path("logs/{$logFile}");

            if (File::exists($logPath)) {
                return response()->download($logPath);
            }

            Notification::make()
                ->title('File Not Found')
                ->body('Log file not found.')
                ->warning()
                ->send();

        } catch (\Exception $e) {
            Notification::make()
                ->title('Download Failed')
                ->body('Failed to download log: '.$e->getMessage())
                ->danger()
                ->send();
        }
    }

    public function getLogStats(): array
    {
        $stats = [
            'total_entries' => 0,
            'by_level' => [],
        ];

        foreach ($this->logData as $entry) {
            $stats['total_entries']++;
            $level = $entry['level'];
            $stats['by_level'][$level] = ($stats['by_level'][$level] ?? 0) + 1;
        }

        return $stats;
    }

    public function getLevelColor(string $level): string
    {
        return match (strtolower($level)) {
            'emergency', 'alert', 'critical' => 'danger',
            'error' => 'danger',
            'warning' => 'warning',
            'notice' => 'info',
            'info' => 'primary',
            'debug' => 'gray',
            default => 'gray',
        };
    }
}
