<?php
script('brtop', 'main');
style('brtop', 'style');
?>

<div id="brtop-app">
    <h1>BR TOP- und Sitzungsverwaltung</h1>

    <section class="brtop-card">
        <h2>Neue Sitzung</h2>

        <label>Titel</label>
        <input id="meeting-title" type="text" value="Ordentliche BR-Sitzung">

        <label>Datum</label>
        <input id="meeting-date" type="date">

        <label>Uhrzeit</label>
        <input id="meeting-time" type="time" value="10:00">

        <label>Ort</label>
        <input id="meeting-location" type="text" value="BR-Büro / Videokonferenz">

        <button id="create-meeting">Sitzung anlegen</button>
        <button id="seed-demo">Demo-Sitzung anlegen</button>
    </section>

    <section class="brtop-card">
        <h2>TOP hinzufügen</h2>

        <label>Sitzung</label>
        <select id="top-meeting-select"></select>

        <label>Einordnung</label>
        <select id="top-type">
            <option value="protocol">1. Protokolle</option>
            <option value="personnel_99">2.1 Personelle Einzelmaßnahme nach § 99 BetrVG</option>
            <option value="personnel_100">2.2 Vorläufige personelle Maßnahme nach § 100 BetrVG</option>
            <option value="personnel_102">2.3 Anhörung zu Kündigung nach § 102 BetrVG</option>
            <option value="organisation">3. Arbeitsorganisatorisches</option>
            <option value="consultation_report">4. Bericht aus den Sprechstunden</option>
            <option value="other">5. Weiterer TOP</option>
        </select>

        <label>Betreff</label>
        <input id="top-subject" type="text" placeholder="Einstellung Hans Müller">

        <label>Person</label>
        <input id="top-person" type="text" placeholder="Hans Müller">

        <label>Rechtsgrundlage</label>
        <input id="top-legal" type="text" placeholder="wird bei §99/§100/§102 automatisch ergänzt">

        <label class="brtop-checkline">
            <input id="top-requires-resolution" type="checkbox">
            Dieser TOP benötigt einen Beschluss
        </label>

        <label>Beschlusstyp</label>
        <select id="top-resolution-kind">
            <option value="consent_refusal">Zustimmungsverweigerung</option>
            <option value="urgency_dispute">§ 100: Dringlichkeit bestreiten</option>
            <option value="dismissal_objection">§ 102: Kündigung widersprechen</option>
            <option value="approval_general">Allgemeiner Zustimmungsbeschluss</option>
            <option value="delegation_training">Entsendung / Schulung</option>
            <option value="works_agreement">Betriebsvereinbarung beschließen</option>
            <option value="committee_mandate">Ausschuss / Arbeitsauftrag</option>
            <option value="legal_mandate">Beauftragung / Verfahren</option>
            <option value="protocol_approval">Protokollgenehmigung</option>
        </select>

        <label>Beschlussfrage / Beschlussvorschlag</label>
        <textarea id="top-resolution" rows="4" placeholder="wird aus Beschlusstyp und Maßnahme vorgeschlagen"></textarea>

        <button id="add-top">TOP speichern</button>
    </section>

    <section class="brtop-card">
        <h2>Ausgabe</h2>
        <p class="brtop-help">Einladungen werden zuerst als kopierbare E-Mail erzeugt. Protokollvorlagen und Beschlussdokumente werden erst im jeweiligen Schritt erstellt.</p>

        <label>Zuletzt erzeugter Text</label>
        <textarea id="result-box" rows="12" readonly></textarea>
        <button id="copy-result">Text kopieren</button>
    </section>

    <section class="brtop-card">
        <h2>Sitzungen</h2>
        <div id="state"></div>
    </section>
</div>
