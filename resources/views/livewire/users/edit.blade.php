<div>
    <div class="space-y-6">
        <!-- Breadcrumb -->
        <nav class="flex items-center text-sm text-gray-600">
            <a href="/usuarios" wire:navigate class="hover:text-gray-900 transition-colors">Usuarios</a>
            <svg class="w-4 h-4 mx-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/>
            </svg>
            <span class="text-gray-900">Editar Usuario</span>
        </nav>

        <!-- Header -->
        <div>
            <h1 class="text-2xl font-bold text-gray-900">Editar Usuario</h1>
            <p class="mt-1 text-sm text-gray-600">Actualiza la información del usuario {{ $user->name }}</p>
        </div>

        <!-- Formulario -->
        <div class="bg-white rounded-lg shadow-sm p-6">
            <form wire:submit="update" class="space-y-6">
                <!-- Nombre -->
                <div>
                    <label for="name" class="block text-sm font-medium text-gray-700 mb-2">
                        Nombre completo <span class="text-red-500">*</span>
                    </label>
                    <input
                        type="text"
                        id="name"
                        wire:model="name"
                        class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 @error('name') border-red-500 @enderror"
                        placeholder="Ej: Juan Pérez"
                    >
                    @error('name')
                        <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                    @enderror
                </div>

                <!-- Email -->
                <div>
                    <label for="email" class="block text-sm font-medium text-gray-700 mb-2">
                        Correo electrónico <span class="text-red-500">*</span>
                    </label>
                    <input
                        type="email"
                        id="email"
                        wire:model="email"
                        class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 @error('email') border-red-500 @enderror"
                        placeholder="correo@ejemplo.com"
                    >
                    @error('email')
                        <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                    @enderror
                </div>

                <!-- Contraseña -->
                <div>
                    <label for="password" class="block text-sm font-medium text-gray-700 mb-2">
                        Nueva Contraseña
                    </label>
                    <input
                        type="password"
                        id="password"
                        wire:model="password"
                        class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 @error('password') border-red-500 @enderror"
                        placeholder="Dejar en blanco para no cambiar"
                    >
                    @error('password')
                        <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                    @enderror
                    <p class="mt-1 text-xs text-gray-500">Dejar en blanco si no deseas cambiar la contraseña. Mínimo 8 caracteres si deseas cambiarla.</p>
                </div>

                <!-- Rol -->
                <div>
                    <label for="role" class="block text-sm font-medium text-gray-700 mb-2">
                        Rol <span class="text-red-500">*</span>
                    </label>
                    <select
                        id="role"
                        wire:model="role"
                        class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 @error('role') border-red-500 @enderror"
                    >
                        <option value="">Seleccionar rol</option>
                        @foreach($roles as $roleItem)
                            <option value="{{ $roleItem->name }}">{{ ucfirst($roleItem->name) }}</option>
                        @endforeach
                    </select>
                    @error('role')
                        <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                    @enderror
                </div>

                <!-- Estado Activo -->
                <div class="flex items-center">
                    <input
                        type="checkbox"
                        id="active"
                        wire:model="active"
                        class="w-4 h-4 text-blue-600 border-gray-300 rounded focus:ring-blue-500"
                    >
                    <label for="active" class="ml-2 text-sm font-medium text-gray-700">
                        Usuario activo
                    </label>
                </div>

                <!-- Información adicional -->
                <div class="bg-gray-50 rounded-lg p-4 space-y-2">
                    <p class="text-xs text-gray-600">
                        <span class="font-medium">Fecha de registro:</span> {{ $user->created_at->format('d/m/Y H:i') }}
                    </p>
                    @if($user->updated_at->ne($user->created_at))
                        <p class="text-xs text-gray-600">
                            <span class="font-medium">Última actualización:</span> {{ $user->updated_at->format('d/m/Y H:i') }}
                        </p>
                    @endif
                </div>

                <!-- Botones -->
                <div class="flex items-center justify-end gap-3 pt-4 border-t">
                    <a href="/usuarios" wire:navigate
                       class="px-4 py-2 text-sm font-medium text-gray-700 bg-white border border-gray-300 rounded-lg hover:bg-gray-50 transition-colors">
                        Cancelar
                    </a>
                    <button
                        type="submit"
                        class="px-4 py-2 text-sm font-medium text-white bg-blue-600 rounded-lg hover:bg-blue-700 transition-colors disabled:opacity-50 disabled:cursor-not-allowed"
                        wire:loading.attr="disabled"
                    >
                        <span wire:loading.remove>Actualizar Usuario</span>
                        <span wire:loading class="flex items-center">
                            <svg class="animate-spin -ml-1 mr-2 h-4 w-4 text-white" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                            </svg>
                            Actualizando...
                        </span>
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>
