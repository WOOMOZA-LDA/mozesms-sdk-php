<?php

declare(strict_types=1);

namespace MozeSMS\Exception;

/** 5xx: erro do servidor ou do gateway. Num envio, o SMS pode ter sido registado: confirme antes de repetir. */
class ServerException extends ApiException
{
}
