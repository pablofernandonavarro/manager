<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;
use Spatie\Permission\Traits\HasRoles;

class User extends Authenticatable
{
    /** @use HasFactory<\Database\Factories\UserFactory> */
    use HasApiTokens, HasFactory, HasRoles, Notifiable, SoftDeletes;

    /**
     * Roles que atienden una caja: bajan a las cajas de sus sucursales con el PIN.
     * El supervisor además autoriza anulaciones y descuentos, y puede tener varias sucursales.
     */
    public const ROLES_CAJA = ['cajero', 'supervisor'];

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'email',
        'password',
        'pin_hash',
        'active',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'pin_hash',
        'remember_token',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'active' => 'boolean',
        ];
    }

    public function sucursales(): BelongsToMany
    {
        return $this->belongsToMany(Sucursal::class);
    }

    /** `cajero`, `supervisor` o null si no atiende cajas. */
    public function rolDeCaja(): ?string
    {
        return $this->getRoleNames()->first(fn (string $rol) => in_array($rol, self::ROLES_CAJA, true));
    }

    /** Lo que recibe la caja: activos, con PIN y asignados a esa sucursal. */
    public function scopeDeCajaEnSucursal(Builder $query, int $sucursalId): Builder
    {
        return $query->where('active', true)
            ->whereNotNull('pin_hash')
            ->role(self::ROLES_CAJA)
            ->whereHas('sucursales', fn ($q) => $q->whereKey($sucursalId));
    }
}
