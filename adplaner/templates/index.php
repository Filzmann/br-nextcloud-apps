<?php
script('adplaner', 'modules/api');
script('adplaner', 'modules/ui');
script('adplaner', 'components/month-plan');
script('adplaner', 'components/vacation-plan');
script('adplaner', 'components/settings-panel');
script('adplaner', 'main');
style('adplaner', 'style');
?>

<div id="adplaner-app">
    <header class="adp-head">
        <h1>Assistenzplanung</h1>
        <div class="adp-controls">
            <label>
                Assistenznehmer
                <select id="team-select"></select>
            </label>
            <label>
                Monat
                <input id="month-input" type="month">
            </label>
            <label>
                Jahr
                <input id="year-input" type="number" min="2000" max="2100" step="1">
            </label>
        </div>
    </header>

    <nav class="adp-tabs" aria-label="Planbereiche">
        <button type="button" class="adp-tab is-active" data-view="month">Wunschplan</button>
        <button type="button" class="adp-tab" data-view="vacation">Urlaub</button>
        <button type="button" class="adp-tab" data-view="settings">Einstellungen</button>
    </nav>

    <div id="adp-notice" class="adp-notice" hidden></div>
    <main id="adp-panel" class="adp-panel"></main>
</div>
