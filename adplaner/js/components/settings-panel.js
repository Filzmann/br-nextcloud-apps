(function() {
    const { esc, dateShort } = window.ADPlaner.ui;

    function render(team) {
        if (!team) {
            return '<p>Kein Assistenznehmer gewaehlt.</p>';
        }

        const settings = team.settings || {};
        const starts = settings.shiftStarts || {};
        const enabled = settings.enabledSegments || {};

        if (!team.canCoordinate) {
            return `
                <section class="adp-section">
                    <div class="adp-section-head">
                        <h2>${esc(team.displayName || team.code)}</h2>
                    </div>
                    <dl class="adp-settings-readonly">
                        <dt>Assistentinnentreffen</dt>
                        <dd>${settings.meetingDay ? esc(dateShort(settings.meetingDay)) : '-'}</dd>
                        <dt>Frueh</dt>
                        <dd>${esc(starts.early || '')}</dd>
                        <dt>Spaet</dt>
                        <dd>${esc(starts.late || '')}</dd>
                        <dt>Nacht</dt>
                        <dd>${esc(starts.night || '')}</dd>
                    </dl>
                </section>
            `;
        }

        return `
            <section class="adp-section">
                <div class="adp-section-head">
                    <h2>${esc(team.displayName || team.code)}</h2>
                </div>
                <form id="settings-form" class="adp-settings-form">
                    <label>Anzeigename <input name="displayName" type="text" maxlength="255" value="${esc(team.displayName || team.code)}"></label>
                    <label>Assistentinnentreffen <input name="meetingDay" type="date" value="${esc(settings.meetingDay || '')}"></label>
                    <fieldset>
                        <legend>Schichtgrenzen</legend>
                        <label>Beginn Frueh <input name="earlyStart" type="time" value="${esc(starts.early || '06:00')}"></label>
                        <label>Beginn Spaet <input name="lateStart" type="time" value="${esc(starts.late || '14:00')}"></label>
                        <label>Beginn Nacht <input name="nightStart" type="time" value="${esc(starts.night || '22:00')}"></label>
                    </fieldset>
                    <fieldset>
                        <legend>Schichten</legend>
                        ${checkbox('enabledBeforeEarly', '0 bis Frueh', enabled.before_early !== false)}
                        ${checkbox('enabledEarly', 'Frueh', enabled.early !== false)}
                        ${checkbox('enabledLate', 'Spaet', enabled.late !== false)}
                        ${checkbox('enabledNight', 'Nacht bis 24', enabled.night !== false)}
                    </fieldset>
                    <button type="submit">Speichern</button>
                </form>
            </section>
        `;
    }

    function checkbox(name, label, checked) {
        return `
            <label class="adp-check">
                <input name="${esc(name)}" type="checkbox" ${checked ? 'checked' : ''}>
                ${esc(label)}
            </label>
        `;
    }

    window.ADPlaner = window.ADPlaner || {};
    window.ADPlaner.settingsPanel = { render };
})();
