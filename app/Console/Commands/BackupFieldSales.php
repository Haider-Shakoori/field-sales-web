<?php

namespace App\Console\Commands;

use App\Services\Operations\BackupService;
use Illuminate\Console\Command;
use Throwable;

class BackupFieldSales extends Command
{
    protected $signature = 'field-sales:backup {--label=scheduled : Human-readable backup label}';

    protected $description = 'Create a production backup of the MySQL database and uploaded public storage.';

    public function handle(BackupService $backups): int
    {
        try {
            $result = $backups->create((string) $this->option('label'));
        } catch (Throwable $exception) {
            report($exception);
            $this->error('Backup failed. Review application logs for the underlying operational error.');

            return self::FAILURE;
        }

        $this->info('Backup completed successfully.');
        $this->line('Directory: '.$result['directory']);
        $this->line('Database: '.$result['database']);

        if ($result['media']) {
            $this->line('Public storage: '.$result['media']);
        }

        $this->line('Manifest: '.$result['manifest']);

        return self::SUCCESS;
    }
}
