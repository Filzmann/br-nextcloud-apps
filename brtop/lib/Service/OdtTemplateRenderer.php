<?php

declare(strict_types=1);

namespace OCA\BrTop\Service;

use RuntimeException;
use ZipArchive;

class OdtTemplateRenderer {
    public function renderProtocol(array $meeting, array $tops): string {
        $templatePath = __DIR__ . '/../../templates/odt/protokoll-template.odt';

        if (!is_file($templatePath)) {
            throw new RuntimeException('ODT-Template nicht gefunden.');
        }

        $settings = [
            'GREMIENNAME' => 'Betriebsrat ambulante dienste e.V.',
            'GREMIUM_ADRESSE' => 'Wilhelm-Kabus-Str. 21-35, 10829 Berlin',
        ];

        $replacements = [
            '{{GREMIENNAME}}' => $settings['GREMIENNAME'],
            '{{GREMIUM_ADRESSE}}' => $settings['GREMIUM_ADRESSE'],
        ];

        $zip = new ZipArchive();
        if ($zip->open($templatePath) !== true) {
            throw new RuntimeException('ODT-Template konnte nicht geöffnet werden.');
        }

        $tmp = tempnam(sys_get_temp_dir(), 'brtop-odt-');
        if ($tmp === false) {
            $zip->close();
            throw new RuntimeException('Temporäre ODT-Datei konnte nicht erzeugt werden.');
        }

        $out = new ZipArchive();
        if ($out->open($tmp, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
            $zip->close();
            throw new RuntimeException('ODT-Zieldatei konnte nicht geschrieben werden.');
        }

        for ($i = 0; $i < $zip->numFiles; $i++) {
            $name = $zip->getNameIndex($i);
            if ($name === false) {
                continue;
            }

            $data = $zip->getFromName($name);
            if ($data === false) {
                continue;
            }

            if ($name === 'content.xml') {
                $data = $this->replaceOfficeText($data, $this->buildProtocolOfficeText($meeting, $tops));
            }

            if ($name === 'styles.xml') {
                $data = str_replace(
                    array_keys($replacements),
                    array_map([$this, 'xml'], array_values($replacements)),
                    $data
                );
            }

            $out->addFromString($name, $data);
        }

        if ($out->locateName('mimetype') !== false) {
            $out->setCompressionName('mimetype', ZipArchive::CM_STORE);
        }

        $zip->close();
        $out->close();

        $result = file_get_contents($tmp);
        @unlink($tmp);

        if ($result === false) {
            throw new RuntimeException('ODT-Zieldatei konnte nicht gelesen werden.');
        }

        return $result;
    }

    private function replaceOfficeText(string $contentXml, string $officeText): string {
        $pattern = '/<office:text\b[^>]*>.*?<\/office:text>/s';

        if (!preg_match($pattern, $contentXml)) {
            throw new RuntimeException('office:text wurde in content.xml nicht gefunden.');
        }

        return preg_replace($pattern, $officeText, $contentXml, 1);
    }

    private function buildProtocolOfficeText(array $meeting, array $tops): string {
        $date = $this->formatGermanDate((string)($meeting['meeting_date'] ?? ''));

        $groups = $this->groupTops($tops);

        $xml = [];

        $xml[] = '<office:text>';
        $xml[] = $this->p('BR-Sitzung ' . $date, 'TitleCenter');

        $xml[] = '<table:table table:name="Anwesenheit" table:style-name="BoxTableFull">';
        $xml[] = '<table:table-column table:style-name="ColFull"/>';
        $xml[] = '<table:table-row><table:table-cell table:style-name="BoxCell">';
        $xml[] = $this->pSpan('Anwesend:', '{{ANWESEND}}', 'SmallTight');
        $xml[] = $this->pSpan('Entschuldigt:', '{{ENTSCHULDIGT}}', 'SmallTight');
        $xml[] = $this->pSpan('Unentschuldigt:', '{{UNENTSCHULDIGT}}', 'SmallTight');
        $xml[] = '</table:table-cell></table:table-row></table:table>';

        $xml[] = $this->emptyP();
        $xml[] = $this->p('Moderation: {{MODERATION}}', 'SmallTight');
        $xml[] = $this->p('Protokoll: {{PROTOKOLL}}', 'SmallTight');
        $xml[] = $this->emptyP();
        $xml[] = $this->p('Anwesenheit und ordnungsgemäße Einladung zu den Tagesordnungspunkten werden festgestellt. Der Betriebsrat ist beschlussfähig.', 'SmallTight');
        $xml[] = $this->emptyP();

        $xml[] = $this->tocTable();
        $xml[] = $this->emptyP();

        $xml[] = $this->h('1. Protokolle', 1);
        $this->appendSimpleTops($xml, $groups['protocol'], '1', 'Keine Protokolle eingetragen.');

        $xml[] = $this->h('2. Personelle Angelegenheiten', 1);

        $xml[] = $this->h('2.1 Personelle Einzelmaßnahmen nach § 99 BetrVG', 2);
        if (count($groups['personnel_99']) === 0) {
            $xml[] = $this->p('Keine personellen Einzelmaßnahmen nach § 99 BetrVG eingetragen.', 'Text_20_body');
        } else {
            foreach ($groups['personnel_99'] as $index => $top) {
                $n = $index + 1;

                $xml[] = $this->h(
                    '2.1.' . $n . '. ' . $this->subjectLabel($top, 'Personelle Einzelmaßnahme'),
                    3
                );

                $xml[] = $this->p('Person: ' . $this->personLabel($top), 'SmallTight');
                $xml[] = $this->p('(Schreiben der GF vom {{GF_SCHREIBEN_VOM}}, empfangsbestätigt am {{EMPFANGSBESTAETIGT_AM}}).', 'SmallTight');
                $xml[] = $this->beschlusskasten($this->resolutionQuestion($top), 'BeschlusskastenPersonell99' . $n);
                $xml[] = $this->p('Damit ist die Zustimmung verweigert / nicht verweigert. Es geht ein Schreiben / kein Schreiben an GF.', 'SmallTight');
                $xml[] = $this->emptyP();
            }
        }

        $xml[] = $this->h('2.2 Vorläufige personelle Maßnahmen nach § 100 BetrVG', 2);
        if (count($groups['personnel_100']) === 0) {
            $xml[] = $this->p('Keine vorläufigen personellen Maßnahmen nach § 100 BetrVG eingetragen.', 'Text_20_body');
        } else {
            foreach ($groups['personnel_100'] as $index => $top) {
                $n = $index + 1;

                $xml[] = $this->h(
                    '2.2.' . $n . '. ' . $this->subjectLabel($top, 'Vorläufige personelle Maßnahme'),
                    3
                );

                $xml[] = $this->p('Person: ' . $this->personLabel($top), 'SmallTight');
                $xml[] = $this->p('(Schreiben der GF vom {{GF_SCHREIBEN_VOM}}, empfangsbestätigt am {{EMPFANGSBESTAETIGT_AM}}).', 'SmallTight');
                $xml[] = $this->beschlusskasten($this->resolutionQuestion($top), 'BeschlusskastenPersonell100' . $n);
                $xml[] = $this->p('Damit ist die Dringlichkeit bestritten / nicht bestritten. Es geht ein Schreiben / kein Schreiben an GF.', 'SmallTight');
                $xml[] = $this->emptyP();
            }
        }

        $xml[] = $this->h('2.3 Anhörungen zu Kündigungen nach § 102 BetrVG', 2);
        if (count($groups['personnel_102']) === 0) {
            $xml[] = $this->p('Keine Anhörungen zu Kündigungen nach § 102 BetrVG eingetragen.', 'Text_20_body');
        } else {
            foreach ($groups['personnel_102'] as $index => $top) {
                $n = $index + 1;

                $xml[] = $this->h(
                    '2.3.' . $n . '. ' . $this->subjectLabel($top, 'Anhörung zu Kündigung'),
                    3
                );

                $xml[] = $this->p('Person: ' . $this->personLabel($top), 'SmallTight');
                $xml[] = $this->p('(Schreiben der GF vom {{GF_SCHREIBEN_VOM}}, empfangsbestätigt am {{EMPFANGSBESTAETIGT_AM}}).', 'SmallTight');
                $xml[] = $this->beschlusskasten($this->resolutionQuestion($top), 'BeschlusskastenKuendigung102' . $n);
                $xml[] = $this->p('Damit ist der beabsichtigten Kündigung widersprochen / nicht widersprochen. Es geht ein Schreiben / kein Schreiben an GF.', 'SmallTight');
                $xml[] = $this->emptyP();
            }
        }

        $xml[] = $this->h('3. Arbeitsorganisatorisches:', 1);
        $xml[] = $this->h('3.1 nächste Sitzung', 2);
        $xml[] = $this->p('Vorbereitung:', 'SmallTight');
        $xml[] = $this->p('Nachbereitung:', 'SmallTight');
        $xml[] = $this->p('Moderation:', 'SmallTight');
        $xml[] = $this->p('Protokoll:', 'SmallTight');
        $xml[] = $this->p('Nextcloud:', 'SmallTight');
        $xml[] = $this->p('TOPs:', 'SmallTight');
        $xml[] = $this->emptyP();

        $xml[] = $this->h('4. Bericht aus den Sprechstunden', 1);
        $xml[] = $this->emptyP();

        $xml[] = $this->h('5. Weitere Tagesordnungspunkte', 1);
        if (count($groups['other']) === 0) {
            $xml[] = $this->p('Keine weiteren Tagesordnungspunkte eingetragen.', 'Text_20_body');
        }
        foreach ($groups['other'] as $index => $top) {
            $xml[] = $this->h('5.' . ($index + 1) . '. ' . (string)($top['subject'] ?? 'weiterer TOP'), 2);
            if ((int)($top['requires_resolution'] ?? 0) === 1) {
                $xml[] = $this->beschlusskasten($this->resolutionQuestion($top), 'BeschlusskastenSonstige' . ($index + 1));
            }
        }

        $xml[] = $this->emptyP();
        $xml[] = $this->signaturesTable();
        $xml[] = '</office:text>';

        return implode("\n", $xml);
    }

    public function beschlusskasten(string $frage, string $name = 'Beschlusskasten'): string {
        return implode("\n", [
            '<table:table table:name="' . $this->xml($name) . '" table:style-name="VoteTableFull">',
            '<table:table-column table:style-name="VoteColFull"/>',
            '<table:table-column table:style-name="VoteColFull"/>',
            '<table:table-column table:style-name="VoteColFull"/>',
            '<table:table-row>',
            '<table:table-cell table:style-name="VoteCell" table:number-columns-spanned="3">',
            '<text:p text:style-name="SmallTight">Nach Erörterung stimmt der BR ab:<text:line-break/><text:span text:style-name="T_Bold">' . $this->xml($frage) . '</text:span></text:p>',
            '</table:table-cell><table:covered-table-cell/><table:covered-table-cell/></table:table-row>',
            '<table:table-row>',
            '<table:table-cell table:style-name="VoteCell">' . $this->p('Ja-Stimmen: ?', 'SmallTight') . '</table:table-cell>',
            '<table:table-cell table:style-name="VoteCell">' . $this->p('Enthaltungen: ?', 'SmallTight') . '</table:table-cell>',
            '<table:table-cell table:style-name="VoteCell">' . $this->p('Nein-Stimmen: ?', 'SmallTight') . '</table:table-cell>',
            '</table:table-row>',
            '</table:table>',
        ]);
    }

    private function tocTable(): string {
        return implode("\n", [
            '<table:table table:name="Inhaltsverzeichnis" table:style-name="TocTableFull">',
            '<table:table-column table:style-name="ColFull"/>',
            '<table:table-row><table:table-cell table:style-name="TocTitleCell">',
            $this->p('Inhaltsverzeichnis', 'TocTitle'),
            '</table:table-cell></table:table-row>',
            '<table:table-row><table:table-cell table:style-name="TocBodyCell">',
            '<text:table-of-content text:name="Inhaltsverzeichnis1" text:protected="false">',
            '<text:table-of-content-source text:outline-level="3" text:use-outline-level="true">',
            '<text:table-of-content-entry-template text:outline-level="1" text:style-name="TocEntry1"><text:index-entry-text/><text:index-entry-tab-stop style:type="right" style:leader-char="."/><text:index-entry-page-number/></text:table-of-content-entry-template>',
            '<text:table-of-content-entry-template text:outline-level="2" text:style-name="TocEntry2"><text:index-entry-text/><text:index-entry-tab-stop style:type="right" style:leader-char="."/><text:index-entry-page-number/></text:table-of-content-entry-template>',
            '<text:table-of-content-entry-template text:outline-level="3" text:style-name="TocEntry3"><text:index-entry-text/><text:index-entry-tab-stop style:type="right" style:leader-char="."/><text:index-entry-page-number/></text:table-of-content-entry-template>',
            '</text:table-of-content-source>',
            '<text:index-body>',
            '<text:p text:style-name="TocEntry1">1. Protokolle<text:tab/>1</text:p>',
            '<text:p text:style-name="TocEntry1">2. Personelle Angelegenheiten<text:tab/>1</text:p>',
            '<text:p text:style-name="TocEntry2">2.1 Personelle Einzelmaßnahmen nach § 99 BetrVG<text:tab/>1</text:p>',
            '<text:p text:style-name="TocEntry2">2.2 Vorläufige personelle Maßnahmen nach § 100 BetrVG<text:tab/>1</text:p>',
            '<text:p text:style-name="TocEntry2">2.3 Anhörungen zu Kündigungen nach § 102 BetrVG<text:tab/>1</text:p>',
            '<text:p text:style-name="TocEntry1">3. Arbeitsorganisatorisches:<text:tab/>1</text:p>',
            '<text:p text:style-name="TocEntry2">3.1 nächste Sitzung<text:tab/>1</text:p>',
            '<text:p text:style-name="TocEntry1">4. Bericht aus den Sprechstunden<text:tab/>1</text:p>',
            '<text:p text:style-name="TocEntry1">5. Weitere Tagesordnungspunkte<text:tab/>1</text:p>',
            '</text:index-body>',
            '</text:table-of-content>',
            '</table:table-cell></table:table-row>',
            '</table:table>',
        ]);
    }

    private function signaturesTable(): string {
        return implode("\n", [
            '<table:table table:name="Unterschriften" table:style-name="SigOuterFull">',
            '<table:table-column table:style-name="SigColFull"/>',
            '<table:table-column table:style-name="SigGapFull"/>',
            '<table:table-column table:style-name="SigColFull"/>',
            '<table:table-row>',
            '<table:table-cell table:style-name="SigCell">',
            $this->p('X', 'SigX'),
            $this->p('Betriebsratsvorsitzender', 'SigLabel'),
            '</table:table-cell>',
            '<table:table-cell table:style-name="NoBorderCell"/>',
            '<table:table-cell table:style-name="SigCell">',
            $this->p('X', 'SigX'),
            $this->p('Protokollant', 'SigLabel'),
            '</table:table-cell>',
            '</table:table-row>',
            '</table:table>',
        ]);
    }

    private function h(string $text, int $level): string {
        $style = match ($level) {
            1 => 'Heading_20_1',
            2 => 'Heading_20_2',
            default => 'Heading_20_3',
        };

        return '<text:h text:outline-level="' . $level . '" text:style-name="' . $style . '">' . $this->xml($text) . '</text:h>';
    }

    private function p(string $text, string $style = 'Standard'): string {
        return '<text:p text:style-name="' . $this->xml($style) . '">' . $this->xml($text) . '</text:p>';
    }

    private function emptyP(): string {
        return '<text:p text:style-name="Standard"/>';
    }

    private function pSpan(string $label, string $value, string $style): string {
        return '<text:p text:style-name="' . $this->xml($style) . '"><text:span text:style-name="T_Bold">' . $this->xml($label) . '</text:span> ' . $this->xml($value) . '</text:p>';
    }

    private function appendSimpleTops(array &$xml, array $tops, string $prefix, string $emptyText): void {
        if (count($tops) === 0) {
            $xml[] = $this->p($emptyText, 'Text_20_body');
            return;
        }

        foreach ($tops as $index => $top) {
            $n = $index + 1;

            $xml[] = $this->h($prefix . '.' . $n . '. ' . $this->subjectLabel($top, 'TOP ergänzen'), 2);

            if (!empty($top['legal_basis'])) {
                $xml[] = $this->p('Rechtsgrundlage: ' . (string)$top['legal_basis'], 'SmallTight');
            }

            if ((int)($top['requires_resolution'] ?? 0) === 1) {
                $xml[] = $this->beschlusskasten($this->resolutionQuestion($top), 'BeschlusskastenTop' . str_replace('.', '_', $prefix) . '_' . $n);
            }

            $xml[] = $this->emptyP();
        }
    }

    private function groupTops(array $tops): array {
        $groups = [
            'protocol' => [],
            'personnel_99' => [],
            'personnel_100' => [],
            'personnel_102' => [],
            'organisation' => [],
            'consultation_report' => [],
            'other' => [],
        ];

        foreach ($tops as $top) {
            if (($top['agenda_item_kind'] ?? '') === 'section') {
                continue;
            }

            $type = (string)($top['type'] ?? '');

            $key = match ($type) {
                'protocol', 'protokolle' => 'protocol',
                'personnel_99', 'personelle_einzelmassnahme', 'pe_einstellung', 'pe_sonstige' => 'personnel_99',
                'personnel_100' => 'personnel_100',
                'personnel_102', 'kuendigung', 'pe_kuendigung' => 'personnel_102',
                'organisation' => 'organisation',
                'consultation_report', 'sprechstunden' => 'consultation_report',
                default => 'other',
            };

            $groups[$key][] = $top;
        }

        return $groups;
    }

    private function subjectLabel(array $top, string $fallback): string {
        $subject = trim((string)($top['subject'] ?? ''));

        return $subject !== '' ? $subject : $fallback;
    }

    private function measureLabel(array $top): string {
        $subject = trim((string)($top['subject'] ?? ''));
        if ($subject !== '') {
            return $subject;
        }

        $person = trim((string)($top['person_name'] ?? ''));
        if ($person !== '') {
            return $person;
        }

        return 'die Maßnahme';
    }

    private function resolutionQuestion(array $top): string {
        $question = trim((string)($top['resolution_text'] ?? ''));
        if ($question !== '') {
            return $question;
        }

        $measure = $this->measureLabel($top);

        return match ((string)($top['type'] ?? '')) {
            'personnel_99', 'personelle_einzelmassnahme', 'pe_einstellung', 'pe_sonstige'
                => 'Wer verweigert die Zustimmung zu ' . $measure . ' und widerspricht ihr damit?',
            'personnel_100'
                => 'Wer bestreitet, dass die vorläufige Durchführung der personellen Maßnahme ' . $measure . ' aus sachlichen Gründen dringend erforderlich ist?',
            'personnel_102', 'kuendigung', 'pe_kuendigung'
                => 'Wer widerspricht der beabsichtigten Kündigung ' . $measure . ' gemäß § 102 BetrVG?',
            default
                => 'Wer stimmt ' . $measure . ' zu?',
        };
    }

    private function personLabel(array $top): string {
        $person = trim((string)($top['person_name'] ?? ''));
        if ($person !== '') {
            return $person;
        }

        return trim((string)($top['subject'] ?? 'Person ergänzen'));
    }

    private function xml(string $value): string {
        return htmlspecialchars($value, ENT_XML1 | ENT_COMPAT, 'UTF-8');
    }

    private function formatGermanDate(string $date): string {
        $ts = strtotime($date);
        if ($ts === false) {
            return $date;
        }

        return date('d.m.Y', $ts);
    }
}
