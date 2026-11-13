<?php

declare(strict_types=1);

// Enviar um SMS, um lote e agendar. ATENÇÃO: estes envios gastam saldo.
$moze = require __DIR__ . '/_client.php';

use MozeSMS\Exception\InsufficientBalanceException;
use MozeSMS\Exception\InvalidRequestException;
use MozeSMS\Exception\ServerException;

$numero = $argv[1] ?? '846000000';

try {
    // 1) Um SMS
    $sms = $moze->sms->send($numero, 'Olá da MozeSMS!', 'MozeSMS');
    printf("Enviado: id=%s estado=%s custo=%s saldo=%s\n", $sms['id'], $sms['status'], $sms['cost'], $sms['remaining_balance']);

    // 2) Consultar o estado depois
    $estado = $moze->sms->status($sms['id']);
    echo 'Estado agora: ' . ($estado['status'] ?? '?') . "\n";

    // 3) Um texto personalizado para vários números ({{nome}}, {{telefone}}, {{empresa}})
    $lote = $moze->sms->bulk([$numero, '847000000'], 'Olá {{nome}}, temos novidades!', 'MozeSMS');
    printf("Lote %s: %d enviados, %d na fila, %d inválidos\n", $lote['batch_id'], $lote['sent'], $lote['queued'], $lote['invalid']);

    // 4) Texto diferente por pessoa
    $moze->sms->bulkMessages([
        ['phone' => $numero, 'message' => 'A sua encomenda #123 saiu.'],
        ['phone' => '847000000', 'message' => 'A sua encomenda #124 saiu.'],
    ], 'MozeSMS');

    // 5) Agendar (fuso da conta) e listar os agendamentos
    $moze->sms->schedule([$numero], 'Lembrete: reunião amanhã às 9h', date('Y-m-d H:i:s', time() + 3600), 'MozeSMS');
    print_r($moze->sms->schedules());

    // 6) Histórico com filtros (a resposta traz também a paginação)
    $hist = $moze->sms->history(['status' => 'enviado', 'page' => 1, 'limit' => 20]);
    echo count($hist['data'] ?? []) . " mensagens na página 1\n";
} catch (InvalidRequestException $e) {
    // O SDK recusou antes de enviar: nada foi enviado nem cobrado.
    echo 'Dados inválidos: ' . $e->getMessage() . "\n";
} catch (InsufficientBalanceException $e) {
    $b = $e->getBody();
    printf("Sem saldo (tem %s, precisa de %s)\n", $b['current_balance'] ?? '?', $b['required_amount'] ?? '?');
} catch (ServerException $e) {
    // O gateway falhou: o SMS pode já estar registado e cobrado. Veja o histórico antes de repetir.
    echo 'Falha do servidor: ' . $e->getMessage() . "\n";
}
