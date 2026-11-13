<?php

declare(strict_types=1);

namespace MozeSMS\Exception;

/** Webhook recebido com assinatura em falta/inválida ou corpo que não é JSON. Responda 401/400. */
class SignatureVerificationException extends MozeSMSException
{
}
