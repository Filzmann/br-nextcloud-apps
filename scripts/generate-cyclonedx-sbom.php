<?php

declare(strict_types=1);

require_once __DIR__ . '/lib/third-party-inventory.php';

/** @return array<string, string> */
function options(array $arguments): array
{
    $result = [];
    for ($index = 1, $count = count($arguments); $index < $count; $index += 2) {
        $key = $arguments[$index] ?? '';
        $value = $arguments[$index + 1] ?? null;
        if (!str_starts_with($key, '--') || $value === null) {
            throw new InvalidArgumentException('Optionen müssen als --name wert angegeben werden.');
        }
        $result[substr($key, 2)] = $value;
    }
    return $result;
}

/** @return list<array<string, string>> */
function manifestRows(string $path): array
{
    $handle = fopen($path, 'rb');
    if ($handle === false) {
        throw new RuntimeException("Manifest kann nicht gelesen werden: {$path}");
    }
    $header = fgetcsv($handle, null, "\t");
    if ($header !== ['app', 'version', 'git_commit', 'sha256', 'signed']) {
        throw new RuntimeException('Unbekanntes Release-Manifestformat.');
    }
    $rows = [];
    while (($row = fgetcsv($handle, null, "\t")) !== false) {
        if (count($row) !== count($header)) {
            throw new RuntimeException('Ungültige Manifestzeile.');
        }
        $entry = array_combine($header, $row);
        if ($entry === false || !preg_match('/^[a-f0-9]{40}$/', $entry['git_commit']) || !preg_match('/^[a-f0-9]{64}$/', $entry['sha256'])) {
            throw new RuntimeException('Ungültiger Commit oder SHA-256 im Manifest.');
        }
        $rows[] = $entry;
    }
    fclose($handle);
    return $rows;
}

$options = options($argv);
foreach (['manifest', 'output', 'name', 'version', 'nextcloud-major', 'repository-root'] as $required) {
    if (!isset($options[$required]) || trim($options[$required]) === '') {
        throw new InvalidArgumentException("Pflichtoption fehlt: --{$required}");
    }
}
if (!preg_match('/^[0-9]+$/', $options['nextcloud-major'])) {
    throw new InvalidArgumentException('Nextcloud-Major muss numerisch sein.');
}

$rows = manifestRows($options['manifest']);
$releaseVersions = [];
foreach ($rows as $row) {
    $releaseVersions[$row['app']] = $row['version'];
}
$nextcloudRef = 'platform:nextcloud@' . $options['nextcloud-major'];
$components = [[
    'type' => 'framework',
    'bom-ref' => $nextcloudRef,
    'name' => 'Nextcloud',
    'version' => $options['nextcloud-major'],
    'scope' => 'required',
    'properties' => [[
        'name' => 'filzmann:responsibility',
        'value' => 'externally operated platform; authentication, session, identity and framework baseline',
    ]],
]];
$rootDependencies = [$nextcloudRef];
$dependencies = [];
$componentRefs = [$nextcloudRef => true];
foreach ($releaseVersions as $app => $version) {
    $componentRefs['application:' . $app . '@' . $version] = true;
}
foreach ($rows as $row) {
    $componentRef = 'application:' . $row['app'] . '@' . $row['version'];
    $rootDependencies[] = $componentRef;
    $components[] = [
        'type' => 'application',
        'bom-ref' => $componentRef,
        'name' => $row['app'],
        'version' => $row['version'],
        'scope' => 'required',
        'hashes' => [['alg' => 'SHA-256', 'content' => $row['sha256']]],
        'properties' => [
            ['name' => 'filzmann:git-commit', 'value' => $row['git_commit']],
            ['name' => 'filzmann:nextcloud-signature', 'value' => $row['signed']],
        ],
    ];
    $infoPath = rtrim($options['repository-root'], '/') . '/' . $row['app'] . '/appinfo/info.xml';
    if (!is_file($infoPath)) {
        throw new RuntimeException("Kanonische App-Metadaten fehlen: {$row['app']}/appinfo/info.xml");
    }
    $info = simplexml_load_file($infoPath);
    if ($info === false || (string) $info->id !== $row['app']) {
        throw new RuntimeException("Kanonische App-ID stimmt nicht: {$row['app']}");
    }
    $componentDependencies = [$nextcloudRef];
    foreach ($info->dependencies->app ?? [] as $dependencyNode) {
        $dependency = trim((string) $dependencyNode);
        if ($dependency === '') {
            throw new RuntimeException("Leere App-Laufzeitabhängigkeit in {$row['app']}/appinfo/info.xml");
        }
        if (!isset($releaseVersions[$dependency])) {
            throw new RuntimeException("Deklarierte App-Laufzeitabhängigkeit fehlt im Release: {$row['app']} -> {$dependency}");
        }
        $componentDependencies[] = 'application:' . $dependency . '@' . $releaseVersions[$dependency];
    }
    $thirdPartyInventory = ftpiLoad(rtrim($options['repository-root'], '/') . '/' . $row['app'], $row['app']);
    if ($thirdPartyInventory !== null) {
        foreach ($thirdPartyInventory['components'] as $thirdParty) {
            if (isset($componentRefs[$thirdParty['bom_ref']])) {
                throw new RuntimeException("Doppelte SBOM-Komponentenreferenz: {$thirdParty['bom_ref']}");
            }
            $componentRefs[$thirdParty['bom_ref']] = true;
            $components[] = $thirdParty['component'];
            $componentDependencies[] = $thirdParty['bom_ref'];
        }
    }
    $dependencies[] = ['ref' => $componentRef, 'dependsOn' => array_values(array_unique($componentDependencies))];
}
array_unshift($dependencies, [
    'ref' => 'release:' . $options['name'] . '@' . $options['version'],
    'dependsOn' => $rootDependencies,
]);

$bom = [
    'bomFormat' => 'CycloneDX',
    'specVersion' => '1.6',
    'version' => 1,
    'metadata' => [
        'component' => [
            'type' => 'application',
            'bom-ref' => 'release:' . $options['name'] . '@' . $options['version'],
            'name' => $options['name'],
            'version' => $options['version'],
        ],
        'properties' => [[
            'name' => 'filzmann:dependency-qualification',
            'value' => 'Bundled applications come from the release manifest; their runtime app dependencies come from canonical appinfo/info.xml metadata. Build tooling is outside this release component inventory.',
        ]],
    ],
    'components' => $components,
    'dependencies' => $dependencies,
];

$encoded = json_encode($bom, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR) . "\n";
if (file_put_contents($options['output'], $encoded) === false) {
    throw new RuntimeException("SBOM kann nicht geschrieben werden: {$options['output']}");
}
