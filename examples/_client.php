<?php

declare(strict_types=1);

// Partilhado pelos exemplos: lê as credenciais do ambiente.
//   Windows (PowerShell):  $env:MOZESMS_KEY = 'mk_...'; $env:MOZESMS_SECRET = 'sk_...'
//   Linux/macOS:           export MOZESMS_KEY=mk_... MOZESMS_SECRET=sk_...
require __DIR__ . '/../autoload.php';

use MozeSMS\Client;

$key = getenv('MOZESMS_KEY');
$secret = getenv('MOZESMS_SECRET');
if (!$key || !$secret) {
    fwrite(STDERR, "Defina MOZESMS_KEY e MOZESMS_SECRET (painel MozeSMS → API).\n");
    exit(1);
}

return new Client($key, $secret, [
    // Enquanto a API só enviar SMS para Moçambique, deixe isto ligado.
    'allowed_countries' => ['258'],
]);
