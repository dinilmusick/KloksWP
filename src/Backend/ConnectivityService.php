<?php
declare(strict_types=1);

namespace KloksWP\Backend;

/** WordPress-native outbound transport; never conceal provider failures. */
final class ConnectivityService {
    public function post(string $url, array $payload = []): array {
        if (!function_exists('wp_safe_remote_post') || !function_exists('wp_json_encode')) {
            throw new \RuntimeException('WordPress HTTP transport is unavailable.');
        }
        $body = wp_json_encode($payload);
        if (!is_string($body)) {
            throw new \JsonException('Unable to encode WordPress HTTP request body.');
        }
        // Use WordPress' SSRF-safe transport, not wp_remote_post.
        $response = wp_safe_remote_post($url, [
            'headers' => ['Content-Type' => 'application/json'],
            'body' => $body,
            'timeout' => 15,
            'redirection' => 0,
        ]);
        if (is_wp_error($response)) {
            throw new \RuntimeException('KloksWP WordPress HTTP transport error: ' . $response->get_error_message());
        }
        $status = (int) wp_remote_retrieve_response_code($response);
        if ($status < 200 || $status >= 300) {
            throw new \RuntimeException("KloksWP WordPress HTTP request failed with status {$status}.");
        }
        if ($status === 204 || $status === 205) {
            return ['status' => $status, 'body' => []];
        }
        $raw = (string) wp_remote_retrieve_body($response);
        if (trim($raw) === '') {
            throw new \UnexpectedValueException('KloksWP WordPress HTTP response body was empty.');
        }
        $decoded = json_decode($raw, true, 512, JSON_THROW_ON_ERROR);
        if (!is_array($decoded)) {
            throw new \UnexpectedValueException('KloksWP WordPress HTTP response must be a JSON object or list.');
        }
        return ['status' => $status, 'body' => $decoded];
    }
}
