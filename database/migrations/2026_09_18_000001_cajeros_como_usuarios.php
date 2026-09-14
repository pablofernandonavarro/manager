<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

/**
 * Los cajeros y supervisores de caja pasan a ser usuarios del Manager (rol `cajero` o
 * `supervisor`) con PIN de caja y las sucursales donde atienden. Reemplaza a la tabla
 * `cajeros`, que era una lista aparte.
 *
 * Un cajero no entra al Manager: email y contraseña quedan opcionales para ellos.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->string('email')->nullable()->change();
            $table->string('password')->nullable()->change();
            $table->string('pin_hash')->nullable()->after('password');
        });

        Schema::create('sucursal_user', function (Blueprint $table): void {
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('sucursal_id')->constrained('sucursales')->cascadeOnDelete();
            $table->primary(['user_id', 'sucursal_id']);
        });

        Schema::dropIfExists('cajeros');

        app()[PermissionRegistrar::class]->forgetCachedPermissions();
        Permission::where('name', 'cajeros.gestionar')->where('guard_name', 'web')->delete();
        app()[PermissionRegistrar::class]->forgetCachedPermissions();
    }

    public function down(): void
    {
        Schema::create('cajeros', function (Blueprint $table): void {
            $table->id();
            $table->string('nombre', 100);
            $table->string('pin_hash');
            $table->enum('rol', ['cajero', 'supervisor'])->default('cajero');
            $table->json('sucursales')->nullable();
            $table->boolean('activo')->default(true);
            $table->timestamps();
        });

        Schema::dropIfExists('sucursal_user');

        Schema::table('users', function (Blueprint $table): void {
            $table->dropColumn('pin_hash');
        });

        app()[PermissionRegistrar::class]->forgetCachedPermissions();
        Permission::firstOrCreate(['name' => 'cajeros.gestionar', 'guard_name' => 'web']);
        Role::where('name', 'admin')->where('guard_name', 'web')->first()?->givePermissionTo('cajeros.gestionar');
        app()[PermissionRegistrar::class]->forgetCachedPermissions();
    }
};
