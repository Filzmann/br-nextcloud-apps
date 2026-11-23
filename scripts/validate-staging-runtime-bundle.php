#!/usr/bin/env php
<?php

declare(strict_types=1);

if ($argc !== 3 || $argv[2] !== 'flzurlaub')
{
    fwrite(STDERR, "Aufruf: validate-staging-runtime-bundle.php <App-Root> flzurlaub\n");
    exit(2);
}

try
{
    $appRoot = rtrim($argv[1], '/');
    $bundleRoot = $appRoot . '/vendor/flz-shared';
    if (is_link($appRoot . '/vendor') || is_link($bundleRoot) || !is_dir($bundleRoot))
    {
        throw new RuntimeException('Das app-lokale Runtimebundle fehlt oder besitzt einen verlinkten Wurzelpfad.');
    }

    // Nur dieser gebaute Produktionsbestand gehört zum freigegebenen Bundle.
    // Neue Generatorausgaben benötigen eine erneute Prüfung dieses Paketvertrags.
    $expected = [
        'LICENSE',
        'flz-shared.lock.json',
        'php/composer.json',
        'php/src/Controller/ApiResponder.php',
        'php/src/Model/ModelApiTrait.php',
        'php/src/Service/AppLogger.php',
        'php/vendor/autoload.php',
        'php/vendor/composer/ClassLoader.php',
        'php/vendor/composer/LICENSE',
        'php/vendor/composer/autoload_classmap.php',
        'php/vendor/composer/autoload_namespaces.php',
        'php/vendor/composer/autoload_psr4.php',
        'php/vendor/composer/autoload_real.php',
        'php/vendor/composer/autoload_static.php',
        'php/vendor/composer/platform_check.php',
    ];
    $actual = [];
    $entries = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($bundleRoot, FilesystemIterator::SKIP_DOTS),
        RecursiveIteratorIterator::SELF_FIRST,
    );
    foreach ($entries as $entry)
    {
        $relative = substr($entry->getPathname(), strlen($bundleRoot) + 1);
        if ($entry->isLink())
        {
            throw new RuntimeException('Das Runtimebundle enthält einen Symlink.');
        }
        if ($entry->isDir())
        {
            $knownDirectory = false;
            foreach ($expected as $path)
            {
                $knownDirectory = $knownDirectory || str_starts_with($path, $relative . '/');
            }
            if (!$knownDirectory)
            {
                throw new RuntimeException('Das Runtimebundle enthält ein nicht freigegebenes Verzeichnis.');
            }
            continue;
        }
        if (!$entry->isFile())
        {
            throw new RuntimeException('Das Runtimebundle enthält einen nicht regulären Eintrag.');
        }
        $actual[] = $relative;
    }
    sort($actual, SORT_STRING);
    sort($expected, SORT_STRING);
    if ($actual !== $expected)
    {
        throw new RuntimeException('Das Runtimebundle besitzt nicht exakt den freigegebenen Produktionsbestand.');
    }

    $lock = json_decode(file_get_contents($bundleRoot . '/flz-shared.lock.json'), true, 512, JSON_THROW_ON_ERROR);
    $namespace = 'OCA\\FlzUrlaub\\Vendor\\Filzmann\\Shared';
    $phpPackage = $lock['packages']['php'] ?? [];
    if (($lock['schema'] ?? null) !== 1
        || ($lock['app_id'] ?? null) !== 'flzurlaub'
        || ($lock['app_namespace'] ?? null) !== 'FlzUrlaub'
        || ($lock['php_namespace'] ?? null) !== $namespace
        || ($phpPackage['name'] ?? null) !== 'filzmann/shared-runtime'
        || !is_string($phpPackage['version'] ?? null)
        || !preg_match('/^\d+\.\d+\.\d+(?:[-+][0-9A-Za-z.-]+)?$/D', $phpPackage['version'])
        || !is_string($phpPackage['sha256'] ?? null)
        || !preg_match('/^[a-f0-9]{64}$/D', $phpPackage['sha256']))
    {
        throw new RuntimeException('Der Runtime-Lock ist nicht gültig für das isolierte flzurlaub-Bundle.');
    }
    $composer = json_decode(file_get_contents($bundleRoot . '/php/composer.json'), true, 512, JSON_THROW_ON_ERROR);
    if (($composer['name'] ?? null) !== 'flzurlaub/flz-shared-runtime-bundle'
        || ($composer['autoload'] ?? null) !== ['psr-4' => [$namespace . '\\' => 'src/']]
        || array_keys($composer['require'] ?? []) !== ['php']
        || isset($composer['scripts'])
        || isset($composer['repositories'])
        || isset($composer['require-dev']))
    {
        throw new RuntimeException('Der Composer-Vertrag des Runtimebundles ist nicht app-isoliert.');
    }

    foreach ($expected as $relative)
    {
        echo 'vendor/flz-shared/' . $relative, "\n";
    }
}
catch (Throwable $error)
{
    fwrite(STDERR, 'Unsicheres Runtimebundle: ' . $error->getMessage() . "\n");
    exit(2);
}
