<?php
declare(strict_types=1);

namespace KloksWP\Services;

use Symfony\Component\HttpClient\HttpClient;
use Symfony\Contracts\HttpClient\HttpClientInterface;

final class HttpService {
    private HttpClientInterface $client;

    public function __construct(?HttpClientInterface $client = null) {
        $this->client = $client ?? HttpClient::create();
    }

    /**
     * Return decoded successful JSON only. HTTP failures must never be
     * interpreted as a successful provider response.
     */
    public function request(string $method, string $url, array $options = []): array {
        $response = $this->client->request($method, $url, $options);
        $status = $response->getStatusCode();
        if ($status < 200 || $status >= 300) {
            throw new \RuntimeException("KloksWP HTTP request failed with status {$status}.");
        }
        if ($status === 204 || $status === 205) return [];
        return $response->toArray(false);
    }
}
