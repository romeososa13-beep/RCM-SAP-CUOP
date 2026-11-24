<?php

namespace App\Exceptions;

use DomainException;

/** Error de regla de negocio en cobranza: el mensaje es seguro para mostrarse al cajero. */
class CobranzaException extends DomainException
{
}
