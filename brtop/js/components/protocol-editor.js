(function() {
    const {
        esc,
        agendaKindLabel,
        agendaNumber,
        buttonPresetHtml
    } = window.BRTop.ui;

    function protocolBlockHtml(top, block) {
        return `
            <div class="brtop-protocol-block-row">
                <textarea
                    class="brtop-protocol-block"
                    rows="4"
                    data-action="protocol-block-content"
                    data-top-id="${esc(top.id)}"
                    data-block-id="${esc(block.id)}">${esc(block.content || '')}</textarea>
                <small class="brtop-block-status">Gespeichert</small>
            </div>
        `;
    }

    function protocolEditorHtml(tops) {
        if (tops.length === 0) {
            return '<p>Für diese Sitzung gibt es noch keine TOPs.</p>';
        }

        return tops.map(top => {
            const blocks = top.protocol_blocks || [];
            const blockList = blocks.length > 0
                ? blocks.map(block => protocolBlockHtml(top, block)).join('')
                : '<p class="brtop-meta">Noch kein Protokollinhalt.</p>';
            const kind = typeof top.kindLabel === 'function'
                ? top.kindLabel()
                : agendaKindLabel(top.agenda_item_kind, top.requires_resolution, top.resolution_count);

            return `
                <div class="brtop-protocol-top">
                    <h3>
                        <span class="brtop-agenda-number">${esc(agendaNumber(top))}</span>
                        ${esc(top.subject)}
                    </h3>
                    <p class="brtop-meta">${esc(kind)}</p>
                    <div class="brtop-block-list">${blockList}</div>
                    ${buttonPresetHtml('addProtocolBlock', { 'data-top-id': top.id })}
                </div>
            `;
        }).join('');
    }

    window.BRTop = window.BRTop || {};
    window.BRTop.protocolEditor = { protocolEditorHtml };
})();
