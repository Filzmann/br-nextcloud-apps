(function() {
    const {
        esc,
        agendaKindLabel,
        agendaNumber,
        buttonPresetHtml
    } = window.BRTop.ui;

    function agendaListHtml(tops, editingTopId) {
        if (tops.length === 0) {
            return '<p>Noch keine TOPs vorhanden.</p>';
        }

        const items = tops.map(top => {
            const level = Math.min(3, Math.max(1, Number(top.level || 1)));
            const kind = typeof top.kindLabel === 'function'
                ? top.kindLabel()
                : agendaKindLabel(top.agenda_item_kind, top.requires_resolution, top.resolution_count);
            const legal = top.legal_basis ? ` · ${top.legal_basis}` : '';
            const invitationNote = String(top.invitation_note || '').trim();
            const attachments = typeof top.attachmentLines === 'function'
                ? top.attachmentLines()
                : String(top.attachment_paths || '').split(/\r?\n/).map(line => line.trim()).filter(Boolean);
            const details = [
                invitationNote ? `<small class="brtop-agenda-detail">${esc(invitationNote)}</small>` : '',
                attachments.length > 0 ? `<small class="brtop-agenda-detail">Anhänge: ${esc(attachments.join('; '))}</small>` : ''
            ].filter(Boolean).join('');
            const editing = String(editingTopId) === String(top.id);
            const title = editing
                ? `
                    <span class="brtop-top-title-edit">
                        <input class="brtop-top-title-input" type="text" value="${esc(top.subject)}" data-top-edit-input data-top-id="${esc(top.id)}">
                        ${buttonPresetHtml('saveTopTitle', { 'data-top-id': top.id })}
                    </span>
                `
                : `<span class="brtop-agenda-title">${esc(top.subject)}</span>`;

            return `
                <li class="brtop-agenda-level-${level}">
                    <div class="brtop-agenda-row">
                        <div class="brtop-agenda-main">
                            <span class="brtop-agenda-number">${esc(agendaNumber(top))}</span>
                            ${title}
                            <small>${esc(kind + legal)}</small>
                            ${details}
                        </div>
                        <div class="brtop-top-actions">
                            ${editing ? '' : buttonPresetHtml('editTop', { 'data-top-id': top.id })}
                            ${buttonPresetHtml('moveTopUp', { 'data-top-id': top.id, 'data-direction': 'up' })}
                            ${buttonPresetHtml('moveTopDown', { 'data-top-id': top.id, 'data-direction': 'down' })}
                            ${buttonPresetHtml('outdentTop', { 'data-top-id': top.id, 'data-direction': 'outdent' })}
                            ${buttonPresetHtml('indentTop', { 'data-top-id': top.id, 'data-direction': 'indent' })}
                            ${buttonPresetHtml('deleteTop', { 'data-top-id': top.id, 'data-label': `${agendaNumber(top)} ${top.subject}` })}
                        </div>
                    </div>
                </li>
            `;
        }).join('');

        return `<ul class="brtop-agenda-list">${items}</ul>`;
    }

    function documentsHtml(documents) {
        if (!documents || documents.length === 0) {
            return '';
        }

        const items = documents.map(document => {
            const title = document.title || document.document_type || 'Dokument';
            return `<li>${esc(title)} <small>${esc(document.file_path || '')}</small></li>`;
        }).join('');

        return `<h3>Dokumente</h3><ul class="brtop-documents">${items}</ul>`;
    }

    window.BRTop = window.BRTop || {};
    window.BRTop.agendaList = {
        agendaListHtml,
        documentsHtml
    };
})();
