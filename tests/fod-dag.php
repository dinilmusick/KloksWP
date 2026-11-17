<?php

declare(strict_types=1);

require_once __DIR__ . '/../src/Fod/FodNodeProvider.php';
require_once __DIR__ . '/../src/Fod/FodEvaluator.php';

use KloksWP\Fod\FodEvaluator;
use KloksWP\Fod\FodNodeProvider;

function verifyFod(bool $ok, string $message): void {
    if (!$ok) throw new RuntimeException($message);
}
function rejectFod(callable $fn, string $class, string $message): void {
    try { $fn(); }
    catch (Throwable $e) {
        verifyFod($e instanceof $class, "$message: got " . get_class($e));
        return;
    }
    throw new RuntimeException("$message: accepted invalid flow");
}

$executions = 0;
FodNodeProvider::register('const', static function (array $inputs, array $config) use (&$executions): int {
    $executions++;
    return $config['value'];
});
FodNodeProvider::register('sum', static function (array $inputs): int {
    return array_sum($inputs);
});
FodNodeProvider::register('fail', static fn() => throw new LogicException('provider down'));
$evaluator = new FodEvaluator();

$nodes = [
    ['id' => 'total', 'type' => 'sum'],
    ['id' => 'second', 'type' => 'const', 'config' => ['value' => 4]],
    ['id' => 'first', 'type' => 'const', 'config' => ['value' => 3]],
];
$wires = [
    ['sourceNodeId' => 'second', 'targetNodeId' => 'total', 'targetPort' => 'right'],
    ['sourceNodeId' => 'first', 'targetNodeId' => 'total', 'targetPort' => 'left'],
    ['sourceNodeId' => 'external', 'targetNodeId' => 'total', 'targetPort' => 'extra']
];
$graph = ['nodes' => $nodes, 'wires' => $wires];
$r1 = $evaluator->evaluate($graph, ['external' => 10]);
$r2 = $evaluator->evaluate(['nodes' => array_reverse($nodes), 'wires' => array_reverse($wires)], ['external' => 10]);
verifyFod($r1['total'] === 17 && $r2['total'] === 17, 'declaration-independent topological result');
verifyFod($r1['first'] === 3 && $r1['second'] === 4 && $executions === 4, 'provider executed once per node');

$nullWire = ['nodes' => [['id' => 'sink']], 'wires' =>
    [['sourceNodeId' => 'maybe', 'targetNodeId' => 'sink', 'targetPort' => 'value']]];
verifyFod(array_key_exists('value', $evaluator->evaluate($nullWire, ['maybe' => null])['sink']), 'null external result preserved');
verifyFod($evaluator->evaluate(['nodes' => []], ['external' => 42])['external'] === 42, 'empty graph retains input');

$cycle = [
    'nodes' => [['id' => 'a'], ['id' => 'b']],
    'wires' => [
        ['sourceNodeId' => 'a', 'targetNodeId' => 'b'],
        ['sourceNodeId' => 'b', 'targetNodeId' => 'a'],
    ],
];
rejectFod(fn() => $evaluator->evaluate($cycle), RuntimeException::class, 'cycle');
rejectFod(fn() => $evaluator->evaluate(['nodes' => [['id' => 'loop']], 'wires' =>
    [['sourceNodeId' => 'loop', 'targetNodeId' => 'loop']]]), RuntimeException::class, 'self-cycle');
rejectFod(fn() => $evaluator->evaluate(['nodes' => [['id' => 'a'], ['id' => 'a']]]),
    InvalidArgumentException::class, 'duplicate id');
rejectFod(fn() => $evaluator->evaluate(['nodes' => [['id' => 'a']]], ['a' => 1]),
    InvalidArgumentException::class, 'input collision');
rejectFod(fn() => $evaluator->evaluate(['nodes' => [['id' => 'a']], 'wires' =>
    [['sourceNodeId' => 'missing', 'targetNodeId' => 'a']]]),
    InvalidArgumentException::class, 'missing source');
rejectFod(fn() => $evaluator->evaluate(['nodes' => [['id' => 'a']], 'wires' =>
    [['sourceNodeId' => 'a', 'targetNodeId' => 'missing']]]),
    InvalidArgumentException::class, 'missing target');
rejectFod(fn() => $evaluator->evaluate(['nodes' => [['id' => 'a'], ['id' => 'b']],
    'wires' => [
        ['sourceNodeId' => 'a', 'targetNodeId' => 'b', 'targetPort' => 'x'],
        ['sourceNodeId' => 'a', 'targetNodeId' => 'b', 'targetPort' => 'x']
    ]]), InvalidArgumentException::class, 'ambiguous port');
rejectFod(fn() => $evaluator->evaluate(['nodes' => [['id' => 'a', 'config' => false]]]),
    InvalidArgumentException::class, 'invalid config');

$before = $executions;
rejectFod(fn() => $evaluator->evaluate(['nodes' => [
    ['id' => 'good', 'type' => 'const', 'config' => ['value' => 7]],
    ['id' => 'bad', 'type' => 'unknown-handler']
]]), InvalidArgumentException::class, 'unknown handler');
verifyFod($executions === $before, 'bad handler rejected before effects');
rejectFod(fn() => $evaluator->evaluate(['nodes' => [
    ['id' => 'good', 'type' => 'const', 'config' => ['value' => 7]],
    ['id' => 'a'], ['id' => 'b']
], 'wires' => [
    ['sourceNodeId' => 'a', 'targetNodeId' => 'b'],
    ['sourceNodeId' => 'b', 'targetNodeId' => 'a']
]]), RuntimeException::class, 'cyclic graph preflight');
verifyFod($executions === $before, 'cycle rejected before effects');

try {
    $evaluator->evaluate(['nodes' => [['id' => 'broken', 'type' => 'fail']]]);
    throw new RuntimeException('provider failure was suppressed');
} catch (RuntimeException $e) {
    verifyFod($e->getPrevious() instanceof LogicException, 'provider failure preserved as cause');
    verifyFod(str_contains($e->getMessage(), 'broken'), 'failing node identified');
}
echo "KloksWP FOD DAG tests: OK\n";
