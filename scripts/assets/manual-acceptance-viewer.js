(function () {
    'use strict';

    const token = document.querySelector('meta[name="viewer-token"]').content;
    const documentList = document.getElementById('documents');
    const content = document.getElementById('content');
    const title = document.getElementById('title');
    const path = document.getElementById('path');
    const status = document.getElementById('status');
    const saveButton = document.getElementById('save');
    const reloadButton = document.getElementById('reload');
    const printButton = document.getElementById('print');
    const filter = document.getElementById('filter');
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

    function renderCurrent() {
        title.textContent = current.title;
        path.textContent = current.id;
        content.innerHTML = current.html;
        bindFields();
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
        setStatus('Änderungen werden atomar gespeichert …', false);
        try {
            current = await api('/api/document', {
                method: 'PUT',
                headers: {'Content-Type': 'application/json'},
                body: JSON.stringify({id: current.id, etag: current.etag, values}),
            });
            renderCurrent();
            setStatus('In dieselbe Markdown-Datei gespeichert.', false);
        } catch (error) {
            setDirty(true);
            setStatus(error.status === 409 ? `${error.message} Bitte neu laden.` : error.message, true);
        }
    }

    saveButton.addEventListener('click', save);
    reloadButton.addEventListener('click', () => current && loadDocument(current.id, false));
    printButton.addEventListener('click', () => window.print());
    filter.addEventListener('input', renderList);
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
