<div>
    <!-- Título del sistema -->
    <div class="text-center mb-8">
        <h2 class="text-3xl font-bold text-gray-900">{{ config('app.name', 'Sistema Manager') }}</h2>
        <p class="mt-2 text-sm text-gray-600">Inicia sesión en tu cuenta</p>
    </div>

    <!-- Card de login -->
    <div class="bg-white rounded-2xl shadow-md p-8">
        <form wire:submit="login" class="space-y-6">
            <!-- Email -->
            <div>
                <label for="email" class="block text-sm font-medium text-gray-700 mb-2">
                    Correo electrónico
                </label>
                <input
                    type="email"
                    id="email"
                    wire:model="email"
                    class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 transition-colors @error('email') border-red-500 @enderror"
                    placeholder="tu@email.com"
                    autofocus
                >
                @error('email')
                    <p class="mt-2 text-sm text-red-600">{{ $message }}</p>
                @enderror
            </div>

            <!-- Password -->
            <div>
                <label for="password" class="block text-sm font-medium text-gray-700 mb-2">
                    Contraseña
                </label>
                <input
                    type="password"
                    id="password"
                    wire:model="password"
                    class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 transition-colors @error('password') border-red-500 @enderror"
                    placeholder="••••••••"
                >
                @error('password')
                    <p class="mt-2 text-sm text-red-600">{{ $message }}</p>
                @enderror
            </div>

            <!-- Botón de submit -->
            <div>
                <button
                    type="submit"
                    class="w-full bg-blue-600 hover:bg-blue-700 text-white font-semibold py-3 px-4 rounded-lg transition-colors duration-200 disabled:opacity-50 disabled:cursor-not-allowed"
                    wire:loading.attr="disabled"
                >
                    <span wire:loading.remove>Iniciar sesión</span>
                    <span wire:loading class="flex items-center justify-center">
                        <svg class="animate-spin -ml-1 mr-3 h-5 w-5 text-white" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                        </svg>
                        Iniciando sesión...
                    </span>
                </button>
            </div>

            <!-- Link a recuperar contraseña -->
            <div class="text-center">
                <a href="/forgot-password" wire:navigate class="text-sm text-blue-600 hover:text-blue-700 transition-colors">
                    ¿Olvidaste tu contraseña?
                </a>
            </div>
        </form>
    </div>
</div>
