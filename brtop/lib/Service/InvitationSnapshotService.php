<?php

declare(strict_types=1);

namespace OCA\BrTop\Service;

use DateTimeImmutable;
use OCP\DB\QueryBuilder\IQueryBuilder;
use OCP\IDBConnection;
use OCP\IGroupManager;

class InvitationSnapshotService {
    public function __construct(
        private IDBConnection $db,
        private IGroupManager $groupManager,
        private BrtopSettingsService $settings
    ) {
    }

    public function getOrCreateForMeeting(int $meetingId): array {
        $recipients = $this->recipientsForMeeting($meetingId);
        if (count($recipients) > 0) {
            return $recipients;
        }

        return $this->createSnapshot($meetingId);
    }

    private function recipientsForMeeting(int $meetingId): array {
        $qb = $this->db->getQueryBuilder();
        $qb->select('*')
            ->from('brtop_invitation_recipients')
            ->where($qb->expr()->eq('meeting_id', $qb->createNamedParameter($meetingId, IQueryBuilder::PARAM_INT)))
            ->orderBy('snapshot_position', 'ASC');

        return $qb->executeQuery()->fetchAll();
    }

    private function createSnapshot(int $meetingId): array {
        $groupName = $this->settings->memberGroupName();
        $group = $this->groupManager->get($groupName);
        if ($group === null) {
            throw new \RuntimeException('Nextcloud-Gruppe ' . $groupName . ' nicht gefunden.');
        }

        $users = $group->getUsers();
        usort($users, static function ($a, $b): int {
            return strcasecmp($a->getDisplayName(), $b->getDisplayName());
        });

        if (count($users) === 0) {
            throw new \RuntimeException('Nextcloud-Gruppe ' . $groupName . ' enthält keine Mitglieder.');
        }

        $recipients = [];
        foreach ($users as $index => $user) {
            $recipient = [
                'meeting_id' => $meetingId,
                'user_uid' => $user->getUID(),
                'display_name' => $user->getDisplayName(),
                'email' => $user->getEMailAddress() ?? '',
                'group_name' => $groupName,
                'snapshot_position' => $index + 1,
            ];

            $this->insertRecipient($recipient);
            $recipients[] = $recipient;
        }

        return $recipients;
    }

    private function insertRecipient(array $recipient): void {
        $qb = $this->db->getQueryBuilder();
        $qb->insert('brtop_invitation_recipients')
            ->values([
                'meeting_id' => $qb->createNamedParameter((int)$recipient['meeting_id'], IQueryBuilder::PARAM_INT),
                'user_uid' => $qb->createNamedParameter((string)$recipient['user_uid']),
                'display_name' => $qb->createNamedParameter((string)$recipient['display_name']),
                'email' => $qb->createNamedParameter((string)$recipient['email']),
                'group_name' => $qb->createNamedParameter((string)$recipient['group_name']),
                'snapshot_position' => $qb->createNamedParameter((int)$recipient['snapshot_position'], IQueryBuilder::PARAM_INT),
                'created_at' => $qb->createNamedParameter(new DateTimeImmutable(), IQueryBuilder::PARAM_DATE),
            ]);
        $qb->executeStatement();
    }
}
