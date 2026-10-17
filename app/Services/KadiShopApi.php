<?php

namespace App\Services;

use Illuminate\Container\Attributes\Config;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use SensitiveParameter;

/**
 * The only way this app talks to KadiApi: the /shop/* routes, with this shop's API key.
 *
 * Every call returns a KadiResult and never throws. Nothing is retried automatically: a write
 * is retried only when the cashier asks, with the same Idempotency-Key, so it never happens
 * twice. The key is read here and nowhere else, and is never logged. Every answer updates the
 * DeskBlock, so a suspended shop or a bad key blocks the whole panel.
 */
class KadiShopApi
{
    private const int TIMEOUT_SECONDS = 10;

    private const int CONNECT_TIMEOUT_SECONDS = 5;

    public function __construct(
        #[Config('kadi.url')] private readonly string $baseUrl,
        #[Config('kadi.key'), SensitiveParameter] private readonly ?string $apiKey,
    ) {}

    /**
     * The shop, its float, today's loads and the load limits.
     */
    public function me(): KadiResult
    {
        return $this->send('GET', '/shop/me');
    }

    /**
     * Check a player number and get the masked name to confirm with the player.
     */
    public function lookupPlayer(string $playerNo): KadiResult
    {
        return $this->send('GET', '/shop/players/'.rawurlencode(self::normalizePlayerNo($playerNo)));
    }

    /**
     * Load a player's wallet with the cash they handed over. Use a new key for each load and the
     * same key (with the same player and amount) only to retry one whose outcome is unknown.
     */
    public function load(string $playerNo, int $amount, string $idempotencyKey): KadiResult
    {
        return $this->send('POST', '/shop/loads', [
            'player_no' => self::normalizePlayerNo($playerNo),
            'amount' => $amount,
        ], idempotencyKey: $idempotencyKey);
    }

    /**
     * This shop's loads, newest first.
     *
     * @param  string|null  $from  Y-m-d
     * @param  string|null  $to  Y-m-d
     */
    public function loads(?string $from = null, ?string $to = null, int $page = 1, int $perPage = 50): KadiResult
    {
        return $this->send('GET', '/shop/loads', query: array_filter([
            'from' => $from,
            'to' => $to,
            'page' => $page,
            'per_page' => $perPage,
        ], fn (mixed $value): bool => $value !== null));
    }

    /**
     * Cash withdrawals players asked for at this shop, newest first.
     *
     * @param  string  $status  pending, paid, cancelled, rejected or expired
     */
    public function withdrawals(string $status = 'pending', int $page = 1, int $perPage = 50): KadiResult
    {
        return $this->send('GET', '/shop/withdrawals', query: [
            'status' => $status,
            'page' => $page,
            'per_page' => $perPage,
        ]);
    }

    /**
     * Pay a withdrawal with the pickup code the player gives. Use a new key for each code
     * attempt and the same key only to retry one whose outcome is unknown.
     */
    public function payWithdrawal(string $withdrawalId, #[SensitiveParameter] string $pickupCode, string $idempotencyKey): KadiResult
    {
        return $this->send('POST', '/shop/withdrawals/'.rawurlencode($withdrawalId).'/pay', [
            'pickup_code' => $pickupCode,
        ], idempotencyKey: $idempotencyKey);
    }

    /**
     * Turn a pending withdrawal down; the money goes back to the player, who sees the reason.
     */
    public function rejectWithdrawal(string $withdrawalId, string $reason, string $idempotencyKey): KadiResult
    {
        return $this->send('POST', '/shop/withdrawals/'.rawurlencode($withdrawalId).'/reject', [
            'reason' => $reason,
        ], idempotencyKey: $idempotencyKey);
    }

    /**
     * The player number as the 8 bare digits: players read it as `1234 5678` and cashiers may
     * type spaces or dashes. Anything else is left for KadiApi's check digit to refuse.
     */
    public static function normalizePlayerNo(string $playerNo): string
    {
        return preg_replace('/[\s-]+/', '', $playerNo) ?? $playerNo;
    }

    /**
     * @param  array<string, mixed>  $body
     * @param  array<string, mixed>  $query
     */
    private function send(string $method, string $path, array $body = [], array $query = [], ?string $idempotencyKey = null): KadiResult
    {
        $request = $this->request();

        if ($idempotencyKey !== null) {
            $request->withHeaders(['Idempotency-Key' => $idempotencyKey]);
        }

        try {
            $response = $method === 'GET'
                ? $request->get($path, $query)
                : $request->post($path, $body);
        } catch (ConnectionException $exception) {
            Log::warning('KadiApi unreachable', [
                'method' => $method,
                'path' => $path,
                'error' => $exception->getMessage(),
            ]);

            return KadiResult::unreachable();
        }

        $result = KadiResult::fromResponse($response);

        DeskBlock::recordFrom($result);

        if (! $result->ok()) {
            Log::warning('KadiApi refused a request', [
                'method' => $method,
                'path' => $path,
                'status' => $result->status,
                'code' => $result->code,
            ]);
        }

        return $result;
    }

    private function request(): PendingRequest
    {
        return Http::baseUrl(rtrim($this->baseUrl, '/'))
            ->withHeaders([
                'X-API-KEY' => (string) $this->apiKey,
                'Accept' => 'application/json',
            ])
            ->asJson()
            ->timeout(self::TIMEOUT_SECONDS)
            ->connectTimeout(self::CONNECT_TIMEOUT_SECONDS);
    }
}
