<?php

namespace App\Livewire\PuntosDeVenta;

use App\Models\PuntoDeVenta;
use App\Models\Sucursal;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Livewire\Attributes\Layout;
use Livewire\Component;

class Index extends Component
{
    public ?int $sucursalId = null;

    public string $nombre = '';

    public function save(): void
    {
        $this->validate([
            'sucursalId' => 'required|integer|exists:sucursales,id',
            'nombre' => 'required|string|max:100',
        ]);

        $secret = Str::random(40);

        $pdv = PuntoDeVenta::create([
            'sucursal_id' => $this->sucursalId,
            'nombre' => $this->nombre,
            'secret' => Hash::make($secret),
            'activo' => true,
        ]);

        $this->dispatch('secret-generado', secret: $secret, nombre: $pdv->nombre);

        $this->sucursalId = null;
        $this->nombre = '';
    }

    public function regenerarSecret(int $id): void
    {
        $pdv = PuntoDeVenta::findOrFail($id);
        $secret = Str::random(40);
        $pdv->update(['secret' => Hash::make($secret)]);

        $this->dispatch('secret-generado', secret: $secret, nombre: $pdv->nombre);
    }

    public function toggleActive(int $id): void
    {
        $pdv = PuntoDeVenta::findOrFail($id);
        $pdv->update(['activo' => ! $pdv->activo]);
    }

    public function generarToken(int $id): void
    {
        $pdv = PuntoDeVenta::findOrFail($id);
        $pdv->tokens()->delete();

        $token = $pdv->createToken('pos-sync')->plainTextToken;
        $this->dispatch('token-generado', token: $token);
    }

    public function revocarTokens(int $id): void
    {
        PuntoDeVenta::findOrFail($id)->tokens()->delete();
    }

    public function delete(int $id): void
    {
        $pdv = PuntoDeVenta::findOrFail($id);
        $pdv->tokens()->delete();
        $pdv->delete();
    }

    #[Layout('layouts.app')]
    public function render(): mixed
    {
        return view('livewire.puntos-de-venta.index', [
            'puntosDeVenta' => PuntoDeVenta::with('sucursal')->orderBy('sucursal_id')->orderBy('nombre')->get(),
            'sucursales' => Sucursal::where('activo', true)->orderBy('nombre')->get(),
        ]);
    }
}
