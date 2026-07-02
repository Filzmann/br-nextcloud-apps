(function() {
    let meetings = [];
    let currentSettings = {};
    let selectedMeetingId = null;
    let lastAddedProtocolBlockId = null;
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
    const { protocolEditorHtml } = window.BRTop.protocolEditor;

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
        hideTopForm();
        showView('meeting-detail-view');
    }

    function showTopForm() {
        refreshTopFormContext();
        byId('top-form-panel').hidden = false;
        byId('show-top-form').setAttribute('aria-expanded', 'true');
    }

    function hideTopForm() {
        byId('top-form-panel').hidden = true;
        byId('show-top-form').setAttribute('aria-expanded', 'false');
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

        editor.querySelectorAll('textarea[data-action="protocol-block-content"]').forEach(textarea => {
            textarea.dataset.lastSaved = textarea.value;
            textarea.dataset.dirty = '0';
        });

        if (lastAddedProtocolBlockId) {
            const textarea = Array.from(editor.querySelectorAll('textarea[data-action="protocol-block-content"]'))
                .find(element => element.dataset.blockId === lastAddedProtocolBlockId);
            lastAddedProtocolBlockId = null;
            if (textarea) {
                textarea.focus();
            }
        }
    }

    function openProtocolEditor() {
        renderProtocolEditor();
        showView('protocol-view');
    }

    async function saveProtocolBlock(textarea) {
        if (textarea.dataset.lastSaved === textarea.value && textarea.dataset.dirty !== '1') {
            return;
        }

        const meetingId = selectedMeetingId;
        const topId = textarea.dataset.topId;
        const blockId = textarea.dataset.blockId;
        const status = textarea.closest('.brtop-protocol-block-row').querySelector('.brtop-block-status');

        status.textContent = 'Speichert...';

        await api(`/api/meetings/${meetingId}/tops/${topId}/protocol-blocks/${blockId}`, {
            method: 'POST',
            body: JSON.stringify({ content: textarea.value })
        });

        textarea.dataset.lastSaved = textarea.value;
        textarea.dataset.dirty = '0';
        status.textContent = 'Gespeichert';
    }

    async function saveDirtyProtocolBlocks() {
        const blocks = Array.from(byId('protocol-editor').querySelectorAll('textarea[data-action="protocol-block-content"]'))
            .filter(textarea => textarea.dataset.dirty === '1' || textarea.dataset.lastSaved !== textarea.value);

        for (const textarea of blocks) {
            await saveProtocolBlock(textarea);
        }
    }

    async function addProtocolBlock(topId) {
        const result = await api(`/api/meetings/${selectedMeetingId}/tops/${topId}/protocol-blocks`, {
            method: 'POST',
            body: JSON.stringify({ blockType: 'text', content: '' })
        });

        if (result.block && result.block.id) {
            lastAddedProtocolBlockId = String(result.block.id);
        }

        await loadState();
        renderProtocolEditor();
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
        await saveDirtyProtocolBlocks();

        const result = await api(`/api/meetings/${selectedMeetingId}/protocol`, { method: 'POST' });
        await loadState();
        renderProtocolEditor();
        alert(documentResultText(result) || 'Protokolldokument erzeugt.');
    }

    function defaultResolutionKind(type) {
        if (type === 'personnel_99') {
            return 'consent_refusal';
        }
        if (type === 'personnel_100') {
            return 'urgency_dispute';
        }
        if (type === 'personnel_102') {
            return 'dismissal_objection';
        }
        if (type === 'protocol') {
            return 'protocol_approval';
        }
        return 'approval_general';
    }

    function defaultAgendaKind(type) {
        if (type === 'consultation_report') {
            return 'report';
        }
        if (type === 'personnel') {
            return 'section';
        }
        return 'discussion';
    }

    function buildResolutionQuestion(kind, type, measure) {
        const m = (measure || 'die Maßnahme').trim();

        if (kind === 'consent_refusal') {
            return `Wer verweigert die Zustimmung zu ${m} und widerspricht ihr damit?`;
        }

        if (kind === 'urgency_dispute') {
            return `Wer bestreitet, dass die vorläufige Durchführung der personellen Maßnahme ${m} aus sachlichen Gründen dringend erforderlich ist?`;
        }

        if (kind === 'dismissal_objection') {
            return `Wer widerspricht der beabsichtigten Kündigung ${m} gemäß § 102 BetrVG?`;
        }

        return `Wer stimmt ${m} zu?`;
    }

    function setResolutionQuestion(force = false) {
        const checkbox = byId('top-requires-resolution');
        const kind = byId('top-resolution-kind');
        const subject = byId('top-subject');
        const resolution = byId('top-resolution');
        const type = byId('top-type');

        if (!checkbox.checked) {
            return;
        }

        const isAuto = resolution.dataset.autoGenerated !== '0';
        if (force || isAuto || resolution.value.trim() === '') {
            const count = Math.max(1, Number(byId('top-resolution-count').value || 1));
            const questions = [buildResolutionQuestion(kind.value, type.value, subject.value)];
            while (questions.length < count) {
                questions.push(`Beschlussfrage ${questions.length + 1} ergänzen.`);
            }
            resolution.value = questions.join('\n');
            resolution.dataset.autoGenerated = '1';
        }
    }

    function isResolutionSelected() {
        return byId('top-requires-resolution').checked;
    }

    function setFieldVisible(id, visible) {
        byId(id).hidden = !visible;
    }

    function usesPersonnelContext(type) {
        return ['personnel_99', 'personnel_100', 'personnel_102'].includes(type);
    }

    function refreshTopFormContext() {
        const type = byId('top-type').value;
        const resolutionSelected = isResolutionSelected();
        const personnelSelected = usesPersonnelContext(type);

        setFieldVisible('top-person-field', personnelSelected);
        setFieldVisible('top-legal-field', personnelSelected || resolutionSelected);
        setFieldVisible('top-resolution-options', resolutionSelected);

        if (!resolutionSelected) {
            byId('top-resolution').dataset.autoGenerated = '1';
        }
    }

    function updateResolutionDefault() {
        const type = byId('top-type').value;
        const box = byId('top-requires-resolution');
        const legal = byId('top-legal');
        const kind = byId('top-resolution-kind');
        const agendaKind = byId('top-agenda-kind');

        agendaKind.value = defaultAgendaKind(type);
        kind.value = defaultResolutionKind(type);

        if (type === 'personnel_99') {
            legal.value = '§ 99 BetrVG';
        } else if (type === 'personnel_100') {
            legal.value = '§ 100 BetrVG';
        } else if (type === 'personnel_102') {
            legal.value = '§ 102 BetrVG';
        } else if (['§ 99 BetrVG', '§ 100 BetrVG', '§ 102 BetrVG'].includes(legal.value)) {
            legal.value = '';
        }

        setResolutionQuestion(true);
        refreshTopFormContext();
    }

    function clearTopForm() {
        byId('top-subject').value = '';
        byId('top-person').value = '';
        byId('top-legal').value = '';
        byId('top-resolution').value = '';
        byId('top-invitation-note').value = '';
        byId('top-attachments').value = '';
        byId('top-resolution-count').value = '1';
        byId('top-requires-resolution').checked = false;
        byId('top-protocol-content').value = '';
        byId('top-resolution').dataset.autoGenerated = '1';
        updateResolutionDefault();
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

    byId('show-top-form').addEventListener('click', () => {
        showTopForm();
    });

    byId('hide-top-form').addEventListener('click', () => {
        hideTopForm();
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
            await saveDirtyProtocolBlocks();
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

            const type = byId('top-type').value;
            const resolutionSelected = isResolutionSelected();
            const resolutionCount = resolutionSelected ? Math.max(1, Number(byId('top-resolution-count').value || 1)) : 0;
            const personnelSelected = usesPersonnelContext(type);
            const legalSelected = personnelSelected || resolutionSelected;

            const payload = {
                type,
                parentId: 0,
                agendaItemKind: byId('top-agenda-kind').value,
                subject: byId('top-subject').value,
                personName: personnelSelected ? byId('top-person').value : '',
                legalBasis: legalSelected ? byId('top-legal').value : '',
                resolutionText: resolutionSelected ? byId('top-resolution').value : '',
                requiresResolution: resolutionSelected,
                resolutionCount,
                invitationNote: byId('top-invitation-note').value,
                attachmentPaths: byId('top-attachments').value,
                protocolContent: byId('top-protocol-content').value
            };

            await api(`/api/meetings/${selectedMeetingId}/tops`, {
                method: 'POST',
                body: JSON.stringify(payload)
            });

            clearTopForm();
            hideTopForm();
            await loadState();
            renderMeetingDetail();
        } catch (e) {
            alert('Fehler beim Speichern des TOP:\n' + e.message);
        }
    });

    byId('protocol-editor').addEventListener('click', async (event) => {
        const button = event.target instanceof Element ? event.target.closest('button[data-action="add-protocol-block"]') : null;
        if (!button) {
            return;
        }

        try {
            await saveDirtyProtocolBlocks();
            await addProtocolBlock(button.getAttribute('data-top-id'));
        } catch (e) {
            alert('Fehler beim Hinzufügen des Protokollblocks:\n' + e.message);
        }
    });

    byId('protocol-editor').addEventListener('input', (event) => {
        const textarea = event.target;
        if (!(textarea instanceof HTMLTextAreaElement) || textarea.dataset.action !== 'protocol-block-content') {
            return;
        }

        textarea.dataset.dirty = '1';
        const status = textarea.closest('.brtop-protocol-block-row').querySelector('.brtop-block-status');
        status.textContent = 'Ungespeichert';
    });

    byId('protocol-editor').addEventListener('blur', (event) => {
        const textarea = event.target;
        if (!(textarea instanceof HTMLTextAreaElement) || textarea.dataset.action !== 'protocol-block-content') {
            return;
        }

        saveProtocolBlock(textarea).catch(() => {
            const status = textarea.closest('.brtop-protocol-block-row').querySelector('.brtop-block-status');
            status.textContent = 'Fehler beim Speichern';
        });
    }, true);

    byId('top-type').addEventListener('change', updateResolutionDefault);

    byId('top-subject').addEventListener('input', () => setResolutionQuestion(false));

    byId('top-resolution-kind').addEventListener('change', () => setResolutionQuestion(true));

    byId('top-agenda-kind').addEventListener('change', () => {
        setResolutionQuestion(true);
        refreshTopFormContext();
    });

    byId('top-requires-resolution').addEventListener('change', () => {
        const box = byId('top-requires-resolution');
        if (box.checked) {
            byId('top-resolution-count').value = Math.max(1, Number(byId('top-resolution-count').value || 1));
        }
        setResolutionQuestion(false);
        refreshTopFormContext();
    });

    byId('top-resolution-count').addEventListener('input', () => {
        if (Number(byId('top-resolution-count').value || 0) < 1) {
            byId('top-resolution-count').value = '1';
        }
        setResolutionQuestion(false);
    });

    byId('top-resolution').addEventListener('input', () => {
        byId('top-resolution').dataset.autoGenerated = '0';
    });

    updateResolutionDefault();
    loadState().catch(e => alert('Fehler beim Laden der Sitzungen:\n' + e.message));
})();
