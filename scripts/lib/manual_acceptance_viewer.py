#!/usr/bin/env python3
"""Shared Markdown viewer with constrained manual-acceptance form editing."""

from __future__ import annotations

import csv
import hashlib
import html as html_module
from http.server import BaseHTTPRequestHandler, ThreadingHTTPServer
import json
import os
from pathlib import Path, PurePosixPath
import re
import stat
import tempfile
import threading
from typing import NamedTuple
from urllib.parse import parse_qs, urlparse


MAX_REQUEST_BYTES = 1_048_576
MAX_FIELD_CHARS = 10_000
CHECKBOX_RE = re.compile(r"\[([ xX])\]")
LIST_FIELD_RE = re.compile(r"^(\s*[-*]\s+)([^:\r\n]+):(.*?)(\r?\n)?$")
HEADING_RE = re.compile(r"^(#{1,6})\s+(.+?)\s*$")
TABLE_DIVIDER_RE = re.compile(r"^:?-{3,}:?$")


class ViewerError(Exception):
    """Base error for expected viewer failures."""


class UnknownDocument(ViewerError):
    """The requested document is outside the discovered allowlist."""


class StaleDocument(ViewerError):
    """The document changed after it was loaded."""


class InvalidForm(ViewerError):
    """Submitted values do not match the current form contract."""


class InvalidEvidence(ViewerError):
    """Synchronized evidence is unsafe, invalid or inconsistently bound."""


class DocumentRef(NamedTuple):
    id: str
    title: str
    repository: str
    is_manual_acceptance: bool
    app_id: str | None


class FormField(NamedTuple):
    id: str
    kind: str
    label: str
    value: object
    group: str | None


class DocumentView(NamedTuple):
    id: str
    title: str
    etag: str
    html: str
    fields: tuple[FormField, ...]
    is_manual_acceptance: bool
    candidate_evidence: tuple[dict[str, object], ...]
    evidence_error: str | None


class _EditableField(NamedTuple):
    public: FormField
    start: int
    end: int
    original: str
    context: str


def _sha256(content: bytes) -> str:
    return hashlib.sha256(content).hexdigest()


def _canonical_digest(value: dict[str, object]) -> str:
    payload = {key: item for key, item in value.items() if key != "integrity"}
    encoded = (
        json.dumps(payload, ensure_ascii=False, sort_keys=True, separators=(",", ":")) + "\n"
    ).encode("utf-8")
    return _sha256(encoded)


def _evidence_id(value: object, label: str) -> str:
    if not isinstance(value, str) or re.fullmatch(r"[A-Za-z0-9][A-Za-z0-9._-]{2,127}", value) is None:
        raise InvalidEvidence(f"Evidence enthält eine ungültige {label}")
    return value


def _evidence_file(directory: Path, identifier: str) -> dict[str, object]:
    _evidence_id(identifier, "ID")
    path = directory / f"{identifier}.json"
    try:
        metadata = path.lstat()
    except FileNotFoundError as error:
        raise InvalidEvidence(f"Evidence-Datei fehlt: {path.name}") from error
    if path.is_symlink() or not stat.S_ISREG(metadata.st_mode) or metadata.st_nlink != 1:
        raise InvalidEvidence(f"Evidence-Datei ist nicht sicher: {path.name}")
    flags = os.O_RDONLY
    if hasattr(os, "O_NOFOLLOW"):
        flags |= os.O_NOFOLLOW
    descriptor = os.open(path, flags)
    try:
        current = os.fstat(descriptor)
        if not stat.S_ISREG(current.st_mode) or current.st_nlink != 1:
            raise InvalidEvidence(f"Evidence-Datei ist nicht regulär: {path.name}")
        chunks: list[bytes] = []
        while True:
            chunk = os.read(descriptor, 65536)
            if not chunk:
                break
            chunks.append(chunk)
    finally:
        os.close(descriptor)
    try:
        value = json.loads(b"".join(chunks).decode("utf-8"))
    except (UnicodeDecodeError, json.JSONDecodeError) as error:
        raise InvalidEvidence(f"Evidence-Datei ist kein gültiges UTF-8-JSON: {path.name}") from error
    if not isinstance(value, dict):
        raise InvalidEvidence(f"Evidence-Datei enthält kein Objekt: {path.name}")
    integrity = value.get("integrity")
    if (
        not isinstance(integrity, dict)
        or integrity.get("algorithm") != "sha256"
        or integrity.get("sha256") != _canonical_digest(value)
    ):
        raise InvalidEvidence(f"Evidence-Integrität ist ungültig: {path.name}")
    return value


def _title(source: str, fallback: str) -> str:
    for line in source.splitlines():
        match = HEADING_RE.match(line)
        if match:
            return match.group(2).strip()
    return fallback


def _table_cells(line: str, line_start: int) -> list[tuple[str, int, int]]:
    body = line.rstrip("\r\n")
    delimiters: list[int] = []
    escaped = False
    for index, character in enumerate(body):
        if character == "\\" and not escaped:
            escaped = True
            continue
        if character == "|" and not escaped:
            delimiters.append(index)
        escaped = False
    if len(delimiters) < 2:
        return []
    cells = []
    for left, right in zip(delimiters, delimiters[1:]):
        start = line_start + left + 1
        end = line_start + right
        cells.append((body[left + 1 : right], start, end))
    return cells


def _is_table_divider(cells: list[tuple[str, int, int]]) -> bool:
    return bool(cells) and all(TABLE_DIVIDER_RE.fullmatch(cell.strip()) for cell, _, _ in cells)


def _field_id(kind: str, line_number: int, ordinal: int, label: str) -> str:
    label_key = re.sub(r"[^a-z0-9]+", "-", label.casefold()).strip("-")[:36] or "field"
    return f"{kind}:{line_number}:{ordinal}:{label_key}"


def _manual_fields(source: str) -> tuple[_EditableField, ...]:
    lines = source.splitlines(keepends=True)
    offsets: list[int] = []
    offset = 0
    for line in lines:
        offsets.append(offset)
        offset += len(line)

    editable: list[_EditableField] = []
    table_rows: set[int] = set()
    line_index = 0
    while line_index + 1 < len(lines):
        header_cells = _table_cells(lines[line_index], offsets[line_index])
        divider_cells = _table_cells(lines[line_index + 1], offsets[line_index + 1])
        if not header_cells or not _is_table_divider(divider_cells):
            line_index += 1
            continue
        headers = [cell.strip() for cell, _, _ in header_cells]
        row_index = line_index + 2
        while row_index < len(lines):
            cells = _table_cells(lines[row_index], offsets[row_index])
            if not cells or len(cells) != len(headers):
                break
            table_rows.add(row_index)
            for column, (raw, start, end) in enumerate(cells):
                header = headers[column]
                normalized_header = header.casefold()
                is_entry = len(headers) == 2 and column == 1 and headers[0].casefold() in {
                    "feld",
                    "angabe",
                }
                is_note = any(
                    marker in normalized_header
                    for marker in ("eintrag", "warum", "beleg", "abweich", "notiz", "kommentar")
                )
                if (is_entry or is_note) and not CHECKBOX_RE.search(raw):
                    value = raw.strip()
                    field = FormField(
                        _field_id("text", row_index + 1, column + 1, header),
                        "text",
                        header,
                        value,
                        None,
                    )
                    editable.append(_EditableField(field, start, end, raw, "table"))
            row_index += 1
        line_index = max(row_index, line_index + 1)

    for index, line in enumerate(lines):
        line_start = offsets[index]
        checkbox_ordinal = 0
        checkbox_matches = list(CHECKBOX_RE.finditer(line))
        group = f"choice:{index + 1}" if len(checkbox_matches) > 1 else None
        for match in checkbox_matches:
            checkbox_ordinal += 1
            label_tail = line[match.end() :].lstrip()
            label = re.split(r"\s+\[[ xX]\]|\|", label_tail, maxsplit=1)[0].strip(" |")
            public = FormField(
                _field_id("checkbox", index + 1, checkbox_ordinal, label),
                "checkbox",
                label or f"Auswahl {checkbox_ordinal}",
                match.group(1).casefold() == "x",
                group,
            )
            editable.append(
                _EditableField(
                    public,
                    line_start + match.start(),
                    line_start + match.end(),
                    match.group(0),
                    "checkbox",
                )
            )

        if index in table_rows:
            continue
        list_match = LIST_FIELD_RE.match(line)
        if not list_match:
            continue
        label = list_match.group(2).strip()
        raw = list_match.group(3)
        value = raw.strip()
        start = line_start + list_match.start(3)
        end = line_start + list_match.end(3)
        public = FormField(_field_id("text", index + 1, 1, label), "text", label, value, None)
        editable.append(_EditableField(public, start, end, raw, "list"))

    editable.sort(key=lambda item: (item.start, item.end))
    previous_end = -1
    for field in editable:
        if field.start < previous_end:
            raise InvalidForm(f"Überlappende Formularfelder: {field.public.id}")
        previous_end = field.end
    return tuple(editable)


def _control(field: _EditableField) -> str:
    public = field.public
    field_id = html_module.escape(public.id, quote=True)
    label = html_module.escape(public.label, quote=True)
    if public.kind == "checkbox":
        checked = " checked" if public.value else ""
        group = (
            f' data-choice-group="{html_module.escape(public.group, quote=True)}"'
            if public.group
            else ""
        )
        return (
            f'<input class="form-checkbox" type="checkbox" data-field-id="{field_id}"'
            f'{group} aria-label="{label}"{checked}>'
        )
    value = html_module.escape(str(public.value), quote=True)
    return (
        f'<input class="form-text" type="text" data-field-id="{field_id}" '
        f'aria-label="{label}" value="{value}">'
    )


def _render_fragment(source: str, absolute_start: int, fields: tuple[_EditableField, ...]) -> str:
    relevant = [field for field in fields if absolute_start <= field.start and field.end <= absolute_start + len(source)]
    if not relevant:
        return html_module.escape(source)
    result: list[str] = []
    cursor = 0
    for field in relevant:
        local_start = field.start - absolute_start
        local_end = field.end - absolute_start
        result.append(html_module.escape(source[cursor:local_start]))
        result.append(_control(field))
        cursor = local_end
    result.append(html_module.escape(source[cursor:]))
    return "".join(result)


def _render_markdown(source: str, fields: tuple[_EditableField, ...]) -> str:
    lines = source.splitlines(keepends=True)
    offsets: list[int] = []
    offset = 0
    for line in lines:
        offsets.append(offset)
        offset += len(line)

    output: list[str] = []
    index = 0
    in_code = False
    code_lines: list[str] = []
    while index < len(lines):
        line = lines[index]
        plain = line.rstrip("\r\n")
        if plain.startswith("```"):
            if in_code:
                output.append("<pre><code>" + html_module.escape("".join(code_lines)) + "</code></pre>")
                code_lines = []
                in_code = False
            else:
                in_code = True
            index += 1
            continue
        if in_code:
            code_lines.append(line)
            index += 1
            continue

        header_cells = _table_cells(line, offsets[index])
        next_cells = (
            _table_cells(lines[index + 1], offsets[index + 1]) if index + 1 < len(lines) else []
        )
        if header_cells and _is_table_divider(next_cells):
            output.append('<div class="table-scroll"><table><thead><tr>')
            for raw, start, _ in header_cells:
                output.append(f"<th>{_render_fragment(raw.strip(), start + len(raw) - len(raw.lstrip()), fields)}</th>")
            output.append("</tr></thead><tbody>")
            index += 2
            while index < len(lines):
                cells = _table_cells(lines[index], offsets[index])
                if not cells or len(cells) != len(header_cells):
                    break
                output.append("<tr>")
                for raw, start, _ in cells:
                    output.append(f"<td>{_render_fragment(raw, start, fields)}</td>")
                output.append("</tr>")
                index += 1
            output.append("</tbody></table></div>")
            continue

        heading = HEADING_RE.match(plain)
        if heading:
            level = len(heading.group(1))
            content = heading.group(2)
            content_start = offsets[index] + plain.index(content)
            output.append(f"<h{level}>{_render_fragment(content, content_start, fields)}</h{level}>")
        elif not plain.strip():
            output.append("")
        elif re.match(r"^\s*[-*]\s+", plain):
            marker = re.match(r"^(\s*[-*]\s+)", plain)
            assert marker is not None
            content = plain[marker.end() :]
            output.append(
                '<div class="list-item"><span aria-hidden="true">•</span><div>'
                + _render_fragment(content, offsets[index] + marker.end(), fields)
                + "</div></div>"
            )
        else:
            output.append(f"<p>{_render_fragment(plain, offsets[index], fields)}</p>")
        index += 1
    if in_code:
        output.append("<pre><code>" + html_module.escape("".join(code_lines)) + "</code></pre>")
    return "\n".join(output)


def _case_rows(source: str, answers: dict[str, object] | None = None) -> tuple[list[dict[str, object]], bool]:
    lines = source.splitlines(keepends=True)
    offsets: list[int] = []
    offset = 0
    for line in lines:
        offsets.append(offset)
        offset += len(line)
    fields = _manual_fields(source)
    fields_by_line: dict[int, list[_EditableField]] = {}
    for field in fields:
        parts = field.public.id.split(":", 3)
        if len(parts) >= 3 and parts[1].isdigit():
            fields_by_line.setdefault(int(parts[1]), []).append(field)

    result: list[dict[str, object]] = []
    independent = True
    seen: set[str] = set()
    index = 0
    while index + 1 < len(lines):
        header_cells = _table_cells(lines[index], offsets[index])
        divider_cells = _table_cells(lines[index + 1], offsets[index + 1])
        if not header_cells or not _is_table_divider(divider_cells):
            index += 1
            continue
        headers = [cell.strip() for cell, _, _ in header_cells]
        normalized = [header.casefold() for header in headers]
        try:
            id_column = normalized.index("id")
        except ValueError:
            index += 1
            continue
        result_column = next(
            (column for column, header in enumerate(normalized) if "ergebnis" in header), None
        )
        title_column = next(
            (column for column, header in enumerate(normalized) if "prüfung" in header), None
        )
        if result_column is None:
            index += 1
            continue
        reason_columns = {
            column
            for column, header in enumerate(normalized)
            if any(marker in header for marker in ("warum", "beleg", "abweich", "notiz", "kommentar"))
        }
        row_index = index + 2
        while row_index < len(lines):
            cells = _table_cells(lines[row_index], offsets[row_index])
            if not cells or len(cells) != len(headers):
                break
            values = [cell.strip() for cell, _, _ in cells]
            case_id = values[id_column]
            if not case_id or case_id in seen:
                independent = False
            seen.add(case_id)
            title = values[title_column] if title_column is not None else case_id
            fingerprint = tuple(
                (headers[column], value)
                for column, value in enumerate(values)
                if column != result_column and column not in reason_columns
            )
            previous_result = None
            previous_reason = ""
            if answers is not None:
                for field in fields_by_line.get(row_index + 1, []):
                    answer = answers.get(field.public.id)
                    if field.public.kind == "checkbox" and answer is True:
                        previous_result = field.public.label.casefold().strip()
                    elif field.public.kind == "text" and any(
                        marker in field.public.label.casefold()
                        for marker in ("warum", "beleg", "abweich", "notiz", "kommentar")
                    ):
                        previous_reason = str(answer or "").strip()
            result.append(
                {
                    "id": case_id,
                    "title": title,
                    "fingerprint": fingerprint,
                    "previous_result": previous_result,
                    "previous_reason": previous_reason,
                }
            )
            row_index += 1
        index = max(row_index, index + 1)
    return result, independent


def _rework(candidate: dict[str, object], evidence: dict[str, object], current_source: str) -> dict[str, object]:
    protocol = candidate["protocol"]
    assert isinstance(protocol, dict)
    answers = evidence.get("answers")
    if not isinstance(answers, dict):
        raise InvalidEvidence("Evidence-Antworten fehlen")
    historical, historical_independent = _case_rows(str(protocol["source"]), answers)
    current, current_independent = _case_rows(current_source)
    independent = historical_independent and current_independent and bool(historical) and bool(current)
    cases: list[dict[str, object]] = []
    if independent:
        historical_by_id = {str(item["id"]): item for item in historical}
        current_by_id = {str(item["id"]): item for item in current}
        for case in historical:
            case_id = str(case["id"])
            current_case = current_by_id.get(case_id)
            projected = {
                "id": case_id,
                "title": case["title"],
                "previous_result": case["previous_result"],
                "previous_reason": case["previous_reason"],
                "present_in_current": current_case is not None,
            }
            if current_case is None:
                projected.update(status="entfallen", recheck_required=False, reason="Prüffall ist in der aktuellen Vorlage entfallen.")
            elif case["previous_result"] != "erfolgreich":
                reason = "Vorheriges Ergebnis war nicht erfolgreich oder nicht geprüft."
                if case["fingerprint"] != current_case["fingerprint"]:
                    reason += " Der Prüffall wurde außerdem geändert."
                projected.update(status="erneut prüfen", recheck_required=True, reason=reason)
            elif case["fingerprint"] != current_case["fingerprint"]:
                projected.update(status="erneut prüfen", recheck_required=True, reason="Prüffall wurde geändert.")
            else:
                projected.update(
                    status="Vorergebnis sichtbar",
                    recheck_required=False,
                    reason=(
                        "Vorlage ist unverändert; bei fachlicher oder technischer Auswirkung "
                        "der Korrektur lokal als betroffen markieren."
                    ),
                )
            cases.append(projected)
        for case in current:
            if str(case["id"]) in historical_by_id:
                continue
            cases.append(
                {
                    "id": case["id"],
                    "title": case["title"],
                    "previous_result": None,
                    "previous_reason": "",
                    "present_in_current": True,
                    "status": "neu",
                    "recheck_required": True,
                    "reason": "Prüffall ist in der aktuellen Vorlage neu.",
                }
            )
        warning = None
    else:
        for case in historical:
            cases.append(
                {
                    "id": case["id"],
                    "title": case["title"],
                    "previous_result": case["previous_result"],
                    "previous_reason": case["previous_reason"],
                    "present_in_current": False,
                    "status": "historisch",
                    "recheck_required": False,
                    "reason": "Historisches Ergebnis bleibt sichtbar; eine eindeutige Zuordnung ist nicht möglich.",
                }
            )
        for case in current:
            cases.append(
                {
                    "id": case["id"],
                    "title": case["title"],
                    "previous_result": None,
                    "previous_reason": "",
                    "present_in_current": True,
                    "status": "erneut prüfen",
                    "recheck_required": True,
                    "reason": "Prüffall ist nicht eindeutig einem historischen Einzelfall zugeordnet.",
                }
            )
        warning = (
            "Die Vorlage benötigt eindeutige, stabile Fall-IDs. Breite oder doppelte Fälle "
            "müssen vor dem nächsten Candidate in unabhängig prüfbare Fälle geteilt werden."
        )
    return {"independent_cases": independent, "warning": warning, "cases": cases}


class EvidenceIndex:
    """Read-only projection of a synchronized immutable evidence checkout."""

    def __init__(self, root: Path):
        supplied = Path(root)
        if supplied.is_symlink():
            raise InvalidEvidence("Evidence-Root darf kein Symlink sein")
        try:
            self.root = supplied.resolve(strict=True)
        except FileNotFoundError as error:
            raise InvalidEvidence("Evidence-Root fehlt") from error
        if not self.root.is_dir():
            raise InvalidEvidence("Evidence-Root ist kein Verzeichnis")
        for directory in ("candidates", "deployments", "evidence"):
            child = self.root / directory
            if child.is_symlink() or not child.is_dir():
                raise InvalidEvidence(f"Evidence-Verzeichnis ist unsicher oder fehlt: {directory}")

    @staticmethod
    def _binding(candidate: dict[str, object], deployment: dict[str, object]) -> None:
        expected = (
            candidate.get("candidate_id"),
            candidate.get("repository"),
            candidate.get("app_id"),
            candidate.get("staging_target"),
            candidate.get("source_commit"),
            candidate.get("build"),
        )
        actual = (
            deployment.get("candidate_id"),
            deployment.get("repository"),
            deployment.get("app_id"),
            deployment.get("staging_target"),
            deployment.get("source_commit"),
            deployment.get("build"),
        )
        if actual != expected or deployment.get("environment") != "staging":
            raise InvalidEvidence(
                "Evidence-Deployment ist nicht an Candidate, Ziel, Commit und Build gebunden"
            )

    @staticmethod
    def _final_binding(
        candidate: dict[str, object], deployment: dict[str, object], evidence: dict[str, object]
    ) -> None:
        protocol = candidate.get("protocol")
        final_protocol = evidence.get("protocol")
        final_deployment = evidence.get("deployment")
        if not isinstance(protocol, dict) or not isinstance(final_protocol, dict) or not isinstance(final_deployment, dict):
            raise InvalidEvidence("Finale Evidence-Bindung ist unvollständig")
        expected = (
            candidate.get("candidate_id"),
            candidate.get("work_order_id"),
            candidate.get("repository"),
            candidate.get("source_commit"),
            candidate.get("build"),
            deployment.get("deployment_id"),
            deployment.get("staging_target"),
            protocol.get("path"),
            protocol.get("version"),
            protocol.get("sha256"),
        )
        actual = (
            evidence.get("candidate_id"),
            evidence.get("work_order_id"),
            evidence.get("repository"),
            evidence.get("source_commit"),
            evidence.get("build"),
            final_deployment.get("id"),
            final_deployment.get("staging_target"),
            final_protocol.get("path"),
            final_protocol.get("version"),
            final_protocol.get("sha256"),
        )
        if actual != expected or final_deployment.get("environment") != "staging":
            raise InvalidEvidence(
                "Finale Evidence ist nicht exakt an Candidate, Ziel und Staging-Deployment gebunden"
            )

    def for_app(self, app_id: str, current_source: str) -> tuple[dict[str, object], ...]:
        if re.fullmatch(r"[a-z][a-z0-9_]{1,63}", app_id) is None:
            return ()
        candidate_dir = self.root / "candidates"
        projected: list[dict[str, object]] = []
        for path in sorted(candidate_dir.glob("*.json")):
            candidate_id = path.stem
            candidate = _evidence_file(candidate_dir, candidate_id)
            if candidate.get("schema") != "filzmann.candidate/v2" or candidate.get("candidate_id") != candidate_id:
                raise InvalidEvidence(f"Candidate-Evidence ist ungültig: {path.name}")
            if candidate.get("app_id") != app_id:
                continue
            staging_target = candidate.get("staging_target")
            if (
                not isinstance(staging_target, str)
                or re.fullmatch(r"[a-z][a-z0-9-]{1,31}", staging_target) is None
            ):
                raise InvalidEvidence(f"Candidate-Ziel ist ungültig: {path.name}")
            protocol = candidate.get("protocol")
            build = candidate.get("build")
            if not isinstance(protocol, dict) or not isinstance(build, dict):
                raise InvalidEvidence(f"Candidate-Bindung ist unvollständig: {path.name}")
            source = protocol.get("source")
            if not isinstance(source, str) or protocol.get("sha256") != _sha256(source.encode("utf-8")):
                raise InvalidEvidence(f"Candidate-Protokolldigest ist ungültig: {path.name}")
            parsed_fields = [
                {
                    "id": field.public.id,
                    "kind": field.public.kind,
                    "label": field.public.label,
                    "group": field.public.group,
                }
                for field in _manual_fields(source)
            ]
            if protocol.get("fields") != parsed_fields:
                raise InvalidEvidence(f"Candidate-Protokollfelder sind ungültig: {path.name}")
            deployment_path = self.root / "deployments" / f"{candidate_id}.json"
            if not deployment_path.exists():
                continue
            deployment = _evidence_file(self.root / "deployments", candidate_id)
            if deployment.get("schema") != "filzmann.deployment/v2":
                raise InvalidEvidence(f"Deployment-Evidence ist ungültig: {candidate_id}")
            self._binding(candidate, deployment)
            item: dict[str, object] = {
                "candidate_id": candidate_id,
                "repository": candidate.get("repository"),
                "source_commit": candidate.get("source_commit"),
                "build": build,
                "issued_at": candidate.get("issued_at"),
                "deployment_id": deployment.get("deployment_id"),
                "staging_target": staging_target,
                "deployed_at": deployment.get("deployed_at"),
                "protocol": {
                    "path": protocol.get("path"),
                    "version": protocol.get("version"),
                    "sha256": protocol.get("sha256"),
                },
                "candidate_sha256": (candidate.get("integrity") or {}).get("sha256"),
                "deployment_sha256": (deployment.get("integrity") or {}).get("sha256"),
                "outcome": None,
                "rework": None,
            }
            evidence_path = self.root / "evidence" / f"{candidate_id}.json"
            if evidence_path.exists():
                evidence = _evidence_file(self.root / "evidence", candidate_id)
                if evidence.get("schema") != "filzmann.acceptance-evidence/v2" or evidence.get("outcome") not in {"PASS", "FAIL"}:
                    raise InvalidEvidence(f"Finale Evidence ist ungültig: {candidate_id}")
                self._final_binding(candidate, deployment, evidence)
                answers = evidence.get("answers")
                expected_ids = {field["id"] for field in parsed_fields}
                if not isinstance(answers, dict) or set(answers) != expected_ids:
                    raise InvalidEvidence(f"Finale Evidence-Antworten sind unvollständig: {candidate_id}")
                item.update(
                    outcome=evidence.get("outcome"),
                    comments=evidence.get("comments") if isinstance(evidence.get("comments"), str) else "",
                    submitted_at=evidence.get("submitted_at"),
                    evidence_sha256=(evidence.get("integrity") or {}).get("sha256"),
                )
                if evidence.get("outcome") == "FAIL":
                    item["rework"] = _rework(candidate, evidence, current_source)
            projected.append(item)
        projected.sort(key=lambda item: (str(item.get("issued_at") or ""), str(item["candidate_id"])), reverse=True)
        return tuple(projected)


class Workspace:
    """Discovers and edits allowlisted Markdown documents in one workspace."""

    def __init__(self, root: Path, *, evidence_root: Path | None = None):
        self.root = root.resolve(strict=True)
        self.inventory = self.root / "config" / "workspace-repositories.tsv"
        self._write_lock = threading.Lock()
        self._evidence: EvidenceIndex | None = None
        self._evidence_error: str | None = None
        if evidence_root is not None:
            try:
                self._evidence = EvidenceIndex(evidence_root)
            except InvalidEvidence as error:
                self._evidence_error = str(error)

    def _repository_roots(self) -> list[tuple[str, str | None, Path]]:
        roots: list[tuple[str, str | None, Path]] = []
        with self.inventory.open("r", encoding="utf-8", newline="") as handle:
            rows = csv.DictReader(handle, delimiter="\t")
            if rows.fieldnames is None or "path" not in rows.fieldnames:
                raise InvalidForm("Repositoryinventar besitzt keine path-Spalte")
            for row in rows:
                raw = row["path"]
                relative = PurePosixPath(raw)
                if relative.is_absolute() or ".." in relative.parts:
                    raise InvalidForm(f"Ungültiger Repositorypfad im Inventar: {raw}")
                repository = self.root if raw == "." else self.root.joinpath(*relative.parts)
                if repository.is_symlink() or not repository.is_dir():
                    continue
                resolved = repository.resolve(strict=True)
                if resolved != self.root and self.root not in resolved.parents:
                    continue
                app_id = row.get("app_id")
                roots.append((raw, app_id if app_id and app_id != "-" else None, repository))
        return roots

    def _discover(self) -> dict[str, tuple[DocumentRef, Path]]:
        discovered: dict[str, tuple[DocumentRef, Path]] = {}
        for repository_name, app_id, repository in self._repository_roots():
            docs = repository / "docs"
            if docs.is_symlink() or not docs.is_dir():
                continue
            for directory, directory_names, file_names in os.walk(docs, followlinks=False):
                directory_path = Path(directory)
                directory_names[:] = sorted(
                    name for name in directory_names if not (directory_path / name).is_symlink()
                )
                for file_name in sorted(file_names):
                    if not file_name.endswith(".md"):
                        continue
                    candidate = directory_path / file_name
                    try:
                        metadata = candidate.lstat()
                    except FileNotFoundError:
                        continue
                    if not stat.S_ISREG(metadata.st_mode):
                        continue
                    resolved = candidate.resolve(strict=True)
                    if docs.resolve(strict=True) not in resolved.parents:
                        continue
                    document_id = candidate.relative_to(self.root).as_posix()
                    try:
                        source = candidate.read_text(encoding="utf-8")
                    except (OSError, UnicodeError):
                        continue
                    reference = DocumentRef(
                        document_id,
                        _title(source, candidate.stem),
                        "Parent" if repository_name == "." else repository_name,
                        candidate.name == "manual-acceptance.md",
                        app_id,
                    )
                    discovered[document_id] = (reference, candidate)
        return discovered

    def list_documents(self) -> list[DocumentRef]:
        return [entry[0] for entry in sorted(self._discover().values(), key=lambda entry: entry[0].id)]

    def _path_for(self, document_id: str) -> tuple[DocumentRef, Path]:
        if not isinstance(document_id, str):
            raise UnknownDocument("Dokument-ID fehlt")
        entry = self._discover().get(document_id)
        if entry is None:
            raise UnknownDocument(f"Nicht freigegebenes Dokument: {document_id}")
        return entry

    @staticmethod
    def _read_regular(path: Path) -> tuple[bytes, os.stat_result]:
        flags = os.O_RDONLY
        if hasattr(os, "O_NOFOLLOW"):
            flags |= os.O_NOFOLLOW
        descriptor = os.open(path, flags)
        try:
            metadata = os.fstat(descriptor)
            if not stat.S_ISREG(metadata.st_mode):
                raise UnknownDocument("Dokument ist keine reguläre Datei")
            chunks: list[bytes] = []
            while True:
                chunk = os.read(descriptor, 65536)
                if not chunk:
                    break
                chunks.append(chunk)
            return b"".join(chunks), metadata
        finally:
            os.close(descriptor)

    def load_document(self, document_id: str) -> DocumentView:
        reference, path = self._path_for(document_id)
        content, _ = self._read_regular(path)
        try:
            source = content.decode("utf-8")
        except UnicodeDecodeError as error:
            raise InvalidForm("Markdown-Datei ist nicht UTF-8-kodiert") from error
        internal_fields = _manual_fields(source) if reference.is_manual_acceptance else ()
        candidate_evidence: tuple[dict[str, object], ...] = ()
        evidence_error = None
        if reference.is_manual_acceptance and reference.app_id and self._evidence is not None:
            try:
                candidate_evidence = self._evidence.for_app(reference.app_id, source)
            except InvalidEvidence as error:
                evidence_error = str(error)
        elif reference.is_manual_acceptance and reference.app_id:
            evidence_error = self._evidence_error
        return DocumentView(
            reference.id,
            reference.title,
            _sha256(content),
            _render_markdown(source, internal_fields),
            tuple(field.public for field in internal_fields),
            reference.is_manual_acceptance,
            candidate_evidence,
            evidence_error,
        )

    @staticmethod
    def _replacement(field: _EditableField, value: object) -> str:
        if field.public.kind == "checkbox":
            if type(value) is not bool:
                raise InvalidForm(f"Checkboxwert ist nicht boolesch: {field.public.id}")
            return "[x]" if value else "[ ]"
        if not isinstance(value, str):
            raise InvalidForm(f"Textwert ist keine Zeichenkette: {field.public.id}")
        if len(value) > MAX_FIELD_CHARS or "\n" in value or "\r" in value or "\0" in value:
            raise InvalidForm(f"Unzulässiger Textwert: {field.public.id}")
        if field.context == "table" and "|" in value:
            raise InvalidForm(f"Tabellenwert darf kein Pipe-Zeichen enthalten: {field.public.id}")
        if value == field.public.value:
            return field.original
        if field.context == "table":
            return f" {value} " if value else " "
        return f" {value}" if value else ""

    def save_document(self, document_id: str, etag: str, values: dict[str, object]) -> DocumentView:
        if not isinstance(etag, str) or not isinstance(values, dict):
            raise InvalidForm("ETag oder Formularwerte fehlen")
        with self._write_lock:
            reference, path = self._path_for(document_id)
            if not reference.is_manual_acceptance:
                raise InvalidForm("Normale Markdown-Dokumente sind schreibgeschützt")
            content, metadata = self._read_regular(path)
            if _sha256(content) != etag:
                raise StaleDocument("Dokument wurde seit dem Laden geändert")
            try:
                source = content.decode("utf-8")
            except UnicodeDecodeError as error:
                raise InvalidForm("Markdown-Datei ist nicht UTF-8-kodiert") from error
            fields = _manual_fields(source)
            expected_ids = {field.public.id for field in fields}
            if set(values) != expected_ids:
                raise InvalidForm("Formularfelder stimmen nicht mit dem geladenen Dokument überein")

            result = source
            for field in reversed(fields):
                replacement = self._replacement(field, values[field.public.id])
                result = result[: field.start] + replacement + result[field.end :]
            encoded = result.encode("utf-8")
            if encoded == content:
                return self.load_document(document_id)

            current = path.lstat()
            identity = (metadata.st_dev, metadata.st_ino, metadata.st_size, metadata.st_mtime_ns)
            if (current.st_dev, current.st_ino, current.st_size, current.st_mtime_ns) != identity:
                raise StaleDocument("Dokument wurde während des Speicherns geändert")

            descriptor, temporary_name = tempfile.mkstemp(
                prefix=f".{path.name}.", suffix=".tmp", dir=path.parent
            )
            temporary = Path(temporary_name)
            try:
                os.fchmod(descriptor, stat.S_IMODE(metadata.st_mode))
                written = 0
                while written < len(encoded):
                    written += os.write(descriptor, encoded[written:])
                os.fsync(descriptor)
                os.close(descriptor)
                descriptor = -1
                current = path.lstat()
                if (current.st_dev, current.st_ino, current.st_size, current.st_mtime_ns) != identity:
                    raise StaleDocument("Dokument wurde während des Speicherns geändert")
                os.replace(temporary, path)
                directory_fd = os.open(path.parent, os.O_RDONLY)
                try:
                    os.fsync(directory_fd)
                finally:
                    os.close(directory_fd)
            finally:
                if descriptor >= 0:
                    os.close(descriptor)
                try:
                    temporary.unlink()
                except FileNotFoundError:
                    pass
            return self.load_document(document_id)


def _document_json(document: DocumentView) -> dict[str, object]:
    return {
        "id": document.id,
        "title": document.title,
        "etag": document.etag,
        "html": document.html,
        "fields": [field._asdict() for field in document.fields],
        "is_manual_acceptance": document.is_manual_acceptance,
        "candidate_evidence": list(document.candidate_evidence),
        "evidence_error": document.evidence_error,
    }


def _asset(path: str) -> bytes:
    asset_path = Path(__file__).resolve().parents[1] / "assets" / path
    return asset_path.read_bytes()


def _page(token: str) -> bytes:
    safe_token = html_module.escape(token, quote=True)
    return f"""<!doctype html>
<html lang="de">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <meta name="viewer-token" content="{safe_token}">
  <title>Workspace-Dokumente</title>
  <link rel="stylesheet" href="/assets/manual-acceptance-viewer.css">
</head>
<body>
  <a class="skip-link" href="#document">Zum Dokument</a>
  <div class="layout">
    <aside>
      <h1>Dokumente</h1>
      <label for="filter">Dokumente filtern</label>
      <input id="filter" type="search" autocomplete="off">
      <nav id="documents" aria-label="Workspace-Dokumente"></nav>
    </aside>
    <main id="document" tabindex="-1">
      <div class="toolbar" role="toolbar" aria-label="Dokumentaktionen">
        <div><strong id="title">Dokument wählen</strong><span id="path"></span></div>
        <button id="reload" type="button" disabled>Neu laden</button>
        <button id="save" type="button" disabled>Speichern</button>
        <button id="print" type="button" disabled>Drucken / PDF</button>
      </div>
      <p id="status" role="status" aria-live="polite"></p>
      <div class="document-columns">
        <article id="content"></article>
        <aside id="evidence-panel" aria-labelledby="evidence-title" hidden>
          <h2 id="evidence-title">Candidate-Evidence</h2>
          <div id="evidence-content"></div>
        </aside>
      </div>
    </main>
  </div>
  <script src="/assets/manual-acceptance-viewer.js"></script>
</body>
</html>""".encode("utf-8")


def create_server(workspace: Workspace, host: str, port: int, token: str) -> ThreadingHTTPServer:
    if host not in {"127.0.0.1", "localhost"}:
        raise ValueError("Der Viewer darf nur an localhost gebunden werden")

    class Handler(BaseHTTPRequestHandler):
        server_version = "ManualAcceptanceViewer/1"

        def log_message(self, format_string: str, *args: object) -> None:
            print(f"{self.client_address[0]} - {format_string % args}")

        def _host_allowed(self) -> bool:
            host_header = self.headers.get("Host", "")
            parsed = urlparse(f"//{host_header}")
            return parsed.hostname in {"127.0.0.1", "localhost"}

        def _origin_allowed(self) -> bool:
            origin = self.headers.get("Origin")
            if not origin:
                return True
            parsed = urlparse(origin)
            return (
                parsed.scheme == "http"
                and parsed.hostname in {"127.0.0.1", "localhost"}
                and parsed.port == self.server.server_port
            )

        def _api_allowed(self) -> bool:
            return (
                self._host_allowed()
                and self._origin_allowed()
                and self.headers.get("X-Viewer-Token") == token
            )

        def _send(self, status_code: int, payload: bytes, content_type: str) -> None:
            self.send_response(status_code)
            self.send_header("Content-Type", content_type)
            self.send_header("Content-Length", str(len(payload)))
            self.send_header("Cache-Control", "no-store")
            self.send_header("X-Content-Type-Options", "nosniff")
            self.send_header("Content-Security-Policy", "default-src 'self'; script-src 'self'; style-src 'self'; base-uri 'none'; frame-ancestors 'none'; form-action 'none'")
            self.end_headers()
            self.wfile.write(payload)

        def _json(self, status_code: int, value: object) -> None:
            self._send(
                status_code,
                json.dumps(value, ensure_ascii=False).encode("utf-8"),
                "application/json; charset=utf-8",
            )

        def do_GET(self) -> None:
            parsed = urlparse(self.path)
            if not self._host_allowed():
                self._json(403, {"error": "Ungültiger Host"})
                return
            if parsed.path == "/":
                self._send(200, _page(token), "text/html; charset=utf-8")
                return
            if parsed.path == "/assets/manual-acceptance-viewer.css":
                self._send(200, _asset("manual-acceptance-viewer.css"), "text/css; charset=utf-8")
                return
            if parsed.path == "/assets/manual-acceptance-viewer.js":
                self._send(200, _asset("manual-acceptance-viewer.js"), "text/javascript; charset=utf-8")
                return
            if not self._api_allowed():
                self._json(403, {"error": "Lokale Viewer-Autorisierung fehlt"})
                return
            try:
                if parsed.path == "/api/documents":
                    self._json(200, {"documents": [item._asdict() for item in workspace.list_documents()]})
                    return
                if parsed.path == "/api/document":
                    document_ids = parse_qs(parsed.query).get("id", [])
                    if len(document_ids) != 1:
                        raise UnknownDocument("Eindeutige Dokument-ID fehlt")
                    self._json(200, _document_json(workspace.load_document(document_ids[0])))
                    return
                self._json(404, {"error": "Nicht gefunden"})
            except UnknownDocument as error:
                self._json(404, {"error": str(error)})
            except InvalidForm as error:
                self._json(400, {"error": str(error)})

        def do_PUT(self) -> None:
            parsed = urlparse(self.path)
            if not self._api_allowed():
                self._json(403, {"error": "Lokale Viewer-Autorisierung fehlt"})
                return
            if parsed.path != "/api/document":
                self._json(404, {"error": "Nicht gefunden"})
                return
            if self.headers.get_content_type() != "application/json":
                self._json(415, {"error": "JSON erforderlich"})
                return
            try:
                length = int(self.headers.get("Content-Length", "0"))
            except ValueError:
                length = 0
            if length <= 0 or length > MAX_REQUEST_BYTES:
                self._json(413, {"error": "Ungültige Anfragegröße"})
                return
            try:
                request = json.loads(self.rfile.read(length))
                if not isinstance(request, dict):
                    raise InvalidForm("JSON-Objekt erforderlich")
                saved = workspace.save_document(
                    request.get("id"), request.get("etag"), request.get("values")
                )
                self._json(200, _document_json(saved))
            except json.JSONDecodeError:
                self._json(400, {"error": "Ungültiges JSON"})
            except UnknownDocument as error:
                self._json(404, {"error": str(error)})
            except StaleDocument as error:
                self._json(409, {"error": str(error)})
            except InvalidForm as error:
                self._json(400, {"error": str(error)})

    return ThreadingHTTPServer((host, port), Handler)
