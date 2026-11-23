<?php

declare(strict_types=1);

use OCA\FlzDataProtection\BackgroundJob\RetentionExecutionJob;
use OCA\FlzDataProtection\PublicApi\V2\RegisterRetentionExecutionProvidersEvent;
use OCA\FlzDataProtection\PublicApi\V2\RetentionExecutionBatch;
use OCA\FlzDataProtection\PublicApi\V2\RetentionExecutionRequest;
use OCA\FlzDataProtection\Service\RetentionExecutionActivationService;
use OCA\FlzDataProtection\Service\RetentionExecutionCoordinator;
use OCA\FlzRoom\Model\Booking;
use OCA\FlzRoom\Privacy\RoomRetentionExecutionProvider;
use OCA\FlzRoom\Repository\BookingRepository;
use OCP\DB\QueryBuilder\IQueryBuilder;
use OCP\EventDispatcher\IEventDispatcher;
use OCP\IDBConnection;
use OCP\IUserManager;
use OCP\IUserSession;

define('OC_CONSOLE', true);
require __DIR__ . '/lib/base.php';

$phase = $argv[1] ?? '';
$statePath = $argv[2] ?? '';
$allowed = [
    'inspect',
    'seed-delete',
    'execute-delete',
    'verify-idempotent',
    'seed-concurrent',
    'execute-concurrent',
    'verify-concurrent',
    'seed-rollback',
    'execute-rollback',
    'verify-rollback',
    'seed-restore-quarantine',
    'disturb-restore',
    'verify-restore-quarantine',
];
if (!in_array($phase, $allowed, true) || $statePath === '') {
    throw new RuntimeException('Unknown V2 runtime probe phase or missing state path.');
}

/** @return array<string,mixed> */
function readState(string $path): array {
    if (!is_file($path)) return [];
    $decoded = json_decode((string)file_get_contents($path), true, flags: JSON_THROW_ON_ERROR);
    if (!is_array($decoded)) throw new RuntimeException('Invalid runtime state.');
    return $decoded;
}

/** @param array<string,mixed> $state */
function writeState(string $path, array $state): void {
    $encoded = json_encode($state, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
    if (file_put_contents($path, $encoded . "\n") === false) throw new RuntimeException('Runtime state could not be written.');
}

/** @param array<string,mixed> $payload */
function result(array $payload): never {
    echo json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR), "\n";
    exit(0);
}

function expect(bool $condition, string $message): void {
    if (!$condition) throw new RuntimeException($message);
}

function setAdminSession(): void {
    $users = OCP\Server::get(IUserManager::class);
    $session = OCP\Server::get(IUserSession::class);
    $admin = $users->get('v2-admin');
    if ($admin === null) throw new RuntimeException('Synthetic runtime admin is missing.');
    $session->setUser($admin);
}

/** @return array<string, OCA\FlzDataProtection\PublicApi\V2\RetentionExecutionProvider> */
function providers(): array {
    $event = new RegisterRetentionExecutionProvidersEvent();
    OCP\Server::get(IEventDispatcher::class)->dispatchTyped($event);
    return $event->providers();
}

function insertRow(IDBConnection $db, string $table, array $values, array $types): int {
    $qb = $db->getQueryBuilder();
    $qb->insert($table);
    foreach ($values as $column => $value) {
        $qb->setValue($column, $qb->createNamedParameter($value, $types[$column]));
    }
    $qb->executeStatement();
    return (int)$qb->getLastInsertId();
}

function deleteById(IDBConnection $db, string $table, int $id): void {
    $qb = $db->getQueryBuilder();
    $qb->delete($table)
        ->where($qb->expr()->eq('id', $qb->createNamedParameter($id, IQueryBuilder::PARAM_INT)))
        ->executeStatement();
}

function rowExists(IDBConnection $db, string $table, int $id): bool {
    $qb = $db->getQueryBuilder();
    return $qb->select('id')->from($table)
        ->where($qb->expr()->eq('id', $qb->createNamedParameter($id, IQueryBuilder::PARAM_INT)))
        ->executeQuery()->fetchOne() !== false;
}

function seedRoom(IDBConnection $db, string $suffix): int {
    $now = new DateTimeImmutable('now', new DateTimeZone('UTC'));
    return insertRow($db, 'flz_room_rooms', [
        'name' => 'V2 synthetic room ' . $suffix,
        'description' => '',
        'sort_order' => 0,
        'created_at' => $now,
        'updated_at' => $now,
    ], [
        'name' => IQueryBuilder::PARAM_STR,
        'description' => IQueryBuilder::PARAM_STR,
        'sort_order' => IQueryBuilder::PARAM_INT,
        'created_at' => IQueryBuilder::PARAM_DATETIME_IMMUTABLE,
        'updated_at' => IQueryBuilder::PARAM_DATETIME_IMMUTABLE,
    ]);
}

function seedBooking(IDBConnection $db, string $suffix): int {
    $roomId = seedRoom($db, $suffix);
    $endsAt = new DateTimeImmutable('2024-01-02T10:00:00+00:00');
    $booking = Booking::get([
        'roomId' => $roomId,
        'userUid' => 'v2-synthetic',
        'purpose' => 'Synthetic runtime acceptance',
        'title' => 'Synthetic record',
        'startsAt' => $endsAt->sub(new DateInterval('PT1H')),
        'endsAt' => $endsAt,
    ]);
    if (!$booking instanceof Booking) throw new RuntimeException('Synthetic booking fixture is invalid.');
    return OCP\Server::get(BookingRepository::class)->save($booking);
}

function seedAdminHistory(IDBConnection $db, string $table, string $suffix): int {
    $starts = new DateTimeImmutable('2024-01-01T09:00:00+00:00');
    $ends = new DateTimeImmutable('2024-01-01T10:00:00+00:00');
    return insertRow($db, $table, [
        'target_uid' => 'v2-target-' . $suffix,
        'granted_by' => 'v2-admin',
        'starts_at' => $starts,
        'ends_at' => $ends,
        'revoked_at' => null,
        'revoked_by' => null,
        'created_at' => $starts,
    ], [
        'target_uid' => IQueryBuilder::PARAM_STR,
        'granted_by' => IQueryBuilder::PARAM_STR,
        'starts_at' => IQueryBuilder::PARAM_DATETIME_IMMUTABLE,
        'ends_at' => IQueryBuilder::PARAM_DATETIME_IMMUTABLE,
        'revoked_at' => IQueryBuilder::PARAM_NULL,
        'revoked_by' => IQueryBuilder::PARAM_NULL,
        'created_at' => IQueryBuilder::PARAM_DATETIME_IMMUTABLE,
    ]);
}

function seedHold(IDBConnection $db, int $bookingId): int {
    $now = new DateTimeImmutable('now', new DateTimeZone('UTC'));
    return insertRow($db, 'flz_room_retention_holds', [
        'policy_id' => RoomRetentionExecutionProvider::BOOKING_POLICY_ID,
        'record_ref' => 'booking:' . $bookingId,
        'reason_code' => 'runtime_acceptance',
        'evidence_ref' => 'synthetic-v2-runtime',
        'placed_by' => 'v2-admin',
        'placed_at' => $now,
        'review_due_at' => $now->add(new DateInterval('P90D')),
        'released_by' => null,
        'released_at' => null,
    ], [
        'policy_id' => IQueryBuilder::PARAM_STR,
        'record_ref' => IQueryBuilder::PARAM_STR,
        'reason_code' => IQueryBuilder::PARAM_STR,
        'evidence_ref' => IQueryBuilder::PARAM_STR,
        'placed_by' => IQueryBuilder::PARAM_STR,
        'placed_at' => IQueryBuilder::PARAM_DATETIME_IMMUTABLE,
        'review_due_at' => IQueryBuilder::PARAM_DATETIME_IMMUTABLE,
        'released_by' => IQueryBuilder::PARAM_NULL,
        'released_at' => IQueryBuilder::PARAM_NULL,
    ]);
}

function roomProvider(): RoomRetentionExecutionProvider {
    $provider = providers()['flzroom'] ?? null;
    if (!$provider instanceof RoomRetentionExecutionProvider) throw new RuntimeException('Room V2 provider is unavailable.');
    return $provider;
}

/** @return array{deleted:int,held:int,stale:int,failed:int} */
function executeBooking(int $bookingId): array {
    $provider = roomProvider();
    $policy = null;
    foreach ($provider->policies() as $candidate) {
        if ($candidate->policyId() === RoomRetentionExecutionProvider::BOOKING_POLICY_ID) $policy = $candidate;
    }
    if ($policy === null) throw new RuntimeException('Room booking DELETE policy is unavailable.');
    $request = new RetentionExecutionRequest($policy->policyId(), $policy->version(), (new DateTimeImmutable('now'))->format(DATE_ATOM), 100);
    $page = $provider->plan($request);
    $reference = 'booking:' . $bookingId;
    $candidates = array_values(array_filter($page->candidates(), static fn($item): bool => $item->reference() === $reference));
    $execution = $provider->execute(new RetentionExecutionBatch($request, $candidates));
    return [
        'deleted' => count($execution->deletedReferences()),
        'held' => count($execution->heldReferences()),
        'stale' => count($execution->staleReferences()),
        'failed' => count($execution->failedReferences()),
    ];
}

$db = OCP\Server::get(IDBConnection::class);
$state = readState($statePath);

if ($phase === 'inspect') {
    foreach (['flz_dp_retention_exec', 'flz_dp_retention_holds', 'flz_room_retention_holds', 'flz_room_bookings'] as $table) {
        $qb = $db->getQueryBuilder();
        $qb->select('id')->from($table)->setMaxResults(1)->executeQuery()->fetchOne();
    }
    $registered = providers();
    ksort($registered);
    expect(array_keys($registered) === ['flz_data_protection', 'flzroom'], 'Unexpected V2 provider registry.');
    $descriptors = [];
    foreach ($registered as $appId => $provider) {
        $descriptor = $provider->descriptor();
        expect($descriptor->contractVersion() === '2.0', 'Unexpected V2 contract version for ' . $appId);
        $descriptors[$appId] = [
            'contractVersion' => $descriptor->contractVersion(),
            'policies' => array_map(static fn($policy): array => [
                'id' => $policy->policyId(),
                'version' => $policy->version(),
                'action' => $policy->action(),
            ], $provider->policies()),
        ];
    }
    $activation = OCP\Server::get(RetentionExecutionActivationService::class)->status();
    expect($activation['executionAvailable'] === false, 'Fresh install unexpectedly permits DELETE.');
    expect(in_array('activation_missing', $activation['blockers'], true), 'Fresh install has no activation_missing blocker.');
    $blocked = OCP\Server::get(RetentionExecutionCoordinator::class)->run();
    expect(($blocked['status'] ?? null) === 'blocked', 'Fresh install coordinator did not fail closed.');
    result(['phase' => $phase, 'providers' => $descriptors, 'activation' => $activation['blockers']]);
}

if ($phase === 'seed-delete') {
    setAdminSession();
    $activation = OCP\Server::get(RetentionExecutionActivationService::class);
    try {
        $activation->save([
            'enabled' => true,
            'backupRegularDays' => 365,
            'backupBufferDays' => 5,
            'backupVerifiedAt' => '+1 day',
            'restoreVerifiedAt' => '-1 hour',
            'verificationDueAt' => '+300 days',
            'expectedRevision' => 0,
        ]);
        throw new RuntimeException('Future verification timestamp was accepted.');
    } catch (InvalidArgumentException) {
    }
    expect($activation->status()['executionAvailable'] === false, 'Rejected activation changed persisted state.');
    $now = new DateTimeImmutable('now', new DateTimeZone('UTC'));
    $valid = $activation->save([
        'enabled' => true,
        'backupRegularDays' => 365,
        'backupBufferDays' => 5,
        'backupVerifiedAt' => $now->sub(new DateInterval('P1D'))->format(DATE_ATOM),
        'restoreVerifiedAt' => $now->sub(new DateInterval('PT1H'))->format(DATE_ATOM),
        'verificationDueAt' => $now->add(new DateInterval('P300D'))->format(DATE_ATOM),
        'expectedRevision' => 0,
    ]);
    expect($valid['executionAvailable'] === true, 'Valid technical activation did not permit DELETE.');

    $qb = $db->getQueryBuilder();
    $qb->update('flz_dp_retention_exec')
        ->set('verification_due_at', $qb->createNamedParameter($now->sub(new DateInterval('P1D')), IQueryBuilder::PARAM_DATETIME_IMMUTABLE))
        ->where($qb->expr()->eq('revision', $qb->createNamedParameter(1, IQueryBuilder::PARAM_INT)))
        ->executeStatement();
    $stale = $activation->status();
    expect($stale['executionAvailable'] === false && $stale['blockers'] === ['technical_verification_due'], 'Stale activation did not fail closed.');
    expect((OCP\Server::get(RetentionExecutionCoordinator::class)->run()['status'] ?? null) === 'blocked', 'Stale activation reached a provider.');
    $qb = $db->getQueryBuilder();
    $qb->update('flz_dp_retention_exec')
        ->set('verification_due_at', $qb->createNamedParameter($now->add(new DateInterval('P300D')), IQueryBuilder::PARAM_DATETIME_IMMUTABLE))
        ->where($qb->expr()->eq('revision', $qb->createNamedParameter(1, IQueryBuilder::PARAM_INT)))
        ->executeStatement();
    expect($activation->status()['executionAvailable'] === true, 'Valid activation was not restored after negative probe.');

    $state['deleteBookingId'] = seedBooking($db, 'delete');
    $state['heldBookingId'] = seedBooking($db, 'held');
    $state['holdId'] = seedHold($db, (int)$state['heldBookingId']);
    $state['roomAdminId'] = seedAdminHistory($db, 'flz_room_admin_access', 'room');
    $state['dataProtectionAdminId'] = seedAdminHistory($db, 'flz_dp_admin_access', 'data-protection');
    writeState($statePath, $state);
    result(['phase' => $phase, 'activation' => 'valid', 'fixtures' => 4]);
}

if ($phase === 'execute-delete') {
    $report = OCP\Server::get(RetentionExecutionCoordinator::class)->run();
    expect(($report['status'] ?? null) === 'complete', 'Coordinated DELETE run did not complete.');
    expect(!rowExists($db, 'flz_room_bookings', (int)$state['deleteBookingId']), 'Eligible room booking was not deleted.');
    expect(rowExists($db, 'flz_room_bookings', (int)$state['heldBookingId']), 'Held room booking was deleted.');
    expect(!rowExists($db, 'flz_room_admin_access', (int)$state['roomAdminId']), 'Eligible room admin history was not deleted.');
    expect(!rowExists($db, 'flz_dp_admin_access', (int)$state['dataProtectionAdminId']), 'Eligible privacy admin history was not deleted.');
    expect(($report['providers']['flzroom']['policies']['room_booking_delete']['deleted'] ?? null) === 1, 'Room deletion report is incomplete.');
    expect(($report['providers']['flzroom']['policies']['room_booking_delete']['held'] ?? null) === 1, 'Hold conflict is not reported.');
    result(['phase' => $phase, 'report' => $report]);
}

if ($phase === 'verify-idempotent') {
    $job = OCP\Server::get(RetentionExecutionJob::class);
    $run = new ReflectionMethod($job, 'run');
    $run->setAccessible(true);
    $run->invoke($job, null);
    $report = OCP\Server::get(RetentionExecutionCoordinator::class)->run();
    expect(($report['status'] ?? null) === 'complete', 'Restarted idempotency run did not complete.');
    expect(($report['providers']['flzroom']['policies']['room_booking_delete']['deleted'] ?? null) === 0, 'Restarted job repeated a committed deletion.');
    expect(rowExists($db, 'flz_room_bookings', (int)$state['heldBookingId']), 'Restarted job ignored an active hold.');
    result(['phase' => $phase, 'job' => 'restarted', 'repeatedDeletes' => 0]);
}

if ($phase === 'seed-concurrent') {
    $state['concurrentBookingId'] = seedBooking($db, 'concurrent');
    writeState($statePath, $state);
    result(['phase' => $phase]);
}

if ($phase === 'execute-concurrent') {
    result(['phase' => $phase, 'execution' => executeBooking((int)$state['concurrentBookingId'])]);
}

if ($phase === 'verify-concurrent') {
    $left = json_decode((string)file_get_contents($argv[3] ?? ''), true, flags: JSON_THROW_ON_ERROR);
    $right = json_decode((string)file_get_contents($argv[4] ?? ''), true, flags: JSON_THROW_ON_ERROR);
    $deleted = (int)($left['execution']['deleted'] ?? -1) + (int)($right['execution']['deleted'] ?? -1);
    $failed = (int)($left['execution']['failed'] ?? -1) + (int)($right['execution']['failed'] ?? -1);
    expect($deleted === 1 && $failed === 0, 'Concurrent execution did not produce one atomic deletion.');
    expect(!rowExists($db, 'flz_room_bookings', (int)$state['concurrentBookingId']), 'Concurrent target remains after successful execution.');
    result(['phase' => $phase, 'deleted' => $deleted, 'failed' => $failed]);
}

if ($phase === 'seed-rollback') {
    $state['rollbackBookingId'] = seedBooking($db, 'rollback');
    writeState($statePath, $state);
    result(['phase' => $phase]);
}

if ($phase === 'execute-rollback') {
    $execution = executeBooking((int)$state['rollbackBookingId']);
    expect($execution['failed'] === 1 && $execution['deleted'] === 0, 'Injected candidate failure did not fail closed.');
    expect(rowExists($db, 'flz_room_bookings', (int)$state['rollbackBookingId']), 'Failed candidate deletion was not rolled back.');
    result(['phase' => $phase, 'execution' => $execution]);
}

if ($phase === 'verify-rollback') {
    $execution = executeBooking((int)$state['rollbackBookingId']);
    expect($execution['deleted'] === 1 && $execution['failed'] === 0, 'Rollback target did not recover after fault removal.');
    expect(!rowExists($db, 'flz_room_bookings', (int)$state['rollbackBookingId']), 'Recovered rollback target remains.');
    result(['phase' => $phase, 'execution' => $execution]);
}

if ($phase === 'seed-restore-quarantine') {
    $state['restoreBookingId'] = seedBooking($db, 'restore');
    $latest = OCP\Server::get(RetentionExecutionActivationService::class)->status()['configuration'];
    $now = new DateTimeImmutable('now', new DateTimeZone('UTC'));
    $revision = (int)$latest['revision'] + 1;
    insertRow($db, 'flz_dp_retention_exec', [
        'revision' => $revision,
        'enabled' => true,
        'approved_policy_ids' => json_encode($latest['approvedPolicyIds'], JSON_THROW_ON_ERROR),
        'backup_regular_days' => 365,
        'backup_buffer_days' => 5,
        'backup_verified_at' => $now->sub(new DateInterval('P370D')),
        'restore_verified_at' => $now->sub(new DateInterval('P370D')),
        'verification_due_at' => $now->sub(new DateInterval('P1D')),
        'changed_by' => 'v2-admin',
        'created_at' => $now->sub(new DateInterval('P370D')),
    ], [
        'revision' => IQueryBuilder::PARAM_INT,
        'enabled' => IQueryBuilder::PARAM_BOOL,
        'approved_policy_ids' => IQueryBuilder::PARAM_STR,
        'backup_regular_days' => IQueryBuilder::PARAM_INT,
        'backup_buffer_days' => IQueryBuilder::PARAM_INT,
        'backup_verified_at' => IQueryBuilder::PARAM_DATETIME_IMMUTABLE,
        'restore_verified_at' => IQueryBuilder::PARAM_DATETIME_IMMUTABLE,
        'verification_due_at' => IQueryBuilder::PARAM_DATETIME_IMMUTABLE,
        'changed_by' => IQueryBuilder::PARAM_STR,
        'created_at' => IQueryBuilder::PARAM_DATETIME_IMMUTABLE,
    ]);
    $state['restoreRevision'] = $revision;
    writeState($statePath, $state);
    $status = OCP\Server::get(RetentionExecutionActivationService::class)->status();
    expect($status['executionAvailable'] === false && $status['blockers'] === ['technical_verification_due'], 'Stale restore fixture is not quarantined.');
    result(['phase' => $phase, 'blockers' => $status['blockers']]);
}

if ($phase === 'disturb-restore') {
    deleteById($db, 'flz_room_bookings', (int)$state['restoreBookingId']);
    deleteById($db, 'flz_dp_retention_exec', (int)$state['restoreRevision']);
    expect(!rowExists($db, 'flz_room_bookings', (int)$state['restoreBookingId']), 'Restore disturbance did not change the database.');
    result(['phase' => $phase]);
}

if ($phase === 'verify-restore-quarantine') {
    expect(rowExists($db, 'flz_room_bookings', (int)$state['restoreBookingId']), 'Database restore did not restore the synthetic record.');
    $status = OCP\Server::get(RetentionExecutionActivationService::class)->status();
    expect($status['executionAvailable'] === false, 'Restored stale technical state escaped quarantine.');
    expect($status['blockers'] === ['technical_verification_due'], 'Restored state has the wrong quarantine diagnostic.');
    $report = OCP\Server::get(RetentionExecutionCoordinator::class)->run();
    expect(($report['status'] ?? null) === 'blocked', 'Restored stale state reached DELETE execution.');
    expect(rowExists($db, 'flz_room_bookings', (int)$state['restoreBookingId']), 'Restore quarantine changed the restored record.');
    result(['phase' => $phase, 'blockers' => $status['blockers'], 'recordPreserved' => true]);
}

throw new RuntimeException('Unhandled V2 runtime probe phase.');
