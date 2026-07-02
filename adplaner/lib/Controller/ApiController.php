<?php

declare(strict_types=1);

namespace OCA\AdPlaner\Controller;

use OCA\AdPlaner\AppInfo\Application;
use OCA\AdPlaner\Service\AdPlanerLogger;
use OCA\AdPlaner\Service\ScheduleService;
use OCA\AdPlaner\Service\TeamAccessService;
use OCA\AdPlaner\Service\TeamSettingsService;
use OCA\AdPlaner\Service\VacationService;
use OCP\AppFramework\Controller;
use OCP\AppFramework\Http;
use OCP\AppFramework\Http\Attribute\NoAdminRequired;
use OCP\AppFramework\Http\DataResponse;
use OCP\IRequest;

class ApiController extends Controller {
    public function __construct(
        IRequest $request,
        private TeamAccessService $teamAccess,
        private TeamSettingsService $teamSettings,
        private ScheduleService $scheduleService,
        private VacationService $vacationService,
        private AdPlanerLogger $logger
    ) {
        parent::__construct(Application::APP_ID, $request);
    }

    #[NoAdminRequired]
    public function state(): DataResponse {
        return $this->respond(function (): array {
            $uid = $this->teamAccess->currentUserId();

            return [
                'currentUser' => ['uid' => $uid],
                'teams' => array_map(
                    static fn($team): array => $team->toApiArray(),
                    $this->teamAccess->teamsForCurrentUser()
                ),
                'defaultMonth' => date('Y-m'),
                'defaultYear' => (int)date('Y'),
                'notice' => 'Prototyp: Assistenznehmer werden aus ad-ASN-<Kuerzel> gelesen; EB-Rechte aus zusaetzlicher ad-EB-*-Mitgliedschaft.',
            ];
        }, 'state');
    }

    #[NoAdminRequired]
    public function monthPlan(string $teamCode, string $month): DataResponse {
        return $this->respond(function () use ($teamCode, $month): array {
            $team = $this->teamAccess->assertTeamAccess($teamCode);

            return $this->scheduleService->monthPlan($team, $month, $this->teamAccess->currentUserId());
        }, 'month_plan', ['team_code' => $teamCode, 'month' => $month]);
    }

    #[NoAdminRequired]
    public function saveTeamSettings(
        string $teamCode,
        string $displayName = '',
        string $meetingDay = '',
        string $shiftsJson = '',
        string $earlyStart = '08:00',
        string $lateStart = '14:00',
        string $nightStart = '20:00',
        bool $enabledEarly = true,
        bool $enabledLate = true,
        bool $enabledNight = true
    ): DataResponse {
        return $this->respond(function () use (
            $teamCode,
            $displayName,
            $meetingDay,
            $shiftsJson,
            $earlyStart,
            $lateStart,
            $nightStart,
            $enabledEarly,
            $enabledLate,
            $enabledNight
        ): array {
            $team = $this->teamAccess->assertCanCoordinate($teamCode);
            $config = [
                'meetingDay' => $meetingDay,
            ];
            $shifts = $this->decodeShiftsJson($shiftsJson);
            if ($shifts !== null) {
                $config['shifts'] = $shifts;
            } else {
                $config['shiftStarts'] = [
                    'early' => $earlyStart,
                    'late' => $lateStart,
                    'night' => $nightStart,
                ];
                $config['enabledSegments'] = [
                    'early' => $enabledEarly,
                    'late' => $enabledLate,
                    'night' => $enabledNight,
                ];
            }

            $settings = $this->teamSettings->saveFromApi($team->code, $displayName, $config);

            return ['ok' => true, 'settings' => $settings];
        }, 'save_team_settings', ['team_code' => $teamCode]);
    }

    #[NoAdminRequired]
    public function saveDayNote(string $teamCode, string $month, string $workDate, string $note = ''): DataResponse {
        return $this->respond(function () use ($teamCode, $workDate, $note): array {
            $team = $this->teamAccess->assertCanCoordinate($teamCode);
            $this->scheduleService->saveDayNote($team, $workDate, $note, $this->teamAccess->currentUserId());

            return ['ok' => true];
        }, 'save_day_note', ['team_code' => $teamCode, 'month' => $month, 'work_date' => $workDate]);
    }

    #[NoAdminRequired]
    public function addShiftCandidate(string $teamCode, string $month, int $slotId, string $targetUid = ''): DataResponse {
        return $this->respond(function () use ($teamCode, $month, $slotId, $targetUid): array {
            $team = $this->teamAccess->assertTeamAccess($teamCode);
            $this->scheduleService->addCandidate($team, $month, $slotId, $targetUid, $this->teamAccess->currentUserId());

            return ['ok' => true];
        }, 'add_shift_candidate', ['team_code' => $teamCode, 'month' => $month, 'slot_id' => $slotId]);
    }

    #[NoAdminRequired]
    public function removeShiftCandidate(string $teamCode, string $month, int $slotId, string $targetUid = ''): DataResponse {
        return $this->respond(function () use ($teamCode, $month, $slotId, $targetUid): array {
            $team = $this->teamAccess->assertTeamAccess($teamCode);
            $this->scheduleService->removeCandidate($team, $month, $slotId, $targetUid, $this->teamAccess->currentUserId());

            return ['ok' => true];
        }, 'remove_shift_candidate', ['team_code' => $teamCode, 'month' => $month, 'slot_id' => $slotId]);
    }

    #[NoAdminRequired]
    public function yearVacation(string $teamCode, int $year): DataResponse {
        return $this->respond(function () use ($teamCode, $year): array {
            $team = $this->teamAccess->assertTeamAccess($teamCode);

            return $this->vacationService->yearPlan($team, $year, $this->teamAccess->currentUserId());
        }, 'year_vacation', ['team_code' => $teamCode, 'year' => $year]);
    }

    #[NoAdminRequired]
    public function createVacationRequest(string $dateFrom, string $dateTo, string $note = ''): DataResponse {
        return $this->respond(function () use ($dateFrom, $dateTo, $note): array {
            $id = $this->vacationService->createVacationRequest($this->teamAccess->currentUserId(), $dateFrom, $dateTo, $note);

            return ['ok' => true, 'id' => $id];
        }, 'create_vacation_request');
    }

    #[NoAdminRequired]
    public function deleteVacationRequest(int $requestId): DataResponse {
        return $this->respond(function () use ($requestId): array {
            $this->vacationService->deleteOwnRequest($this->teamAccess->currentUserId(), $requestId);

            return ['ok' => true];
        }, 'delete_vacation_request', ['request_id' => $requestId]);
    }

    #[NoAdminRequired]
    public function setVacationStatus(string $teamCode, int $year, string $assistantUid, string $date, string $status): DataResponse {
        return $this->respond(function () use ($teamCode, $assistantUid, $date, $status): array {
            $team = $this->teamAccess->assertCanCoordinate($teamCode);
            $this->vacationService->setStatusForDate($team, $assistantUid, $date, $status, $this->teamAccess->currentUserId());

            return ['ok' => true];
        }, 'set_vacation_status', ['team_code' => $teamCode, 'year' => $year, 'assistant_uid' => $assistantUid, 'date' => $date]);
    }

    private function respond(callable $callback, string $action, array $context = []): DataResponse {
        try {
            return new DataResponse($callback());
        } catch (\InvalidArgumentException $e) {
            return new DataResponse(['ok' => false, 'message' => $e->getMessage()], Http::STATUS_BAD_REQUEST);
        } catch (\DomainException $e) {
            return new DataResponse(['ok' => false, 'message' => $e->getMessage()], Http::STATUS_FORBIDDEN);
        } catch (\Throwable $e) {
            $this->logger->error($action, $e, $context);

            return new DataResponse([
                'ok' => false,
                'message' => 'Die Aktion konnte nicht ausgefuehrt werden. Details stehen im Nextcloud-Log.',
            ], Http::STATUS_INTERNAL_SERVER_ERROR);
        }
    }

    private function decodeShiftsJson(string $shiftsJson): ?array {
        $shiftsJson = trim($shiftsJson);
        if ($shiftsJson === '') {
            return null;
        }

        $decoded = json_decode($shiftsJson, true);
        if (!is_array($decoded)) {
            throw new \InvalidArgumentException('Schichten konnten nicht gelesen werden.');
        }

        return $decoded;
    }
}
