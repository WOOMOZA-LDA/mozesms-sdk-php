<?php

declare(strict_types=1);

namespace MozeSMS\Exception;

/** A API respondeu com sucesso HTTP mas o corpo não é JSON utilizável (proxy, manutenção, HTML). */
class InvalidResponseException extends ApiException
{
}
