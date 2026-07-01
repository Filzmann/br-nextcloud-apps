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

        <label>Sitzungstyp</label>
        <select id="meeting-type"></select>

        <label>Ausschuss / AG</label>
        <select id="meeting-committee"></select>

        <label>Datum</label>
        <input id="meeting-date" type="date">

        <label>Uhrzeit</label>
        <input id="meeting-time" type="time" value="10:00">

        <label>Ort</label>
        <input id="meeting-location" type="text" value="BR-Büro / Videokonferenz">

        <button id="create-meeting">Sitzung anlegen</button>
        <button id="plan-next-meeting">Nächste BR-Sitzung planen</button>
        <button id="seed-demo">Demo-Sitzung anlegen</button>
    </section>

    <section class="brtop-card">
        <h2>Einstellungen</h2>

        <label>Standardtitel</label>
        <input id="settings-default-title" type="text">

        <label>Sitzungstag</label>
        <select id="settings-meeting-weekday">
            <option value="1">Montag</option>
            <option value="2">Dienstag</option>
            <option value="3">Mittwoch</option>
            <option value="4">Donnerstag</option>
            <option value="5">Freitag</option>
            <option value="6">Samstag</option>
            <option value="7">Sonntag</option>
        </select>

        <label>Ladungstag</label>
        <select id="settings-invitation-weekday">
            <option value="1">Montag</option>
            <option value="2">Dienstag</option>
            <option value="3">Mittwoch</option>
            <option value="4">Donnerstag</option>
            <option value="5">Freitag</option>
            <option value="6">Samstag</option>
            <option value="7">Sonntag</option>
        </select>

        <label>Standarduhrzeit</label>
        <input id="settings-default-time" type="time">

        <label>Standardort</label>
        <input id="settings-default-location" type="text">

        <label>Mitgliedergruppe</label>
        <input id="settings-member-group" type="text">

        <label>Standardagenda BR-Sitzung (JSON)</label>
        <textarea id="settings-agenda-template" rows="18" spellcheck="false"></textarea>

        <button id="save-settings">Einstellungen speichern</button>
    </section>

    <section class="brtop-card">
        <h2>TOP hinzufügen</h2>

        <label>Sitzung</label>
        <select id="top-meeting-select"></select>

        <label>Übergeordneter TOP</label>
        <select id="top-parent-select"></select>

        <label>Einordnung</label>
        <select id="top-type">
            <option value="personnel">2. Personelle Angelegenheiten</option>
            <option value="protocol">1. Protokolle</option>
            <option value="personnel_99">2.1 Personelle Einzelmaßnahme nach § 99 BetrVG</option>
            <option value="personnel_100">2.2 Vorläufige personelle Maßnahme nach § 100 BetrVG</option>
            <option value="personnel_102">2.3 Anhörung zu Kündigung nach § 102 BetrVG</option>
            <option value="organisation">3. Arbeitsorganisatorisches</option>
            <option value="consultation_report">4. Bericht aus den Sprechstunden</option>
            <option value="other">5. Weiterer TOP</option>
        </select>

        <label>TOP-Art</label>
        <select id="top-agenda-kind">
            <option value="discussion">Beratung</option>
            <option value="report">Bericht</option>
            <option value="resolution">Beschluss</option>
            <option value="section">Gliederungspunkt</option>
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

        <label>Protokollinhalt / Vorlage</label>
        <textarea id="top-protocol-content" rows="4" placeholder="optional, wird in die Protokollvorlage übernommen"></textarea>

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
