<?php

declare(strict_types=1);

/** @return array<string, mixed> */
function ftpiJsonObject(string $path): array
{
    if (!is_file($path) || is_link($path)) {
        throw new RuntimeException("Third-Party-Inventar fehlt oder ist keine reguläre Datei: {$path}");
    }
    $decoded = json_decode((string) file_get_contents($path), true, 512, JSON_THROW_ON_ERROR);
    if (!is_array($decoded) || array_is_list($decoded)) {
        throw new RuntimeException('Third-Party-Inventar ist kein JSON-Objekt.');
    }
    return $decoded;
}

function ftpiRelativePath(mixed $value): string
{
    if (!is_string($value) || $value === '' || str_contains($value, '\\')
        || str_starts_with($value, '/') || str_ends_with($value, '/')
        || preg_match('/[\x00-\x1f\x7f]/', $value)
        || preg_match('#(^|/)(?:\.|\.\.)(?:/|$)#', $value)) {
        throw new RuntimeException('Ungültiger app-relativer Third-Party-Pfad.');
    }
    return $value;
}

function ftpiRejectSymlinkSegments(string $appRoot, string $relativePath): void
{
    $candidate = rtrim($appRoot, '/');
    foreach (explode('/', $relativePath) as $segment) {
        $candidate .= '/' . $segment;
        if (is_link($candidate)) {
            throw new RuntimeException("Third-Party-Pfad enthält einen Symlink: {$relativePath}");
        }
    }
}

/** @return array<string, string> */
function ftpiProperties(mixed $raw, string $context): array
{
    if (!is_array($raw) || !array_is_list($raw)) {
        throw new RuntimeException("Third-Party-Properties fehlen: {$context}");
    }
    $properties = [];
    foreach ($raw as $property) {
        if (!is_array($property) || !is_string($property['name'] ?? null)
            || !is_string($property['value'] ?? null) || trim($property['value']) === '') {
            throw new RuntimeException("Ungültige Third-Party-Property: {$context}");
        }
        if (isset($properties[$property['name']])) {
            throw new RuntimeException("Doppelte Third-Party-Property: {$property['name']}");
        }
        $properties[$property['name']] = $property['value'];
    }
    return $properties;
}

function ftpiRequiredProperty(array $properties, string $name): string
{
    if (!isset($properties[$name])) {
        throw new RuntimeException("Third-Party-Pflichteigenschaft fehlt: {$name}");
    }
    return $properties[$name];
}

function ftpiRegularContainedFile(string $appRoot, string $relativePath): string
{
    ftpiRejectSymlinkSegments($appRoot, $relativePath);
    $root = realpath($appRoot);
    $path = realpath($appRoot . '/' . $relativePath);
    if ($root === false || $path === false || !is_file($path) || is_link($appRoot . '/' . $relativePath)
        || !str_starts_with($path, rtrim($root, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR)) {
        throw new RuntimeException("Third-Party-Datei fehlt oder verlässt den App-Root: {$relativePath}");
    }
    return $path;
}

/**
 * @return array{
 *   inventory_path: string,
 *   owner: string,
 *   components: list<array{purl: string, bom_ref: string, bundled_root: string, tree_sha256: string, file_count: int, component: array<string, mixed>}>
 * }|null
 */
function ftpiLoad(string $appRoot, string $expectedOwner): ?array
{
    $inventoryRelativePath = 'resources/third-party-components.cdx.json';
    $inventoryPath = rtrim($appRoot, '/') . '/' . $inventoryRelativePath;
    if (!file_exists($inventoryPath) && !is_link($inventoryPath)) {
        return null;
    }
    if (!preg_match('/^[a-z][a-z0-9_]{1,63}$/', $expectedOwner)) {
        throw new RuntimeException("Ungültige erwartete App-ID für Third-Party-Inventar: {$expectedOwner}");
    }

    ftpiRejectSymlinkSegments($appRoot, $inventoryRelativePath);

    $inventory = ftpiJsonObject($inventoryPath);
    if (($inventory['bomFormat'] ?? null) !== 'CycloneDX' || ($inventory['specVersion'] ?? null) !== '1.6'
        || !is_int($inventory['version'] ?? null) || $inventory['version'] < 1) {
        throw new RuntimeException('Third-Party-Inventar muss ein versioniertes CycloneDX-1.6-Dokument sein.');
    }
    $metadata = is_array($inventory['metadata'] ?? null) ? $inventory['metadata'] : [];
    $metadataProperties = ftpiProperties($metadata['properties'] ?? null, 'metadata');
    $owner = ftpiRequiredProperty($metadataProperties, 'filzmann:inventory-owner');
    if ($owner !== $expectedOwner) {
        throw new RuntimeException("Third-Party-Inventar-Owner stimmt nicht: erwartet {$expectedOwner}, erhalten {$owner}");
    }
    if (!is_array($inventory['components'] ?? null) || !array_is_list($inventory['components']) || $inventory['components'] === []) {
        throw new RuntimeException('Third-Party-Inventar enthält keine Komponenten.');
    }

    $validated = [];
    $seenRefs = [];
    $seenRoots = [];
    foreach ($inventory['components'] as $index => $component) {
        if (!is_array($component) || ($component['type'] ?? null) !== 'library'
            || ($component['scope'] ?? null) !== 'required') {
            throw new RuntimeException("Ungültige Third-Party-Library-Komponente: {$index}");
        }
        foreach (['name', 'version', 'purl', 'bom-ref'] as $field) {
            if (!is_string($component[$field] ?? null) || trim($component[$field]) === '') {
                throw new RuntimeException("Third-Party-Komponentenfeld fehlt: {$field}");
            }
        }
        $purl = $component['purl'];
        if ($component['bom-ref'] !== $purl
            || !preg_match('~^pkg:[a-z0-9.+-]+/[^@/?#]+@[^/?#]+$~', $purl)
            || !str_ends_with($purl, '@' . $component['version'])) {
            throw new RuntimeException("Third-Party-PURL ist nicht versionsgepinnt oder stimmt nicht mit bom-ref überein: {$purl}");
        }
        if (isset($seenRefs[$component['bom-ref']])) {
            throw new RuntimeException("Doppelte Third-Party-bom-ref: {$component['bom-ref']}");
        }
        $seenRefs[$component['bom-ref']] = true;

        $properties = ftpiProperties($component['properties'] ?? null, $purl);
        $bundledRoot = ftpiRelativePath(ftpiRequiredProperty($properties, 'filzmann:bundled-root'));
        $declaredTreeHash = ftpiRequiredProperty($properties, 'filzmann:bundled-tree-sha256');
        $declaredFileCount = ftpiRequiredProperty($properties, 'filzmann:bundled-file-count');
        if (!preg_match('/^[a-f0-9]{64}$/', $declaredTreeHash) || !preg_match('/^[1-9][0-9]*$/', $declaredFileCount)) {
            throw new RuntimeException("Ungültige Third-Party-Baumhash- oder Dateizahl: {$purl}");
        }
        foreach ($seenRoots as $otherRoot) {
            if ($bundledRoot === $otherRoot || str_starts_with($bundledRoot, $otherRoot . '/') || str_starts_with($otherRoot, $bundledRoot . '/')) {
                throw new RuntimeException("Überlappende Third-Party-Bundle-Roots: {$otherRoot}, {$bundledRoot}");
            }
        }
        $seenRoots[] = $bundledRoot;
        if (ftpiRequiredProperty($properties, 'filzmann:dependency-scan') !== 'osv-by-purl'
            || ftpiRequiredProperty($properties, 'filzmann:sast-treatment') !== 'pinned-third-party-source') {
            throw new RuntimeException("Third-Party-Scanvertrag ist nicht verbindlich: {$purl}");
        }
        ftpiRequiredProperty($properties, 'filzmann:runtime-scope');
        $licensePath = ftpiRelativePath(ftpiRequiredProperty($properties, 'filzmann:license-path'));
        if (!str_starts_with($licensePath, $bundledRoot . '/')) {
            throw new RuntimeException("Third-Party-Lizenzpfad liegt nicht im Bundle-Root: {$licensePath}");
        }
        ftpiRegularContainedFile($appRoot, $licensePath);

        ftpiRejectSymlinkSegments($appRoot, $bundledRoot);
        $bundleDirectory = realpath($appRoot . '/' . $bundledRoot);
        $appRealRoot = realpath($appRoot);
        if ($bundleDirectory === false || $appRealRoot === false || !is_dir($bundleDirectory)
            || is_link($appRoot . '/' . $bundledRoot)
            || !str_starts_with($bundleDirectory, rtrim($appRealRoot, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR)) {
            throw new RuntimeException("Third-Party-Bundle-Root fehlt oder verlässt den App-Root: {$bundledRoot}");
        }
        $files = [];
        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($bundleDirectory, FilesystemIterator::SKIP_DOTS),
        );
        foreach ($iterator as $file) {
            if ($file->isLink() || !$file->isFile()) {
                throw new RuntimeException("Third-Party-Baum enthält keine reguläre Datei: {$bundledRoot}");
            }
            $files[] = substr($file->getPathname(), strlen($bundleDirectory) + 1);
        }
        sort($files, SORT_STRING);
        if (count($files) !== (int) $declaredFileCount) {
            throw new RuntimeException("Third-Party-Dateizahl stimmt nicht: {$bundledRoot}");
        }
        $treeHash = hash_init('sha256');
        foreach ($files as $file) {
            hash_update($treeHash, $file . "\0");
            if (!hash_update_file($treeHash, $bundleDirectory . '/' . $file)) {
                throw new RuntimeException("Third-Party-Datei kann nicht gehasht werden: {$bundledRoot}/{$file}");
            }
        }
        if (!hash_equals($declaredTreeHash, hash_final($treeHash))) {
            throw new RuntimeException("Third-Party-Baumhash stimmt nicht: {$bundledRoot}");
        }

        if (!is_array($component['components'] ?? null) || !array_is_list($component['components']) || $component['components'] === []) {
            throw new RuntimeException("Gepinnte Third-Party-Dateihashes fehlen: {$purl}");
        }
        $pinnedFiles = [];
        foreach ($component['components'] as $fileComponent) {
            if (!is_array($fileComponent) || ($fileComponent['type'] ?? null) !== 'file'
                || ($fileComponent['scope'] ?? null) !== 'required') {
                throw new RuntimeException("Ungültige gepinnte Third-Party-Dateikomponente: {$purl}");
            }
            $filePath = ftpiRelativePath($fileComponent['name'] ?? null);
            if (($fileComponent['bom-ref'] ?? null) !== 'file:' . $filePath
                || !str_starts_with($filePath, $bundledRoot . '/') || isset($pinnedFiles[$filePath])
                || isset($seenRefs[$fileComponent['bom-ref'] ?? ''])) {
                throw new RuntimeException("Ungültiger gepinnter Third-Party-Dateipfad: {$filePath}");
            }
            $pinnedFiles[$filePath] = true;
            $seenRefs[$fileComponent['bom-ref']] = true;
            $hashes = is_array($fileComponent['hashes'] ?? null) ? $fileComponent['hashes'] : [];
            $sha256 = null;
            foreach ($hashes as $hash) {
                if (is_array($hash) && ($hash['alg'] ?? null) === 'SHA-256') {
                    if ($sha256 !== null) {
                        throw new RuntimeException("Doppelter SHA-256-Dateihash: {$filePath}");
                    }
                    $sha256 = $hash['content'] ?? null;
                }
            }
            if (!is_string($sha256) || !preg_match('/^[a-f0-9]{64}$/', $sha256)
                || !hash_equals($sha256, (string) hash_file('sha256', ftpiRegularContainedFile($appRoot, $filePath)))) {
                throw new RuntimeException("Gepinnter Third-Party-Dateihash stimmt nicht: {$filePath}");
            }
        }

        $validated[] = [
            'purl' => $purl,
            'bom_ref' => $component['bom-ref'],
            'bundled_root' => $bundledRoot,
            'tree_sha256' => $declaredTreeHash,
            'file_count' => (int) $declaredFileCount,
            'component' => $component,
        ];
    }

    return ['inventory_path' => $inventoryRelativePath, 'owner' => $owner, 'components' => $validated];
}
