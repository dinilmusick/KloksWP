<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/src/Ai/AiProvider.php';

use KloksWP\Ai\AiProvider;
use KloksWP\Ai\MockAiProvider;

function expectMissing(string $name): void {
    try {
        AiProvider::get($name);
    } catch (RuntimeException $e) {
        if ($e->getMessage() === "AI provider is not registered: {$name}") {
            return;
        }
        throw $e;
    }
    throw new RuntimeException("Unregistered provider {$name} unexpectedly succeeded");
}

expectMissing('default');
expectMissing('unregistered');
AiProvider::register('default', new MockAiProvider());
if (AiProvider::get('default')->getName() !== 'mock') {
    throw new RuntimeException('Explicit test provider registration failed');
}
expectMissing('unregistered');
AiProvider::register('explicit-test', new MockAiProvider());
if (AiProvider::get('explicit-test')->getName() !== 'mock') {
    throw new RuntimeException('Named provider registration failed');
}
echo "Provider registration fail-closed checks passed\n";
