<div class="space-y-6">
    <!-- Header -->
    <div class="sm:flex sm:items-center sm:justify-between">
        <div>
            <h1 class="text-3xl font-bold text-gray-900">Editar Rol</h1>
            <p class="mt-2 text-sm text-gray-700">Modifica el rol <span class="font-semibold text-gray-900">{{ ucfirst($role->name) }}</span> y sus permisos</p>
        </div>
        <div class="mt-4 sm:mt-0">
            <a href="/roles" wire:navigate
               class="inline-flex items-center justify-center px-4 py-2.5 border border-gray-300 text-sm font-medium rounded-lg text-gray-700 bg-white hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500 shadow-sm transition-all duration-200">
                <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"/>
                </svg>
                Volver
            </a>
        </div>
    </div>

    <!-- Form -->
    <div class="bg-white rounded-xl shadow-sm border border-gray-200">
        <form wire:submit="update" class="p-6 space-y-6">
            <!-- Nombre del rol -->
            <div>
                <label for="name" class="block text-sm font-semibold text-gray-700 mb-2">
                    <svg class="w-4 h-4 inline mr-1 text-gray-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 7h.01M7 3h5c.512 0 1.024.195 1.414.586l7 7a2 2 0 010 2.828l-7 7a2 2 0 01-2.828 0l-7-7A1.994 1.994 0 013 12V7a4 4 0 014-4z"/>
                    </svg>
                    Nombre del rol
                </label>
                <input type="text"
                       id="name"
                       wire:model="name"
                       placeholder="Ej: vendedor, almacenista, gerente..."
                       class="block w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 text-sm @error('name') border-red-300 text-red-900 placeholder-red-300 focus:ring-red-500 focus:border-red-500 @enderror"
                       @if(in_array($role->name, ['admin', 'supervisor', 'cajero'])) disabled @endif>
                @error('name')
                    <p class="mt-2 text-sm text-red-600 flex items-center">
                        <svg class="w-4 h-4 mr-1" fill="currentColor" viewBox="0 0 20 20">
                            <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zM8.707 7.293a1 1 0 00-1.414 1.414L8.586 10l-1.293 1.293a1 1 0 101.414 1.414L10 11.414l1.293 1.293a1 1 0 001.414-1.414L11.414 10l1.293-1.293a1 1 0 00-1.414-1.414L10 8.586 8.707 7.293z" clip-rule="evenodd"/>
                        </svg>
                        {{ $message }}
                    </p>
                @enderror
                @if(in_array($role->name, ['admin', 'supervisor', 'cajero']))
                    <p class="mt-2 text-xs text-amber-600 flex items-center">
                        <svg class="w-4 h-4 mr-1" fill="currentColor" viewBox="0 0 20 20">
                            <path fill-rule="evenodd" d="M8.257 3.099c.765-1.36 2.722-1.36 3.486 0l5.58 9.92c.75 1.334-.213 2.98-1.742 2.98H4.42c-1.53 0-2.493-1.646-1.743-2.98l5.58-9.92zM11 13a1 1 0 11-2 0 1 1 0 012 0zm-1-8a1 1 0 00-1 1v3a1 1 0 002 0V6a1 1 0 00-1-1z" clip-rule="evenodd"/>
                        </svg>
                        Este es un rol del sistema y su nombre no puede ser modificado
                    </p>
                @else
                    <p class="mt-2 text-xs text-gray-500">El nombre del rol debe ser descriptivo y único en el sistema</p>
                @endif
            </div>

            <!-- Permisos -->
            <div>
                <label class="block text-sm font-semibold text-gray-700 mb-4">
                    <svg class="w-4 h-4 inline mr-1 text-gray-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z"/>
                    </svg>
                    Permisos del rol
                </label>

                <div class="space-y-4">
                    @forelse($permissionsGrouped as $module => $permissions)
                        <div class="border border-gray-200 rounded-lg overflow-hidden">
                            <div class="bg-gradient-to-r from-blue-50 to-indigo-50 px-4 py-3 border-b border-gray-200">
                                <h3 class="text-sm font-semibold text-gray-900 uppercase tracking-wide">
                                    {{ ucfirst($module) }}
                                </h3>
                            </div>
                            <div class="p-4 grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-3">
                                @foreach($permissions as $permission)
                                    <label class="flex items-start p-3 rounded-lg hover:bg-gray-50 transition-colors cursor-pointer group">
                                        <input type="checkbox"
                                               wire:model="selectedPermissions"
                                               value="{{ $permission->name }}"
                                               class="mt-0.5 h-4 w-4 text-blue-600 border-gray-300 rounded focus:ring-blue-500">
                                        <span class="ml-3 text-sm">
                                            <span class="font-medium text-gray-900 group-hover:text-blue-600">
                                                {{ ucfirst(explode('.', $permission->name)[1] ?? $permission->name) }}
                                            </span>
                                            <span class="block text-xs text-gray-500 mt-0.5">
                                                {{ $permission->name }}
                                            </span>
                                        </span>
                                    </label>
                                @endforeach
                            </div>
                        </div>
                    @empty
                        <div class="text-center py-8 text-gray-500">
                            <svg class="w-12 h-12 mx-auto mb-3 text-gray-300" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z"/>
                            </svg>
                            <p class="text-sm">No hay permisos disponibles en el sistema</p>
                        </div>
                    @endforelse
                </div>

                <p class="mt-3 text-xs text-gray-500">Selecciona los permisos que tendrá este rol</p>
            </div>

            <!-- Botones -->
            <div class="flex items-center justify-end gap-3 pt-4 border-t border-gray-200">
                <a href="/roles" wire:navigate
                   class="inline-flex items-center px-4 py-2.5 border border-gray-300 text-sm font-medium rounded-lg text-gray-700 bg-white hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-gray-500 transition-all">
                    Cancelar
                </a>
                <button type="submit"
                        class="inline-flex items-center px-5 py-2.5 border border-transparent text-sm font-medium rounded-lg text-white bg-blue-600 hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500 shadow-sm transition-all duration-200">
                    <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/>
                    </svg>
                    Actualizar Rol
                </button>
            </div>
        </form>
    </div>
</div>
