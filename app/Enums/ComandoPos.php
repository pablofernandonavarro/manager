<?php

namespace App\Enums;

/**
 * Lista cerrada de ordenes que el Manager puede mandarle a una caja.
 *
 * Es a proposito un enum y no texto libre: el POS traduce cada valor a una llamada
 * interna concreta. En ningun momento se manda algo que la caja pueda interpretar como
 * un comando de shell, porque eso convertiria al Manager en una via de ejecucion remota
 * arbitraria sobre todas las terminales.
 */
enum ComandoPos: string
{
    case Resincronizar = 'resincronizar';
    case ResincronizarStock = 'resincronizar_stock';
    case RearmarCatalogo = 'rearmar_catalogo';
    case ReenviarPendientes = 'reenviar_pendientes';
    case LimpiarCache = 'limpiar_cache';
    case RecrearAccesoDirecto = 'recrear_acceso_directo';
    case Actualizar = 'actualizar';
    case LimpiarFallidos = 'limpiar_fallidos';

    public function label(): string
    {
        return match ($this) {
            self::Resincronizar => 'Resincronizar todo',
            self::ResincronizarStock => 'Actualizar stock',
            self::RearmarCatalogo => 'Rearmar catálogo',
            self::ReenviarPendientes => 'Reenviar pendientes',
            self::LimpiarCache => 'Limpiar caché',
            self::RecrearAccesoDirecto => 'Recrear acceso directo',
            self::Actualizar => 'Actualizar el POS',
            self::LimpiarFallidos => 'Limpiar envíos fallidos',
        };
    }

    public function descripcion(): string
    {
        return match ($this) {
            self::Resincronizar => 'Baja catálogo, precios y stock de nuevo.',
            self::ResincronizarStock => 'Solo stock. Rápido y sin tocar el catálogo.',
            self::RearmarCatalogo => 'Borra el catálogo local y lo descarga entero. Para cuando quedó inconsistente.',
            self::ReenviarPendientes => 'Fuerza el envío de ventas y movimientos trabados.',
            self::LimpiarCache => 'Limpia cachés de configuración y vistas.',
            self::RecrearAccesoDirecto => 'Vuelve a crear el ícono del escritorio si alguien lo borró. Solo Windows.',
            self::Actualizar => 'Baja la última versión del código y la aplica. Si algo falla, vuelve sola a la anterior.',
            self::LimpiarFallidos => 'Borra los intentos de sincronización que ya agotaron sus reintentos. No reintenta nada: lo que sigue pendiente de verdad se reenvía solo.',
        };
    }
}
