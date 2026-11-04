<?php

namespace Tests;

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use RuntimeException;

abstract class TestCase extends BaseTestCase
{
    /**
     * Databases the test suite may wipe. Anything else aborts the run before RefreshDatabase touches it.
     *
     * @var list<string>
     */
    private const SAFE_DATABASES = [':memory:', 'testing'];

    public function createApplication(): Application
    {
        $app = parent::createApplication();

        $connection = $app['config']->get('database.default');
        $database = (string) $app['config']->get("database.connections.{$connection}.database");

        if (! in_array($database, self::SAFE_DATABASES, true)) {
            throw new RuntimeException(
                "Refusing to run tests against the [{$connection}] database [{$database}]: tests reset the database. "
                .'Use phpunit.xml (SQLite in memory) or phpunit.pgsql.xml (the "testing" database).'
            );
        }

        return $app;
    }
}
