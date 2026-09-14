<?php

namespace App\Exceptions;

/**
 * No hubo respuesta de AFIP (sin conexión o se cortó esperando). A diferencia de un error
 * que AFIP contestó, acá no se sabe si el pedido llegó: un comprobante pudo quedar
 * autorizado del lado de AFIP sin que nos enteremos.
 */
class AfipSinRespuestaException extends AfipException {}
