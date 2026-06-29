<?php

declare(strict_types=1);

namespace OCA\BrTop\Service;

use OCA\BrTop\AppInfo\Application;
use OCP\IUserSession;
use Psr\Log\LoggerInterface;
use Throwable;

class BrtopLogger {
    public function __construct(
        private LoggerInterface $logger,
        private IUserSession $userSession
    ) {
    }

    public function error(string $action, Throwable $exception, array $context = []): void {
        $safeContext = $this->normalizeContext($context);
        $safeContext['app'] = Application::APP_ID;
        $safeContext['action'] = $action;
        $safeContext['exception_class'] = get_class($exception);
        $safeContext['exception_message'] = $exception->getMessage();

        $user = $this->userSession->getUser();
        if ($user !== null) {
            $safeContext['user_id'] = $user->getUID();
        }

        $this->logger->error('BRTop error during ' . $action, $safeContext);
    }

    private function normalizeContext(array $context): array {
        $safeContext = [];

        foreach ($context as $key => $value) {
            if (is_scalar($value) || $value === null) {
                $safeContext[(string)$key] = $value;
            }
        }

        return $safeContext;
    }
}
