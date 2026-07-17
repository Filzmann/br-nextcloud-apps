#!/usr/bin/env bash
set -euo pipefail

workspace="$(cd "$(dirname "$0")/.." && pwd)"
cd "$workspace"

manifest='config/workspace-repositories.tsv'
canonical_skill='.agents/skills/work-in-nextcloud-app/SKILL.md'
required_parent_files=(
    AGENTS.md
    00_ki_projektkonfiguration_br_nextcloud_apps.md
    br-nextcloud-apps.code-workspace
    "$manifest"
    .codex/config.toml
    .codex/agents/explorer.toml
    .codex/agents/reviewer.toml
    .agents/skills/create-nextcloud-app/SKILL.md
    "$canonical_skill"
    .agents/skills/verify-workspace/SKILL.md
    .agents/skills/build-ad-suite-release/SKILL.md
    .agents/skills/evaluate-learning-candidate/SKILL.md
    docs/plans/codex-structure-correction.md
    docs/plans/codex-structure-migration.md
    docs/workspace.md
    scripts/check-fast
    scripts/check-full
    scripts/check-workspace-structure
    scripts/check-apps
    scripts/check-ad-suite-delivery
    scripts/verify-ad-suite-delivery.sh
    tests/check-codex-structure.sh
)
required_executables=(
    scripts/check-fast
    scripts/check-full
    scripts/check-workspace-structure
    scripts/check-apps
    scripts/check-ad-suite-delivery
    scripts/verify-ad-suite-delivery.sh
)

fail() {
    echo "Codex-Struktur ungültig: $*" >&2
    exit 1
}

for file in "${required_parent_files[@]}"; do
    [[ -f "$file" ]] || fail "Datei fehlt: $file"
    [[ ! -L "$file" ]] || fail "Steuerungsdatei darf kein Symlink sein: $file"
done
for script in "${required_executables[@]}"; do
    [[ -x "$script" ]] || fail "Prüfskript ist nicht ausführbar: $script"
done

command -v python3 >/dev/null 2>&1 || fail 'Python 3 fehlt; TOML- und Strukturprüfung kann nicht ausgeführt werden.'
if ! python3 -c 'import tomllib' >/dev/null 2>&1; then
    fail 'Python-Modul tomllib fehlt; Python 3.11+ ist für die TOML-Prüfung erforderlich.'
fi

python3 - <<'PY'
from __future__ import annotations

import json
import re
from pathlib import Path
import tomllib

workspace = Path.cwd().resolve()
manifest = workspace / 'config/workspace-repositories.tsv'


def fail(message: str) -> None:
    raise SystemExit(f'Codex-Struktur ungültig: {message}')


def parse_manifest() -> list[dict[str, str]]:
    lines = manifest.read_text(encoding='utf-8').splitlines()
    if not lines or lines[0] != 'path\tkind\tapp_id\trequired_skill':
        fail('Repository-Manifest hat keinen gültigen Header')
    rows: list[dict[str, str]] = []
    seen: set[str] = set()
    for number, line in enumerate(lines[1:], start=2):
        columns = line.split('\t')
        if len(columns) != 4:
            fail(f'Repository-Manifest Zeile {number} braucht vier Spalten')
        path, kind, app_id, required_skill = columns
        if path in seen:
            fail(f'Repository-Pfad ist doppelt: {path}')
        if path != '.' and (Path(path).is_absolute() or len(Path(path).parts) != 1):
            fail(f'Repository muss direkt unter dem Parent liegen: {path}')
        if kind not in {'parent', 'app', 'product-docs'}:
            fail(f'Unbekannter Repository-Typ für {path}: {kind}')
        if kind == 'app' and (app_id == '-' or required_skill == '-'):
            fail(f'App braucht App-ID und lokalen Pflicht-Skill: {path}')
        seen.add(path)
        rows.append({
            'path': path,
            'kind': kind,
            'app_id': app_id,
            'required_skill': required_skill,
        })
    if not rows or rows[0]['path'] != '.' or rows[0]['kind'] != 'parent':
        fail('Parent muss der erste Manifest-Eintrag sein')
    return rows


def parse_skill(skill_path: Path) -> tuple[str, str]:
    text = skill_path.read_text(encoding='utf-8')
    lines = text.splitlines()
    if len(lines) < 5 or lines[0] != '---':
        fail(f'Skill-Frontmatter fehlt: {skill_path.relative_to(workspace)}')
    try:
        closing = lines.index('---', 1)
    except ValueError:
        fail(f'Skill-Frontmatter ist nicht geschlossen: {skill_path.relative_to(workspace)}')
    frontmatter: dict[str, str] = {}
    for line in lines[1:closing]:
        if ': ' not in line:
            fail(f'Ungültige Frontmatter-Zeile in {skill_path.relative_to(workspace)}: {line}')
        key, value = line.split(': ', 1)
        frontmatter[key] = value.strip()
    if set(frontmatter) != {'name', 'description'}:
        fail(f'Skill-Frontmatter braucht genau name und description: {skill_path.relative_to(workspace)}')
    name = frontmatter['name']
    description = frontmatter['description']
    if not re.fullmatch(r'[a-z0-9-]+', name):
        fail(f'Ungültiger Skill-Name: {skill_path.relative_to(workspace)}')
    if name != skill_path.parent.name:
        fail(f'Skill-Name und Ordner stimmen nicht überein: {skill_path.relative_to(workspace)}')
    lowered = description.lower()
    if 'use ' not in lowered or 'do not' not in lowered:
        fail(f'Skill-Beschreibung braucht positiven und negativen Selektor: {skill_path.relative_to(workspace)}')
    return name, description


rows = parse_manifest()
manifest_paths = {row['path'] for row in rows}

workspace_catalog = workspace / 'br-nextcloud-apps.code-workspace'
try:
    workspace_config = json.loads(workspace_catalog.read_text(encoding='utf-8'))
except (json.JSONDecodeError, OSError) as error:
    fail(f'VS-Code-Workspace ist nicht lesbar: {error}')
folders = workspace_config.get('folders')
if not isinstance(folders, list):
    fail('VS-Code-Workspace braucht eine folders-Liste')
catalog_paths: list[str] = []
for number, folder in enumerate(folders, start=1):
    if not isinstance(folder, dict) or not isinstance(folder.get('path'), str):
        fail(f'VS-Code-Workspace-Ordner {number} braucht einen Pfad')
    catalog_paths.append(folder['path'])
if len(catalog_paths) != len(set(catalog_paths)):
    fail('VS-Code-Workspace enthält doppelte Repository-Pfade')
if set(catalog_paths) != manifest_paths:
    missing = sorted(manifest_paths - set(catalog_paths))
    unexpected = sorted(set(catalog_paths) - manifest_paths)
    fail(f'VS-Code-Workspace weicht vom Repository-Manifest ab; fehlt={missing}, unerwartet={unexpected}')

actual_paths: set[str] = set()
for git_dir in workspace.glob('**/.git'):
    if not git_dir.is_dir():
        continue
    repository = git_dir.parent.relative_to(workspace)
    actual_paths.add('.' if not repository.parts else repository.as_posix())
if actual_paths != manifest_paths:
    missing = sorted(actual_paths - manifest_paths)
    absent = sorted(manifest_paths - actual_paths)
    fail(f'Repository-Liste weicht ab; nicht gelistet={missing}, nicht vorhanden={absent}')

for row in rows:
    repo = workspace if row['path'] == '.' else workspace / row['path']
    agents = repo / 'AGENTS.md'
    if not agents.is_file() or agents.is_symlink():
        fail(f'Lokale, reguläre AGENTS.md fehlt: {row["path"]}')

    skill_root = repo / '.agents' / 'skills'
    skill_names: set[str] = set()
    if skill_root.exists():
        if skill_root.is_symlink():
            fail(f'.agents/skills darf kein Symlink sein: {row["path"]}')
        for candidate in sorted(skill_root.glob('*/SKILL.md')):
            if candidate.is_symlink() or candidate.parent.is_symlink():
                fail(f'Skill darf kein Symlink sein: {candidate.relative_to(workspace)}')
            name, _ = parse_skill(candidate)
            if name in skill_names:
                fail(f'Skill-Name ist im Repository doppelt: {row["path"]}/{name}')
            skill_names.add(name)

    agents_text = agents.read_text(encoding='utf-8')
    referenced = set(re.findall(r'(?i)\bskill\s+`([a-z0-9-]+)`', agents_text))
    missing_references = referenced - skill_names
    if missing_references:
        fail(f'AGENTS.md referenziert nicht lokale Skills in {row["path"]}: {sorted(missing_references)}')

    if row['kind'] == 'app':
        required = row['required_skill']
        local_skill = skill_root / required / 'SKILL.md'
        if required not in skill_names or not local_skill.is_file():
            fail(f'Lokaler Pflicht-Skill fehlt in {row["path"]}: {required}')
        canonical = workspace / '.agents' / 'skills' / required / 'SKILL.md'
        if local_skill.read_bytes() != canonical.read_bytes():
            fail(f'Lokale Skill-Kopie weicht von der kanonischen Fassung ab: {row["path"]}/{required}')
        if 'vollständige Repository-Steuerung' not in agents_text:
            fail(f'Direkte Standalone-Steuerung ist nicht erklärt: {row["path"]}/AGENTS.md')
        forbidden = ('Parent-Skill', 'Parent-`AGENTS.md` gilt ergaenzend', 'Parent-`AGENTS.md` gilt ergänzend')
        if any(fragment in agents_text for fragment in forbidden):
            fail(f'Unwirksame Parent-Laufzeitabhängigkeit in {row["path"]}/AGENTS.md')

canonical_text = (workspace / '.agents/skills/work-in-nextcloud-app/SKILL.md').read_text(encoding='utf-8')
required_contracts = (
    'file storage, **file paths**, uploads, downloads, or document generation',
    'The rollback path being unclear is a separate stop reason.',
    'Nextcloud-native group, user, session, AppConfig, share, file, capability, configuration, and request mechanisms must be used',
    'Never construct SQL fragments from request data.',
    'Develop executable UI logic test-first',
    'real employee, works-council, customer, mail, health, conflict, decision, or internal-document data',
    'semantically identically',
    'role="tablist"',
    'overflow-y: auto',
    '85 percent line coverage',
    'CLI memory limit',
    '`apps_paths`',
    'Nextcloud core paths stay read-only',
    '`custom_apps` path is writable',
    'static-webserver context can read assets and traverse',
    'HTTPS asset `403`',
    'Unexpectedly required external services also trigger a stop.',
    'the native option is demonstrably insufficient',
    'permissions, migration, maintenance, and interoperability',
    'approved before implementation',
    'stop before implementation',
    'DDEV paths, DDEV users, container paths, PHP binaries, database credentials',
    'must never be transferred to a production or hosting environment',
    'A switch between DDEV and production is an environment boundary',
    'If the target environment is unclear, stop before proceeding.',
)
for contract in required_contracts:
    if contract not in canonical_text:
        fail(f'Verbindlicher App-Skill-Vertrag fehlt: {contract}')

parent_text = (workspace / 'AGENTS.md').read_text(encoding='utf-8')
required_parent_contracts = (
    'nachweislich nicht ausreicht',
    'Berechtigungen, Migration, Wartung und Interoperabilität',
    'vor der Implementierung freigegeben',
    'muss Codex vor der Implementierung stoppen',
    'DDEV-Pfade, DDEV-Benutzer, Containerpfade, PHP-Binaries, Datenbankzugänge',
    'niemals auf eine Produktiv- oder Hostingumgebung übertragen',
    'Ein Wechsel zwischen DDEV und Produktion ist eine Umgebungsgrenze',
    'Bei unklarer Zielumgebung muss Codex stoppen.',
)
for contract in required_parent_contracts:
    if contract not in parent_text:
        fail(f'Verbindlicher Parent-Vertrag fehlt: {contract}')

create_skill_text = (workspace / '.agents/skills/create-nextcloud-app/SKILL.md').read_text(encoding='utf-8')
for contract in (
    'config/workspace-repositories.tsv',
    '.agents/skills/work-in-nextcloud-app/SKILL.md',
    'byte-for-byte',
    'REQUIRE_TRACKED_STRUCTURE=1 scripts/check-workspace-structure',
    'Do not report a new app as complete',
):
    if contract not in create_skill_text:
        fail(f'Verbindlicher Neue-App-Workflow fehlt: {contract}')

workspace_docs_text = (workspace / 'docs/workspace.md').read_text(encoding='utf-8')
for contract in (
    'nicht-kanonische, human-lesbare Übersicht',
    'reguläre lokale Kopie von `.agents/skills/work-in-nextcloud-app/SKILL.md`',
    'REQUIRE_TRACKED_STRUCTURE=1 scripts/check-workspace-structure',
    'Fehlt die Commit-Freigabe, bleibt dieser Punkt ausdrücklich offen.',
):
    if contract not in workspace_docs_text:
        fail(f'Verbindliche Workspace-Dokumentation fehlt: {contract}')

check_fast_text = (workspace / 'scripts/check-fast').read_text(encoding='utf-8')
delivery_wrapper_text = (workspace / 'scripts/check-ad-suite-delivery').read_text(encoding='utf-8')
delivery_verify_text = (workspace / 'scripts/verify-ad-suite-delivery.sh').read_text(encoding='utf-8')
parent_contract_scripts = (
    'check-ad-suite-coverage-baseline.sh',
    'check-ad-suite-ci-contract.sh',
    'check-ad-suite-standalone-contract.sh',
    'check-ad-product-installer.sh',
    'check-ad-release-pruning.sh',
)
for script in parent_contract_scripts:
    if script not in check_fast_text:
        fail(f'Parent-Contract-Test fehlt im kanonischen Fast-Pfad: {script}')
    if script in delivery_verify_text:
        fail(f'Parent-Contract-Test wird im Delivery-Verify doppelt ausgeführt: {script}')
if 'REQUIRE_TRACKED_STRUCTURE=1 "$workspace/scripts/check-fast"' not in delivery_wrapper_text:
    fail('Sauberes Delivery-Gate muss den strikten Parent-Fast-Pfad genau einmal ausführen')
if 'PARENT_FAST_CHECK_VERIFIED=1' not in delivery_wrapper_text or 'PARENT_FAST_CHECK_VERIFIED' not in delivery_verify_text:
    fail('Delivery-Verify muss durch den erfolgreich geprüften Parent-Fast-Pfad geschützt sein')


def require_string(value: object, label: str) -> str:
    if not isinstance(value, str) or not value.strip():
        fail(f'{label} muss eine nichtleere Zeichenkette sein')
    return value


config_path = workspace / '.codex/config.toml'
with config_path.open('rb') as handle:
    config = tomllib.load(handle)
if config.get('sandbox_mode') != 'read-only':
    fail('.codex/config.toml muss Parent-Standardzugriff read-only setzen')
if 'sandbox_workspace_write' in config:
    fail('sandbox_workspace_write widerspricht dem read-only Parent-Standard')
agents_config = config.get('agents')
if not isinstance(agents_config, dict):
    fail('.codex/config.toml: agents muss eine Tabelle sein')
if agents_config.get('max_depth') != 1 or agents_config.get('max_threads') != 3:
    fail('Subagent-Grenzen müssen max_depth=1 und max_threads=3 sein')
expected_agents = {'explorer', 'reviewer'}
declared = set(agents_config) - {'max_depth', 'max_threads'}
if declared != expected_agents:
    fail(f'Erwartete Agenten sind explorer/reviewer, gefunden: {sorted(declared)}')
for name in sorted(expected_agents):
    declaration = agents_config.get(name)
    if not isinstance(declaration, dict):
        fail(f'agents.{name} muss eine Tabelle sein')
    require_string(declaration.get('description'), f'agents.{name}.description')
    relative = Path(require_string(declaration.get('config_file'), f'agents.{name}.config_file'))
    if relative.is_absolute():
        fail(f'agents.{name}.config_file muss relativ sein')
    role_path = (config_path.parent / relative).resolve()
    if not role_path.is_relative_to(config_path.parent.resolve()) or not role_path.is_file():
        fail(f'Ungültiger Agentpfad: {relative}')
    with role_path.open('rb') as handle:
        role = tomllib.load(handle)
    if role.get('name') != name:
        fail(f'{role_path.relative_to(workspace)}: name muss {name} sein')
    if role.get('sandbox_mode') != 'read-only':
        fail(f'{role_path.relative_to(workspace)}: sandbox_mode muss read-only sein')
    instructions = require_string(role.get('developer_instructions'), f'{role_path}: developer_instructions')
    for required_phrase in ('Do not edit files', 'access the network', 'spawn subagents'):
        if required_phrase not in instructions:
            fail(f'{role_path.relative_to(workspace)}: Rollenverbot fehlt: {required_phrase}')
    forbidden_keys = {'writable_roots', 'add_dir', 'add_dirs', 'sandbox_workspace_write'}
    if forbidden_keys.intersection(role):
        fail(f'{role_path.relative_to(workspace)} enthält schreibende Sandbox-Optionen')
PY

tracking_warning=0
for control_file in "${required_parent_files[@]}"; do
    if git check-ignore -q "$control_file"; then
        fail "Parent-Steuerungsdatei wird ignoriert: $control_file"
    fi
    if ! git ls-files --error-unmatch "$control_file" >/dev/null 2>&1; then
        if [[ "${REQUIRE_TRACKED_STRUCTURE:-0}" == '1' ]]; then
            fail "Repository .: Pflichtdatei ist nicht getrackt: $control_file"
        fi
        echo "Hinweis: noch nicht getrackte Parent-Steuerungsdatei: $control_file"
        tracking_warning=1
    fi
done

while IFS=$'\t' read -r path kind app_id required_skill; do
    [[ "$path" == 'path' ]] && continue
    [[ "$path" == '.' ]] && continue
    repo="$workspace"
    [[ "$path" == '.' ]] || repo="$workspace/$path"

    control_files=(AGENTS.md)
    if [[ "$kind" == 'app' ]]; then
        control_files+=(".agents/skills/$required_skill/SKILL.md")
    elif [[ "$kind" == 'product-docs' ]]; then
        control_files+=("docs/DELIVERY-GATE.md")
    fi
    for control_file in "${control_files[@]}"; do
        if git -C "$repo" check-ignore -q "$control_file"; then
            fail "Steuerungsdatei wird ignoriert: $path/$control_file"
        fi
        if ! git -C "$repo" ls-files --error-unmatch "$control_file" >/dev/null 2>&1; then
            if [[ "${REQUIRE_TRACKED_STRUCTURE:-0}" == '1' ]]; then
                fail "Repository $path: Pflichtdatei ist nicht getrackt: $control_file"
            fi
            echo "Hinweis: noch nicht getrackte Steuerungsdatei: $path/$control_file"
            tracking_warning=1
        fi
    done
done < "$manifest"

if find .codex .agents -type l -print -quit | grep -q .; then
    fail 'Symlink in Parent-Steuerungsstruktur gefunden'
fi
while IFS=$'\t' read -r path kind app_id required_skill; do
    [[ "$kind" == 'app' ]] || continue
    if find "$path/.agents" -type l -print -quit | grep -q .; then
        fail "Symlink in lokaler App-Steuerungsstruktur gefunden: $path"
    fi
done < "$manifest"

apps=(brtop adplaner brstunden localbase br_permission_matrix adcalendar adurlaub orgsuite adroom ad-suite)
for app in "${apps[@]}"; do
    grep -Fxq "$app/" .gitignore || fail "Getrenntes Repository fehlt in .gitignore: $app"
    if git ls-files --error-unmatch "$app" >/dev/null 2>&1; then
        fail "Getrenntes Repository wird im Parent getrackt: $app"
    fi
done

if python3 -c 'import yaml' >/dev/null 2>&1; then
    while IFS= read -r yaml; do
        python3 -c 'import sys, yaml; yaml.safe_load(open(sys.argv[1], encoding="utf-8"))' "$yaml"
    done < <(git ls-files '*.yaml' '*.yml')
elif command -v ruby >/dev/null 2>&1; then
    while IFS= read -r yaml; do
        ruby -e 'require "yaml"; YAML.safe_load_file(ARGV.fetch(0), aliases: true)' "$yaml"
    done < <(git ls-files '*.yaml' '*.yml')
else
    echo 'Hinweis: YAML-Parser nicht verfügbar; YAML wurde nicht semantisch geparst.'
fi

while IFS= read -r markdown; do
    fences="$(grep -c '^```' "$markdown" || true)"
    (( fences % 2 == 0 )) || fail "Nicht geschlossenes Markdown-Codefence: $markdown"
done < <({ git ls-files '*.md'; git ls-files --others --exclude-standard '*.md'; } | sort -u)

if (( tracking_warning )); then
    echo 'STRUKTUR VORHANDEN – NICHT FREIGABEFÄHIG: PFLICHTDATEIEN UNGETRACKT'
else
    echo 'Codex-Struktur: OK'
fi
