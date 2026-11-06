<?php

namespace App\Console\Commands;

use Database\Seeders\LarkspurScenarioSeeder;
use Illuminate\Console\Command;

/**
 * Rebuild only the Larkspur benchmark initiative (and its cast of users) to pick up
 * new seed data. Every other initiative, user, setting and guide edit is left alone.
 */
class RefreshScenario extends Command
{
    protected $signature = 'app:refresh-scenario {--force : Skip the confirmation prompt}';

    protected $description = 'Rebuild the Larkspur demo initiative without touching any other data';

    public function handle(): int
    {
        $name = LarkspurScenarioSeeder::INITIATIVE_NAME;

        if (! $this->option('force') && ! $this->confirm("This replaces the \"{$name}\" initiative, including any edits made to it. Other initiatives are not touched. Continue?", true)) {
            return self::FAILURE;
        }

        $this->call('db:seed', ['--class' => LarkspurScenarioSeeder::class, '--force' => true]);
        $this->components->info("Rebuilt \"{$name}\".");

        return self::SUCCESS;
    }
}
