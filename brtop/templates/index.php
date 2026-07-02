<?php
use OCA\BrTop\View\UiComponents;

script('brtop', 'modules/api');
script('brtop', 'modules/ui');
script('brtop', 'models/protocol-block');
script('brtop', 'models/agenda-item');
script('brtop', 'models/generated-document');
script('brtop', 'models/meeting');
script('brtop', 'components/meeting-list');
script('brtop', 'components/agenda-list');
script('brtop', 'components/protocol-editor');
script('brtop', 'main');
style('brtop', 'style');
?>

<div id="brtop-app">
    <h1>BR TOP- und Sitzungsverwaltung</h1>

    <section id="sessions-view" class="brtop-card brtop-view is-active" aria-hidden="false">
        <div class="brtop-section-head">
            <h2>Sitzungen</h2>
            <?= UiComponents::preset('newMeeting', ['id' => 'new-meeting']) ?>
        </div>
        <div id="state"></div>
    </section>

    <section id="meeting-detail-view" class="brtop-card brtop-view" aria-hidden="true" hidden>
        <div class="brtop-section-head">
            <h2 id="meeting-detail-heading">Sitzung</h2>
            <div class="brtop-actions">
                <?= UiComponents::preset('backSessions', ['id' => 'back-to-sessions']) ?>
                <?= UiComponents::preset('createInvitation', ['id' => 'detail-create-invitation']) ?>
                <?= UiComponents::preset('editProtocol', ['id' => 'detail-edit-protocol']) ?>
            </div>
        </div>

        <div id="meeting-detail-content"></div>

        <div class="brtop-top-create-actions">
            <?= UiComponents::preset('addTop', ['id' => 'show-top-form', 'aria-expanded' => 'false']) ?>
        </div>

        <div id="top-form-panel" class="brtop-inline-panel" hidden>
            <div class="brtop-section-head">
                <h3>TOP hinzufügen</h3>
                <?= UiComponents::preset('close', ['id' => 'hide-top-form']) ?>
            </div>

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
                <option value="section">Gliederungspunkt</option>
            </select>

            <label>Betreff</label>
            <input id="top-subject" type="text" placeholder="Einstellung Hans Müller">

            <label>Kurzer Text für die Ladung</label>
            <textarea id="top-invitation-note" rows="3" placeholder="optional, erscheint nur in der Ladung"></textarea>

            <label>Anhang-Dateien für die Ladung</label>
            <textarea id="top-attachments" rows="3" placeholder="optional, je Zeile ein Dateiname oder Nextcloud-Pfad"></textarea>

            <div id="top-person-field" class="brtop-form-field" hidden>
                <label>Person</label>
                <input id="top-person" type="text" placeholder="Hans Müller">
            </div>

            <div id="top-legal-field" class="brtop-form-field" hidden>
                <label>Rechtsgrundlage</label>
                <input id="top-legal" type="text" placeholder="wird bei §99/§100/§102 automatisch ergänzt">
            </div>

            <label class="brtop-checkline">
                <input id="top-requires-resolution" type="checkbox">
                Dieser TOP benötigt einen Beschluss
            </label>

            <div id="top-resolution-options" class="brtop-form-field" hidden>
                <label>Anzahl Beschlüsse</label>
                <input id="top-resolution-count" type="number" min="1" step="1" value="1">

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

                <label>Beschlussfrage(n) / Beschlussvorschlag</label>
                <textarea id="top-resolution" rows="4" placeholder="bei mehreren Beschlüssen: eine Frage je Zeile"></textarea>
            </div>

            <label>Protokollinhalt / Vorlage</label>
            <textarea id="top-protocol-content" rows="4" placeholder="optional, wird in die Protokollvorlage übernommen"></textarea>

            <?= UiComponents::preset('saveTop', ['id' => 'add-top']) ?>
        </div>
    </section>

    <section id="protocol-view" class="brtop-card brtop-view" aria-hidden="true" hidden>
        <div class="brtop-section-head">
            <h2 id="protocol-heading">Protokoll bearbeiten</h2>
            <div class="brtop-actions">
                <?= UiComponents::preset('backDetail', ['id' => 'back-to-detail']) ?>
                <?= UiComponents::preset('generateProtocolDocument', ['id' => 'generate-protocol-document']) ?>
            </div>
        </div>
        <div id="protocol-editor"></div>
    </section>

</div>
