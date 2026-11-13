<?php

declare(strict_types=1);

namespace MozeSMS;

use MozeSMS\Exception\InvalidRequestException;

/** Validação local: o que falha aqui não chega à API e, por isso, não é cobrado. @internal */
final class Validator
{
    public const MAX_MESSAGE_BYTES = 60000;
    public const MAX_SENDER_LEN = 50;
    public const MAX_BULK_PHONES = 100000;
    public const MAX_BULK_MESSAGES = 2000;
    public const MAX_SCHEDULE_PHONES = 100000;

    private bool $checkPhones;
    /** @var string[]|null */
    private ?array $allowedCountries;

    /** @param string[]|null $allowedCountries códigos de país aceites (ex.: ['258','27']); null = qualquer país */
    public function __construct(bool $checkPhones = true, ?array $allowedCountries = null)
    {
        $this->checkPhones = $checkPhones;
        $this->allowedCountries = $allowedCountries === null ? null : array_map('strval', $allowedCountries);
    }

    /**
     * Devolve o número normalizado (E.164, só dígitos). Com validate_phones=false devolve-o como veio.
     */
    public function phone(string $phone, string $field = 'phone'): string
    {
        if (trim($phone) === '') {
            throw new InvalidRequestException("'$field' é obrigatório");
        }
        if (!$this->checkPhones) {
            return $phone;
        }
        $n = Phone::normalize($phone);
        if ($n === null) {
            throw new InvalidRequestException(
                "Número inválido: '$phone'. Use o formato internacional (+258846000000, +27821234567) ou 9 dígitos de Moçambique (84xxxxxxx).",
                [$phone]
            );
        }
        $this->checkCountry($n, $phone);
        return $n;
    }

    /**
     * @param mixed $phones
     * @return string[] números normalizados, na mesma ordem
     */
    public function phones($phones, int $max, string $field = 'phones'): array
    {
        if (!is_array($phones) || $phones === []) {
            throw new InvalidRequestException("'$field' tem de ser uma lista não vazia");
        }
        if (count($phones) > $max) {
            throw new InvalidRequestException("'$field' admite no máximo $max números (recebeu " . count($phones) . ')');
        }
        $bad = [];
        $out = [];
        foreach ($phones as $p) {
            if (!is_string($p) && !is_int($p)) {
                $bad[] = gettype($p);
                continue;
            }
            $raw = (string) $p;
            if (trim($raw) === '') {
                $bad[] = $raw;
            } elseif (!$this->checkPhones) {
                $out[] = $raw;
            } else {
                $n = Phone::normalize($raw);
                if ($n === null || !$this->countryAllowed($n)) {
                    $bad[] = $raw;
                } else {
                    $out[] = $n;
                }
            }
        }
        if ($bad) {
            // A API cobra os inválidos e não os envia: recusar tudo evita pagar por SMS perdidos.
            $shown = implode(', ', array_slice($bad, 0, 10)) . (count($bad) > 10 ? ', ...' : '');
            throw new InvalidRequestException(count($bad) . " número(s) inválido(s) em '$field': $shown. Filtre com MozeSMS\\Phone::isValid().", $bad);
        }
        return $out;
    }

    private function countryAllowed(string $normalized): bool
    {
        if ($this->allowedCountries === null) {
            return true;
        }
        foreach ($this->allowedCountries as $code) {
            if (strncmp($normalized, $code, strlen($code)) === 0) {
                return true;
            }
        }
        return false;
    }

    private function checkCountry(string $normalized, string $original): void
    {
        if (!$this->countryAllowed($normalized)) {
            throw new InvalidRequestException(
                "Número de um país não permitido: '$original'. Países aceites (allowed_countries): " . implode(', ', $this->allowedCountries ?? []),
                [$original]
            );
        }
    }

    public function message(string $message, string $field = 'message'): void
    {
        if (trim($message) === '') {
            throw new InvalidRequestException("'$field' é obrigatório");
        }
        if (!mb_check_encoding($message, 'UTF-8')) {
            throw new InvalidRequestException("'$field' não é UTF-8 válido");
        }
        if (strlen($message) > self::MAX_MESSAGE_BYTES) {
            throw new InvalidRequestException("'$field' excede " . self::MAX_MESSAGE_BYTES . ' bytes');
        }
    }

    public function sender(?string $sender): void
    {
        if ($sender === null) {
            return;
        }
        if (trim($sender) === '' || mb_strlen($sender) > self::MAX_SENDER_LEN) {
            throw new InvalidRequestException('sender_id tem de ter entre 1 e ' . self::MAX_SENDER_LEN . ' caracteres');
        }
    }

    public function id(string $id, string $field = 'id'): void
    {
        if (trim($id) === '') {
            throw new InvalidRequestException("'$field' é obrigatório");
        }
    }

    public function positiveInt(int $n, string $field = 'id'): void
    {
        if ($n < 1) {
            throw new InvalidRequestException("'$field' tem de ser um inteiro positivo");
        }
    }
}
