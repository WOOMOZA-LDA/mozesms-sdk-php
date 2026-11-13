<?php

declare(strict_types=1);

// Verificação por OTP: enviar o código e validar o que o utilizador escreveu.
//   php otp_login.php 846000000        → envia e fica à espera do código no terminal
$moze = require __DIR__ . '/_client.php';

use MozeSMS\Exception\ConflictException;
use MozeSMS\Exception\RateLimitException;
use MozeSMS\Exception\ValidationException;

$telefone = $argv[1] ?? '846000000';

try {
    $sessao = $moze->otp->send($telefone, [
        'sender_id' => 'MozeSMS',
        'digits' => 6,      // 4 a 10
        'expiry' => 300,    // segundos
        'max_attempts' => 3,
        'ref_id' => 'login-' . bin2hex(random_bytes(4)),   // a sua referência
    ]);
    echo "Código enviado. Expira em {$sessao['expires_in']}s (sessão {$sessao['session_id']})\n";

    // Em produção: guarde session_id na sessão do utilizador e valide quando ele submeter o código.
    $codigo = trim((string) readline('Código recebido: '));

    if ($moze->otp->check($sessao['session_id'], $codigo)) {
        echo "Verificado!\n";
    } else {
        echo "Código errado.\n";
    }
} catch (RateLimitException $e) {
    $espera = $e->getRetryAfter();
    echo 'Muitos pedidos' . ($espera ? ", tente daqui a {$espera}s" : '') . ".\n";
} catch (ConflictException $e) {
    // ALREADY_VERIFIED (409) ou EXPIRED (410)
    echo 'Sessão já não é válida: ' . $e->getErrorCode() . "\n";
} catch (ValidationException $e) {
    // MAX_ATTEMPTS, SESSION_NOT_FOUND...
    echo 'Não foi possível verificar: ' . $e->getErrorCode() . "\n";
}
