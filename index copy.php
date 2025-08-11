<?php
require_once __DIR__ . '/includes/categorias.php';
require_once __DIR__ . '/includes/estados.php';
require_once __DIR__ . '/includes/ut.php';

// Cargar datos desde los archivos JSON
$categorias = get_categorias();
$estados = get_estados();
$ut_json = get_ut();
$valor_ut = $ut_json['valor_ut'];
$anio_valor_ut = $ut_json['anio_valor_ut'];

// Valores del formulario
$modo = $_POST['modo'] ?? 'm2';
$m2 = $_POST['m2'] ?? '';
$categoria_id = $_POST['categoria_id'] ?? '';
$estado_id = $_POST['estado_id'] ?? '';
$resultado = null;
$error = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if ($modo === 'm2') {
        if (!is_numeric($m2) || $m2 <= 0) {
            $error = "Por favor, ingrese los metros cuadrados correctamente.";
        } elseif (empty($estado_id)) {
            $error = "Debe seleccionar un estado de obra.";
        } else {
            // CAMBIO AQUÍ: Se reemplaza 'detectar_categoria' por 'detectar_categoria_por_m2'
            $cat = detectar_categoria_por_m2(floatval($m2), $categorias);
            if (!$cat) {
                $error = "No hay una categoría configurada para los m² ingresados.";
            }
        }
    } else { // modo categoría
        if (empty($categoria_id)) {
            $error = "Seleccione una categoría.";
        } elseif (empty($estado_id)) {
            $error = "Debe seleccionar un estado de obra.";
        } else {
            $cat = null;
            foreach ($categorias as $c) {
                if ($c['id'] == $categoria_id) {
                    $cat = $c;
                    break;
                }
            }
            if (!$cat) {
                $error = "Categoría no encontrada.";
            }
        }
    }
    
    // Si no hay errores, se procede con los cálculos
    if (!$error && isset($cat) && isset($estado_id) && !empty($estado_id)) {
        // Buscar el estado de obra por ID
        $estado = null;
        foreach ($estados as $e) {
            if ($e['id'] == $estado_id) {
                $estado = $e;
                break;
            }
        }

        if ($estado) {
            $valor_m2 = floatval($cat['valor_m2']);
            $porc     = floatval($estado['porcentaje']);

            // Fórmula original, no modificada
            // Total = valor_m2 × (valor_m2 × valor_ut) × (porcentaje / 100)
            $base_m2_ut = $valor_m2 * $valor_ut;
            $factor     = $valor_m2 * $base_m2_ut;
            $total      = $factor * ($porc / 100);

            $resultado = [
                'cat'           => $cat,
                'estado'        => $estado,
                'valor_m2'      => $valor_m2,
                'valor_ut'      => $valor_ut,
                'anio_valor_ut' => $anio_valor_ut,
                'porc'          => $porc,
                'base_m2_ut'    => $base_m2_ut,
                'factor'        => $factor,
                'total'         => $total,
                'modo'          => $modo,
                'm2'            => $m2
            ];
        } else {
            $error = "Estado de obra no encontrado.";
        }
    }
}
?>
<!DOCTYPE html>
<html lang="es">

<head>
    <meta charset="UTF-8">
    <title>Calculadora de Derechos - <?= $anio_valor_ut ?></title>
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
    </style>
</head>

<body>
    <div class="container py-4">
        <div class="row justify-content-center">
            <div class="col-md-7 position-relative">
                <div class="circle-bg"></div>
                <div class="card shadow-card border-0">
                    <div class="card-header bg-verde fs-5">
                        <i class="bi bi-calculator"></i> Calculadora de Derechos Municipales <?= $anio_valor_ut ?>
                    </div>
                    <div class="card-body">
                        <div class="mb-3">
                            <strong>Valor de la UT correspondiente al año (<?= $anio_valor_ut ?>): <?= $valor_ut ?></strong>
                        </div>
                        <form method="post" autocomplete="off" class="mb-3" id="calc-form">
                            <div class="mb-3">
                                <div class="form-check form-check-inline">
                                    <input class="form-check-input" type="radio" name="modo" id="modo-m2" value="m2" onchange="toggleModo()" <?= $modo == 'm2' ? 'checked' : '' ?>>
                                    <label class="form-check-label" for="modo-m2">Calcular por m²</label>
                                </div>
                                <div class="form-check form-check-inline">
                                    <input class="form-check-input" type="radio" name="modo" id="modo-cat" value="cat" onchange="toggleModo()" <?= $modo == 'cat' ? 'checked' : '' ?>>
                                    <label class="form-check-label" for="modo-cat">Seleccionar categoría</label>
                                </div>
                            </div>
                            <div id="grupo-m2" class="mb-3" style="<?= $modo == 'cat' ? 'display:none' : '' ?>">
                                <label for="m2" class="form-label">Metros cuadrados (m²):</label>
                                <input type="number" step="0.01" min="0" class="form-control" id="m2" name="m2" value="<?= htmlspecialchars($m2) ?>">
                            </div>
                            <div id="grupo-cat" class="mb-3" style="<?= $modo == 'cat' ? '' : 'display:none' ?>">
                                <label for="categoria_id" class="form-label">Categoría:</label>
                                <select name="categoria_id" id="categoria_id" class="form-select">
                                    <option value="">Seleccione categoría...</option>
                                    <?php foreach ($categorias as $cat) : ?>
                                        <option value="<?= $cat['id'] ?>" <?= ($cat['id'] == $categoria_id ? 'selected' : '') ?>>
                                            <?= descripcion_categoria_por_rango($cat) ?> (<?= $cat['valor_m2'] ?> valor m²)
                                            <?php if (!empty($cat['detalle'])) : ?> - <?= htmlspecialchars($cat['detalle']) ?> <?php endif; ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                                <?php if ($modo == 'cat' && $categoria_id) : ?>
                                    <?php foreach ($categorias as $cat) : ?>
                                        <?php if ($cat['id'] == $categoria_id && !empty($cat['detalle'])) : ?>
                                            <div class="detalle-categoria mt-1"><?= htmlspecialchars($cat['detalle']) ?></div>
                                        <?php endif; ?>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </div>
                            <div class="mb-3">
                                <label for="estado_id" class="form-label">Estado de la obra:</label>
                                <select name="estado_id" id="estado_id" class="form-select" required>
                                    <option value="">Seleccione...</option>
                                    <?php foreach ($estados as $e) : ?>
                                        <option value="<?= $e['id'] ?>" <?= ($e['id'] == $estado_id ? 'selected' : '') ?>>
                                            <?= htmlspecialchars($e['descripcion']) ?> (<?= rtrim(rtrim(number_format($e['porcentaje'], 2, ',', '.'), '0'), ',') ?>%)
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <button type="submit" class="btn bg-verde px-4">
                                Calcular
                            </button>
                            <button type="button" class="btn btn-outline-secondary ms-2" onclick="limpiarFormulario()">
                                Limpiar
                            </button>
                        </form>
                        <?php if ($error) : ?>
                            <div class="alert alert-danger borde-verde"><?= htmlspecialchars($error) ?></div>
                        <?php endif; ?>
                        
                        <?php if ($resultado) : ?>
                            <div id="bloque-resultado" class="mt-4 p-4 rounded-3" style="background-color: #f8f9fa;">

                                <div class="text-center mb-4">
                                    <h3 class="text-muted fw-light">Total Estimado</h3>
                                    <p class="display-5 fw-bold text-success mb-0">
                                        $<?= number_format($resultado['total'], 2, ',', '.') ?>
                                    </p>
                                </div>

                                <div class="card shadow-sm border-0 mb-3">
                                    <div class="card-body">
                                        <div class="row text-center">
                                            <div class="col-md-4">
                                                <small class="text-muted d-block">Categoría</small>
                                                <span class="badge bg-success fs-6">
                                                    <?= descripcion_categoria_por_rango($resultado['cat']) ?>
                                                </span>
                                            </div>
                                            <?php if ($resultado['modo'] == 'm2') : ?>
                                                <div class="col-md-4">
                                                    <small class="text-muted d-block">Superficie</small>
                                                    <strong class="fs-6"><?= htmlspecialchars($resultado['m2']) ?> m²</strong>
                                                </div>
                                            <?php endif; ?>
                                            <div class="col-md-4">
                                                <small class="text-muted d-block">Estado de Obra</small>
                                                <strong class="fs-6"><?= htmlspecialchars($resultado['estado']['descripcion']) ?></strong>
                                            </div>
                                        </div>
                                    </div>
                                </div>

                                <div class="accordion" id="accordionDetalle">
                                    <div class="accordion-item border-0">
                                        <h2 class="accordion-header" id="headingOne">
                                            <button class="accordion-button collapsed bg-transparent shadow-none" type="button" data-bs-toggle="collapse" data-bs-target="#collapseDetalle" aria-expanded="false" aria-controls="collapseDetalle">
                                                <i class="bi bi-plus-circle me-2"></i> Ver detalle del cálculo
                                            </button>
                                        </h2>
                                        <div id="collapseDetalle" class="accordion-collapse collapse" aria-labelledby="headingOne" data-bs-parent="#accordionDetalle">
                                            <div class="accordion-body">

                                                <ul class="list-group list-group-flush">
                                                    <li class="list-group-item d-flex justify-content-between align-items-center">
                                                        <span><i class="bi bi-rulers text-muted me-2"></i>Valor m² de la categoría</span>
                                                        <strong class="text-end"><?= number_format($valor_m2, 2, ',', '.') ?></strong>
                                                    </li>
                                                    <li class="list-group-item d-flex justify-content-between align-items-center">
                                                        <span><i class="bi bi-calendar-check text-muted me-2"></i>UT vigente (<?= $resultado['anio_valor_ut'] ?>)</span>
                                                        <strong class="text-end"><?= number_format($valor_ut, 2, ',', '.') ?></strong>
                                                    </li>
                                                    <li class="list-group-item d-flex justify-content-between align-items-center">
                                                        <span><i class="bi bi-arrow-right-short text-muted me-2"></i>Monto (valor m² × UT)</span>
                                                        <strong class="text-end">$<?= number_format($base_m2_ut, 2, ',', '.') ?></strong>
                                                    </li>
                                                    <li class="list-group-item d-flex justify-content-between align-items-center">
                                                        <span><i class="bi bi-calculator text-muted me-2"></i>Subtotal (monto × superficie)</span>
                                                        <strong class="text-end">$<?= number_format($factor, 2, ',', '.') ?></strong>
                                                    </li>
                                                    <li class="list-group-item d-flex justify-content-between align-items-center">
                                                        <span><i class="bi bi-percent text-muted me-2"></i>Aplicación por estado de obra(<?= rtrim(rtrim(number_format($porc, 2, ',', '.'), '0'), ',') ?>%)</span>
                                                        <strong class="text-end text-success">$<?= number_format($resultado['total'], 2, ',', '.') ?></strong>
                                                    </li>
                                                </ul>

                                            </div>
                                        </div>
                                    </div>
                                </div>

                                <p class="small text-muted text-center mt-3">
                                    <i class="bi bi-info-circle me-1"></i> El presente cálculo es estimativo; no incluye costos menores referidos a los Tributos 231 y 233.
                                </p>

                            </div>
                        <?php endif; ?>

                    </div>
                </div>
                <footer class="mt-4 text-center text-muted">
                    <small>&copy; <?= date('Y') ?> - Municipalidad de Posadas | Cálculo orientativo. Verifique la normativa vigente.</small>
                    </footer>
            </div>
        </div>
    </div>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>

    <script>
        function toggleModo() {
            const modo = document.querySelector('input[name="modo"]:checked').value;
            document.getElementById('grupo-m2').style.display = (modo === 'm2') ? '' : 'none';
            document.getElementById('grupo-cat').style.display = (modo === 'cat') ? '' : 'none';
        }

        function limpiarFormulario() {
            window.location = window.location.pathname;
        }
        document.addEventListener("DOMContentLoaded", function() {
            toggleModo();
        });
    </script>
</body>

</html>