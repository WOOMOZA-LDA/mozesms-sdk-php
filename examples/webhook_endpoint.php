<?php

declare(strict_types=1);

// Endpoint que recebe os webhooks da MozeSMS (URL registada com $moze->webhooks->create(...)).
require __DIR__ . '/../autoload.php';

use MozeSMS\Exception\SignatureVerificationException;
use MozeSMS\WebhookSignature;

try {
    $event = WebhookSignature::parse(getenv('MOZESMS_WEBHOOK_SECRET') ?: 'whsec_...');
} catch (SignatureVerificationException $e) {
    http_response_code(401);
    exit('invalid signature');
}

error_log('MozeSMS ' . ($_SERVER['HTTP_X_MOZESMS_EVENT'] ?? '?') . ': ' . json_encode($event));
http_response_code(200);
echo 'ok';
