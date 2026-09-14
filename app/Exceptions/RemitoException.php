<?php

namespace App\Exceptions;

use RuntimeException;

/**
 * Un remito que no se puede crear, confirmar o cancelar. El mensaje es para mostrarle
 * al usuario tal cual: no lleva detalles internos.
 */
class RemitoException extends RuntimeException {}
