#!/usr/bin/env python3

import hashlib
import http.client
import importlib.util
import json
import os
from pathlib import Path
import tempfile
import threading
import unittest


MODULE_PATH = Path(__file__).resolve().parents[1] / "scripts" / "lib" / "manual_acceptance_viewer.py"
SPEC = importlib.util.spec_from_file_location("manual_acceptance_viewer", MODULE_PATH)
VIEWER = importlib.util.module_from_spec(SPEC)
assert SPEC.loader is not None
SPEC.loader.exec_module(VIEWER)


FORM = """# Manuelles Abnahmeformular

## Kopfdaten

- Datum:
- Prüfer*in: Noch offen

| Feld | Eintrag |
|---|---|
| Umgebung |  |
| Browser | Firefox |
| Gesamtentscheidung | [ ] abgenommen [ ] nicht abgenommen |

| ID | Prüfung | Ergebnis | Warum/Beleg/Abweichung |
|---|---|---|---|
| A1 | Start | [ ] erfolgreich [ ] nicht erfolgreich [ ] nicht geprüft | |
| A2 | Tastatur | [x] erfolgreich [ ] nicht erfolgreich [ ] nicht geprüft | mit Tab geprüft |
"""


class WorkspaceFixture(unittest.TestCase):
    def setUp(self):
        self.temp = tempfile.TemporaryDirectory()
        self.root = Path(self.temp.name)
        (self.root / "config").mkdir()
        (self.root / "docs").mkdir()
        (self.root / "app-one" / "docs" / "nested").mkdir(parents=True)
        (self.root / "ignored" / "docs").mkdir(parents=True)
        (self.root / "config" / "workspace-repositories.tsv").write_text(
            "path\tkind\tapp_id\trequired_skills\n"
            ".\tparent\t-\t-\n"
            "app-one\tapp\tapp_one\t-\n",
            encoding="utf-8",
        )
        (self.root / "docs" / "overview.md").write_text(
            "# Übersicht\n\nEin normales Dokument mit <script>alert(1)</script>.\n",
            encoding="utf-8",
        )
        self.form_path = self.root / "app-one" / "docs" / "manual-acceptance.md"
        self.form_path.write_text(FORM, encoding="utf-8")
        (self.root / "app-one" / "docs" / "nested" / "guide.md").write_text(
            "# Leitfaden\n", encoding="utf-8"
        )
        (self.root / "ignored" / "docs" / "hidden.md").write_text(
            "# Nicht registriert\n", encoding="utf-8"
        )
        os.symlink(
            self.root / "docs" / "overview.md",
            self.root / "app-one" / "docs" / "linked.md",
        )
        self.workspace = VIEWER.Workspace(self.root)

    def tearDown(self):
        self.temp.cleanup()


class DiscoveryTest(WorkspaceFixture):
    def test_discovers_only_regular_markdown_below_registered_docs(self):
        documents = self.workspace.list_documents()
        self.assertEqual(
            [document.id for document in documents],
            [
                "app-one/docs/manual-acceptance.md",
                "app-one/docs/nested/guide.md",
                "docs/overview.md",
            ],
        )
        self.assertTrue(documents[0].is_manual_acceptance)
        self.assertFalse(documents[1].is_manual_acceptance)

    def test_rejects_traversal_unknown_non_markdown_and_symlink(self):
        for document_id in (
            "../outside.md",
            "/etc/passwd",
            "ignored/docs/hidden.md",
            "app-one/docs/not-markdown.txt",
            "app-one/docs/linked.md",
        ):
            with self.subTest(document_id=document_id):
                with self.assertRaises(VIEWER.UnknownDocument):
                    self.workspace.load_document(document_id)

    def test_server_rejects_non_loopback_bind_before_opening_socket(self):
        with self.assertRaises(ValueError):
            VIEWER.create_server(self.workspace, "0.0.0.0", 0, "test-token")


class FormMappingTest(WorkspaceFixture):
    def test_renders_normal_markdown_safely_and_manual_fields_as_controls(self):
        normal = self.workspace.load_document("docs/overview.md")
        self.assertIn("<h1>Übersicht</h1>", normal.html)
        self.assertNotIn("<script>", normal.html)
        self.assertIn("&lt;script&gt;", normal.html)
        self.assertEqual(normal.fields, ())

        form = self.workspace.load_document("app-one/docs/manual-acceptance.md")
        kinds = [field.kind for field in form.fields]
        self.assertEqual(kinds.count("checkbox"), 8)
        self.assertEqual(kinds.count("text"), 6)
        self.assertIn('type="checkbox"', form.html)
        self.assertIn('data-field-id="text:', form.html)
        self.assertIn("Noch offen", form.html)

    def test_save_changes_only_recognized_spans_and_reload_returns_values(self):
        before = self.workspace.load_document("app-one/docs/manual-acceptance.md")
        values = {field.id: field.value for field in before.fields}
        checkbox = next(field for field in before.fields if field.kind == "checkbox" and not field.value)
        date = next(field for field in before.fields if field.kind == "text" and field.label == "Datum")
        note = next(
            field
            for field in before.fields
            if field.kind == "text" and field.label == "Warum/Beleg/Abweichung" and field.value == ""
        )
        values[checkbox.id] = True
        values[date.id] = "2026-10-03"
        values[note.id] = "synthetischer Beleg"

        saved = self.workspace.save_document(before.id, before.etag, values)
        source = self.form_path.read_text(encoding="utf-8")
        self.assertIn("- Datum: 2026-10-03", source)
        self.assertIn("[x] erfolgreich", source)
        self.assertIn("| synthetischer Beleg |", source)
        self.assertIn("| A2 | Tastatur", source)
        self.assertNotEqual(saved.etag, before.etag)
        self.assertEqual(hashlib.sha256(source.encode()).hexdigest(), saved.etag)
        reloaded = self.workspace.load_document(before.id)
        self.assertEqual(reloaded.etag, saved.etag)
        self.assertTrue(next(field for field in reloaded.fields if field.id == checkbox.id).value)

    def test_rejects_stale_or_invalid_save_without_side_effect(self):
        loaded = self.workspace.load_document("app-one/docs/manual-acceptance.md")
        original = self.form_path.read_bytes()
        values = {field.id: field.value for field in loaded.fields}

        with self.assertRaises(VIEWER.InvalidForm):
            self.workspace.save_document(loaded.id, loaded.etag, {**values, "unknown": "x"})
        self.assertEqual(self.form_path.read_bytes(), original)

        self.form_path.write_text(FORM + "\nExtern geändert.\n", encoding="utf-8")
        externally_changed = self.form_path.read_bytes()
        with self.assertRaises(VIEWER.StaleDocument):
            self.workspace.save_document(loaded.id, loaded.etag, values)
        self.assertEqual(self.form_path.read_bytes(), externally_changed)
        self.assertEqual(list(self.form_path.parent.glob(".manual-acceptance.md.*.tmp")), [])

    def test_second_writer_with_same_etag_is_rejected_without_overwrite(self):
        first = self.workspace.load_document("app-one/docs/manual-acceptance.md")
        second = self.workspace.load_document("app-one/docs/manual-acceptance.md")
        first_values = {field.id: field.value for field in first.fields}
        date = next(field for field in first.fields if field.kind == "text" and field.label == "Datum")
        first_values[date.id] = "2026-10-03"
        saved = self.workspace.save_document(first.id, first.etag, first_values)
        saved_content = self.form_path.read_bytes()

        second_values = {field.id: field.value for field in second.fields}
        second_values[date.id] = "2026-10-04"
        with self.assertRaises(VIEWER.StaleDocument):
            self.workspace.save_document(second.id, second.etag, second_values)
        self.assertEqual(self.form_path.read_bytes(), saved_content)
        self.assertIn("2026-10-03", saved.html)

    def test_normal_markdown_is_read_only(self):
        loaded = self.workspace.load_document("docs/overview.md")
        original = (self.root / "docs" / "overview.md").read_bytes()
        with self.assertRaises(VIEWER.InvalidForm):
            self.workspace.save_document(loaded.id, loaded.etag, {})
        self.assertEqual((self.root / "docs" / "overview.md").read_bytes(), original)

    def test_rejects_multiline_table_value_without_side_effect(self):
        loaded = self.workspace.load_document("app-one/docs/manual-acceptance.md")
        values = {field.id: field.value for field in loaded.fields}
        field = next(item for item in loaded.fields if item.kind == "text")
        values[field.id] = "erste Zeile\nzweite Zeile"
        original = self.form_path.read_bytes()
        with self.assertRaises(VIEWER.InvalidForm):
            self.workspace.save_document(loaded.id, loaded.etag, values)
        self.assertEqual(self.form_path.read_bytes(), original)


@unittest.skipUnless(
    os.environ.get("RUN_LOCAL_SOCKET_TESTS") == "1",
    "Lokale Socket-Integration wird explizit aktiviert",
)
class HttpBoundaryTest(WorkspaceFixture):
    def setUp(self):
        super().setUp()
        self.server = VIEWER.create_server(self.workspace, "127.0.0.1", 0, "test-token")
        self.thread = threading.Thread(target=self.server.serve_forever, daemon=True)
        self.thread.start()
        self.port = self.server.server_address[1]

    def tearDown(self):
        self.server.shutdown()
        self.server.server_close()
        self.thread.join(timeout=2)
        super().tearDown()

    def request(self, method, path, body=None, headers=None):
        connection = http.client.HTTPConnection("127.0.0.1", self.port, timeout=2)
        connection.request(method, path, body=body, headers=headers or {})
        response = connection.getresponse()
        payload = response.read()
        connection.close()
        return response.status, payload, response.getheaders()

    def test_local_api_lists_loads_and_saves_with_token(self):
        headers = {"Host": f"127.0.0.1:{self.port}", "X-Viewer-Token": "test-token"}
        status, payload, _ = self.request("GET", "/api/documents", headers=headers)
        self.assertEqual(status, 200)
        self.assertEqual(len(json.loads(payload)["documents"]), 3)

        status, payload, _ = self.request(
            "GET", "/api/document?id=app-one%2Fdocs%2Fmanual-acceptance.md", headers=headers
        )
        self.assertEqual(status, 200)
        loaded = json.loads(payload)
        values = {field["id"]: field["value"] for field in loaded["fields"]}
        first_checkbox = next(field for field in loaded["fields"] if field["kind"] == "checkbox")
        values[first_checkbox["id"]] = True
        request_body = json.dumps(
            {"id": loaded["id"], "etag": loaded["etag"], "values": values}
        ).encode()
        status, _, _ = self.request(
            "PUT",
            "/api/document",
            body=request_body,
            headers={**headers, "Content-Type": "application/json", "Content-Length": str(len(request_body))},
        )
        self.assertEqual(status, 200)
        self.assertIn("[x] erfolgreich", self.form_path.read_text(encoding="utf-8"))

    def test_rejects_foreign_host_missing_token_and_cross_origin_write(self):
        status, _, _ = self.request("GET", "/api/documents", headers={"Host": "attacker.example"})
        self.assertEqual(status, 403)
        status, _, _ = self.request(
            "GET", "/api/documents", headers={"Host": f"127.0.0.1:{self.port}"}
        )
        self.assertEqual(status, 403)
        status, _, _ = self.request(
            "PUT",
            "/api/document",
            body=b"{}",
            headers={
                "Host": f"127.0.0.1:{self.port}",
                "X-Viewer-Token": "test-token",
                "Origin": "https://attacker.example",
                "Content-Type": "application/json",
            },
        )
        self.assertEqual(status, 403)


if __name__ == "__main__":
    unittest.main()
