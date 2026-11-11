<?php
/**
 * Guru Marketing — Alta de suscripción a Guru Conecta.
 *
 * Página pensada para enviarse por WhatsApp una vez cerrado el trato, con el
 * plan preseleccionado:
 *   https://gurumkt.com.mx/suscribirse.php?plan=conecta-crecimiento&periodo=mensual
 *
 * Los precios se leen del catálogo del servidor (mp-catalogo.php); el navegador
 * nunca envía montos.
 */

$catalogo = require __DIR__ . '/mp-catalogo.php';

$planSel    = isset($_GET['plan'])    && isset($catalogo[$_GET['plan']]) ? $_GET['plan'] : 'conecta-crecimiento';
$periodoSel = isset($_GET['periodo']) && isset($catalogo[$planSel]['periodos'][$_GET['periodo']]) ? $_GET['periodo'] : 'mensual';

function h($v) { return htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8'); }
function money($v) { return '$' . number_format((float) $v, 0, '.', ','); }
?>
<!DOCTYPE html>
<html lang="es">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Activar mi suscripción · Guru Conecta</title>
    <meta name="description" content="Activa tu suscripción a Guru Conecta y empieza a atender tu WhatsApp de forma automática.">
    <meta name="robots" content="noindex, nofollow">
    <link rel="canonical" href="https://gurumkt.com.mx/suscribirse.php">

    <link rel="stylesheet" href="final.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" media="print" onload="this.media='all'">
    <noscript><link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css"></noscript>
    <link rel="icon" type="image/x-icon" href="/favicon.ico">
    <link rel="icon" type="image/png" sizes="32x32" href="/img/icons/favicon-32x32.png">
    <link rel="icon" type="image/png" sizes="16x16" href="/img/icons/favicon-16x16.png">
    <link rel="apple-touch-icon" sizes="180x180" href="/img/icons/apple-touch-icon.png">
    <meta name="theme-color" content="#0f172a">
</head>

<body class="bg-black text-white font-sans antialiased min-h-screen">

    <div class="fixed top-0 left-1/2 -translate-x-1/2 w-[800px] h-[800px] bg-green-600/10 rounded-full blur-[120px] -z-10"></div>

    <div class="container mx-auto px-6 py-12 max-w-2xl">

        <a href="/" class="inline-flex items-center gap-2 mb-10">
            <img src="img/logo.webp" alt="Guru Marketing" class="h-9">
            <span class="text-xl font-bold">GURU <span class="text-green-400">CONECTA</span></span>
        </a>

        <h1 class="text-3xl md:text-4xl font-bold mb-3">Activa tu suscripción</h1>
        <p class="text-gray-400 mb-10">
            Elige tu plan y confirma. Te llevamos a Mercado Pago para registrar tu método de pago de forma segura.
            <span class="block mt-3 text-sm text-gray-500">Aquí autorizas solo la mensualidad. La implementación
            se paga por transferencia bancaria y te enviamos los datos por separado.</span>
        </p>

        <form id="form-suscripcion" class="glass-card p-8 rounded-3xl border border-white/10 space-y-8">

            <!-- Plan -->
            <div>
                <label class="block text-sm font-bold text-gray-300 mb-3">Plan</label>
                <div class="space-y-3" id="planes">
                    <?php foreach ($catalogo as $key => $plan): ?>
                        <label class="block cursor-pointer">
                            <input type="radio" name="plan" value="<?= h($key) ?>" class="peer sr-only"
                                <?= $key === $planSel ? 'checked' : '' ?>
                                data-precios='<?= h(json_encode(array_map(fn($p) => ['monto' => $p['monto'], 'etiqueta' => $p['etiqueta']], $plan['periodos']), JSON_UNESCAPED_UNICODE)) ?>'>
                            <div class="glass-card p-4 rounded-2xl border border-white/10 peer-checked:border-green-500 peer-checked:bg-green-500/10 transition-all">
                                <div class="flex items-start justify-between gap-4">
                                    <div>
                                        <p class="font-bold text-white"><?= h($plan['nombre']) ?></p>
                                        <p class="text-xs text-gray-400 mt-1"><?= h($plan['descripcion']) ?></p>
                                    </div>
                                    <p class="text-sm text-gray-400 whitespace-nowrap">
                                        desde <strong class="text-white"><?= money($plan['periodos']['mensual']['monto']) ?></strong>
                                    </p>
                                </div>
                                <p class="text-xs text-gray-500 mt-3">
                                    Implementación: <?= money($plan['implementacion']) ?> — pago único por transferencia
                                </p>
                            </div>
                        </label>
                    <?php endforeach; ?>
                </div>
            </div>

            <!-- Periodo -->
            <div>
                <label class="block text-sm font-bold text-gray-300 mb-3">Forma de pago</label>
                <div class="grid grid-cols-1 sm:grid-cols-3 gap-3" id="periodos">
                    <?php
                    $etiquetas = [
                        'mensual'   => ['Mensual', ''],
                        'semestral' => ['Semestral', '−15%'],
                        'anual'     => ['Anual', '−20%'],
                    ];
                    foreach ($etiquetas as $pk => [$titulo, $desc]): ?>
                        <label class="block cursor-pointer">
                            <input type="radio" name="periodo" value="<?= h($pk) ?>" class="peer sr-only"
                                <?= $pk === $periodoSel ? 'checked' : '' ?>>
                            <div class="glass-card p-4 rounded-2xl border border-white/10 peer-checked:border-green-500 peer-checked:bg-green-500/10 transition-all text-center">
                                <p class="font-bold text-white text-sm"><?= h($titulo) ?></p>
                                <?php if ($desc): ?><p class="text-xs text-green-400 mt-1"><?= h($desc) ?></p><?php endif; ?>
                                <p class="text-xs text-gray-400 mt-2" data-precio-de="<?= h($pk) ?>"></p>
                            </div>
                        </label>
                    <?php endforeach; ?>
                </div>
            </div>

            <!-- Datos -->
            <div class="space-y-4">
                <div>
                    <label for="negocio" class="block text-sm font-bold text-gray-300 mb-2">Nombre de tu negocio</label>
                    <input type="text" id="negocio" name="negocio" required maxlength="120"
                        class="w-full px-4 py-3 bg-white/5 border border-white/10 rounded-xl focus:border-green-500 focus:outline-none transition-colors"
                        placeholder="Clínica Dental Sonrisa">
                </div>
                <div>
                    <label for="email" class="block text-sm font-bold text-gray-300 mb-2">Correo electrónico</label>
                    <input type="email" id="email" name="email" required maxlength="160"
                        class="w-full px-4 py-3 bg-white/5 border border-white/10 rounded-xl focus:border-green-500 focus:outline-none transition-colors"
                        placeholder="tucorreo@ejemplo.com">
                    <p class="text-xs text-gray-500 mt-2">Aquí llegarán tus comprobantes de pago.</p>
                </div>
            </div>

            <!-- Resumen -->
            <div class="glass-card p-5 rounded-2xl border border-green-500/30 bg-green-500/5">
                <div class="flex items-baseline justify-between">
                    <span class="text-sm text-gray-300">Mensualidad recurrente</span>
                    <span class="text-2xl font-bold text-white" id="resumen-monto">—</span>
                </div>
                <p class="text-xs text-gray-400 mt-2" id="resumen-detalle">—</p>
            </div>

            <!-- Términos -->
            <label class="flex items-start gap-3 cursor-pointer">
                <input type="checkbox" id="terminos" required class="mt-1 w-4 h-4 accent-green-500">
                <span class="text-xs text-gray-400">
                    Acepto los
                    <a href="contrato-servicios.html" target="_blank" class="text-green-400 hover:text-green-300 underline">Términos y Condiciones</a>
                    y el
                    <a href="aviso-privacidad.html" target="_blank" class="text-green-400 hover:text-green-300 underline">Aviso de Privacidad</a>.
                    Entiendo que aquí solo autorizo la <strong class="text-gray-200">mensualidad recurrente</strong>, que la
                    implementación se paga aparte por transferencia bancaria, y que puedo cancelar la suscripción
                    cuando quiera.
                </span>
            </label>

            <button type="submit" id="btn-suscribir"
                class="w-full py-4 bg-gradient-to-r from-green-500 to-emerald-600 rounded-xl font-bold hover:shadow-lg hover:shadow-green-500/30 transition-all disabled:opacity-50 disabled:cursor-not-allowed">
                <span id="btn-texto">Continuar al pago seguro</span>
                <span id="btn-cargando" class="hidden"><i class="fas fa-circle-notch fa-spin mr-2"></i>Conectando con Mercado Pago…</span>
            </button>

            <p id="error" class="hidden text-sm text-red-400 text-center"></p>

            <p class="text-xs text-gray-500 text-center">
                <i class="fas fa-lock mr-1"></i> El pago lo procesa Mercado Pago. Guru Marketing no almacena los datos de tu tarjeta.
            </p>
        </form>

        <p class="text-center text-sm text-gray-500 mt-8">
            ¿Dudas antes de pagar?
            <a href="https://wa.me/523141461520?text=CONECTA" target="_blank" rel="noopener"
                class="text-green-400 hover:text-green-300">Escríbenos por WhatsApp</a>
        </p>
    </div>

    <script>
        const form = document.getElementById('form-suscripcion');
        const errorEl = document.getElementById('error');
        const btn = document.getElementById('btn-suscribir');

        function planActivo() {
            return document.querySelector('input[name="plan"]:checked');
        }
        function periodoActivo() {
            return document.querySelector('input[name="periodo"]:checked').value;
        }

        function actualizarResumen() {
            const precios = JSON.parse(planActivo().dataset.precios);
            // Precio dentro de cada tarjeta de periodo
            document.querySelectorAll('[data-precio-de]').forEach(el => {
                const p = precios[el.dataset.precioDe];
                el.textContent = p ? '$' + p.monto.toLocaleString('es-MX') : '';
            });
            // Resumen
            const p = precios[periodoActivo()];
            document.getElementById('resumen-monto').textContent = '$' + p.monto.toLocaleString('es-MX') + ' MXN';
            document.getElementById('resumen-detalle').textContent = p.etiqueta.charAt(0).toUpperCase() + p.etiqueta.slice(1) +
                ', de forma automática, hasta que canceles.';
        }

        form.addEventListener('change', actualizarResumen);
        actualizarResumen();

        form.addEventListener('submit', async (e) => {
            e.preventDefault();
            errorEl.classList.add('hidden');

            btn.disabled = true;
            document.getElementById('btn-texto').classList.add('hidden');
            document.getElementById('btn-cargando').classList.remove('hidden');

            try {
                const r = await fetch('mp-suscripcion.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({
                        plan: planActivo().value,
                        periodo: periodoActivo(),
                        email: document.getElementById('email').value.trim(),
                        negocio: document.getElementById('negocio').value.trim()
                    })
                });
                const data = await r.json();

                if (data.ok && data.init_point) {
                    window.location.href = data.init_point;
                    return;
                }
                throw new Error(data.error || 'No pudimos iniciar el pago.');
            } catch (err) {
                errorEl.textContent = err.message;
                errorEl.classList.remove('hidden');
                btn.disabled = false;
                document.getElementById('btn-texto').classList.remove('hidden');
                document.getElementById('btn-cargando').classList.add('hidden');
            }
        });
    </script>
</body>

</html>
