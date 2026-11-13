<?php

declare(strict_types=1);

namespace MozeSMS\Exception;

/**
 * Falha de rede/timeout. Use mayHaveReachedServer() antes de repetir um POST de envio:
 * se for true, o pedido pode ter sido processado (e cobrado) — confirme no histórico.
 */
class ConnectionException extends MozeSMSException
{
    private bool $mayHaveReachedServer;
    private ?int $curlErrno;

    public function __construct(string $message, bool $mayHaveReachedServer = true, ?int $curlErrno = null, ?\Throwable $previous = null)
    {
        parent::__construct($message, 0, $previous);
        $this->mayHaveReachedServer = $mayHaveReachedServer;
        $this->curlErrno = $curlErrno;
    }

    /** false só quando a ligação nem chegou a abrir (DNS, recusada): repetir é seguro. */
    public function mayHaveReachedServer(): bool
    {
        return $this->mayHaveReachedServer;
    }

    public function getCurlErrno(): ?int
    {
        return $this->curlErrno;
    }
}
