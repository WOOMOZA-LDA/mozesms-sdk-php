<?php

declare(strict_types=1);

namespace MozeSMS\Exception;

/** 409/410: conflito de estado (OTP já verificado, expirado...). */
class ConflictException extends ApiException
{
}
