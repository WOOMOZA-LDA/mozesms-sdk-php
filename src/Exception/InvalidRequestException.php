<?php

declare(strict_types=1);

namespace MozeSMS\Exception;

/** Dados rejeitados pelo SDK antes de qualquer pedido (nada foi enviado nem cobrado). */
class InvalidRequestException extends MozeSMSException
{
    /** @var string[] */
    private array $problems;

    /** @param string[] $problems */
    public function __construct(string $message, array $problems = [])
    {
        parent::__construct($message);
        $this->problems = $problems;
    }

    /** @return string[] p.ex. os números inválidos */
    public function getProblems(): array
    {
        return $this->problems;
    }
}
