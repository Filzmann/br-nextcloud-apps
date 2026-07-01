<?php

declare(strict_types=1);

namespace OCA\BrTop\Service;

use OCA\BrTop\AppInfo\Application;
use OCP\IConfig;

class BrtopSettingsService {
    public function __construct(
        private IConfig $config
    ) {
    }

    public function values(): array {
        return [
            'defaultMeetingTitle' => $this->defaultMeetingTitle(),
            'regularMeetingWeekday' => $this->regularMeetingWeekday(),
            'invitationWeekday' => $this->invitationWeekday(),
            'defaultMeetingTime' => $this->defaultMeetingTime(),
            'defaultLocation' => $this->defaultLocation(),
            'memberGroupName' => $this->memberGroupName(),
            'meetingTypes' => $this->meetingTypes(),
            'committeeCodes' => $this->committeeCodes(),
        ];
    }

    public function save(
        string $defaultMeetingTitle,
        int $regularMeetingWeekday,
        int $invitationWeekday,
        string $defaultMeetingTime,
        string $defaultLocation,
        string $memberGroupName,
        string $regularAgendaTemplateJson
    ): array {
        $this->setStringValue('default_meeting_title', $defaultMeetingTitle, 'Ordentliche BR-Sitzung');
        $this->setWeekdayValue('regular_meeting_weekday', $regularMeetingWeekday);
        $this->setWeekdayValue('invitation_weekday', $invitationWeekday);
        $this->setTimeValue('default_meeting_time', $defaultMeetingTime);
        $this->setStringValue('default_location', $defaultLocation, 'BR-Büro / Videokonferenz');
        $this->setStringValue('member_group_name', $memberGroupName, 'Betriebsrat');
        $this->setTextValue('regular_agenda_template_json', $regularAgendaTemplateJson);

        return $this->values();
    }

    public function defaultMeetingTitle(): string {
        return $this->stringValue('default_meeting_title', 'Ordentliche BR-Sitzung');
    }

    public function regularMeetingWeekday(): int {
        return $this->weekdayValue('regular_meeting_weekday', 2);
    }

    public function invitationWeekday(): int {
        return $this->weekdayValue('invitation_weekday', 5);
    }

    public function defaultMeetingTime(): string {
        return $this->stringValue('default_meeting_time', '10:00');
    }

    public function defaultLocation(): string {
        return $this->stringValue('default_location', 'BR-Büro / Videokonferenz');
    }

    public function memberGroupName(): string {
        return $this->stringValue('member_group_name', 'Betriebsrat');
    }

    public function regularAgendaTemplateJson(): string {
        return trim($this->config->getAppValue(Application::APP_ID, 'regular_agenda_template_json', ''));
    }

    public function meetingTypes(): array {
        return [
            ['value' => 'regular_br', 'label' => 'Reguläre BR-Sitzung', 'requiresCommittee' => false],
            ['value' => 'monthly_talk', 'label' => 'Monatsgespräch', 'requiresCommittee' => false],
            ['value' => 'works_committee', 'label' => 'Betriebsausschuss', 'requiresCommittee' => false],
            ['value' => 'committee', 'label' => 'Ausschuss / AG', 'requiresCommittee' => true],
            ['value' => 'custom', 'label' => 'Freie Sitzung', 'requiresCommittee' => false],
        ];
    }

    public function committeeCodes(): array {
        return [
            ['value' => 'ASA', 'label' => 'ASA'],
            ['value' => 'DPA', 'label' => 'DPA'],
            ['value' => 'IKT', 'label' => 'IKT'],
            ['value' => 'BA', 'label' => 'BA'],
            ['value' => 'IBF', 'label' => 'IBF'],
        ];
    }

    public function normalizeMeetingType(string $meetingType): string {
        $meetingType = trim($meetingType);
        foreach ($this->meetingTypes() as $type) {
            if ($type['value'] === $meetingType) {
                return $meetingType;
            }
        }

        return 'custom';
    }

    public function normalizeCommitteeCode(string $committeeCode): string {
        $committeeCode = strtoupper(trim($committeeCode));
        foreach ($this->committeeCodes() as $committee) {
            if ($committee['value'] === $committeeCode) {
                return $committeeCode;
            }
        }

        return '';
    }

    private function stringValue(string $key, string $default): string {
        $value = trim($this->config->getAppValue(Application::APP_ID, $key, $default));

        return $value !== '' ? $value : $default;
    }

    private function weekdayValue(string $key, int $default): int {
        $value = (int)$this->config->getAppValue(Application::APP_ID, $key, (string)$default);

        if ($value < 1 || $value > 7) {
            return $default;
        }

        return $value;
    }

    private function setStringValue(string $key, string $value, string $default): void {
        $value = trim($value);
        if ($value === '') {
            $value = $default;
        }

        $this->config->setAppValue(Application::APP_ID, $key, substr($value, 0, 255));
    }

    private function setTextValue(string $key, string $value): void {
        $this->config->setAppValue(Application::APP_ID, $key, trim($value));
    }

    private function setWeekdayValue(string $key, int $value): void {
        if ($value < 1 || $value > 7) {
            throw new \InvalidArgumentException('Ungültiger Wochentag.');
        }

        $this->config->setAppValue(Application::APP_ID, $key, (string)$value);
    }

    private function setTimeValue(string $key, string $value): void {
        $value = trim($value);
        if (!preg_match('/^([01]\d|2[0-3]):[0-5]\d$/', $value)) {
            throw new \InvalidArgumentException('Ungültige Uhrzeit.');
        }

        $this->config->setAppValue(Application::APP_ID, $key, $value);
    }
}
