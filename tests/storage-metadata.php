<?php

declare(strict_types=1);

require_once __DIR__ . '/../src/Services/MetaStore.php';
require_once __DIR__ . '/../src/Backend/StorageService.php';

use KloksWP\Services\MetaStore;
use KloksWP\Backend\StorageService;

$meta = [];
$options = [];
function metadata_exists(string $type, int $objectId, string $key): bool {
    global $meta;
    if ($type !== 'post') throw new RuntimeException('unexpected metadata type');
    return array_key_exists($key, $meta[$objectId] ?? []);
}
function get_post_meta(int $objectId, string $key, bool $single = false): mixed {
    global $meta;
    return array_key_exists($key, $meta[$objectId] ?? []) ? $meta[$objectId][$key] : '';
}
function update_post_meta(int $objectId, string $key, mixed $value): bool {
    global $meta;
    $meta[$objectId][$key] = $value;
    return true;
}
function delete_post_meta(int $objectId, string $key): bool {
    global $meta;
    unset($meta[$objectId][$key]);
    return true;
}
function get_option(string $key, mixed $default = null): mixed {
    global $options;
    return array_key_exists($key, $options) ? $options[$key] : $default;
}
function update_option(string $key, mixed $value, bool $autoload = false): bool {
    global $options;
    $options[$key] = $value;
    return true;
}
function verify(bool $ok, string $message): void {
    if (!$ok) throw new RuntimeException($message);
}

$store = new MetaStore();
$backend = new StorageService();
verify($store->get(1, 'missing', 'fallback') === 'fallback', 'missing meta defaults');
verify($backend->postMeta(1, 'missing', false) === false, 'backend missing meta defaults');
foreach (['empty' => '', 'zero' => '0', 'integer' => 0, 'boolean' => false, 'array' => [], 'null' => null] as $key => $value) {
    $meta[1][$key] = $value;
    verify($store->get(1, $key, 'fallback') === $value, "MetaStore must preserve $key");
    verify($backend->postMeta(1, $key, 'fallback') === $value, "StorageService must preserve $key");
}
verify($store->set(2, 'count', 0), 'meta write');
verify($store->get(2, 'count', 100) === 0, 'meta read after write');
verify($store->delete(2, 'count'), 'meta delete');
verify($store->get(2, 'count', 100) === 100, 'meta fallback after delete');
verify($backend->saveOption('flag', false), 'option write');
verify($backend->option('flag', 'fallback') === false, 'stored false option must survive');
echo "KloksWP metadata regression tests: OK\n";
