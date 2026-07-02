(function() {
    const byId = (id) => document.getElementById(id);

    const esc = (s) => String(s ?? '').replace(/[&<>"']/g, m => ({
        '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#039;'
    }[m]));

    const fmtDate = (date) => {
        const value = String(date || '');
        const parts = /^(\d{4})-(\d{2})-(\d{2})$/.exec(value);
        if (!parts) {
            return value;
        }

        return `${parts[3]}.${parts[2]}.${parts[1]}`;
    };

    const fmtTime = (time) => String(time || '').slice(0, 5);

    const fmtMeeting = (meeting) => {
        const date = fmtDate(meeting.meeting_date);
        const time = fmtTime(meeting.meeting_time);
        return [date, time, meeting.title].filter(Boolean).join(' · ');
    };

    const agendaKindLabel = (kind, requiresResolution, resolutionCount = 0) => {
        if (kind === 'section') {
            return 'Gliederung';
        }
        if (kind === 'report') {
            return 'Bericht';
        }
        if (kind === 'resolution' || Number(requiresResolution) === 1) {
            const count = Math.max(1, Number(resolutionCount || 0));
            return count > 1 ? `${count} Beschlüsse` : 'Beschluss';
        }
        return 'Beratung';
    };

    const agendaNumber = (top) => {
        if (top && typeof top.agendaNumberLabel === 'function') {
            return top.agendaNumberLabel();
        }

        const number = String(top.agenda_number || top.position || '').trim();
        return number === '' ? '' : number + '.';
    };

    const buttonIcons = Object.freeze({
        plus: '+',
        back: '&larr;',
        edit: '&#9998;',
        save: '&#10003;',
        delete: '&times;',
        up: '&uarr;',
        down: '&darr;',
        outdent: '&larr;',
        indent: '&rarr;',
        mail: '&#9993;',
        protocol: '&#9998;'
    });

    const buttonPresets = Object.freeze({
        editMeeting: { action: 'edit-meeting', icon: 'edit', label: 'Bearbeiten' },
        deleteMeeting: { action: 'delete-meeting', icon: 'delete', label: 'Löschen', className: 'brtop-icon-button-danger' },
        editTop: { action: 'edit-top', icon: 'edit', label: 'Bearbeiten' },
        saveTopTitle: { action: 'save-top-title', icon: 'save', label: 'Speichern', className: 'brtop-icon-button-primary' },
        moveTopUp: { action: 'move-top', icon: 'up', label: 'Hoch' },
        moveTopDown: { action: 'move-top', icon: 'down', label: 'Runter' },
        outdentTop: { action: 'depth-top', icon: 'outdent', label: 'Ebene raus' },
        indentTop: { action: 'depth-top', icon: 'indent', label: 'Ebene rein' },
        deleteTop: { action: 'delete-top', icon: 'delete', label: 'Löschen', className: 'brtop-icon-button-danger' },
        addProtocolBlock: { action: 'add-protocol-block', icon: 'plus', label: 'Block hinzufügen', className: 'brtop-icon-button-primary' }
    });

    const attrsHtml = (attrs) => Object.entries(attrs)
        .filter(([, value]) => value !== null && value !== undefined && value !== false)
        .map(([key, value]) => value === true ? esc(key) : `${esc(key)}="${esc(value)}"`)
        .join(' ');

    const iconHtml = (icon) => {
        const iconValue = buttonIcons[icon] || icon;
        const iconClass = String(icon || 'item').replace(/[^a-z0-9_-]/gi, '-').toLowerCase();

        return `<span class="brtop-icon brtop-icon-${esc(iconClass)}" aria-hidden="true">${iconValue}</span>`;
    };

    const iconButtonHtml = (action, icon, label, attrs = {}, extraClass = '') => {
        const attrText = attrsHtml(attrs);

        return `
            <button class="brtop-icon-button ${esc(extraClass)}" type="button" data-action="${esc(action)}" title="${esc(label)}" aria-label="${esc(label)}"${attrText ? ' ' + attrText : ''}>
                ${iconHtml(icon)}
                <span class="brtop-button-label">${esc(label)}</span>
            </button>
        `;
    };

    const buttonPresetHtml = (preset, attrs = {}, extraClass = '') => {
        const button = buttonPresets[preset];
        if (!button) {
            throw new Error(`Unbekanntes BRTop-Button-Preset: ${preset}`);
        }

        return iconButtonHtml(
            button.action,
            button.icon,
            button.label,
            attrs,
            [button.className || '', extraClass].filter(Boolean).join(' ')
        );
    };

    const documentResultText = (result) => {
        const created = (result.created || []).join('\n');
        const warnings = (result.warnings || []).join('\n');
        return [
            result.folder ? `Ordner: ${result.folder}` : '',
            created ? `Erzeugt:\n${created}` : '',
            result.message || '',
            warnings ? `Hinweise:\n${warnings}` : ''
        ].filter(Boolean).join('\n\n');
    };

    window.BRTop = window.BRTop || {};
    window.BRTop.ui = {
        byId,
        esc,
        fmtDate,
        fmtTime,
        fmtMeeting,
        agendaKindLabel,
        agendaNumber,
        iconButtonHtml,
        buttonPresetHtml,
        documentResultText
    };
})();
