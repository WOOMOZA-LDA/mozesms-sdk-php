<?php

declare(strict_types=1);

namespace MozeSMS;

/**
 * Números em formato internacional (E.164, só dígitos, sem "+").
 *
 *   "+27 82 123 4567"  → 27821234567     (África do Sul)
 *   "0027821234567"    → 27821234567
 *   "27821234567"      → 27821234567     (10 a 15 dígitos: já traz o código do país)
 *   "846000000"        → 258846000000    (atalho: 9 dígitos 8x/9x = Moçambique)
 *
 * Números de Moçambique (258) são validados a fundo (258 + 8x/9x + 7 dígitos). Para os restantes países
 * valida-se o formato E.164 (8 a 15 dígitos, sem zero inicial); não existe uma lista de operadores.
 */
final class Phone
{
    /** Devolve os dígitos E.164 ou null se o número não for válido. */
    public static function normalize(string $phone): ?string
    {
        $p = trim($phone);
        if ($p === '' || preg_match('/[^0-9+\s().\-]/', $p)) {
            return null; // letras e outros símbolos
        }
        $digits = (string) preg_replace('/\D/', '', $p);
        if ($digits === '') {
            return null;
        }
        if ($p[0] === '+') {
            // "+" tem de vir só à frente
            if (strpos($p, '+', 1) !== false) {
                return null;
            }
        } elseif (strncmp($digits, '00', 2) === 0 && strncmp($p, '00', 2) === 0) {
            $digits = substr($digits, 2);
        } elseif (strlen($digits) === 9 && preg_match('/^[89]/', $digits)) {
            $digits = '258' . $digits; // atalho nacional de Moçambique
        } elseif (strlen($digits) < 10) {
            return null; // curto demais para já trazer o código do país
        }
        if (!preg_match('/^[1-9][0-9]{7,14}$/', $digits)) {
            return null;
        }
        if (strncmp($digits, '258', 3) === 0 && !preg_match('/^258[89][0-9]{8}$/', $digits)) {
            return null;
        }
        return $digits;
    }

    /** @param string[]|null $allowedCountries códigos de país permitidos (ex.: ['258','27']); null = qualquer */
    public static function isValid(string $phone, ?array $allowedCountries = null): bool
    {
        $n = self::normalize($phone);
        if ($n === null) {
            return false;
        }
        if ($allowedCountries === null) {
            return true;
        }
        foreach ($allowedCountries as $code) {
            if (strncmp($n, (string) $code, strlen((string) $code)) === 0) {
                return true;
            }
        }
        return false;
    }

    public static function isMozambican(string $phone): bool
    {
        $n = self::normalize($phone);
        return $n !== null && strncmp($n, '258', 3) === 0;
    }
}
