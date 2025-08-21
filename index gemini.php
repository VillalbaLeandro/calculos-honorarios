<?php
require_once __DIR__ . '/includes/categorias.php';
require_once __DIR__ . '/includes/estados.php';
require_once __DIR__ . '/includes/ut.php';

/**
 * Utilitario: buscar estado por id en arreglo
 */
function buscar_estado_por_id($estados, $id)
{
    foreach ($estados as $e) {
        if ($e['id'] == $id) return $e;
    }
    return null;
}

// Cargar datos desde los archivos JSON
$categorias = get_categorias();
$estados    = get_estados();
$ut_json    = get_ut();
$valor_ut   = $ut_json['valor_ut'];
$anio_valor_ut = $ut_json['anio_valor_ut'];

// --- Entrada del formulario ---
// Estructura esperada:
// $_POST['sections'] = [
//   ['titulo' => 'Sección 1', 'm2' => '60', 'estado_id' => '1'],
//   ['titulo' => 'Balcones',  'm2' => '30', 'estado_id' => '3'],
// ]
$sections_post = $_POST['sections'] ?? [];

// Para mantener valores tras submit fallido, normalizamos a array de arrays
$sections_input = [];
if (is_array($sections_post)) {
    foreach ($sections_post as $sec) {
        if (!is_array($sec)) continue;
        $sections_input[] = [
            'titulo'    => trim($sec['titulo'] ?? ''),
            'm2'        => $sec['m2'] ?? '',
            'estado_id' => $sec['estado_id'] ?? '',
        ];
    }
}

// Si no hay submit, mostramos una sección inicial vacía
if ($_SERVER['REQUEST_METHOD'] !== 'POST' && empty($sections_input)) {
    $sections_input[] = ['titulo' => '', 'm2' => '', 'estado_id' => ''];
}

$errores_generales = [];
$errores_por_seccion = []; // idx => mensaje
$resultado_general = null; // se llena con ['secciones' => [...], 'total_general' => number]

// --- Procesamiento ---
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (empty($sections_input)) {
        $errores_generales[] = "Agregue al menos una sección para calcular.";
    } else {
        $resultados = [];
        $total_general = 0.0;

        foreach ($sections_input as $idx => $sec) {
            $m2_raw = $sec['m2'];
            $estado_id = $sec['estado_id'];

            if (!is_numeric($m2_raw) || floatval($m2_raw) <= 0) {
                $errores_por_seccion[$idx] = "Ingrese m² válidos (mayor a 0).";
                continue;
            }
            if (empty($estado_id)) {
                $errores_por_seccion[$idx] = "Seleccione un estado de obra.";
                continue;
            }

            $m2 = floatval($m2_raw);
            // Detectar categoría por m² (rango)
            $cat = detectar_categoria_por_m2($m2, $categorias);
            if (!$cat) {
                $errores_por_seccion[$idx] = "No hay categoría configurada para {$m2} m².";
                continue;
            }

            // Buscar estado por ID
            $estado = buscar_estado_por_id($estados, $estado_id);
            if (!$estado) {
                $errores_por_seccion[$idx] = "Estado de obra no encontrado.";
                continue;
            }

            // Cálculo por sección (fórmula corregida)
            // base_m2_ut = valor_m2_categoria × UT
            // factor     = m² sección × base_m2_ut
            // total_sec  = factor × (porcentaje_estado / 100)
            $valor_m2 = floatval($cat['valor_m2']);
            $porc     = floatval($estado['porcentaje']);

            $base_m2_ut = $valor_m2 * $valor_ut;
            $factor     = $m2 * $base_m2_ut;
            $total_sec  = $factor * ($porc / 100);

            $total_general += $total_sec;

            $resultados[] = [
                'titulo'        => $sec['titulo'],
                'm2'            => $m2,
                'cat'           => $cat,
                'estado'        => $estado,
                'valor_m2'      => $valor_m2,
                'valor_ut'      => $valor_ut,
                'anio_valor_ut' => $anio_valor_ut,
                'porc'          => $porc,
                'base_m2_ut'    => $base_m2_ut,
                'factor'        => $factor,
                'total'         => $total_sec
            ];
        }

        if (empty($errores_generales) && empty($errores_por_seccion)) {
            $resultado_general = [
                'secciones'     => $resultados,
                'total_general' => $total_general
            ];
        }
    }
}
?>
<!DOCTYPE html>
<html lang="es">

<head>
    <meta charset="UTF-8">
    <title>Calculadora de Derechos - <?= htmlspecialchars($anio_valor_ut) ?></title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">
    <style>
        body {
            background: #f7faf8;
        }

        .bg-verde {
            background: #51b96a !important;
            color: #fff !important;
        }

        .borde-verde {
            border-left: 5px solid #51b96a;
        }

        .shadow-card {
            box-shadow: 0 4px 16px 0 #51b96a22;
        }

        .circle-bg {
            position: absolute;
            right: -60px;
            top: -60px;
            width: 200px;
            height: 200px;
            background: radial-gradient(circle, #51b96a66 0%, #f7faf8 80%);
            border-radius: 50%;
            z-index: 0;
        }

        .detalle-categoria {
            font-size: 0.97em;
            color: #368852;
        }

        .resultado-calc strong {
            color: #218838;
        }

        .seccion-card {
            border-left: 4px solid #51b96a22;
        }

        .btn-outline-danger:hover {
            color: #fff !important;
        }

        @keyframes highlight-result {
            from {
                background-color: #e9f5ec;
                transform: scale(1.01);
            }

            to {
                background-color: #f8f9fa;
                transform: scale(1);
            }
        }

        .result-updated {
            animation: highlight-result 1.2s ease-out;
        }
    </style>
</head>

<body>
    <div class="container py-4">
        <div class="row justify-content-center">
            <div class="col-md-9 position-relative">
                <div class="circle-bg"></div>
                <div class="card shadow-card border-0">
                    <div class="card-header bg-verde fs-5">
                        <i class="bi bi-calculator"></i> Calculadora de Derechos Municipales <?= htmlspecialchars($anio_valor_ut) ?>
                    </div>
                    <div class="card-body">
                        <div class="mb-3">
                            <strong>Valor de la UT correspondiente al año (<?= htmlspecialchars($anio_valor_ut) ?>): <?= htmlspecialchars($valor_ut) ?></strong>
                        </div>

                        <?php if (!empty($errores_generales)): ?>
                            <?php foreach ($errores_generales as $msg): ?>
                                <div class="alert alert-danger borde-verde"><?= htmlspecialchars($msg) ?></div>
                            <?php endforeach; ?>
                        <?php endif; ?>

                        <form method="post" autocomplete="off" class="mb-3" id="calc-form">

                            <div id="secciones-container" class="d-grid gap-3">
                                <?php foreach ($sections_input as $i => $sec): ?>
                                    <div class="card seccion-card border-0" data-index="<?= $i ?>">
                                        <div class="card-body">
                                            <div class="d-flex justify-content-between align-items-center mb-2">
                                                <h6 class="mb-0">
                                                    <i class="bi bi-layers-fill text-success me-2"></i>
                                                    <?= htmlspecialchars($sec['titulo'] !== '' ? $sec['titulo'] : 'Sección ' . ($i + 1)) ?>
                                                </h6>
                                                <?php if (count($sections_input) > 1): ?>
                                                    <button type="button" class="btn btn-sm btn-outline-danger" onclick="eliminarSeccion(this)">
                                                        <i class="bi bi-x-circle"></i> Eliminar
                                                    </button>
                                                <?php endif; ?>
                                            </div>

                                            <?php if (isset($errores_por_seccion[$i])): ?>
                                                <div class="alert alert-warning py-2 mb-3">
                                                    <i class="bi bi-exclamation-triangle me-1"></i>
                                                    <?= htmlspecialchars($errores_por_seccion[$i]) ?>
                                                </div>
                                            <?php endif; ?>

                                            <div class="row g-3 align-items-end">
                                                <div class="col-md-4">
                                                    <label class="form-label">Nombre de la sección (opcional)</label>
                                                    <input type="text" class="form-control" name="sections[<?= $i ?>][titulo]" value="<?= htmlspecialchars($sec['titulo']) ?>" placeholder="Ej.: Obra nueva, Balcones, etc.">
                                                </div>
                                                <div class="col-md-4">
                                                    <label class="form-label">Metros cuadrados (m²)</label>
                                                    <input type="number" step="0.01" min="0" class="form-control" name="sections[<?= $i ?>][m2]" value="<?= htmlspecialchars($sec['m2']) ?>" required>
                                                </div>
                                                <div class="col-md-4">
                                                    <label class="form-label">Estado de la obra</label>
                                                    <select class="form-select" name="sections[<?= $i ?>][estado_id]" required>
                                                        <option value="">Seleccione...</option>
                                                        <?php foreach ($estados as $e): ?>
                                                            <option value="<?= $e['id'] ?>" <?= ($e['id'] == ($sec['estado_id'] ?? '') ? 'selected' : '') ?>>
                                                                <?= htmlspecialchars($e['descripcion']) ?> (<?= rtrim(rtrim(number_format($e['porcentaje'], 2, ',', '.'), '0'), ',') ?>%)
                                                            </option>
                                                        <?php endforeach; ?>
                                                    </select>
                                                </div>
                                            </div>

                                            <?php
                                            // Feedback de categoría detectada si hay datos válidos (post)
                                            $cat_feedback = null;
                                            if (
                                                $_SERVER['REQUEST_METHOD'] === 'POST'
                                                && is_numeric($sec['m2']) && floatval($sec['m2']) > 0
                                            ) {
                                                $cat_tmp = detectar_categoria_por_m2(floatval($sec['m2']), $categorias);
                                                if ($cat_tmp) {
                                                    $cat_feedback = 'Categoría detectada: ' . descripcion_categoria_por_rango($cat_tmp)
                                                        . ' — Valor m²: ' . number_format(floatval($cat_tmp['valor_m2']), 2, ',', '.');
                                                }
                                            }
                                            ?>
                                            <?php if ($cat_feedback): ?>
                                                <div class="detalle-categoria mt-2"><?= htmlspecialchars($cat_feedback) ?></div>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>

                            <div class="mt-3 d-flex gap-2">
                                <button type="button" class="btn btn-outline-success" onclick="agregarSeccion()">
                                    <i class="bi bi-plus-circle"></i> Agregar sección
                                </button>
                                <button type="submit" class="btn bg-verde px-4">
                                    Calcular total
                                </button>
                                <button type="button" class="btn btn-outline-secondary" onclick="limpiarFormulario()">
                                    Limpiar
                                </button>
                            </div>
                        </form>

                        <?php if ($resultado_general): ?>
                            <div id="bloque-resultado" class="mt-4 p-4 rounded-3 <?php if ($resultado_general) echo 'result-updated'; ?>" style="background-color: #f8f9fa;">
                                <div class="text-center mb-4">
                                    <h3 class="text-muted fw-light">Total General</h3>
                                    <p class="display-5 fw-bold text-success mb-0">
                                        $<?= number_format($resultado_general['total_general'], 2, ',', '.') ?>
                                    </p>
                                    <span class="text-muted small">Suma de todas las secciones</span>
                                </div>

                                <?php foreach ($resultado_general['secciones'] as $idx => $res): ?>
                                    <div class="card shadow-sm border-0 mb-3">
                                        <div class="card-body">
                                            <div class="row text-center">
                                                <div class="col-md-3">
                                                    <small class="text-muted d-block">Sección</small>
                                                    <strong class="fs-6">
                                                        <?= htmlspecialchars($res['titulo'] !== '' ? $res['titulo'] : 'Sección ' . ($idx + 1)) ?>
                                                    </strong>
                                                </div>
                                                <div class="col-md-3">
                                                    <small class="text-muted d-block">Superficie</small>
                                                    <strong class="fs-6"><?= htmlspecialchars($res['m2']) ?> m²</strong>
                                                </div>
                                                <div class="col-md-3">
                                                    <small class="text-muted d-block">Categoría</small>
                                                    <span class="badge bg-success fs-6"><?= descripcion_categoria_por_rango($res['cat']) ?></span>
                                                </div>
                                                <div class="col-md-3">
                                                    <small class="text-muted d-block">Estado de Obra</small>
                                                    <strong class="fs-6"><?= htmlspecialchars($res['estado']['descripcion']) ?></strong>
                                                </div>
                                            </div>
                                            <hr>
                                            <div class="d-flex justify-content-between align-items-center">
                                                <div class="text-muted">
                                                    Subtotal de la sección
                                                </div>
                                                <div class="fs-5 fw-bold text-success">
                                                    $<?= number_format($res['total'], 2, ',', '.') ?>
                                                </div>
                                            </div>

                                            <div class="accordion mt-3" id="accordionDetalle_<?= $idx ?>">
                                                <div class="accordion-item border-0">
                                                    <h2 class="accordion-header" id="heading_<?= $idx ?>">
                                                        <button class="accordion-button collapsed bg-transparent shadow-none"
                                                            type="button" data-bs-toggle="collapse"
                                                            data-bs-target="#collapseDetalle_<?= $idx ?>"
                                                            aria-expanded="false" aria-controls="collapseDetalle_<?= $idx ?>">
                                                            <i class="bi bi-plus-circle me-2"></i>
                                                            Ver detalle del cálculo
                                                        </button>
                                                    </h2>
                                                    <div id="collapseDetalle_<?= $idx ?>" class="accordion-collapse collapse"
                                                        aria-labelledby="heading_<?= $idx ?>" data-bs-parent="#accordionDetalle_<?= $idx ?>">
                                                        <div class="accordion-body">
                                                            <ul class="list-group list-group-flush">
                                                                <li class="list-group-item d-flex justify-content-between align-items-center">
                                                                    <span><i class="bi bi-rulers text-muted me-2"></i>Valor m² de la categoría</span>
                                                                    <strong class="text-end"><?= number_format($res['valor_m2'], 2, ',', '.') ?></strong>
                                                                </li>
                                                                <li class="list-group-item d-flex justify-content-between align-items-center">
                                                                    <span><i class="bi bi-calendar-check text-muted me-2"></i>UT vigente (<?= $res['anio_valor_ut'] ?>)</span>
                                                                    <strong class="text-end"><?= number_format($res['valor_ut'], 2, ',', '.') ?></strong>
                                                                </li>
                                                                <li class="list-group-item d-flex justify-content-between align-items-center">
                                                                    <span><i class="bi bi-arrow-right-short text-muted me-2"></i>Monto (valor m² × UT)</span>
                                                                    <strong class="text-end">$<?= number_format($res['base_m2_ut'], 2, ',', '.') ?></strong>
                                                                </li>
                                                                <li class="list-group-item d-flex justify-content-between align-items-center">
                                                                    <span><i class="bi bi-calculator text-muted me-2"></i>Subtotal (monto × superficie)</span>
                                                                    <strong class="text-end">$<?= number_format($res['factor'], 2, ',', '.') ?></strong>
                                                                </li>
                                                                <li class="list-group-item d-flex justify-content-between align-items-center">
                                                                    <span><i class="bi bi-percent text-muted me-2"></i>Aplicación por estado de obra (<?= rtrim(rtrim(number_format($res['porc'], 2, ',', '.'), '0'), ',') ?>%)</span>
                                                                    <strong class="text-end text-success">$<?= number_format($res['total'], 2, ',', '.') ?></strong>
                                                                </li>
                                                            </ul>
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>

                                        </div>
                                    </div>
                                <?php endforeach; ?>

                                <p class="small text-muted text-center mt-3">
                                    <i class="bi bi-info-circle me-1"></i>
                                    El presente cálculo es estimativo, no incluye costos menores referidos a los Tributos 231 (Inspecciones y demoliciones) y Tributo 233 (Derechos de Oficina).
                                </p>
                            </div>
                        <?php endif; ?>

                    </div>
                </div>

                <footer class="mt-4 text-center text-muted">
                    <small>&copy; <?= date('Y') ?> - Municipalidad de Posadas | Cálculo orientativo. Verifique la normativa vigente.</small>
                    <div class="mt-2">
                        <button type="button" class="btn btn-sm btn-outline-success" onclick="goToAdmin()">
                            <i class="bi bi-pencil-square"></i> Administrar
                        </button>
                    </div>
                </footer>
            </div>
        </div>
    </div>

    <!-- Opciones de Estado para clonar dinámicamente -->
    <div id="estadoOptions" class="d-none">
        <option value="">Seleccione...</option>
        <?php foreach ($estados as $e): ?>
            <option value="<?= $e['id'] ?>">
                <?= htmlspecialchars($e['descripcion']) ?> (<?= rtrim(rtrim(number_format($e['porcentaje'], 2, ',', '.'), '0'), ',') ?>%)
            </option>
        <?php endforeach; ?>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>

    <script>
        // --- Utilidades de UI ---
        function limpiarFormulario() {
            window.location = window.location.pathname;
        }

        function escapeHtml(text) {
            const map = {
                '&': '&amp;',
                '<': '&lt;',
                '>': '&gt;',
                '"': '&quot;',
                "'": '&#039;'
            };
            return String(text).replace(/[&<>"']/g, m => map[m]);
        }

        /**
         * Actualiza los títulos de las secciones y la visibilidad de los botones de eliminar.
         */
        function actualizarUISecciones() {
            const cards = document.querySelectorAll('#secciones-container .seccion-card');
            const showDeleteButton = cards.length > 1;

            cards.forEach((card, idx) => {
                // Actualizar título
                const h6 = card.querySelector('h6');
                const tituloInput = card.querySelector('input[name*="[titulo]"]');
                const display = (tituloInput && tituloInput.value.trim() !== '') ? tituloInput.value.trim() : `Sección ${idx + 1}`;
                if (h6) h6.innerHTML = `<i class="bi bi-layers-fill text-success me-2"></i>${escapeHtml(display)}`;

                // Actualizar visibilidad del botón de eliminar
                const btn = card.querySelector('button[onclick^="eliminarSeccion"]');
                if (btn) {
                    btn.style.display = showDeleteButton ? '' : 'none';
                }
            });
        }

        // --- Lógica de cálculo y formulario ---

        function hayResultadoMostrado() {
            return !!document.getElementById('bloque-resultado');
        }

        /**
         * Envía el formulario para recalcular si ya se ha mostrado un resultado.
         * Muestra feedback visual al usuario durante el proceso.
         */
        function recomputarSiCorresponde() {
            if (hayResultadoMostrado()) {
                const form = document.getElementById('calc-form');
                const btnCalcular = form.querySelector('button[type="submit"]');
                const resultado = document.getElementById('bloque-resultado');

                if (btnCalcular) {
                    btnCalcular.disabled = true;
                    btnCalcular.innerHTML = '<i class="bi bi-arrow-repeat"></i> Actualizando...';
                }
                if (resultado) {
                    resultado.style.opacity = '0.6';
                }

                if (form) form.requestSubmit();
            }
        }

        let debounceTimer = null;
        /**
         * Envía el formulario para recalcular con un pequeño retardo para no sobrecargar.
         */
        function autoSubmitDebounced() {
            clearTimeout(debounceTimer);
            debounceTimer = setTimeout(() => {
                recomputarSiCorresponde();
            }, 400); // 400ms de espera
        }

        function seccionEsValida(card) {
            const m2Input = card.querySelector('input[name*="[m2]"]');
            const estadoSelect = card.querySelector('select[name*="[estado_id]"]');
            const m2valido = m2Input && m2Input.value !== '' && !isNaN(m2Input.value) && parseFloat(m2Input.value) > 0;
            const estadovalido = estadoSelect && estadoSelect.value !== '';
            return m2valido && estadovalido;
        }

        function enlazarAutoCalculo(card) {
            const inputs = card.querySelectorAll('input, select');
            inputs.forEach(el => {
                el.addEventListener('input', () => {
                    actualizarUISecciones(); // Actualiza el título mientras se escribe
                    if (seccionEsValida(card)) {
                        autoSubmitDebounced();
                    }
                });
                el.addEventListener('change', () => { // Para selects y cuando se pierde el foco
                    if (seccionEsValida(card)) {
                        recomputarSiCorresponde();
                    }
                });
            });
        }

        // --- Acciones de los botones ---

        function eliminarSeccion(btn) {
            const card = btn.closest('.seccion-card');
            card.remove();
            actualizarUISecciones();
            recomputarSiCorresponde();
        }

        function agregarSeccion() {
            const container = document.getElementById('secciones-container');
            const nextIndex = Date.now(); // Usar timestamp para un índice único y simple

            const card = document.createElement('div');
            card.className = 'card seccion-card border-0';
            card.setAttribute('data-index', String(nextIndex));

            card.innerHTML = `
            <div class="card-body">
                <div class="d-flex justify-content-between align-items-center mb-2">
                    <h6 class="mb-0"></h6>
                    <button type="button" class="btn btn-sm btn-outline-danger" onclick="eliminarSeccion(this)">
                        <i class="bi bi-x-circle"></i> Eliminar
                    </button>
                </div>
                <div class="row g-3 align-items-end">
                    <div class="col-md-4">
                        <label class="form-label">Nombre de la sección (opcional)</label>
                        <input type="text" class="form-control" name="sections[${nextIndex}][titulo]" placeholder="Ej.: Obra nueva, Balcones, etc.">
                    </div>
                    <div class="col-md-4">
                        <label class="form-label">Metros cuadrados (m²)</label>
                        <input type="number" step="0.01" min="0" class="form-control" name="sections[${nextIndex}][m2]" required>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label">Estado de la obra</label>
                        <select class="form-select" name="sections[${nextIndex}][estado_id]" required></select>
                    </div>
                </div>
            </div>`;

            const select = card.querySelector('select');
            select.innerHTML = document.getElementById('estadoOptions').innerHTML;

            container.appendChild(card);
            enlazarAutoCalculo(card);
            actualizarUISecciones();

            // ¡Corrección clave! Recalcula si ya había un resultado previo.
            // El backend ignorará la nueva sección vacía, pero procesará las que ya estaban.
            recomputarSiCorresponde();
        }

        // --- Admin ---
        async function goToAdmin() {
            const password = prompt("Ingrese la contraseña para acceder al panel de administración:", "");
            if (password === null) return;
            try {
                const resp = await fetch(`${location.origin}/check_password.php`, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json'
                    },
                    credentials: 'same-origin',
                    body: JSON.stringify({
                        password
                    })
                });
                if (!resp.ok) throw new Error(`HTTP ${resp.status}`);
                const result = await resp.json();
                if (result && result.success) {
                    window.location.href = `${location.origin}/admin.php`;
                } else {
                    alert(result?.message || 'Acceso denegado.');
                }
            } catch (err) {
                console.error('Error al verificar la contraseña:', err);
                alert('Ocurrió un error inesperado.');
            }
        }

        // --- Inicialización ---
        document.addEventListener('DOMContentLoaded', () => {
            document.querySelectorAll('#secciones-container .seccion-card').forEach(card => {
                enlazarAutoCalculo(card);
            });
            actualizarUISecciones();
        });
    </script>

</body>

</html>