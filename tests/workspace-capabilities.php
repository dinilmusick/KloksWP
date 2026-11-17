<?php
declare(strict_types=1);

require_once __DIR__ . '/../src/Backend/WorkspaceService.php';
use KloksWP\Backend\WorkspaceService;

final class MockWorkspaceError {
    public function __construct(private string $message) {}
    public function get_error_message(): string { return $this->message; }
}
$allowed = false;
$postsWritten = [];
$insertResult = 17;
$updateResult = 18;
function current_user_can(string $capability, mixed ...$arguments): bool {
    global $allowed;
    return $allowed;
}
function wp_insert_post(array $post, bool $wpError = false): mixed {
    global $postsWritten, $insertResult;
    $postsWritten[] = ['operation' => 'create', 'post' => $post];
    return $insertResult;
}
function wp_update_post(array $post, bool $wpError = false): mixed {
    global $postsWritten, $updateResult;
    $postsWritten[] = ['operation' => 'update', 'post' => $post];
    return $updateResult;
}
function is_wp_error(mixed $value): bool { return $value instanceof MockWorkspaceError; }
function checkWorkspace(bool $ok, string $message): void { if (!$ok) throw new RuntimeException($message); }
function failsWorkspace(callable $fn, string $message): void {
    try { $fn(); } catch (Throwable $e) { return; }
    throw new RuntimeException($message);
}

$service = new WorkspaceService();
failsWorkspace(fn() => $service->create('Test'), 'Anonymous creation must fail');
failsWorkspace(fn() => $service->update(123, ['post_title' => 'test']), 'Anonymous update must fail');
checkWorkspace(count($postsWritten) === 0, 'No writes without authorization');
$allowed = true;
failsWorkspace(fn() => $service->create('   '), 'Blank names must fail');
failsWorkspace(fn() => $service->update(0, []), 'Invalid ID must fail');
checkWorkspace($service->create('Test') === 17, 'Authorized creation');
checkWorkspace($service->update(12, ['ID' => 999, 'post_title' => 'Updated']) === 18, 'Authorized update');
checkWorkspace($postsWritten[1]['post']['ID'] === 12, 'Caller cannot replace authorized target ID');
$insertResult = new MockWorkspaceError('insert denied');
failsWorkspace(fn() => $service->create('Test'), 'WP insert error must throw');
$insertResult = 0;
failsWorkspace(fn() => $service->create('Test'), 'Zero insert ID must not be success');
$updateResult = new MockWorkspaceError('update failed');
failsWorkspace(fn() => $service->update(12, []), 'WP update error must throw');
$updateResult = 0;
failsWorkspace(fn() => $service->update(12, []), 'Zero update ID must not be success');
echo "KloksWP WordPress workspace permission/failure regression: PASS\n";
