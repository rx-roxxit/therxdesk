<?php

declare(strict_types=1);

namespace Tests\Support;

use Illuminate\Support\Str;
use Illuminate\Support\Uri;
use Stripe\HttpClient\ClientInterface;

/**
 * Answers Stripe's Products and Prices API from memory, for the Catalog
 * tests. Every request is recorded with its nested params, so a test asserts
 * on what Stripe received. A retrieve of an id it never created answers a 404
 * `resource_missing`, which is how a Stripe key change looks; `failOn()`
 * answers a Stripe error for the matching request instead.
 */
final class FakeCatalogStripe implements ClientInterface
{
    /**
     * Every request, in order.
     *
     * @var list<array{method: string, path: string, params: array<string, mixed>}>
     */
    public array $requests = [];

    /**
     * @var array<string, array<string, mixed>>
     */
    private array $products = [];

    /**
     * @var array<string, array<string, mixed>>
     */
    private array $prices = [];

    /**
     * @var array{method: string, path: string, status: int, message: string}|null
     */
    private ?array $failure = null;

    /**
     * Ids stay unique across fake instances, as a real key change never hands
     * back an id the old key already used.
     */
    private string $nonce;

    public function __construct()
    {
        $this->nonce = Str::lower(Str::random(8));
    }

    /**
     * Makes the next request with this method and path answer a Stripe error
     * instead of succeeding.
     */
    public function failOn(string $method, string $path, int $status = 500, string $message = 'An internal error occurred.'): self
    {
        $this->failure = [
            'method' => strtolower($method),
            'path' => $path,
            'status' => $status,
            'message' => $message,
        ];

        return $this;
    }

    /**
     * @return list<array{method: string, path: string, params: array<string, mixed>}>
     */
    public function requestsFor(string $method, string $path): array
    {
        return array_values(array_filter(
            $this->requests,
            fn (array $request): bool => $request['method'] === strtolower($method) && $request['path'] === $path,
        ));
    }

    /**
     * Every request that is not a read: what a converged sync must not create.
     *
     * @return list<array{method: string, path: string, params: array<string, mixed>}>
     */
    public function writes(): array
    {
        return array_values(array_filter(
            $this->requests,
            fn (array $request): bool => $request['method'] !== 'get',
        ));
    }

    /**
     * @return array<string, mixed>|null
     */
    public function findProduct(string $id): ?array
    {
        return $this->products[$id] ?? null;
    }

    /**
     * @return array<string, mixed>|null
     */
    public function findPrice(string $id): ?array
    {
        return $this->prices[$id] ?? null;
    }

    /**
     * The ids this instance created, in creation order.
     *
     * @return list<string>
     */
    public function productIds(): array
    {
        return array_keys($this->products);
    }

    /**
     * @return list<string>
     */
    public function priceIds(): array
    {
        return array_keys($this->prices);
    }

    public function request($method, $absUrl, $headers, $params, $hasFile, $apiMode = 'v1', $maxNetworkRetries = null): array
    {
        $method = strtolower((string) $method);
        $uri = Uri::of($absUrl);
        $path = '/'.ltrim($uri->path(), '/');
        $params = is_array($params) ? $params : [];

        $this->requests[] = ['method' => $method, 'path' => $path, 'params' => $params];

        if ($this->shouldFail($method, $path)) {
            return $this->error($this->failure['status'], $this->failure['message']);
        }

        $segments = $uri->pathSegments();
        $resource = (string) $segments->get(1);
        $id = $segments->get(2);

        if ($resource === 'products') {
            return $this->product($method, $id, $params);
        }

        if ($resource === 'prices') {
            return $this->price($method, $id, $params);
        }

        return $this->error(404, "No such resource: {$path}");
    }

    /**
     * @param  array<string, mixed>  $params
     * @return array{0: string, 1: int, 2: array<string, string>}
     */
    private function product(string $method, ?string $id, array $params): array
    {
        if ($method === 'post' && $id === null) {
            $product = [
                'id' => 'prod_'.$this->nonce.(count($this->products) + 1),
                'object' => 'product',
                'active' => true,
                'name' => (string) data_get($params, 'name', ''),
                'metadata' => (array) data_get($params, 'metadata', []),
                'created' => now()->timestamp,
            ];
            $this->products[$product['id']] = $product;

            return $this->ok($product);
        }

        if ($id === null || ! isset($this->products[$id])) {
            return $this->missing('product', $id);
        }

        if ($method === 'post') {
            $this->products[$id] = $this->merge($this->products[$id], $params);
        }

        return $this->ok($this->products[$id]);
    }

    /**
     * @param  array<string, mixed>  $params
     * @return array{0: string, 1: int, 2: array<string, string>}
     */
    private function price(string $method, ?string $id, array $params): array
    {
        if ($method === 'post' && $id === null) {
            $price = [
                'id' => 'price_'.$this->nonce.(count($this->prices) + 1),
                'object' => 'price',
                'active' => true,
                'currency' => (string) data_get($params, 'currency', 'usd'),
                'unit_amount' => (int) data_get($params, 'unit_amount', 0),
                'product' => (string) data_get($params, 'product', ''),
                'recurring' => (array) data_get($params, 'recurring', []),
                'metadata' => (array) data_get($params, 'metadata', []),
            ];
            $this->prices[$price['id']] = $price;

            return $this->ok($price);
        }

        if ($id === null || ! isset($this->prices[$id])) {
            return $this->missing('price', $id);
        }

        if ($method === 'post') {
            $this->prices[$id] = $this->merge($this->prices[$id], $params);
        }

        return $this->ok($this->prices[$id]);
    }

    /**
     * @param  array<string, mixed>  $object
     * @param  array<string, mixed>  $params
     * @return array<string, mixed>
     */
    private function merge(array $object, array $params): array
    {
        foreach ($params as $key => $value) {
            if ($key === 'metadata' && is_array($value)) {
                $object['metadata'] = [...($object['metadata'] ?? []), ...$value];

                continue;
            }

            if ($key === 'active' && is_string($value)) {
                $object['active'] = $value === 'true';

                continue;
            }

            $object[$key] = $value;
        }

        return $object;
    }

    private function shouldFail(string $method, string $path): bool
    {
        if ($this->failure === null) {
            return false;
        }

        return $this->failure['method'] === $method && $this->failure['path'] === $path;
    }

    /**
     * @param  array<string, mixed>  $object
     * @return array{0: string, 1: int, 2: array<string, string>}
     */
    private function ok(array $object): array
    {
        return [json_encode($object, JSON_THROW_ON_ERROR), 200, []];
    }

    /**
     * @return array{0: string, 1: int, 2: array<string, string>}
     */
    private function missing(string $type, ?string $id): array
    {
        return $this->error(404, "No such {$type}: '{$id}'", [
            'type' => 'invalid_request_error',
            'code' => 'resource_missing',
        ]);
    }

    /**
     * @param  array<string, string>  $error
     * @return array{0: string, 1: int, 2: array<string, string>}
     */
    private function error(int $status, string $message, array $error = []): array
    {
        $body = json_encode([
            'error' => [
                'type' => 'api_error',
                'message' => $message,
                ...$error,
            ],
        ], JSON_THROW_ON_ERROR);

        return [$body, $status, []];
    }
}
