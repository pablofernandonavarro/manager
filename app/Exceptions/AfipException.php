<?php

namespace App\Exceptions;

use RuntimeException;

/**
 * Error de la integración con AFIP (certificado, autenticación o web service). El mensaje
 * se muestra al usuario: se traduce lo que AFIP devuelve cuando se sabe qué significa.
 */
class AfipException extends RuntimeException {}
