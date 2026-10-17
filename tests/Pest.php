<?php

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/*
|--------------------------------------------------------------------------
| Test Case
|--------------------------------------------------------------------------
|
| The closure you provide to your test functions is always bound to a specific PHPUnit test
| case class. By default, that class is "PHPUnit\Framework\TestCase". Of course, you may
| need to change it using the "pest()" function to bind different classes or traits.
|
*/

pest()->extend(TestCase::class)
    ->use(RefreshDatabase::class)
    ->in('Feature');

/*
|--------------------------------------------------------------------------
| Expectations
|--------------------------------------------------------------------------
|
| When you're writing tests, you often need to check that values meet certain conditions. The
| "expect()" function gives you access to a set of "expectations" methods that you can use
| to assert different things. Of course, you may extend the Expectation API at any time.
|
*/

expect()->extend('toBeOne', function () {
    return $this->toBe(1);
});

/*
|--------------------------------------------------------------------------
| Functions
|--------------------------------------------------------------------------
|
| While Pest is very powerful out-of-the-box, you may have some testing code specific to your
| project that you don't want to repeat in every file. Here you can also expose helpers as
| global functions to help you to reduce the number of lines of code in your test files.
|
*/

/**
 * The `data` of a GET /shop/me answer, with any keys replaced.
 *
 * @param  array<string, mixed>  $overrides
 * @return array<string, mixed>
 */
function shopMe(array $overrides = []): array
{
    return array_replace_recursive([
        'shop_no' => 'S101',
        'name' => 'Kariobangi Bet Shop',
        'location' => 'Kariobangi North',
        'status' => 'active',
        'float_balance' => 12500.00,
        'float_bill_ref' => 'SHOP-S101',
        'today' => ['loads' => 4, 'gross_amount' => 3800.00],
        'load_limits' => ['min_amount' => 50, 'max_amount' => 20000, 'daily_limit_per_player' => 50000],
    ], $overrides);
}
