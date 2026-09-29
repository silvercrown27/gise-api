<?php

namespace App\Helpers;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use RuntimeException;

/**
 * Thin wrapper around the Paystack REST API.
 *
 * The secret key never leaves the server: every call here is server to
 * server. The browser is only ever handed Paystack's hosted checkout URL.
 * Amounts are in the currency's subunit (cents for USD, cents for KES).
 */
class Paystack
{
    public static function isConfigured(): bool
    {
        return filled(config('services.paystack.secret_key'));
    }

    /**
     * The public key is safe to share, but it is only needed for Paystack's
     * inline popup - the redirect checkout used at registration doesn't.
     */
    public static function publicKey(): ?string
    {
        return config('services.paystack.public_key');
    }

    /** A unique, unguessable reference for a new transaction. */
    public static function makeReference(): string
    {
        return 'GISE-' . Str::upper((string) Str::ulid());
    }

    /** Course fees are stored in whole units; Paystack wants subunits. */
    public static function toSubunits(int $amount): int
    {
        return $amount * 100;
    }

    /**
     * Start a transaction. Returns Paystack's `data` block:
     * authorization_url, access_code and reference.
     *
     * @throws RuntimeException when Paystack is unreachable or refuses.
     */
    public static function initialize(array $payload): array
    {
        return self::request('post', '/transaction/initialize', $payload);
    }

    /**
     * Look up a transaction by reference. Returns Paystack's `data` block,
     * whose `status` is success, failed, abandoned, ongoing, pending...
     *
     * @throws RuntimeException when Paystack is unreachable or refuses.
     */
    public static function verify(string $reference): array
    {
        return self::request('get', '/transaction/verify/' . rawurlencode($reference));
    }

    /**
     * Webhooks are signed with an HMAC-SHA512 of the raw request body using
     * the secret key. Anything that fails this check didn't come from Paystack.
     */
    public static function isValidSignature(string $payload, ?string $signature): bool
    {
        $secret = config('services.paystack.secret_key');

        if (blank($secret) || blank($signature)) {
            return false;
        }

        return hash_equals(hash_hmac('sha512', $payload, $secret), $signature);
    }

    /**
     * The parts of a Paystack transaction worth keeping for support. Card
     * authorization codes (reusable to charge the card), BINs and customer
     * details are deliberately dropped - we never store them.
     */
    public static function receipt(array $transaction): array
    {
        $authorization = $transaction['authorization'] ?? [];

        return array_filter([
            'id'               => $transaction['id'] ?? null,
            'status'           => $transaction['status'] ?? null,
            'reference'        => $transaction['reference'] ?? null,
            'amount'           => $transaction['amount'] ?? null,
            'currency'         => $transaction['currency'] ?? null,
            'channel'          => $transaction['channel'] ?? null,
            'gateway_response' => $transaction['gateway_response'] ?? null,
            'paid_at'          => $transaction['paid_at'] ?? $transaction['paidAt'] ?? null,
            'fees'             => $transaction['fees'] ?? null,
            'card_type'        => $authorization['card_type'] ?? null,
            'bank'             => $authorization['bank'] ?? null,
            'last4'            => $authorization['last4'] ?? null,
        ], fn ($value) => $value !== null);
    }

    private static function request(string $method, string $path, array $payload = []): array
    {
        if (!self::isConfigured()) {
            throw new RuntimeException('Paystack is not configured.');
        }

        try {
            $response = Http::withToken(config('services.paystack.secret_key'))
                ->acceptJson()
                ->timeout(20)
                ->baseUrl(rtrim(config('services.paystack.base_url'), '/'))
                ->{$method}($path, $payload);
        } catch (ConnectionException $e) {
            Log::error("Paystack {$path}: " . $e->getMessage());
            throw new RuntimeException('Could not reach the payment provider.');
        }

        if ($response->failed() || !$response->json('status')) {
            Log::warning("Paystack {$path} failed", ['status' => $response->status(), 'message' => $response->json('message')]);
            throw new RuntimeException($response->json('message') ?: 'The payment provider rejected the request.');
        }

        return $response->json('data') ?? [];
    }
}
