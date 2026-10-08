#!/usr/bin/env bash
set -euo pipefail

workspace="$(cd "$(dirname "$0")/.." && pwd)"

python3 - "$workspace" <<'PY'
import json
import pathlib
import sys
import xml.etree.ElementTree as ET

workspace = pathlib.Path(sys.argv[1])

apps = {
    "flzcalendar": "FlzCalendar",
    "flzplaner": "FlzPlaner",
    "flzurlaub": "FlzUrlaub",
    "flzroom": "FlzRoom",
    "flzrecruitment": "FlzRecruitment",
    "flzbqplanung": "FlzBqPlanning",
}
platform_apps = {
    "flz_data_protection": "FlzDataProtection",
    "flz_permission_matrix": "FlzPermissionMatrix",
}
repository_slugs = {
    "flzrecruitment": "nextcloud-flzrecruitment",
    "flz_data_protection": "nextcloud-flz-data-protection",
    "flz_permission_matrix": "nextcloud-flz-permission-matrix",
}
all_apps = {**apps, **platform_apps}

inventory = (workspace / "config/workspace-repositories.tsv").read_text(encoding="utf-8")
for app_id in all_apps:
    expected = f"{app_id}\tapp\t{app_id}\t"
    if expected not in inventory:
        raise SystemExit(f"Workspace-Inventar fehlt der kanonische FLZ-Eintrag: {app_id}")

for app_id, namespace in all_apps.items():
    info_file = workspace / app_id / "appinfo/info.xml"
    if not info_file.is_file():
        raise SystemExit(f"App-Metadaten fehlen: {info_file}")
    root = ET.parse(info_file).getroot()
    if root.findtext("id") != app_id:
        raise SystemExit(f"Falsche App-ID in {info_file}")
    if root.findtext("namespace") != namespace:
        raise SystemExit(f"Falscher Namespace in {info_file}")
    if app_id in repository_slugs:
        expected_bugs = f"https://github.com/Filzmann/{repository_slugs[app_id]}/issues"
        if root.findtext("bugs") != expected_bugs:
            raise SystemExit(f"Falscher Repository-Slug in {info_file}")

suite_readme = (workspace / "flz-full-suite" / "README.md").read_text(encoding="utf-8")
expected_recruitment_url = "https://github.com/Filzmann/nextcloud-flzrecruitment"
if expected_recruitment_url not in suite_readme:
    raise SystemExit("Filzmann-Full-Suite-Übersicht verweist nicht auf das kanonische Recruitment-Repository")

catalog_file = workspace / "localbase/resources/flz-product-catalog.json"
catalog = json.loads(catalog_file.read_text(encoding="utf-8"))
products = [entry for entry in catalog["entries"] if entry["kind"] == "product"]
if [entry["id"] for entry in products] != list(apps):
    raise SystemExit("FLZ-Produktkatalog enthält nicht die kanonische Produktreihenfolge")
if any(entry["suite"] != "flz" for entry in products):
    raise SystemExit("FLZ-Produktkatalog enthält einen fremden Suite-Key")

legacy_tokens = (
    "adcalendar", "adplaner", "adurlaub", "adroom", "adrecruitment", "adbqplanung",
    "OCA\\AdCalendar", "OCA\\AdPlaner", "OCA\\AdUrlaub", "OCA\\AdRoom", "OCA\\AdBqPlanning",
    "OCA\\Recruitment", "rec_", "ADPlaner", "ADRecruitmentContactLinks",
    "ADC-", "ADP-", "ADU-", "ADBQ-", "ADROOM_",
    "filzmann_data_protection", "filzmann_permission_matrix",
    "OCA\\FilzmannDataProtection", "OCA\\FilzmannPermissionMatrix", "fdp_",
)
runtime_roots = [workspace / app_id for app_id in all_apps]
runtime_roots.extend((workspace / "localbase", workspace / "orgsuite"))
extensions = {".php", ".js", ".mjs", ".json", ".xml", ".md"}
for root in runtime_roots:
    for path in root.rglob("*"):
        if (
            not path.is_file()
            or path.suffix not in extensions
            or {".git", "coverage", "vendor", "node_modules"}.intersection(path.parts)
        ):
            continue
        text = path.read_text(encoding="utf-8", errors="ignore")
        for token in legacy_tokens:
            if token in text:
                raise SystemExit(f"Legacy-Identifier {token!r} in {path.relative_to(workspace)}")

print("FLZ-Markenvertrag geprüft")
PY
