(function() {
    const {
        esc,
        agendaKindLabel,
        agendaNumber,
        buttonPresetHtml
    } = window.BRTop.ui;

    function protocolBlockHtml(top, block) {
        const content = block && typeof block.content === 'string' ? block.content : '';

        return `
            <div class="brtop-protocol-block-row">
                <textarea
                    class="brtop-protocol-block"
                    rows="4"
                    data-action="protocol-block-content"
                    data-top-id="${esc(top.id)}"
                    data-block-id="${esc(block.id)}">${esc(content)}</textarea>
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

    function createController({ byId, api, getMeetingId, loadState, render }) {
        let lastAddedProtocolBlockId = null;

        function afterRender() {
            const editor = byId('protocol-editor');

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

        async function saveBlock(textarea) {
            if (textarea.dataset.lastSaved === textarea.value && textarea.dataset.dirty !== '1') {
                return;
            }

            const meetingId = getMeetingId();
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

        async function saveDirty() {
            const blocks = Array.from(byId('protocol-editor').querySelectorAll('textarea[data-action="protocol-block-content"]'))
                .filter(textarea => textarea.dataset.dirty === '1' || textarea.dataset.lastSaved !== textarea.value);

            for (const textarea of blocks) {
                await saveBlock(textarea);
            }
        }

        async function addBlock(topId) {
            const result = await api(`/api/meetings/${getMeetingId()}/tops/${topId}/protocol-blocks`, {
                method: 'POST',
                body: JSON.stringify({ blockType: 'text', content: '' })
            });

            if (result.block && result.block.id) {
                lastAddedProtocolBlockId = String(result.block.id);
            }

            await loadState();
            render();
        }

        function init() {
            byId('protocol-editor').addEventListener('click', async (event) => {
                const button = event.target instanceof Element ? event.target.closest('button[data-action="add-protocol-block"]') : null;
                if (!button) {
                    return;
                }

                try {
                    await saveDirty();
                    await addBlock(button.getAttribute('data-top-id'));
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

                saveBlock(textarea).catch(() => {
                    const status = textarea.closest('.brtop-protocol-block-row').querySelector('.brtop-block-status');
                    status.textContent = 'Fehler beim Speichern';
                });
            }, true);
        }

        return {
            init,
            afterRender,
            saveDirty
        };
    }

    window.BRTop = window.BRTop || {};
    window.BRTop.protocolEditor = {
        protocolEditorHtml,
        createController
    };
})();
