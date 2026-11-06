<?php

namespace App\Console\Commands;

use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Console\Command;

/**
 * Safe default for setup and deploys: seed demo data only into a brand-new database.
 */
class SeedIfEmpty extends Command
{
    protected $signature = 'app:seed-if-empty';

    protected $description = 'Seed the demo accounts and Larkspur scenario only if the database has no users yet';

    public function handle(): int
    {
        if (User::query()->exists()) {
            $this->components->info('Database already has data; skipping seed. (Use app:refresh-scenario to update just the Larkspur demo.)');

            return self::SUCCESS;
        }

        $this->call('db:seed', ['--class' => DatabaseSeeder::class, '--force' => true]);

        return self::SUCCESS;
    }
}
