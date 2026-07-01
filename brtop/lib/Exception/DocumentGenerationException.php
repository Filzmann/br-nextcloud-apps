<?php

declare(strict_types=1);

namespace OCA\BrTop\Exception;

class DocumentGenerationException extends \RuntimeException {
    public function __construct(
        private array $responseData,
        \Throwable $previous
    ) {
        parent::__construct($previous->getMessage(), 0, $previous);
    }

    public function responseData(): array {
        return $this->responseData;
    }
}
