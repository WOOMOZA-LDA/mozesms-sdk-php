<?php

declare(strict_types=1);

// Recarregar saldo (topup). Cria uma recarga PENDENTE: o saldo só sobe quando o pagamento é confirmado.
//   php topup.php 500 mpesa 846000000
$moze = require __DIR__ . '/_client.php';

use MozeSMS\Exception\ApiException;
use MozeSMS\Exception\InvalidRequestException;

$valor = (float) ($argv[1] ?? 100);
$metodo = $argv[2] ?? 'mpesa';          // mpesa, emola, bank_transfer, paypal, txenka, manual
$telefone = $argv[3] ?? '846000000';    // obrigatório para mpesa e emola

try {
    // 1) Que métodos estão ativos e com que taxas
    foreach ($moze->billing->gateways()['data'] as $g) {
        printf("%-14s taxa %s%%  mín %s  máx %s\n", $g['gateway'], $g['fee_percentage'], $g['min_amount'], $g['max_amount']);
    }

    // 2) Simular (não cria nada)
    $sim = $moze->billing->calculate($valor, $metodo);
    printf("Paga %s, recebe %s de crédito (taxa %s)\n", $sim['total_amount'], $sim['credits_to_receive'], $sim['gateway_fee']);

    // 3) Criar a recarga
    $t = $moze->billing->topup($valor, $metodo, $telefone);
    echo "Transação {$t['transaction_id']} ({$t['status']}), expira em {$t['expires_at']}\n";
    echo $t['next_step'] ?? $t['payment_instructions'] ?? '', "\n";

    // O que fazer a seguir depende do método:
    //  mpesa/emola   → $t['payment_to'] diz para que número pagar
    //  paypal        → abra $t['redirect_url']
    //  txenka        → abra $t['checkout_url']
    //  bank_transfer → pague e envie o comprovativo:
    //     $moze->billing->uploadProof($t['transaction_db_id'], __DIR__ . '/comprovativo.pdf');

    // 4) Esperar pela confirmação (polling simples)
    for ($i = 0; $i < 10; $i++) {
        $estado = $moze->billing->transactionStatus((int) $t['transaction_db_id']);
        echo 'Estado: ' . ($estado['status'] ?? '?') . "\n";
        if (in_array($estado['status'] ?? '', ['confirmed', 'completed', 'failed', 'expired'], true)) {
            break;
        }
        sleep(15);
    }
    echo 'Saldo atual: ' . $moze->account->balance()['balance'] . " MZN\n";
} catch (InvalidRequestException $e) {
    echo 'Dados inválidos: ' . $e->getMessage() . "\n";
} catch (ApiException $e) {
    echo "Erro {$e->getHttpStatus()}: {$e->getMessage()}\n";
}
