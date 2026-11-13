<?php

declare(strict_types=1);

require __DIR__ . '/../autoload.php';

use MozeSMS\Client;
use MozeSMS\Exception\InsufficientBalanceException;
use MozeSMS\Exception\ValidationException;

$moze = new Client(getenv('MOZESMS_KEY') ?: 'mk_...', getenv('MOZESMS_SECRET') ?: 'sk_...');

try {
    echo 'Saldo: ' . $moze->account->balance()['balance'] . " MZN\n";

    $sms = $moze->sms->send('846000000', 'Olá da MozeSMS!', 'MozeSMS');
    echo "Enviado: {$sms['id']} ({$sms['status']})\n";
    echo 'Estado: ' . $moze->sms->status($sms['id'])['status'] . "\n";

    // OTP
    $session = $moze->otp->send('846000000', ['sender_id' => 'MozeSMS']);
    $ok = $moze->otp->check($session['session_id'], '123456');
    echo $ok ? "Código certo\n" : "Código errado\n";
} catch (InsufficientBalanceException $e) {
    echo 'Sem saldo: ' . $e->getMessage() . "\n";
} catch (ValidationException $e) {
    echo "Pedido inválido [{$e->getErrorCode()}]: {$e->getMessage()}\n";
}
