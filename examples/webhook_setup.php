<?php

declare(strict_types=1);

// Registar um webhook para receber o estado das mensagens. O endpoint que o recebe está em webhook_endpoint.php.
//   php webhook_setup.php https://o-seu-site.co/mozesms/webhook
$moze = require __DIR__ . '/_client.php';

use MozeSMS\Exception\InvalidRequestException;
use MozeSMS\Resource\Webhooks;

$url = $argv[1] ?? null;
if (!$url) {
    fwrite(STDERR, "Uso: php webhook_setup.php https://o-seu-site.co/mozesms/webhook\n");
    exit(1);
}

try {
    echo 'Eventos disponíveis: ' . implode(', ', Webhooks::EVENTS) . "\n";

    $r = $moze->webhooks->create('O meu site', $url, ['sms.delivered', 'sms.failed', 'sms.undelivered']);
    $wh = $r['data'] ?? $r;
    echo "Webhook {$wh['id']} criado.\n";
    // O secret só aparece na criação: guarde-o em MOZESMS_WEBHOOK_SECRET para verificar as assinaturas.
    echo 'Secret: ' . ($wh['secret'] ?? '(ver resposta)') . "\n";

    // Enviar um evento de teste e ver o que aconteceu
    $moze->webhooks->test((int) $wh['id']);
    print_r($moze->webhooks->logs((int) $wh['id']));

    // Desativar/ativar sem apagar
    // $moze->webhooks->toggle((int) $wh['id']);
} catch (InvalidRequestException $e) {
    echo 'Dados inválidos: ' . $e->getMessage() . "\n";
}
