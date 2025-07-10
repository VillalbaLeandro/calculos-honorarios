<?php
require_once __DIR__ . '/includes/categorias.php';
require_once __DIR__ . '/includes/estados.php';
require_once __DIR__ . '/includes/ut.php';

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
            $cat = detectar_categoria(floatval($m2), $categorias);
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
    // Estado de obra
    $estado = null;
    foreach ($estados as $e) {
        if ($e['id'] == $estado_id) {
            $estado = $e;
            break;
        }
    }
    if (!$error && $cat && $estado) {
        $ut = $cat['ut'];
        $porc = floatval($estado['porcentaje']);
        $monto_base = $ut * $valor_ut;
        $recargo = $monto_base * ($porc / 100);
        $total = $monto_base + $recargo;
        $resultado = [
            'cat' => $cat,
            'estado' => $estado,
            'ut' => $ut,
            'valor_ut' => $valor_ut,
            'anio_valor_ut' => $anio_valor_ut,
            'porc' => $porc,
            'monto_base' => $monto_base,
            'recargo' => $recargo,
            'total' => $total,
            'modo' => $modo,
            'm2' => $m2
        ];
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Calculadora de Derechos - <?= $anio_valor_ut ?></title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <!-- Bootstrap 5 CDN -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        body { background: #f7faf8; }
        .bg-verde { background: #51b96a !important; color: #fff !important; }
        .borde-verde { border-left: 5px solid #51b96a; }
        .shadow-card { box-shadow: 0 4px 16px 0 #51b96a22; }
        .circle-bg { position: absolute; right: -60px; top: -60px; width: 200px; height: 200px;
            background: radial-gradient(circle, #51b96a66 0%, #f7faf8 80%); border-radius: 50%; z-index: 0;}
        .detalle-categoria { font-size: 0.97em; color: #368852; }
        .resultado-calc strong { color: #218838;}
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
                            <label class="form-label">Modo de cálculo:</label>
                            <div>
                                <div class="form-check form-check-inline">
                                    <input class="form-check-input" type="radio" name="modo" id="modo_m2" value="m2" <?= $modo=='m2'?'checked':'' ?> onchange="toggleModo()" >
                                    <label class="form-check-label" for="modo_m2">Ingresar metros cuadrados</label>
                                </div>
                                <div class="form-check form-check-inline">
                                    <input class="form-check-input" type="radio" name="modo" id="modo_cat" value="cat" <?= $modo=='cat'?'checked':'' ?> onchange="toggleModo()">
                                    <label class="form-check-label" for="modo_cat">Seleccionar categoría</label>
                                </div>
                            </div>
                        </div>
                        <div id="grupo-m2" class="mb-3" style="<?= $modo=='cat'?'display:none':'' ?>">
                            <label for="m2" class="form-label">Metros cuadrados (m²):</label>
                            <input type="number" step="0.01" min="0" class="form-control" id="m2" name="m2" value="<?= htmlspecialchars($m2) ?>">
                        </div>
                        <div id="grupo-cat" class="mb-3" style="<?= $modo=='cat'?'':'display:none' ?>">
                            <label for="categoria_id" class="form-label">Categoría:</label>
                            <select name="categoria_id" id="categoria_id" class="form-select">
                                <option value="">Seleccione categoría...</option>
                                <?php foreach ($categorias as $cat): ?>
                                    <option value="<?= $cat['id'] ?>" <?= ($cat['id']==$categoria_id?'selected':'') ?>>
                                        <?= descripcion_categoria($cat) ?> (<?= $cat['ut'] ?> UT)
                                        <?php if (!empty($cat['detalle'])): ?>
                                            - <?= htmlspecialchars($cat['detalle']) ?>
                                        <?php endif; ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                            <?php if ($modo=='cat' && $categoria_id): ?>
                                <?php foreach ($categorias as $cat): ?>
                                    <?php if ($cat['id'] == $categoria_id && !empty($cat['detalle'])): ?>
                                        <div class="detalle-categoria mt-1"><?= htmlspecialchars($cat['detalle']) ?></div>
                                    <?php endif; ?>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </div>
                        <div class="mb-3">
                            <label for="estado_id" class="form-label">Estado de la obra:</label>
                            <select name="estado_id" id="estado_id" class="form-select" required>
                                <option value="">Seleccione...</option>
                                <?php foreach ($estados as $e): ?>
                                    <option value="<?= $e['id'] ?>" <?= ($e['id']==$estado_id?'selected':'') ?>>
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
                    <?php if ($error): ?>
                        <div class="alert alert-danger borde-verde"><?= htmlspecialchars($error) ?></div>
                    <?php endif; ?>
                    <?php if ($resultado): ?>
                        <div class="card mt-4 shadow-card border-0 resultado-calc">
                            <div class="card-header bg-verde">Resultado</div>
                            <div class="card-body">
                                <div class="mb-2">
                                    <strong>Categoría aplicada:</strong>
                                    <span class="badge bg-success fs-6"><?= descripcion_categoria($resultado['cat']) ?></span>
                                    <?php if (!empty($resultado['cat']['detalle'])): ?>
                                        <span class="detalle-categoria ms-2"><?= htmlspecialchars($resultado['cat']['detalle']) ?></span>
                                    <?php endif; ?>
                                </div>
                                <?php if ($resultado['modo'] == 'm2'): ?>
                                    <div class="mb-2">
                                        <strong>Metros cuadrados ingresados:</strong> <?= htmlspecialchars($resultado['m2']) ?> m²
                                    </div>
                                <?php endif; ?>
                                <div class="mb-2">
                                    <strong>UT asignadas:</strong> <?= number_format($resultado['ut'], 2, ',', '.') ?>
                                </div>
                                <div class="mb-2">
                                    <strong>Estado de obra:</strong> <?= htmlspecialchars($resultado['estado']['descripcion']) ?> (<?= rtrim(rtrim(number_format($resultado['porc'], 2, ',', '.'), '0'), ',') ?>%)
                                </div>
                                <div class="mb-2">
                                    <strong>Detalle de cálculo:</strong><br>
                                    <?= number_format($resultado['ut'], 2, ',', '.') ?> UT × <?= $resultado['valor_ut'] ?> = <strong>$<?= number_format($resultado['monto_base'], 2, ',', '.') ?></strong><br>
                                    Recargo <?= rtrim(rtrim(number_format($resultado['porc'], 2, ',', '.'), '0'), ',') ?>% = <strong>$<?= number_format($resultado['recargo'], 2, ',', '.') ?></strong><br>
                                    <span class="fw-bold text-success fs-5">
                                        Total: $<?= number_format($resultado['total'], 2, ',', '.') ?>
                                    </span>
                                </div>
                            </div>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
            <footer class="mt-4 text-center text-muted">
                <small>&copy; <?= date('Y') ?> - Municipalidad de Posadas | Cálculo orientativo. Verifique la normativa vigente.</small>
                <!-- <button class="btn"><a href="./admin.php">ir a admin</a></button> -->
            </footer>
        </div>
    </div>
</div>
<!-- Bootstrap icons (opcional, para íconos lindos) -->
<link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">
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
