<?php

namespace App\Exceptions;

use RuntimeException;

/** Operación de cuenta corriente inválida. El mensaje se muestra al usuario. */
class CuentaCorrienteException extends RuntimeException {}
