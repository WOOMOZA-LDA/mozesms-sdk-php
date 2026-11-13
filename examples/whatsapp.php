<?php

declare(strict_types=1);

// WhatsApp: ligar uma sessão e enviar mensagens. Funciona com números de qualquer país.
// Cada mensagem enviada é cobrada conforme o plano da sessão.
$moze = require __DIR__ . '/_client.php';

use MozeSMS\Exception\ApiException;
use MozeSMS\Exception\InvalidRequestException;

try {
    $wa = $moze->whatsapp;

    // 1) Planos e criação da sessão (subscreve o plano; pode debitar o saldo)
    // $planos = $wa->plans();
    // $sessao = $wa->createSession('Loja', (int) $planos['data'][0]['id']);
    $sessionId = $argv[1] ?? 'ID_DA_SESSAO';

    // 2) Ligar: ler o QR (ou emparelhar por código)
    $wa->connect($sessionId);
    // file_put_contents('qr.png', $wa->qrImage($sessionId));
    // $wa->connectWithCode($sessionId, '+258846000000');
    echo 'Estado da sessão: ' . ($wa->session($sessionId)['status'] ?? '?') . "\n";

    // 3) Enviar
    $wa->sendText($sessionId, '+27821234567', 'Hello from MozeSMS!');                 // África do Sul
    $wa->sendMedia($sessionId, '846000000', 'image', 'https://exemplo.co/foto.jpg', ['caption' => 'Novo produto']);
    $wa->sendLocation($sessionId, '846000000', -25.9692, 32.5732, 'Maputo', 'Av. 25 de Setembro');
    // $wa->sendFile($sessionId, '846000000', __DIR__ . '/fatura.pdf', 'A sua fatura');
    $r = $wa->sendTextBulk($sessionId, ['846000000', '+351912345678'], 'Olá a todos!');   // 5 saem já, o resto vai para a fila
    printf("%d enviados, %d na fila\n", $r['sent_count'], $r['queued_count']);

    // 4) Modelos e etiquetas
    $modelo = $wa->templates->create('Confirmação', 'Olá {{nome}}, o seu pedido {{pedido}} foi confirmado.', ['categoria' => 'transacional']);
    $wa->templates->send((int) $modelo['id'], $sessionId, '846000000', ['nome' => 'Ana', 'pedido' => '#123']);
    $etiqueta = $wa->labels->create('Cliente VIP', '#22C55E');

    // 5) Automações: resposta por palavra-chave
    $wa->automations->create('keyword', [
        'name' => 'Preços', 'session_id' => $sessionId,
        'keywords' => ['preço', 'preços'], 'match_mode' => 'contains',
        'response_message' => 'Veja os nossos preços em https://exemplo.co/precos',
    ]);

    // 6) Receber mensagens/estados da sessão num webhook seu (https)
    // $wa->registerWebhook($sessionId, 'https://o-seu-site.co/whatsapp/hook', ['message']);

    // 7) Histórico e estado de uma mensagem
    $msgs = $wa->messages(['limit' => 10]);
    echo count($msgs['data'] ?? []) . " mensagens recentes\n";
} catch (InvalidRequestException $e) {
    echo 'Dados inválidos: ' . $e->getMessage() . "\n";
} catch (ApiException $e) {
    echo "Erro {$e->getHttpStatus()} [{$e->getErrorCode()}]: {$e->getMessage()}\n";
}
