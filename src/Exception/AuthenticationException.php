<?php

declare(strict_types=1);

namespace MozeSMS\Exception;

/** 401/403: credenciais em falta, inválidas ou sem permissão. */
class AuthenticationException extends ApiException
{
}
