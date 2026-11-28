<?php

declare(strict_types=1);

$workflow = file_get_contents(dirname(__DIR__, 2) . '/.github/workflows/tests.yml');
if ($workflow === false) {
    throw new RuntimeException('Der LocalBase-Testworkflow konnte nicht gelesen werden.');
}

if (!str_contains($workflow, 'id: consumer-ref')
    || !str_contains($workflow, 'CANDIDATE_REF: ${{ github.head_ref || github.ref_name }}')
    || !str_contains($workflow, 'echo "ref=main" >> "$GITHUB_OUTPUT"')
    || !str_contains($workflow, 'echo "ref=$CANDIDATE_REF" >> "$GITHUB_OUTPUT"')) {
    throw new RuntimeException('Der Verbraucher-Vertragslauf wählt keinen konsistenten Migrations- oder Main-Stand.');
}

$repositories = [
    'Filzmann/nextcloud-orgsuite',
    'Filzmann/nextcloud-flzcalendar',
    'Filzmann/nextcloud-flzplaner',
    'Filzmann/nextcloud-flzurlaub',
    'Filzmann/nextcloud-flzroom',
    'Filzmann/nextcloud-brtop',
    'Filzmann/nextcloud-brstunden',
    'Filzmann/nextcloud-flz-permission-matrix',
    'Filzmann/nextcloud-flzrecruitment',
    'Filzmann/nextcloud-flz-organization',
];
foreach ($repositories as $repository) {
    if (!str_contains($workflow, $repository)) {
        throw new RuntimeException("Verbraucher-Repository {$repository} fehlt im koordinierten Vertragslauf.");
    }
}

if (substr_count($workflow, 'ref: ${{ steps.consumer-ref.outputs.ref }}') !== count($repositories)) {
    throw new RuntimeException('Nicht alle Verbraucher werden aus demselben koordinierten Git-Ref geprüft.');
}

echo "LocalBase consumer workflow ref contract passed\n";
