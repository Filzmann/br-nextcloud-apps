<?php

declare(strict_types=1);

if ($argc < 2) {
    throw new InvalidArgumentException('Mindestens ein Releaseziel muss angegeben werden.');
}

foreach (array_slice($argv, 1) as $path) {
    if ($path === '') {
        throw new InvalidArgumentException('Leeres Releaseziel ist unzulässig.');
    }
    if (file_exists($path) || is_link($path)) {
        fwrite(STDERR, "Releaseziel existiert bereits: {$path}\n");
        exit(1);
    }
}
