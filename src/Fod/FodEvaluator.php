<?php

declare(strict_types=1);

namespace KloksWP\Fod;

/** Validates and evaluates a directed acyclic FOD graph in dependency order. */
final class FodEvaluator {
    public function evaluate(array $graph, array $initialInputs = []): array {
        $nodes = $graph['nodes'] ?? [];
        $wires = $graph['wires'] ?? [];
        if (!is_array($nodes) || !is_array($wires)) {
            throw new \InvalidArgumentException('FOD nodes and wires must be arrays');
        }
        $definitions = [];
        $inbound = [];
        $outbound = [];
        $indegree = [];

        foreach ($nodes as $node) {
            if (!is_array($node) || !is_string($node['id'] ?? null)
                || trim($node['id']) === '') {
                throw new \InvalidArgumentException('FOD nodes require nonempty IDs');
            }
            $id = $node['id'];
            if (isset($definitions[$id])) throw new \InvalidArgumentException("Duplicate FOD node ID: $id");
            if (array_key_exists($id, $initialInputs)) {
                throw new \InvalidArgumentException("FOD node conflicts with external input: $id");
            }
            $type = $node['type'] ?? 'pass';
            if (!is_string($type) || $type === '' || !is_array($node['config'] ?? [])) {
                throw new \InvalidArgumentException("Invalid FOD node type or config: $id");
            }
            if ($type !== 'pass' && !FodNodeProvider::has($type)) {
                throw new \InvalidArgumentException("Unknown FOD node type: $type");
            }
            $definitions[$id] = $node;
            $inbound[$id] = [];
            $outbound[$id] = [];
            $indegree[$id] = 0;
        }

        foreach ($wires as $wire) {
            if (!is_array($wire)) throw new \InvalidArgumentException('FOD wire must be an object');
            $source = $wire['sourceNodeId'] ?? null;
            $target = $wire['targetNodeId'] ?? null;
            $port = $wire['targetPort'] ?? 'default';
            if (!is_string($source) || $source === ''
                || !is_string($target) || !isset($definitions[$target])
                || !is_string($port) || $port === '') {
                throw new \InvalidArgumentException('FOD wire has invalid source, target or port');
            }
            if (!isset($definitions[$source]) && !array_key_exists($source, $initialInputs)) {
                throw new \InvalidArgumentException("FOD wire source does not exist: $source");
            }
            if (array_key_exists($port, $inbound[$target])) {
                throw new \InvalidArgumentException("Ambiguous FOD target port: $target.$port");
            }
            $inbound[$target][$port] = $source;
            if (isset($definitions[$source])) {
                $outbound[$source][] = $target;
                $indegree[$target]++;
            }
        }

        // Preflight the complete DAG before any potentially side-effecting node runs.
        $queue = new \SplQueue();
        foreach ($definitions as $id => $_) if ($indegree[$id] === 0) $queue->enqueue($id);
        $ordered = [];
        while (!$queue->isEmpty()) {
            $id = $queue->dequeue();
            $ordered[] = $id;
            foreach ($outbound[$id] as $target) {
                $indegree[$target]--;
                if ($indegree[$target] === 0) $queue->enqueue($target);
            }
        }
        if (count($ordered) !== count($definitions)) {
            throw new \RuntimeException('Circular FOD graph dependency detected');
        }

        $results = $initialInputs;
        foreach ($ordered as $id) {
            $node = $definitions[$id];
            $inputs = [];
            foreach ($inbound[$id] as $port => $source) {
                if (!array_key_exists($source, $results)) {
                    throw new \RuntimeException("FOD dependency result is missing: $source");
                }
                $inputs[$port] = $results[$source];
            }
            $type = $node['type'] ?? 'pass';
            if ($type === 'pass') $results[$id] = $inputs;
            else {
                if (!FodNodeProvider::has($type)) {
                    throw new \InvalidArgumentException("Unknown FOD node type: $type");
                }
                try {
                    $results[$id] = FodNodeProvider::execute($type, $inputs, $node['config'] ?? []);
                } catch (\Throwable $error) {
                    throw new \RuntimeException("FOD node '$id' failed: " . $error->getMessage(), 0, $error);
                }
            }
        }
        return $results;
    }
}
