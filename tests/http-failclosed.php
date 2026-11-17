<?php
declare(strict_types=1);

require_once __DIR__ . '/../vendor/autoload.php';

use KloksWP\Services\HttpService;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;

function checkHttp(bool $ok, string $message): void {
    if (!$ok) throw new RuntimeException($message);
}
function failsHttp(callable $fn, string $message): void {
    try { $fn(); } catch (Throwable $e) { return; }
    throw new RuntimeException($message);
}

$client = new MockHttpClient([
    new MockResponse('{"ok":true}', ['http_code' => 200]),
    new MockResponse('{"error":"unauthorized"}', ['http_code' => 401]),
    new MockResponse('{"error":"throttled"}', ['http_code' => 429]),
    new MockResponse('', ['http_code' => 204]),
    new MockResponse('broken-json', ['http_code' => 200]),
]);
$service = new HttpService($client);
checkHttp($service->request('GET', 'https://api.example.test/ok') === ['ok' => true], 'Valid response');
failsHttp(fn() => $service->request('GET', 'https://api.example.test/unauthorized'), 'HTTP 401 cannot be success');
failsHttp(fn() => $service->request('POST', 'https://api.example.test/rate-limited'), 'HTTP 429 cannot be success');
checkHttp($service->request('DELETE', 'https://api.example.test/no-content') === [], '204 is explicit no-content');
failsHttp(fn() => $service->request('GET', 'https://api.example.test/invalid-json'), 'Invalid JSON cannot be a successful array');
echo "KloksWP HTTP fail-closed service regression: PASS\n";
