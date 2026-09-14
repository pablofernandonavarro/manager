@php
    use App\Models\ConfiguracionFiscal;
    use App\Support\Cuit;
    $zona = config('app.display_timezone');
    $faltantes = $config->faltantes();
@endphp

<div class="space-y-6">
    <div class="sm:flex sm:items-start sm:justify-between gap-4">
        <div>
            <h1 class="text-3xl font-bold text-gray-900">Facturación electrónica</h1>
            <p class="mt-2 text-sm text-gray-700">Datos del emisor, certificado digital de AFIP y punto de venta de cada sucursal. Las cajas le piden el CAE a este Manager.</p>
        </div>
        <div class="mt-4 sm:mt-0 flex items-center gap-3">
            <span class="px-3 py-1 rounded-full text-xs font-semibold {{ $config->entorno === 'produccion' ? 'bg-red-100 text-red-800' : 'bg-amber-100 text-amber-800' }}">
                {{ $config->entorno === 'produccion' ? 'PRODUCCIÓN' : 'Homologación (pruebas)' }}
            </span>
            <button type="button" wire:click="alternarFacturacion"
                    wire:confirm="{{ $config->facturacion_activa ? '¿Desactivar la facturación electrónica? Las cajas dejan de emitir comprobantes fiscales.' : '¿Activar la facturación electrónica en todas las cajas?' }}"
                    class="px-4 py-2 rounded-lg text-sm font-semibold {{ $config->facturacion_activa ? 'bg-green-600 text-white hover:bg-green-700' : 'bg-gray-200 text-gray-800 hover:bg-gray-300' }}">
                {{ $config->facturacion_activa ? '● Facturación activa' : 'Activar facturación' }}
            </button>
        </div>
    </div>

    @if($mensaje)
        <div class="rounded-lg bg-green-50 p-4 border-l-4 border-green-400"><p class="text-sm font-medium text-green-800">{{ $mensaje }}</p></div>
    @endif
    @if($error)
        <div class="rounded-lg bg-red-50 p-4 border-l-4 border-red-400"><p class="text-sm font-medium text-red-800">{{ $error }}</p></div>
    @endif
    @if($faltantes)
        <div class="rounded-lg bg-amber-50 p-4 border border-amber-200 text-sm text-amber-900">
            <strong>Falta para poder facturar:</strong> {{ implode(' · ', $faltantes) }}
        </div>
    @endif

    {{-- 1. Datos del emisor --}}
    <form wire:submit="guardarDatos" class="bg-white rounded-xl shadow-sm border border-gray-200 p-6 space-y-4">
        <h2 class="text-lg font-semibold text-gray-900">1 · Datos del emisor</h2>
        <div class="grid sm:grid-cols-2 lg:grid-cols-3 gap-4">
            <div class="lg:col-span-2">
                <label class="block text-sm font-medium text-gray-700 mb-1">Razón social</label>
                <input type="text" wire:model="razonSocial" maxlength="150" class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm">
                @error('razonSocial') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">CUIT</label>
                <input type="text" wire:model="cuit" maxlength="13" placeholder="30-12345678-9" class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm">
                @error('cuit') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Condición frente al IVA</label>
                <select wire:model="condicionIva" class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm">
                    <option value="">— Elegí —</option>
                    @foreach(ConfiguracionFiscal::CONDICIONES_IVA as $valor => $etiqueta)
                        <option value="{{ $valor }}">{{ $etiqueta }}</option>
                    @endforeach
                </select>
                @error('condicionIva') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                @if($config->letraComprobantes())
                    <p class="mt-1 text-xs text-gray-500">Emite comprobantes {{ $config->letraComprobantes() }}.</p>
                @endif
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Ingresos brutos</label>
                <input type="text" wire:model="ingresosBrutos" maxlength="30" placeholder="Nº o «Convenio multilateral»" class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm">
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Inicio de actividades</label>
                <input type="date" wire:model="inicioActividades" class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm">
                @error('inicioActividades') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
            </div>
            <div class="lg:col-span-2">
                <label class="block text-sm font-medium text-gray-700 mb-1">Domicilio comercial</label>
                <input type="text" wire:model="domicilioComercial" maxlength="200" class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm">
                @error('domicilioComercial') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Entorno de AFIP</label>
                <select wire:model="entorno" class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm">
                    <option value="homologacion">Homologación (pruebas, sin validez fiscal)</option>
                    <option value="produccion">Producción (comprobantes reales)</option>
                </select>
            </div>
        </div>
        <div class="flex justify-end">
            <button type="submit" class="px-4 py-2 text-sm font-semibold text-white bg-blue-600 rounded-lg hover:bg-blue-700">Guardar datos</button>
        </div>
    </form>

    {{-- 2. Certificado --}}
    <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-6 space-y-5">
        <div class="flex items-start justify-between gap-4">
            <h2 class="text-lg font-semibold text-gray-900">2 · Certificado digital de AFIP</h2>
            @if($config->tieneCertificado())
                <span class="px-2.5 py-1 rounded-full text-xs font-semibold {{ $config->certificadoVigente() ? 'bg-green-100 text-green-800' : 'bg-red-100 text-red-800' }}">
                    {{ $config->certificadoVigente() ? 'Cargado · vence '.$config->certificado_vence->timezone($zona)->format('d/m/Y') : 'Vencido' }}
                </span>
            @elseif($config->csr)
                <span class="px-2.5 py-1 rounded-full text-xs font-semibold bg-amber-100 text-amber-800">Pedido generado, falta el certificado</span>
            @else
                <span class="px-2.5 py-1 rounded-full text-xs font-semibold bg-gray-100 text-gray-700">Sin certificado</span>
            @endif
        </div>

        @if($config->tieneCertificado())
            <p class="text-sm text-gray-600">Alias <strong>{{ $config->certificado_alias }}</strong> · emitido por {{ $config->certificado_emisor }}.</p>
        @endif

        <div class="grid lg:grid-cols-2 gap-6">
            <div class="space-y-3">
                <p class="text-sm font-semibold text-gray-800">Paso A · Generar el pedido (CSR)</p>
                <p class="text-sm text-gray-600">La clave privada se genera y queda guardada cifrada en este Manager; a AFIP solo se sube el pedido.</p>
                <div class="flex items-end gap-2">
                    <div class="flex-1">
                        <label class="block text-xs font-medium text-gray-600 mb-1">Alias (nombre del certificado en AFIP)</label>
                        <input type="text" wire:model="alias" maxlength="60" class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm">
                    </div>
                    <button type="button" wire:click="generarPedido"
                            @if($config->tieneCertificado()) wire:confirm="Generar una clave nueva deja inservible el certificado actual. ¿Seguir?" @endif
                            class="px-3 py-2 text-sm font-semibold text-white bg-gray-800 rounded-lg hover:bg-gray-900">Generar</button>
                </div>
                @if($config->csr)
                    <button type="button" wire:click="descargarCsr" class="inline-flex items-center gap-2 px-3 py-2 text-sm font-semibold text-blue-700 bg-blue-50 rounded-lg hover:bg-blue-100">
                        ⬇ Descargar pedido (.csr)
                    </button>
                @endif
            </div>

            <form wire:submit="subirCertificado" class="space-y-3">
                <p class="text-sm font-semibold text-gray-800">Paso B · Subir el certificado (.crt)</p>
                <div>
                    <label class="block text-xs font-medium text-gray-600 mb-1">Certificado descargado de AFIP</label>
                    <input type="file" wire:model="archivoCertificado" accept=".crt,.pem,.cer" class="block w-full text-sm text-gray-700">
                    @error('archivoCertificado') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                </div>
                <details class="text-sm text-gray-600">
                    <summary class="cursor-pointer">Ya tengo clave y certificado generados por fuera</summary>
                    <div class="mt-2">
                        <label class="block text-xs font-medium text-gray-600 mb-1">Clave privada (.key)</label>
                        <input type="file" wire:model="archivoClave" accept=".key,.pem" class="block w-full text-sm text-gray-700">
                    </div>
                </details>
                <button type="submit" wire:loading.attr="disabled" class="px-3 py-2 text-sm font-semibold text-white bg-blue-600 rounded-lg hover:bg-blue-700 disabled:opacity-50">Subir certificado</button>
            </form>
        </div>

        <details class="rounded-lg bg-gray-50 border border-gray-200 p-4 text-sm text-gray-700">
            <summary class="cursor-pointer font-semibold text-gray-800">Cómo conseguir el certificado en AFIP</summary>
            <div class="mt-3 grid md:grid-cols-2 gap-6">
                <div>
                    <p class="font-semibold mb-1">Homologación (pruebas)</p>
                    <ol class="list-decimal ml-5 space-y-1">
                        <li>Entrá con tu clave fiscal y adherí el servicio <strong>WSASS – Autogestión Certificados Homologación</strong>.</li>
                        <li>En WSASS: <em>Nuevo certificado</em>, poné el mismo alias y pegá el contenido del .csr.</li>
                        <li>Descargá el certificado y subilo acá.</li>
                        <li>En WSASS: <em>Crear autorización a servicio</em> → servicio <strong>wsfe</strong> con ese alias.</li>
                    </ol>
                </div>
                <div>
                    <p class="font-semibold mb-1">Producción</p>
                    <ol class="list-decimal ml-5 space-y-1">
                        <li>Servicio <strong>Administración de Certificados Digitales</strong> → agregar alias → subir el .csr → descargar el .crt y subirlo acá.</li>
                        <li><strong>Administrador de Relaciones de Clave Fiscal</strong> → Nueva relación → AFIP → WebServices → <strong>Facturación Electrónica</strong> → representante: el certificado.</li>
                        <li><strong>Administración de puntos de venta y domicilios</strong> → alta de un punto de venta tipo <em>«Factura Electrónica – Web Services»</em> por sucursal.</li>
                    </ol>
                </div>
            </div>
        </details>
    </div>

    {{-- 3. Puntos de venta --}}
    <form wire:submit="guardarPuntosVenta" class="bg-white rounded-xl shadow-sm border border-gray-200 p-6 space-y-4">
        <div>
            <h2 class="text-lg font-semibold text-gray-900">3 · Punto de venta de AFIP por sucursal</h2>
            <p class="text-sm text-gray-600">Cada sucursal numera sus comprobantes en su propio punto de venta (dado de alta en AFIP como «Web Services»). Todas las cajas de una sucursal comparten la numeración.</p>
        </div>
        <table class="w-full text-sm">
            <thead class="text-xs text-gray-500 uppercase">
                <tr><th class="py-2 text-left">Sucursal</th><th class="py-2 text-left w-48">Punto de venta</th></tr>
            </thead>
            <tbody class="divide-y divide-gray-100">
                @foreach($sucursales as $s)
                    <tr wire:key="pv-{{ $s->id }}">
                        <td class="py-2 {{ $s->activo ? 'text-gray-900' : 'text-gray-400' }}">{{ $s->nombre }}</td>
                        <td class="py-2">
                            <input type="number" min="1" max="99998" wire:model="puntosVenta.{{ $s->id }}" placeholder="Sin facturación"
                                   class="w-40 px-3 py-1.5 border border-gray-300 rounded-lg text-sm">
                            @error('puntosVenta.'.$s->id) <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                        </td>
                    </tr>
                @endforeach
            </tbody>
        </table>
        <div class="flex justify-end">
            <button type="submit" class="px-4 py-2 text-sm font-semibold text-white bg-blue-600 rounded-lg hover:bg-blue-700">Guardar puntos de venta</button>
        </div>
    </form>

    {{-- 4. Prueba --}}
    <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-6 space-y-4">
        <div class="flex items-center justify-between gap-4">
            <div>
                <h2 class="text-lg font-semibold text-gray-900">4 · Probar conexión con AFIP</h2>
                <p class="text-sm text-gray-600">Verifica servidores, certificado, puntos de venta y numeración, sin emitir ningún comprobante.</p>
            </div>
            <button type="button" wire:click="probarConexion" wire:loading.attr="disabled" wire:target="probarConexion"
                    class="px-4 py-2 text-sm font-semibold text-white bg-gray-800 rounded-lg hover:bg-gray-900 disabled:opacity-50">
                <span wire:loading.remove wire:target="probarConexion">Probar ahora</span>
                <span wire:loading wire:target="probarConexion">Consultando AFIP…</span>
            </button>
        </div>

        @if($config->ultima_prueba_at)
            <p class="text-xs text-gray-500">Última prueba: {{ $config->ultima_prueba_at->timezone($zona)->format('d/m/Y H:i') }}</p>
            <ul class="space-y-2">
                @foreach($config->ultima_prueba_detalle ?? [] as $paso)
                    <li class="flex items-start gap-3 text-sm">
                        <span class="mt-0.5 w-5 text-center">{{ $paso['ok'] === true ? '✅' : ($paso['ok'] === false ? '❌' : '⏸') }}</span>
                        <span><strong class="text-gray-900">{{ $paso['titulo'] }}</strong><br><span class="text-gray-600">{{ $paso['detalle'] }}</span></span>
                    </li>
                @endforeach
            </ul>
        @endif
    </div>
</div>
