(function() {
    let meetings = [];
    let currentSettings = {};
    let selectedMeetingId = null;
    let editingTopId = null;

    const { request: api } = window.BRTop.api;
    const {
        byId,
        esc,
        fmtDate,
        fmtTime,
        fmtMeeting,
        documentResultText
    } = window.BRTop.ui;
    const { Meeting } = window.BRTop.models;
    const { sessionTableHtml } = window.BRTop.meetingList;
    const { agendaListHtml, documentsHtml } = window.BRTop.agendaList;
    const { protocolEditorHtml, createController: createProtocolEditorController } = window.BRTop.protocolEditor;
    const topForm = window.BRTop.topForm.createController(byId);
    const protocolEditor = createProtocolEditorController({
        byId,
        api,
        getMeetingId: () => selectedMeetingId,
        loadState,
        render: renderProtocolEditor
    });

    const meetingTypeLabel = (type) => {
        const found = (currentSettings.meetingTypes || []).find(t => t.value === type);
        if (found) {
            return found.label;
        }
        return type || '';
    };

    const findMeeting = (id) => meetings.find(m => String(m.id) === String(id)) || null;

    const currentMeeting = () => selectedMeetingId ? findMeeting(selectedMeetingId) : null;

    function resetViewPosition() {
        const content = byId('content');
        if (content) {
            content.scrollTop = 0;
        }

        document.documentElement.scrollTop = 0;
        document.body.scrollTop = 0;
    }

    function showView(id) {
        ['sessions-view', 'meeting-detail-view', 'protocol-view'].forEach(viewId => {
            const view = byId(viewId);
            const active = viewId === id;
            view.classList.toggle('is-active', active);
            view.hidden = !active;
            view.setAttribute('aria-hidden', active ? 'false' : 'true');
        });
        resetViewPosition();
    }

    function renderSessionTable() {
        byId('state').innerHTML = sessionTableHtml(meetings);
    }

    async function loadState() {
        const data = await api('/api/state');
        meetings = (data.meetings || []).map(Meeting.fromApi);
        currentSettings = data.settings || {};

        if (selectedMeetingId && !findMeeting(selectedMeetingId)) {
            selectedMeetingId = null;
        }

        renderSessionTable();
    }

    function renderMeetingDetail() {
        const meeting = currentMeeting();
        const content = byId('meeting-detail-content');

        if (!meeting) {
            byId('meeting-detail-heading').textContent = 'Sitzung';
            content.innerHTML = '<p>Die Sitzung wurde nicht gefunden.</p>';
            return;
        }

        byId('meeting-detail-heading').textContent = meeting.title || 'Sitzung';

        const meta = [
            fmtDate(meeting.meeting_date),
            fmtTime(meeting.meeting_time),
            meeting.location || '',
            meetingTypeLabel(meeting.meeting_type),
            meeting.committee_code ? `Ausschuss: ${meeting.committee_code}` : '',
            meeting.invitation_date ? `Ladung: ${fmtDate(meeting.invitation_date)}` : '',
            meeting.invitation_status ? `Status: ${meeting.invitation_status}` : ''
        ].filter(Boolean).join(' · ');

        content.innerHTML = `
            ${meta ? `<p class="brtop-meta">${esc(meta)}</p>` : ''}
            <h3>TOP-Liste</h3>
            ${agendaListHtml(meeting.tops || [], editingTopId)}
            ${documentsHtml(meeting.documents || [])}
        `;

        focusEditingTopInput();
    }

    function openMeetingDetail(id) {
        selectedMeetingId = String(id);
        renderMeetingDetail();
        topForm.hide();
        showView('meeting-detail-view');
    }

    function focusEditingTopInput() {
        if (!editingTopId) {
            return;
        }

        const input = byId('meeting-detail-content').querySelector(`[data-top-edit-input][data-top-id="${editingTopId}"]`);
        if (input) {
            input.focus();
            input.select();
        }
    }

    function renderProtocolEditor() {
        const meeting = currentMeeting();
        const editor = byId('protocol-editor');

        if (!meeting) {
            byId('protocol-heading').textContent = 'Protokoll bearbeiten';
            editor.innerHTML = '<p>Die Sitzung wurde nicht gefunden.</p>';
            return;
        }

        byId('protocol-heading').textContent = `Protokoll: ${meeting.title || 'Sitzung'}`;

        const tops = meeting.tops || [];
        editor.innerHTML = protocolEditorHtml(tops);
        protocolEditor.afterRender();
    }

    function openProtocolEditor() {
        renderProtocolEditor();
        showView('protocol-view');
    }

    async function createNewMeeting() {
        const result = await api('/api/meetings/next-regular', { method: 'POST' });
        await loadState();
        openMeetingDetail(result.id);
    }

    async function deleteMeeting(id) {
        const meeting = findMeeting(id);
        const label = meeting ? fmtMeeting(meeting) : 'diese Sitzung';
        if (!confirm(`Sitzung "${label}" wirklich löschen?`)) {
            return;
        }

        await api(`/api/meetings/${id}/delete`, { method: 'POST' });
        if (String(selectedMeetingId) === String(id)) {
            selectedMeetingId = null;
        }
        await loadState();
        showView('sessions-view');
    }

    async function moveTop(topId, direction) {
        await api(`/api/meetings/${selectedMeetingId}/tops/${topId}/move`, {
            method: 'POST',
            body: JSON.stringify({ direction })
        });
        await loadState();
        renderMeetingDetail();
    }

    async function changeTopDepth(topId, direction) {
        await api(`/api/meetings/${selectedMeetingId}/tops/${topId}/depth`, {
            method: 'POST',
            body: JSON.stringify({ direction })
        });
        await loadState();
        renderMeetingDetail();
    }

    async function saveTopSubject(topId) {
        const input = byId('meeting-detail-content').querySelector(`[data-top-edit-input][data-top-id="${topId}"]`);
        if (!input) {
            return;
        }

        const subject = input.value.trim();
        if (subject === '') {
            alert('Der TOP-Betreff darf nicht leer sein.');
            input.focus();
            return;
        }

        await api(`/api/meetings/${selectedMeetingId}/tops/${topId}/subject`, {
            method: 'POST',
            body: JSON.stringify({ subject })
        });
        editingTopId = null;
        await loadState();
        renderMeetingDetail();
    }

    async function deleteTop(topId, label) {
        if (!confirm(`TOP "${label}" wirklich löschen? Untergeordnete TOPs werden ebenfalls gelöscht.`)) {
            return;
        }

        await api(`/api/meetings/${selectedMeetingId}/tops/${topId}/delete`, { method: 'POST' });
        await loadState();
        renderMeetingDetail();
    }

    async function generateInvitation() {
        const result = await api(`/api/meetings/${selectedMeetingId}/invitation`, { method: 'POST' });
        await loadState();
        renderMeetingDetail();
        alert(documentResultText(result) || 'Ladung erzeugt.');
    }

    async function generateProtocolDocument() {
        await protocolEditor.saveDirty();

        const result = await api(`/api/meetings/${selectedMeetingId}/protocol`, { method: 'POST' });
        await loadState();
        renderProtocolEditor();
        alert(documentResultText(result) || 'Protokolldokument erzeugt.');
    }

    byId('new-meeting').addEventListener('click', async () => {
        try {
            await createNewMeeting();
        } catch (e) {
            alert('Fehler beim Anlegen der Sitzung:\n' + e.message);
        }
    });

    byId('state').addEventListener('click', async (event) => {
        const button = event.target instanceof Element ? event.target.closest('button[data-action]') : null;
        if (!button) {
            return;
        }

        const id = button.getAttribute('data-id');
        const action = button.getAttribute('data-action');

        try {
            if (action === 'edit-meeting') {
                openMeetingDetail(id);
            } else if (action === 'delete-meeting') {
                await deleteMeeting(id);
            }
        } catch (e) {
            alert('Fehler:\n' + e.message);
        }
    });

    byId('back-to-sessions').addEventListener('click', () => {
        showView('sessions-view');
    });

    byId('detail-create-invitation').addEventListener('click', async () => {
        try {
            await generateInvitation();
        } catch (e) {
            alert('Fehler beim Erzeugen der Ladung:\n' + e.message);
        }
    });

    byId('detail-edit-protocol').addEventListener('click', () => {
        openProtocolEditor();
    });

    byId('meeting-detail-content').addEventListener('click', async (event) => {
        const button = event.target instanceof Element ? event.target.closest('button[data-action]') : null;
        if (!button) {
            return;
        }

        const action = button.getAttribute('data-action');
        const topId = button.getAttribute('data-top-id');
        const direction = button.getAttribute('data-direction') || '';

        try {
            if (action === 'edit-top') {
                editingTopId = topId;
                renderMeetingDetail();
            } else if (action === 'save-top-title') {
                await saveTopSubject(topId);
            } else if (action === 'move-top') {
                await moveTop(topId, direction);
            } else if (action === 'depth-top') {
                await changeTopDepth(topId, direction);
            } else if (action === 'delete-top') {
                await deleteTop(topId, button.getAttribute('data-label') || 'diesen TOP');
            }
        } catch (e) {
            alert('Fehler beim Bearbeiten der TOP-Liste:\n' + e.message);
        }
    });

    byId('meeting-detail-content').addEventListener('keydown', async (event) => {
        const input = event.target;
        if (!(input instanceof HTMLInputElement) || !input.matches('[data-top-edit-input]')) {
            return;
        }

        if (event.key === 'Escape') {
            editingTopId = null;
            renderMeetingDetail();
            return;
        }

        if (event.key !== 'Enter') {
            return;
        }

        event.preventDefault();

        try {
            await saveTopSubject(input.getAttribute('data-top-id'));
        } catch (e) {
            alert('Fehler beim Speichern des TOP:\n' + e.message);
        }
    });

    byId('back-to-detail').addEventListener('click', async () => {
        try {
            await protocolEditor.saveDirty();
            await loadState();
            openMeetingDetail(selectedMeetingId);
        } catch (e) {
            alert('Fehler beim Speichern des Protokolls:\n' + e.message);
        }
    });

    byId('generate-protocol-document').addEventListener('click', async () => {
        try {
            await generateProtocolDocument();
        } catch (e) {
            alert('Fehler beim Erzeugen des Protokolls:\n' + e.message);
        }
    });

    byId('add-top').addEventListener('click', async () => {
        try {
            if (!selectedMeetingId) {
                alert('Bitte zuerst eine Sitzung öffnen.');
                return;
            }

            const payload = topForm.payload();

            await api(`/api/meetings/${selectedMeetingId}/tops`, {
                method: 'POST',
                body: JSON.stringify(payload)
            });

            topForm.clear();
            topForm.hide();
            await loadState();
            renderMeetingDetail();
        } catch (e) {
            alert('Fehler beim Speichern des TOP:\n' + e.message);
        }
    });

    protocolEditor.init();
    topForm.init();
    loadState().catch(e => alert('Fehler beim Laden der Sitzungen:\n' + e.message));
})();
