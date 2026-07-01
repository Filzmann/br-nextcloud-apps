<?php

declare(strict_types=1);

namespace OCA\BrTop\Service;

use DateTimeImmutable;

class MeetingScheduleService {
    public function __construct(
        private BrtopSettingsService $settings
    ) {
    }

    public function nextRegularMeetingDefaults(?DateTimeImmutable $referenceDate = null): array {
        $meetingDate = $this->nextWeekday($referenceDate ?? new DateTimeImmutable('today'), $this->settings->regularMeetingWeekday());
        $invitationDate = $this->previousWeekday($meetingDate, $this->settings->invitationWeekday());

        return [
            'title' => $this->settings->defaultMeetingTitle(),
            'meetingDate' => $meetingDate->format('Y-m-d'),
            'meetingTime' => $this->settings->defaultMeetingTime(),
            'location' => $this->settings->defaultLocation(),
            'invitationDate' => $invitationDate->format('Y-m-d'),
        ];
    }

    private function nextWeekday(DateTimeImmutable $referenceDate, int $weekday): DateTimeImmutable {
        $currentWeekday = (int)$referenceDate->format('N');
        $daysUntil = ($weekday - $currentWeekday + 7) % 7;

        if ($daysUntil === 0) {
            $daysUntil = 7;
        }

        return $referenceDate->modify('+' . $daysUntil . ' days');
    }

    private function previousWeekday(DateTimeImmutable $referenceDate, int $weekday): DateTimeImmutable {
        $currentWeekday = (int)$referenceDate->format('N');
        $daysSince = ($currentWeekday - $weekday + 7) % 7;

        if ($daysSince === 0) {
            $daysSince = 7;
        }

        return $referenceDate->modify('-' . $daysSince . ' days');
    }
}
