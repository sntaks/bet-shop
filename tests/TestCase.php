<?php

namespace Tests;

use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Illuminate\Support\Facades\Http;

abstract class TestCase extends BaseTestCase
{
    /**
     * Seed the staff roles after each database refresh, so factory roles can be assigned.
     */
    protected bool $seed = true;

    protected string $seeder = RoleSeeder::class;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutVite();

        // Every KadiApi call in a test must be faked.
        Http::preventStrayRequests();
    }
}
