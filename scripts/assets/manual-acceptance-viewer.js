(function () {
    'use strict';

    const token = document.querySelector('meta[name="viewer-token"]').content;
    const mode = document.querySelector('meta[name="viewer-mode"]');
    const acceptanceMode = Boolean(mode && mode.content === 'acceptance');
    const documentList = document.getElementById('documents');
    const content = document.getElementById('content');
    const title = document.getElementById('title');
    const path = document.getElementById('path');
    const status = document.getElementById('status');
    const saveButton = document.getElementById('save');
    const reloadButton = document.getElementById('reload');
    const printButton = document.getElementById('print');
    const filter = document.getElementById('filter');
    const outcome = document.getElementById('acceptance-outcome');
    const comments = document.getElementById('acceptance-comments');
    const evidencePanel = document.getElementById('evidence-panel');
    const evidenceContent = document.getElementById('evidence-content');
    let documents = [];
    let current = null;
    let dirty = false;

    async function api(url, options) {
        const response = await fetch(url, {
            ...options,
            headers: {
                'X-Viewer-Token': token,
                ...(options && options.headers ? options.headers : {}),
            },
        });
        const result = await response.json();
        if (!response.ok) {
            const error = new Error(result.error || `HTTP ${response.status}`);
            error.status = response.status;
            throw error;
        }
        return result;
    }

    function setStatus(message, isError) {
        status.textContent = message;
        status.classList.toggle('error', Boolean(isError));
    }

    function setDirty(value) {
        dirty = value;
        saveButton.disabled = !current || !current.is_manual_acceptance || !dirty;
        document.title = `${dirty ? '• ' : ''}${current ? current.title : 'Workspace-Dokumente'}`;
    }

    function bindFields() {
        content.querySelectorAll('[data-field-id]').forEach((control) => {
            if (!current.is_manual_acceptance) {
                control.disabled = true;
                return;
            }
            control.addEventListener('change', () => {
                const group = control.dataset.choiceGroup;
                if (group && control.checked) {
                    content.querySelectorAll('[data-choice-group]').forEach((candidate) => {
                        if (candidate !== control && candidate.dataset.choiceGroup === group) {
                            candidate.checked = false;
                        }
                    });
                }
                setDirty(true);
            });
            control.addEventListener('input', () => setDirty(true));
        });
    }

    function appendFact(list, label, value) {
        const term = document.createElement('dt');
        const detail = document.createElement('dd');
        term.textContent = label;
        detail.textContent = value || '—';
        list.append(term, detail);
    }

    function renderEvidence() {
        if (!evidencePanel || !evidenceContent) {
            return;
        }
        evidenceContent.replaceChildren();
        if (current.evidence_error) {
            const error = document.createElement('p');
            error.className = 'evidence-error';
            error.setAttribute('role', 'alert');
            error.textContent = current.evidence_error;
            evidenceContent.append(error);
            evidencePanel.hidden = false;
            return;
        }
        const items = current.candidate_evidence || [];
        evidencePanel.hidden = items.length === 0;
        items.forEach((item) => {
            const details = document.createElement('details');
            const summary = document.createElement('summary');
            const facts = document.createElement('dl');
            summary.textContent = `${item.candidate_id} · ${item.outcome || 'noch keine finale Evidence'}`;
            appendFact(facts, 'Source-Commit', item.source_commit);
            appendFact(facts, 'Build', `${item.build.id} · SHA-256 ${item.build.sha256}`);
            appendFact(facts, 'Staging-Ziel', item.staging_target);
            appendFact(facts, 'Staging-Deployment', item.deployment_id);
            appendFact(facts, 'Protokoll', `${item.protocol.version} · SHA-256 ${item.protocol.sha256}`);
            appendFact(facts, 'Candidate-Digest', item.candidate_sha256);
            appendFact(facts, 'Deployment-Digest', item.deployment_sha256);
            if (item.evidence_sha256) {
                appendFact(facts, 'Finaler Evidence-Digest', item.evidence_sha256);
            }
            details.append(summary, facts);

            const immutable = document.createElement('p');
            immutable.className = 'immutable-note';
            immutable.textContent = 'Unveränderliche, nur lesbare Evidence. Lokale Formularwerte wirken nicht auf diesen Stand.';
            details.append(immutable);
            if (item.comments) {
                const comment = document.createElement('p');
                comment.textContent = `Finaler Kommentar: ${item.comments}`;
                details.append(comment);
            }
            if (item.rework) {
                const heading = document.createElement('h3');
                heading.textContent = 'Lokale FAIL-Nacharbeit';
                details.append(heading);
                const localOnly = document.createElement('p');
                localOnly.className = 'immutable-note';
                localOnly.textContent = 'Nur lokal für diese Ansicht: Weitere fachlich oder technisch betroffene Fälle können markiert werden. Die Auswahl wird weder gespeichert noch an den Harness gesendet.';
                details.append(localOnly);
                if (item.rework.warning) {
                    const warning = document.createElement('p');
                    warning.className = 'evidence-warning';
                    warning.textContent = item.rework.warning;
                    details.append(warning);
                }
                const tableWrapper = document.createElement('div');
                tableWrapper.className = 'table-scroll';
                const table = document.createElement('table');
                const head = document.createElement('thead');
                const headRow = document.createElement('tr');
                ['Fall', 'Bisher', 'Befund', 'Nächster Candidate'].forEach((label) => {
                    const cell = document.createElement('th');
                    cell.scope = 'col';
                    cell.textContent = label;
                    headRow.append(cell);
                });
                head.append(headRow);
                const body = document.createElement('tbody');
                item.rework.cases.forEach((testCase) => {
                    const row = document.createElement('tr');
                    row.className = testCase.recheck_required ? 'recheck-required' : 'carried-result';
                    const name = document.createElement('th');
                    name.scope = 'row';
                    name.textContent = `${testCase.id || '(ohne ID)'} · ${testCase.title}`;
                    const previous = document.createElement('td');
                    previous.textContent = testCase.previous_result || '—';
                    const finding = document.createElement('td');
                    finding.textContent = testCase.previous_reason || '—';
                    const next = document.createElement('td');
                    const nextText = document.createElement('span');
                    nextText.textContent = `${testCase.status}: ${testCase.reason}`;
                    const impactLabel = document.createElement('label');
                    impactLabel.className = 'local-impact-label';
                    const impact = document.createElement('input');
                    impact.type = 'checkbox';
                    impact.className = 'local-impact-toggle';
                    impact.checked = testCase.recheck_required;
                    impact.disabled = testCase.recheck_required;
                    impactLabel.append(impact, document.createTextNode(' Korrektur betrifft diesen Fall'));
                    if (!testCase.recheck_required) {
                        impact.addEventListener('change', () => {
                            row.className = impact.checked ? 'recheck-required' : 'carried-result';
                            nextText.textContent = impact.checked
                                ? 'erneut prüfen: lokal als fachlich oder technisch betroffen markiert.'
                                : `${testCase.status}: ${testCase.reason}`;
                        });
                    }
                    next.append(nextText, impactLabel);
                    row.append(name, previous, finding, next);
                    body.append(row);
                });
                table.append(head, body);
                tableWrapper.append(table);
                details.append(tableWrapper);
            }
            evidenceContent.append(details);
        });
    }

    function renderCurrent() {
        title.textContent = current.title;
        path.textContent = current.id;
        content.innerHTML = current.html;
        bindFields();
        renderEvidence();
        if (acceptanceMode) {
            outcome.value = current.final ? current.final.outcome : '';
            comments.value = current.final ? current.final.comments : '';
            outcome.disabled = !current.is_manual_acceptance;
            comments.disabled = !current.is_manual_acceptance;
        }
        reloadButton.disabled = false;
        printButton.disabled = false;
        setDirty(false);
        document.getElementById('document').focus();
    }

    async function loadDocument(documentId, force) {
        if (dirty && !force && !window.confirm('Ungespeicherte Änderungen verwerfen?')) {
            return;
        }
        setStatus('Dokument wird geladen …', false);
        try {
            current = await api(`/api/document?id=${encodeURIComponent(documentId)}`);
            renderCurrent();
            setStatus(current.is_manual_acceptance ? 'Abnahmeformular ist bearbeitbar.' : 'Dokument ist schreibgeschützt.', false);
            renderList();
        } catch (error) {
            setStatus(error.message, true);
        }
    }

    function renderList() {
        const query = filter.value.trim().toLocaleLowerCase('de');
        documentList.replaceChildren();
        const repositories = new Map();
        documents
            .filter((item) => `${item.title} ${item.id}`.toLocaleLowerCase('de').includes(query))
            .forEach((item) => {
                if (!repositories.has(item.repository)) {
                    const section = document.createElement('section');
                    const heading = document.createElement('h2');
                    const list = document.createElement('div');
                    heading.textContent = item.repository;
                    section.append(heading, list);
                    documentList.append(section);
                    repositories.set(item.repository, list);
                }
                const button = document.createElement('button');
                button.type = 'button';
                button.textContent = item.title;
                button.title = item.id;
                button.classList.toggle('active', Boolean(current && current.id === item.id));
                button.addEventListener('click', () => loadDocument(item.id, false));
                repositories.get(item.repository).append(button);
            });
    }

    async function save() {
        if (!current || !dirty) {
            return;
        }
        const values = {};
        content.querySelectorAll('[data-field-id]').forEach((control) => {
            values[control.dataset.fieldId] = control.type === 'checkbox' ? control.checked : control.value;
        });
        saveButton.disabled = true;
        setStatus(acceptanceMode ? 'Finale Abnahme wird unveränderlich gespeichert …' : 'Änderungen werden atomar gespeichert …', false);
        try {
            if (acceptanceMode) {
                if (!outcome.value) {
                    throw new Error('Bitte PASS oder FAIL wählen.');
                }
                await api('/api/acceptance', {
                    method: 'POST',
                    headers: {'Content-Type': 'application/json'},
                    body: JSON.stringify({
                        candidate_id: current.id,
                        deployment_id: current.deployment_id,
                        outcome: outcome.value,
                        comments: comments.value,
                        answers: values,
                    }),
                });
                current = await api(`/api/document?id=${encodeURIComponent(current.id)}`);
            } else {
                current = await api('/api/document', {
                    method: 'PUT',
                    headers: {'Content-Type': 'application/json'},
                    body: JSON.stringify({id: current.id, etag: current.etag, values}),
                });
            }
            renderCurrent();
            setStatus(acceptanceMode ? 'Finale Abnahme wurde zentral gespeichert und ist schreibgeschützt.' : 'In dieselbe Markdown-Datei gespeichert.', false);
        } catch (error) {
            setDirty(true);
            setStatus(error.status === 409 ? `${error.message} Bitte neu laden.` : error.message, true);
        }
    }

    saveButton.addEventListener('click', save);
    reloadButton.addEventListener('click', () => current && loadDocument(current.id, false));
    printButton.addEventListener('click', () => window.print());
    filter.addEventListener('input', renderList);
    if (acceptanceMode) {
        outcome.addEventListener('change', () => current && current.is_manual_acceptance && setDirty(true));
        comments.addEventListener('input', () => current && current.is_manual_acceptance && setDirty(true));
    }
    window.addEventListener('beforeunload', (event) => {
        if (dirty) {
            event.preventDefault();
            event.returnValue = '';
        }
    });

    api('/api/documents')
        .then((result) => {
            documents = result.documents;
            renderList();
            const firstForm = documents.find((item) => item.is_manual_acceptance);
            if (firstForm) {
                loadDocument(firstForm.id, true);
            } else {
                setStatus('Keine Dokumente gefunden.', false);
            }
        })
        .catch((error) => setStatus(error.message, true));
}());
