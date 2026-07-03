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
script('brtop', 'components/top-form');
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

        <?php require __DIR__ . '/partials/top-form.php'; ?>
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
