<?php

declare(strict_types=1);

namespace OCA\AdPlaner\Service;

use OCA\AdPlaner\AppInfo\Application;
use OCP\IUserSession;
use Psr\Log\LoggerInterface;
use Throwable;

class AdPlanerLogger {
    public function __construct(
        private LoggerInterface $logger,
        private IUserSession $userSession
    ) {
    }

    public function error(string $action, Throwable $exception, array $context = []): void {
        $safeContext = [];
        foreach ($context as $key => $value) {
            if (is_scalar($value) || $value === null) {
                $safeContext[(string)$key] = $value;
            }
        }

        $safeContext['app'] = Application::APP_ID;
        $safeContext['action'] = $action;
        $safeContext['exception_class'] = get_class($exception);
        $safeContext['exception_message'] = $exception->getMessage();

        $user = $this->userSession->getUser();
        if ($user !== null) {
            $safeContext['user_id'] = $user->getUID();
        }

        $this->logger->error('AdPlaner error during ' . $action, $safeContext);
    }
}
