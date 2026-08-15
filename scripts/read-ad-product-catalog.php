<?php

declare(strict_types=1);

use OCA\LocalBase\Catalog\AdProductCatalog;

$workspace = dirname(__DIR__);
$catalogFile = getenv('AD_PRODUCT_CATALOG_FILE') ?: $workspace . '/localbase/resources/ad-product-catalog.json';
require_once $workspace . '/localbase/lib/Catalog/AdProductCatalog.php';

$catalog = new AdProductCatalog($catalogFile);
$command = $argv[1] ?? '';

$writeLines = static function (array $values): void {
    foreach ($values as $value) {
        echo $value, "\n";
    }
};

try {
    switch ($command) {
        case 'validate':
            $catalog->entries();
            echo "AD-Produktkatalog: OK\n";
            break;
        case 'products':
            $writeLines(array_column($catalog->products(), 'id'));
            break;
        case 'bundle-products':
            $writeLines(array_column($catalog->bundleProducts(), 'id'));
            break;
        case 'full-suite':
            $writeLines($catalog->fullSuiteAppIds());
            break;
        case 'product-bundle':
            $writeLines($catalog->productBundleAppIds($argv[2] ?? ''));
            break;
        case 'field':
            $product = $catalog->product($argv[2] ?? '');
            $field = $argv[3] ?? '';
            if (!array_key_exists($field, $product) || is_array($product[$field])) {
                throw new InvalidArgumentException("Unbekanntes oder nicht-skalares Katalogfeld: {$field}");
            }
            $value = $product[$field];
            echo is_bool($value) ? ($value ? 'true' : 'false') : (string)$value;
            echo "\n";
            break;
        default:
            fwrite(STDERR, "Aufruf: read-ad-product-catalog.php validate|products|bundle-products|full-suite|product-bundle <Produkt>|field <Produkt> <Feld>\n");
            exit(2);
    }
} catch (Throwable $error) {
    fwrite(STDERR, $error->getMessage() . "\n");
    exit(1);
}
