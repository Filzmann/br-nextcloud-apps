(function() {
    const { esc, dateShort, monthHeader, statusLabel } = window.ADPlaner.ui;

    function render(plan, currentUser) {
        if (!plan || !plan.team) {
            return '<p>Kein Assistenznehmer gewaehlt.</p>';
        }

        return `
            <section class="adp-section">
                <div class="adp-section-head">
                    <h2>${esc(plan.team.displayName || plan.team.code)} - Urlaub ${esc(plan.year)}</h2>
                </div>
                ${ownVacationForm(plan, currentUser)}
                <div class="adp-table-wrap adp-year-wrap">
                    <table class="adp-table adp-year-table">
                        <thead>
                            <tr>
                                <th>Assistenz</th>
                                ${(plan.days || []).map(day => `<th class="${day.weekday >= 6 ? 'is-weekend' : ''}">${monthHeader(day)}</th>`).join('')}
                            </tr>
                        </thead>
                        <tbody>
                            ${(plan.assistants || []).map(row => assistantRow(row, plan, currentUser)).join('')}
                        </tbody>
                    </table>
                </div>
            </section>
        `;
    }

    function ownVacationForm(plan, currentUser) {
        const uid = currentUser && currentUser.uid ? currentUser.uid : '';
        const requests = (plan.requests || []).filter(request => request.assistantUid === uid);

        return `
            <form id="vacation-form" class="adp-vacation-form">
                <label>Von <input name="dateFrom" type="date" required></label>
                <label>Bis <input name="dateTo" type="date" required></label>
                <label>Notiz <input name="note" type="text" maxlength="255"></label>
                <button type="submit">Eintragen</button>
            </form>
            <div class="adp-request-list">
                ${requests.map(request => requestItem(request)).join('')}
            </div>
        `;
    }

    function requestItem(request) {
        const canDelete = request.status === 'planned';
        return `
            <span class="adp-request adp-request-${esc(request.status)}">
                ${esc(dateShort(request.dateFrom))}-${esc(dateShort(request.dateTo))} - ${esc(statusLabel(request.status))}
                ${canDelete ? `<button type="button" data-action="delete-vacation" data-request-id="${esc(request.id)}">x</button>` : ''}
            </span>
        `;
    }

    function assistantRow(row, plan, currentUser) {
        return `
            <tr>
                <th>${esc(row.displayName || row.uid)}</th>
                ${(plan.days || []).map(day => vacationCell(row, day, plan.team, currentUser)).join('')}
            </tr>
        `;
    }

    function vacationCell(row, day, team, currentUser) {
        const cell = row.days && row.days[day.date] ? row.days[day.date] : { status: '' };
        const status = cell.status || '';
        const title = [row.displayName || row.uid, dateShort(day.date), statusLabel(status)].filter(Boolean).join(' - ');
        const classes = [
            'adp-vac-cell',
            status ? 'adp-vac-' + status : '',
            status ? 'has-vacation' : '',
            day.weekday >= 6 ? 'is-weekend' : ''
        ].filter(Boolean).map(esc).join(' ');

        if (team.canCoordinate) {
            const nextStatus = status === '' ? 'planned' : (status === 'planned' ? 'approved' : 'planned');
            return `
                <td class="${classes}">
                    <button type="button" title="${esc(title)}" aria-label="${esc(title || 'Urlaubsstatus setzen')}" data-action="set-vacation-status" data-target-uid="${esc(row.uid)}" data-date="${esc(day.date)}" data-status="${esc(nextStatus)}"></button>
                </td>
            `;
        }

        return `<td class="${classes}" title="${esc(title)}"></td>`;
    }

    window.ADPlaner = window.ADPlaner || {};
    window.ADPlaner.vacationPlan = { render };
})();
