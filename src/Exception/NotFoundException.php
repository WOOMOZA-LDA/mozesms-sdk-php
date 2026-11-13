<?php

declare(strict_types=1);

namespace MozeSMS\Exception;

/** 404: recurso inexistente (mensagem, sessão OTP, webhook...). */
class NotFoundException extends ApiException
{
}
