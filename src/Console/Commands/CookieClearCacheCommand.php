<?php

namespace Taki47\PrivacyPanel\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;

/**
 * Class CookieClearCacheCommand
 *
 * Clears the cached cookie metadata and scan results
 * that may be stored in the application's local storage.
 *
 * Usage:
 *   php artisan cookie:clear-cache
 *   php artisan cookie:clear-cache --force
 *
 * Options:
 *   --force (-f)   Force delete without asking for confirmation
 *
 * Example output:
 *   ✅ cookie-info-cache.json deleted
 *   ✅ cookie-scan.json deleted
 *
 * @package Taki47\CookieConsent\Console\Commands
 */
class CookieClearCacheCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'cookie:clear-cache {--f|force : Force delete without confirmation}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Clears cached cookie metadata and scan results from local storage.';

    /**
     * Execute the console command.
     *
     * @return int
     */
    public function handle(): int
    {
        // Files to check and delete
        $files = [
            'cookie-info-cache.json',
            'cookie-scan.json',
        ];

        $deleted = 0;

        foreach ($files as $file) {
            if (Storage::disk('local')->exists($file)) {
                // Ask for confirmation unless forced
                if (!$this->option('force') && !$this->confirm("Delete {$file}?", true)) {
                    continue;
                }

                // Delete and log
                Storage::disk('local')->delete($file);
                $this->info("🧹 Deleted: {$file}");
                $deleted++;
            }
        }

        // Summary
        if ($deleted === 0) {
            $this->info('No cookie cache files found.');
        } else {
            $this->info("✅ {$deleted} file(s) deleted successfully.");
        }

        return Command::SUCCESS;
    }
}
