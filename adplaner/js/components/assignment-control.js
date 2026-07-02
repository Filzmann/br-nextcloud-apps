(function() {
    const { esc } = window.ADPlaner.ui;

    function render(slot, team, candidates) {
        const assigned = new Set((candidates || []).map(candidate => candidate.uid));
        const assistants = (team.assistants || []).filter(assistant => {
            return assistant.canReceiveShifts !== false && !assigned.has(assistant.uid);
        });

        return `
            <span class="adp-assignment-control" data-assignment-control="${esc(slot.id)}">
                <button type="button" class="adp-small adp-icon-button" title="Assistenz zuteilen" data-action="open-assignment-picker" data-slot-id="${esc(slot.id)}">+</button>
                <span class="adp-assignment-picker" data-assignment-picker="${esc(slot.id)}" hidden>
                    <select data-add-select="${esc(slot.id)}" ${assistants.length === 0 ? 'disabled' : ''}>
                        ${assistants.map(option).join('')}
                    </select>
                    <button type="button" class="adp-small adp-icon-button" title="Zuteilen" data-action="add-selected" data-slot-id="${esc(slot.id)}" ${assistants.length === 0 ? 'disabled' : ''}>&#10003;</button>
                </span>
            </span>
        `;
    }

    function option(assistant) {
        return `<option value="${esc(assistant.uid)}">${esc(assistant.displayName || assistant.uid)}</option>`;
    }

    function open(button) {
        const slotId = button && button.dataset ? button.dataset.slotId : '';
        if (!slotId) {
            return;
        }

        document.querySelectorAll('[data-assignment-picker]').forEach(picker => {
            picker.hidden = picker.dataset.assignmentPicker !== slotId;
        });

        const picker = document.querySelector(`[data-assignment-picker="${CSS.escape(slotId)}"]`);
        const select = picker ? picker.querySelector('select') : null;
        if (select) {
            select.focus();
        }
    }

    window.ADPlaner = window.ADPlaner || {};
    window.ADPlaner.assignmentControl = { render, open };
})();
