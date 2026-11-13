<?php

declare(strict_types=1);

namespace MozeSMS\Exception;

/** 400/422: dados inválidos (ou, no OTP, código errado). */
class ValidationException extends ApiException
{
}
