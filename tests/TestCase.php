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

        // Every KadiApi call in a test must be faked. The pending-withdrawals badge in the
        // navigation asks on every full page, so it gets a default answer (registered first, so
        // a test's own fakes never replace it).
        Http::preventStrayRequests();
        Http::fake([
            'kadi.test/api/v1/shop/withdrawals?status=pending&page=1&per_page=1' => Http::response([
                'success' => true,
                'data' => ['items' => [], 'pagination' => ['page' => 1, 'per_page' => 1, 'total' => 0, 'last_page' => 1]],
            ]),
        ]);
    }
}
