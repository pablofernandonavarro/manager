<?php

namespace App\Http\Controllers;

use App\Models\ListaPrecio;
use App\Models\Product;
use Illuminate\Http\Request;

class ListaPrecioController extends Controller
{
    public function index()
    {
        $listas = ListaPrecio::with('sucursales')->get();

        return view('listas-precios.index', compact('listas'));
    }

    public function show(ListaPrecio $lista)
    {
        $productos = Product::where('es_vendible', true)
            ->orderBy('nombre')
            ->get()
            ->map(function ($producto) use ($lista) {
                $precioEfectivo = $lista->precioEfectivoParaProducto($producto->id);

                return [
                    'id' => $producto->id,
                    'codigo' => $producto->codigo_interno ?? $producto->codigo_barras,
                    'nombre' => $producto->nombre,
                    'precio_base' => $producto->precio,
                    'precio_lista' => $precioEfectivo,
                    'diferencia' => $precioEfectivo - $producto->precio,
                    'porcentaje' => $producto->precio > 0
                        ? (($precioEfectivo - $producto->precio) / $producto->precio) * 100
                        : 0,
                ];
            });

        return view('listas-precios.show', compact('lista', 'productos'));
    }
}
