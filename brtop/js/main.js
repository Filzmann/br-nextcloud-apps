(function() {
    let meetings = [];
    let currentSettings = {};

    const api = async (url, options = {}) => {
        const response = await fetch(OC.generateUrl('/apps/brtop' + url), {
            ...options,
            headers: {
                'Content-Type': 'application/json',
                'requesttoken': OC.requestToken,
                ...(options.headers || {})
            }
        });

        const text = await response.text();

        let data;
        try {
            data = text ? JSON.parse(text) : {};
        } catch (e) {
            data = { raw: text };
        }

        if (!response.ok) {
            const msg = data.ok === false && data.message ? data.message : ('HTTP ' + response.status);
            const error = new Error(msg);
            error.data = data;
            error.status = response.status;
            throw error;
        }

        return data;
    };

    const esc = (s) => String(s ?? '').replace(/[&<>"']/g, m => ({
        '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#039;'
    }[m]));

    const fmtMeeting = (m) => `${m.meeting_date} · ${m.title}${m.meeting_time ? ' · ' + m.meeting_time : ''}`;

    const meetingTypeLabel = (type) => {
        const found = (currentSettings.meetingTypes || []).find(t => t.value === type);
        if (found) {
            return found.label;
        }
        return type || '';
    };

    const agendaKindLabel = (kind, requiresResolution) => {
        if (kind === 'section') {
            return 'Gliederung';
        }
        if (kind === 'report') {
            return 'Bericht';
        }
        if (kind === 'resolution' || Number(requiresResolution) === 1) {
            return 'Beschluss';
        }
        return 'Beratung';
    };

    function fillMeetingDropdown(selectedId = null) {
        const select = document.getElementById('top-meeting-select');
        select.innerHTML = '';

        for (const meeting of meetings) {
            const option = document.createElement('option');
            option.value = meeting.id;
            option.textContent = fmtMeeting(meeting);
            select.appendChild(option);
        }

        if (selectedId) {
            select.value = String(selectedId);
        }

        fillParentDropdown(select.value);
    }

    function selectedMeeting(id = null) {
        const selectedId = id || document.getElementById('top-meeting-select').value;
        return meetings.find(m => String(m.id) === String(selectedId)) || null;
    }

    function fillParentDropdown(meetingId = null) {
        const select = document.getElementById('top-parent-select');
        if (!select) {
            return;
        }

        const meeting = selectedMeeting(meetingId);
        select.innerHTML = '';

        const root = document.createElement('option');
        root.value = '0';
        root.textContent = 'Oberster TOP';
        select.appendChild(root);

        for (const top of ((meeting && meeting.tops) || [])) {
            const level = Number(top.level || 1);
            if (level >= 3) {
                continue;
            }

            const option = document.createElement('option');
            option.value = top.id;
            option.textContent = `${top.agenda_number || top.position}. ${top.subject}`;
            select.appendChild(option);
        }
    }

    function setResult(text) {
        document.getElementById('result-box').value = text || '';
    }

    function fillSettings(settings = {}) {
        currentSettings = settings;
        document.getElementById('settings-default-title').value = settings.defaultMeetingTitle || '';
        document.getElementById('settings-meeting-weekday').value = String(settings.regularMeetingWeekday || 2);
        document.getElementById('settings-invitation-weekday').value = String(settings.invitationWeekday || 5);
        document.getElementById('settings-default-time').value = settings.defaultMeetingTime || '10:00';
        document.getElementById('settings-default-location').value = settings.defaultLocation || '';
        document.getElementById('settings-member-group').value = settings.memberGroupName || 'Betriebsrat';
        document.getElementById('settings-agenda-template').value = settings.regularAgendaTemplateJson || '';
        fillMeetingTypeControls(settings);
    }

    function fillMeetingTypeControls(settings = {}) {
        const typeSelect = document.getElementById('meeting-type');
        const committeeSelect = document.getElementById('meeting-committee');

        typeSelect.innerHTML = '';
        for (const type of (settings.meetingTypes || [])) {
            const option = document.createElement('option');
            option.value = type.value;
            option.textContent = type.label;
            typeSelect.appendChild(option);
        }
        typeSelect.value = 'custom';

        committeeSelect.innerHTML = '';
        const empty = document.createElement('option');
        empty.value = '';
        empty.textContent = 'kein Ausschuss';
        committeeSelect.appendChild(empty);
        for (const committee of (settings.committeeCodes || [])) {
            const option = document.createElement('option');
            option.value = committee.value;
            option.textContent = committee.label;
            committeeSelect.appendChild(option);
        }
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
        if (['protocol', 'personnel_99', 'personnel_100', 'personnel_102'].includes(type)) {
            return 'resolution';
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
        const checkbox = document.getElementById('top-requires-resolution');
        const kind = document.getElementById('top-resolution-kind');
        const subject = document.getElementById('top-subject');
        const resolution = document.getElementById('top-resolution');
        const type = document.getElementById('top-type');

        if (!checkbox || !kind || !subject || !resolution || !type) {
            return;
        }

        if (!checkbox.checked) {
            return;
        }

        const isAuto = resolution.dataset.autoGenerated !== '0';
        if (force || isAuto || resolution.value.trim() === '') {
            resolution.value = buildResolutionQuestion(kind.value, type.value, subject.value);
            resolution.dataset.autoGenerated = '1';
        }
    }

    async function loadState(selectedId = null) {
        const data = await api('/api/state');
        meetings = data.meetings || [];
        fillSettings(data.settings || {});
        fillMeetingDropdown(selectedId);

        const stateBox = document.getElementById('state');
        stateBox.innerHTML = '';

        if (data.notice) {
            const notice = document.createElement('p');
            notice.className = 'brtop-notice';
            notice.textContent = data.notice;
            stateBox.appendChild(notice);
        }

        if (meetings.length === 0) {
            stateBox.innerHTML += '<p>Noch keine Sitzung vorhanden.</p>';
            return;
        }

        for (const meeting of meetings) {
            const div = document.createElement('div');
            div.className = 'brtop-meeting';

            const tops = meeting.tops || [];
            const documents = meeting.documents || [];
            const topList = tops.map(t => {
                const level = Number(t.level || 1);
                const indent = Math.max(0, level - 1) * 18;
                const kind = agendaKindLabel(t.agenda_item_kind, t.requires_resolution);
                const legal = t.legal_basis ? ` · ${t.legal_basis}` : '';
                return `<li style="margin-left:${indent}px">${esc(t.agenda_number || t.position)}. ${esc(t.subject)} <small>${esc(kind + legal)}</small></li>`;
            }).join('');
            const documentList = documents.map(d => {
                return `<li>${esc(d.title || d.document_type)} <small>${esc(d.file_path || '')}</small></li>`;
            }).join('');
            const meta = [
                meetingTypeLabel(meeting.meeting_type),
                meeting.committee_code ? `Ausschuss: ${meeting.committee_code}` : '',
                meeting.invitation_date ? `Ladung: ${meeting.invitation_date}` : '',
                meeting.invitation_status ? `Status: ${meeting.invitation_status}` : ''
            ].filter(Boolean).join(' · ');

            div.innerHTML = `
                <h3>${esc(fmtMeeting(meeting))}</h3>
                ${meta ? `<p class="brtop-meta">${esc(meta)}</p>` : ''}
                <ol>${topList}</ol>
                ${documentList ? `<h4>Dokumente</h4><ul class="brtop-documents">${documentList}</ul>` : ''}
                <button data-action="invitation" data-id="${esc(meeting.id)}">Einladung erzeugen</button>
                <button data-action="protocol" data-id="${esc(meeting.id)}">Protokollvorlage erzeugen</button>
                <button data-action="resolutions" data-id="${esc(meeting.id)}">Beschlüsse erzeugen</button>
            `;

            stateBox.appendChild(div);
        }

        document.querySelectorAll('[data-action]').forEach(btn => {
            btn.addEventListener('click', async () => {
                const id = btn.getAttribute('data-id');
                const action = btn.getAttribute('data-action');

                try {
                    let result;
                    if (action === 'invitation') {
                        result = await api(`/api/meetings/${id}/invitation`, { method: 'POST' });
                        setResult(`Betreff: ${result.subject}\n\n${result.email || ''}`);
                    } else if (action === 'protocol') {
                        result = await api(`/api/meetings/${id}/protocol`, { method: 'POST' });
                        setResult('');
                    } else if (action === 'resolutions') {
                        result = await api(`/api/meetings/${id}/resolutions`, { method: 'POST' });
                        setResult('');
                    }

                    const created = (result.created || []).join('\n');
                    const warnings = (result.warnings || []).join('\n');
                    const msg = [
                        result.folder ? `Ordner: ${result.folder}` : '',
                        created ? `Erzeugt:\n${created}` : '',
                        result.message || '',
                        warnings ? `Hinweise:\n${warnings}` : ''
                    ].filter(Boolean).join('\n\n');

                    alert(msg || 'Fertig.');
                    await loadState(id);
                } catch (e) {
                    const created = e.data && Array.isArray(e.data.created) ? e.data.created.join('\n') : '';
                    const details = [
                        e.message,
                        created ? `Bereits erzeugt:\n${created}` : ''
                    ].filter(Boolean).join('\n\n');

                    alert('Fehler:\n' + details);
                }
            });
        });
    }

    function updateResolutionDefault() {
        const type = document.getElementById('top-type').value;
        const box = document.getElementById('top-requires-resolution');
        const legal = document.getElementById('top-legal');
        const kind = document.getElementById('top-resolution-kind');
        const agendaKind = document.getElementById('top-agenda-kind');

        if (agendaKind) {
            agendaKind.value = defaultAgendaKind(type);
        }

        box.checked = agendaKind ? agendaKind.value === 'resolution' : ['protocol', 'personnel_99', 'personnel_100', 'personnel_102'].includes(type);

        if (kind) {
            kind.value = defaultResolutionKind(type);
        }

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
    }

    document.getElementById('create-meeting').addEventListener('click', async () => {
        try {
            const payload = {
                title: document.getElementById('meeting-title').value,
                meetingType: document.getElementById('meeting-type').value,
                committeeCode: document.getElementById('meeting-committee').value,
                meetingDate: document.getElementById('meeting-date').value,
                meetingTime: document.getElementById('meeting-time').value,
                location: document.getElementById('meeting-location').value
            };

            const result = await api('/api/meetings', {
                method: 'POST',
                body: JSON.stringify(payload)
            });

            await loadState(result.id);
        } catch (e) {
            alert('Fehler beim Anlegen der Sitzung:\n' + e.message);
        }
    });

    document.getElementById('plan-next-meeting').addEventListener('click', async () => {
        try {
            const result = await api('/api/meetings/next-regular', { method: 'POST' });
            await loadState(result.id);

            alert([
                result.meetingDate ? `Sitzung: ${result.meetingDate}` : '',
                result.invitationDate ? `Ladung geplant: ${result.invitationDate}` : '',
                result.agendaItemsCreated ? `Standard-TOPs: ${result.agendaItemsCreated}` : ''
            ].filter(Boolean).join('\n') || 'Nächste BR-Sitzung geplant.');
        } catch (e) {
            alert('Fehler beim Planen der nächsten Sitzung:\n' + e.message);
        }
    });

    document.getElementById('save-settings').addEventListener('click', async () => {
        try {
            const result = await api('/api/settings', {
                method: 'POST',
                body: JSON.stringify({
                    defaultMeetingTitle: document.getElementById('settings-default-title').value,
                    regularMeetingWeekday: Number(document.getElementById('settings-meeting-weekday').value),
                    invitationWeekday: Number(document.getElementById('settings-invitation-weekday').value),
                    defaultMeetingTime: document.getElementById('settings-default-time').value,
                    defaultLocation: document.getElementById('settings-default-location').value,
                    memberGroupName: document.getElementById('settings-member-group').value,
                    regularAgendaTemplateJson: document.getElementById('settings-agenda-template').value
                })
            });

            fillSettings(result.settings || {});
            alert('Einstellungen gespeichert.');
        } catch (e) {
            alert('Fehler beim Speichern der Einstellungen:\n' + e.message);
        }
    });

    document.getElementById('seed-demo').addEventListener('click', async () => {
        try {
            const result = await api('/api/demo', { method: 'POST' });
            await loadState(result.meetingId);
        } catch (e) {
            alert('Fehler beim Anlegen der Demo:\n' + e.message);
        }
    });

    document.getElementById('add-top').addEventListener('click', async () => {
        try {
            const id = document.getElementById('top-meeting-select').value;

            if (!id) {
                alert('Bitte zuerst eine Sitzung auswählen oder anlegen.');
                return;
            }

            const payload = {
                type: document.getElementById('top-type').value,
                parentId: Number(document.getElementById('top-parent-select').value || 0),
                agendaItemKind: document.getElementById('top-agenda-kind').value,
                subject: document.getElementById('top-subject').value,
                personName: document.getElementById('top-person').value,
                legalBasis: document.getElementById('top-legal').value,
                resolutionText: document.getElementById('top-resolution').value,
                requiresResolution: document.getElementById('top-agenda-kind').value === 'resolution' || document.getElementById('top-requires-resolution').checked,
                protocolContent: document.getElementById('top-protocol-content').value
            };

            await api(`/api/meetings/${id}/tops`, {
                method: 'POST',
                body: JSON.stringify(payload)
            });

            document.getElementById('top-subject').value = '';
            document.getElementById('top-person').value = '';
            document.getElementById('top-legal').value = '';
            document.getElementById('top-resolution').value = '';
            document.getElementById('top-protocol-content').value = '';
            document.getElementById('top-resolution').dataset.autoGenerated = '1';
            updateResolutionDefault();

            await loadState(id);
        } catch (e) {
            alert('Fehler beim Speichern des TOP:\n' + e.message);
        }
    });

    document.getElementById('copy-result').addEventListener('click', async () => {
        const text = document.getElementById('result-box').value;
        if (!text) {
            return;
        }

        try {
            await navigator.clipboard.writeText(text);
            alert('Text kopiert.');
        } catch (e) {
            alert('Kopieren nicht möglich. Bitte Text manuell markieren.');
        }
    });

    document.getElementById('top-type').addEventListener('change', updateResolutionDefault);

    document.getElementById('top-meeting-select').addEventListener('change', () => fillParentDropdown());

    document.getElementById('top-subject').addEventListener('input', () => setResolutionQuestion(false));

    document.getElementById('top-resolution-kind').addEventListener('change', () => setResolutionQuestion(true));

    document.getElementById('top-agenda-kind').addEventListener('change', () => {
        const agendaKind = document.getElementById('top-agenda-kind').value;
        document.getElementById('top-requires-resolution').checked = agendaKind === 'resolution';
        setResolutionQuestion(true);
    });

    document.getElementById('top-requires-resolution').addEventListener('change', () => {
        const box = document.getElementById('top-requires-resolution');
        const agendaKind = document.getElementById('top-agenda-kind');
        if (box.checked) {
            agendaKind.value = 'resolution';
        } else if (agendaKind.value === 'resolution') {
            agendaKind.value = defaultAgendaKind(document.getElementById('top-type').value);
        }
        setResolutionQuestion(false);
    });

    document.getElementById('top-resolution').addEventListener('input', () => {
        document.getElementById('top-resolution').dataset.autoGenerated = '0';
    });

    const dateInput = document.getElementById('meeting-date');
    if (!dateInput.value) {
        const d = new Date();
        d.setDate(d.getDate() + 7);
        dateInput.value = d.toISOString().slice(0, 10);
    }

    updateResolutionDefault();
    loadState();
})();
