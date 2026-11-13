<?php

declare(strict_types=1);

namespace MozeSMS\Exception;

/** A API respondeu com erro (HTTP >= 400 ou success=false). */
class ApiException extends MozeSMSException
{
    private int $httpStatus;
    private ?string $errorCode;
    /** @var array<string,mixed> */
    private array $body;

    /** @param array<string,mixed> $body */
    public function __construct(string $message, int $httpStatus, ?string $errorCode = null, array $body = [])
    {
        parent::__construct($message, $httpStatus);
        $this->httpStatus = $httpStatus;
        $this->errorCode = $errorCode;
        $this->body = $body;
    }

    public function getHttpStatus(): int
    {
        return $this->httpStatus;
    }

    /** Código da API, p.ex. INSUFFICIENT_BALANCE, WRONG_CODE, RATE_LIMITED. */
    public function getErrorCode(): ?string
    {
        return $this->errorCode;
    }

    /** @return array<string,mixed> Corpo completo da resposta. */
    public function getBody(): array
    {
        return $this->body;
    }
}
