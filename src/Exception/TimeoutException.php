<?php

declare(strict_types=1);

namespace MozeSMS\Exception;

/** O servidor não respondeu a tempo. O envio pode ter sido feito. */
class TimeoutException extends ConnectionException
{
}
