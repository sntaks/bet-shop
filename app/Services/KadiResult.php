<?php

namespace App\Services;

use Illuminate\Http\Client\Response;

/**
 * What KadiApi answered, or that it could not be reached. Branch on the status and `code`,
 * never on `message`, which is only for showing to the cashier.
 */
final readonly class KadiResult
{
    /**
     * The `code` given to a request that got no response (timeout or connection failure).
     */
    public const string UNREACHABLE = 'unreachable';

    /**
     * @param  int|null  $status  The HTTP status, or null when no response came back.
     * @param  array<string, mixed>  $data  The response's `data`.
     * @param  array<string, list<string>>  $errors  Field errors from a 422.
     * @param  array<string, mixed>  $body  The whole decoded response, for refusals that carry more (`attempts_left`, `float_balance`).
     * @param  int|null  $retryAfter  Seconds to wait, from a 429's Retry-After header.
     */
    public function __construct(
        public ?int $status,
        public ?string $code,
        public string $message,
        public array $data = [],
        public array $errors = [],
        public array $body = [],
        public ?int $retryAfter = null,
    ) {}

    public static function fromResponse(Response $response): self
    {
        $body = $response->json();
        $body = is_array($body) ? $body : [];
        $retryAfter = $response->header('Retry-After');

        return new self(
            status: $response->status(),
            code: is_string($body['code'] ?? null) ? $body['code'] : null,
            message: is_string($body['message'] ?? null) ? $body['message'] : '',
            data: is_array($body['data'] ?? null) ? $body['data'] : [],
            errors: is_array($body['errors'] ?? null) ? $body['errors'] : [],
            body: $body,
            retryAfter: is_numeric($retryAfter) ? (int) $retryAfter : null,
        );
    }

    public static function unreachable(): self
    {
        return new self(
            status: null,
            code: self::UNREACHABLE,
            message: 'Kadi Kings could not be reached.',
        );
    }

    public function ok(): bool
    {
        return $this->status !== null && $this->status >= 200 && $this->status < 300;
    }

    /**
     * No response, or a server error: the request may or may not have taken effect. Retry a
     * write only with the same Idempotency-Key, and never hand over cash on this.
     */
    public function outcomeUnknown(): bool
    {
        return $this->status === null || $this->status >= 500;
    }

    /**
     * The shop is suspended or closed: every action is blocked until Kadi Kings reopens it.
     */
    public function blocksShop(): bool
    {
        return $this->status === 403 && $this->code === 'shop_not_active';
    }

    /**
     * The key is wrong, revoked or missing.
     */
    public function invalidKey(): bool
    {
        return $this->status === 401;
    }

    /**
     * The configured key is not a shop key (it works elsewhere in KadiApi, never on /shop/*).
     */
    public function notShopKey(): bool
    {
        return $this->status === 403 && $this->code === null;
    }

    public function rateLimited(): bool
    {
        return $this->status === 429;
    }

    public function is(string $code): bool
    {
        return $this->code === $code;
    }

    /**
     * The `items` of a paged list (data.items), each an array.
     *
     * @return list<array<mixed>>
     */
    public function items(): array
    {
        $items = $this->data['items'] ?? null;

        return is_array($items) ? array_values(array_filter($items, is_array(...))) : [];
    }

    /**
     * The `pagination` of a paged list (data.pagination): page, per_page, total, last_page.
     *
     * @return array{page: int, per_page: int, total: int, last_page: int}|null
     */
    public function pagination(): ?array
    {
        $pagination = $this->data['pagination'] ?? null;

        if (! is_array($pagination) || ! isset($pagination['page'], $pagination['per_page'], $pagination['total'])) {
            return null;
        }

        return [
            'page' => (int) $pagination['page'],
            'per_page' => (int) $pagination['per_page'],
            'total' => (int) $pagination['total'],
            'last_page' => (int) ($pagination['last_page'] ?? 1),
        ];
    }

    /**
     * A top-level field of the response next to `code` and `message`, e.g. `attempts_left`.
     */
    public function field(string $key): mixed
    {
        return $this->body[$key] ?? null;
    }
}
