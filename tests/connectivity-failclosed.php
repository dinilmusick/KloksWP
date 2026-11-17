<?php
declare(strict_types=1);

require_once __DIR__ . '/../src/Backend/ConnectivityService.php';
use KloksWP\Backend\ConnectivityService;

final class MockWPError {
    public function __construct(private string $error) {}
    public function get_error_message(): string { return $this->error; }
}
$sent = [];
$responses = [];
function wp_json_encode(mixed $value): string|false { return json_encode($value); }
function wp_safe_remote_post(string $url, array $opts): mixed {
    global $sent, $responses;
    $sent[] = ['url' => $url, 'options' => $opts];
    return array_shift($responses);
}
function is_wp_error(mixed $value): bool { return $value instanceof MockWPError; }
function wp_remote_retrieve_response_code(array $response): int { return $response['status']; }
function wp_remote_retrieve_body(array $response): string { return $response['body']; }
function checkConnection(bool $ok, string $message): void { if (!$ok) throw new RuntimeException($message); }
function failsConnection(callable $fn, string $message): void {
    try { $fn(); } catch (Throwable $e) { return; }
    throw new RuntimeException($message);
}

$service = new ConnectivityService();
$responses = [
    ['status' => 200, 'body' => '{"ok":true}'],
    ['status' => 403, 'body' => '{"error":"forbidden"}'],
    ['status' => 429, 'body' => '{"error":"retry"}'],
    new MockWPError('Network failure'),
    ['status' => 200, 'body' => '<html>not json</html>'],
    ['status' => 200, 'body' => ''],
    ['status' => 204, 'body' => ''],
];
checkConnection($service->post('https://api.example.test/action', ['id' => 1]) === ['status' => 200, 'body' => ['ok' => true]], 'Valid JSON success');
checkConnection($sent[0]['options']['redirection'] === 0, 'Do not auto-follow redirects');
failsConnection(fn() => $service->post('https://api.example.test/forbidden'), 'HTTP 403 must throw');
failsConnection(fn() => $service->post('https://api.example.test/limited'), 'HTTP 429 must throw');
failsConnection(fn() => $service->post('https://api.example.test/error'), 'WP transport errors must throw');
failsConnection(fn() => $service->post('https://api.example.test/invalid'), 'Invalid JSON must throw');
failsConnection(fn() => $service->post('https://api.example.test/empty'), 'Unexpected empty JSON must throw');
checkConnection($service->post('https://api.example.test/no-content') === ['status' => 204, 'body' => []], '204 no-content is explicit');
$before = count($sent);
failsConnection(fn() => $service->post('https://api.example.test/bad-request-body', ['bad' => INF]), 'Invalid request encoding must throw');
checkConnection(count($sent) === $before, 'No outbound request after JSON encoding failure');
echo "KloksWP WordPress connectivity fail-closed regression: PASS\n";
