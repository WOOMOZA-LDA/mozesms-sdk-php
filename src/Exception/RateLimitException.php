<?php

declare(strict_types=1);

namespace MozeSMS\Exception;

/** 429: demasiados pedidos / tentativas. */
class RateLimitException extends ApiException
{
    private ?int $retryAfter = null;

    public function setRetryAfter(?int $seconds): void
    {
        $this->retryAfter = $seconds;
    }

    /** Segundos a esperar (cabeçalho Retry-After), se a API o indicou. */
    public function getRetryAfter(): ?int
    {
        return $this->retryAfter;
    }
}
