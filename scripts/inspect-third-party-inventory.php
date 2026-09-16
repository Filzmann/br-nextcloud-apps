#!/usr/bin/env php
<?php

declare(strict_types=1);

require_once __DIR__ . '/lib/third-party-inventory.php';

$options = getopt('', ['repository-root:', 'owner:', 'output:']);
foreach (['repository-root', 'owner', 'output'] as $required) {
    if (!is_string($options[$required] ?? null) || trim($options[$required]) === '') {
        throw new InvalidArgumentException("Pflichtoption fehlt: --{$required}");
    }
}
$inventory = ftpiLoad($options['repository-root'], $options['owner']);
if ($inventory === null) {
    throw new RuntimeException('Third-Party-Inventar fehlt.');
}
$normalized = [
    'inventory_path' => $inventory['inventory_path'],
    'owner' => $inventory['owner'],
    'components' => array_map(static fn (array $component): array => [
        'purl' => $component['purl'],
        'bom_ref' => $component['bom_ref'],
        'bundled_root' => $component['bundled_root'],
        'tree_sha256' => $component['tree_sha256'],
        'file_count' => $component['file_count'],
    ], $inventory['components']),
];
$encoded = json_encode($normalized, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR) . "\n";
if (file_put_contents($options['output'], $encoded) === false) {
    throw new RuntimeException('Validiertes Third-Party-Inventar kann nicht geschrieben werden.');
}
