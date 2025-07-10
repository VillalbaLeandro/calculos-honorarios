<?php
// ----------- VALOR UT / AÑO -----------
$valor_ut_json_path = __DIR__ . '/data/valor_ut.json';
$ut_data = json_decode(file_get_contents($valor_ut_json_path), true);
$valor_ut = $ut_data['valor_ut'];
$anio_valor_ut = $ut_data['anio_valor_ut'];
$msg = $error = "";

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['accion']) && $_POST['accion'] === 'guardar_ut') {
    $nuevo_valor_ut = floatval($_POST['valor_ut'] ?? 0);
    $nuevo_anio = intval($_POST['anio_valor_ut'] ?? 0);
    if ($nuevo_valor_ut <= 0 || $nuevo_anio <= 2000) {
        $error = "Datos inválidos. Verifique los valores.";
    } else {
        $save = ['valor_ut' => $nuevo_valor_ut, 'anio_valor_ut' => $nuevo_anio];
        file_put_contents($valor_ut_json_path, json_encode($save, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
        $valor_ut = $nuevo_valor_ut;
        $anio_valor_ut = $nuevo_anio;
        $msg = "¡Valor UT actualizado correctamente!";
    }
}
?>

<!DOCTYPE html>
<html lang="es">

<head>
    <meta charset="UTF-8">
    <title>Admin - Valor UT y Categorías</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
</head>

<body>
    <div class="container my-4">
        <h2 class="mb-4">Administrar Valor de la UT</h2>
        <div class="card">
            <div class="card-body">
                <form method="post" class="row g-3">
                    <input type="hidden" name="accion" value="guardar_ut">
                    <div class="col-md-4">
                        <label for="valor_ut" class="form-label">Valor de la UT</label>
                        <input type="number" step="0.01" min="0" class="form-control" id="valor_ut" name="valor_ut" value="<?= htmlspecialchars($valor_ut) ?>" required>
                    </div>
                    <div class="col-md-4">
                        <label for="anio_valor_ut" class="form-label">Año</label>
                        <input type="number" min="2000" max="2100" class="form-control" id="anio_valor_ut" name="anio_valor_ut" value="<?= htmlspecialchars($anio_valor_ut) ?>" required>
                    </div>
                    <div class="col-md-4 align-self-end">
                        <button type="submit" class="btn btn-success">Guardar</button>
                    </div>
                </form>
                <?php if ($msg): ?>
                    <div class="alert alert-success mt-3"><?= $msg ?></div>
                <?php endif; ?>
                <?php if ($error): ?>
                    <div class="alert alert-danger mt-3"><?= $error ?></div>
                <?php endif; ?>
            </div>
        </div>

        <?php
        // --------- CATEGORÍAS -------------
        $categorias_json_path = __DIR__ . '/data/categorias.json';
        $categorias = json_decode(file_get_contents($categorias_json_path), true) ?? [];
        $msg_cat = $error_cat = "";

        // Validación simple (solo superposición entre 'entre')
        function get_category_range($cat)
        {
            switch ($cat['tipo']) {
                case 'menor':
                    return ['min' => PHP_INT_MIN, 'max' => $cat['valor'] - 1];
                case 'menor_igual':
                    return ['min' => PHP_INT_MIN, 'max' => $cat['valor']];
                case 'mayor':
                    return ['min' => $cat['valor'] + 1, 'max' => PHP_INT_MAX];
                case 'mayor_igual':
                    return ['min' => $cat['valor'], 'max' => PHP_INT_MAX];
                case 'igual':
                    return ['min' => $cat['valor'], 'max' => $cat['valor']];
                case 'entre':
                    return ['min' => $cat['min'], 'max' => $cat['max']];
                default:
                    return ['min' => 0, 'max' => 0];
            }
        }

        // --- Reemplaza esta función ---
        function categorias_solapadas($nueva, $todas, $omit_id = null)
        {
            $nueva_rng = get_category_range($nueva);
            foreach ($todas as $cat) {
                if ($omit_id && $cat['id'] == $omit_id) continue;
                $r = get_category_range($cat);
                // si se superponen
                if ($nueva_rng['min'] <= $r['max'] && $nueva_rng['max'] >= $r['min']) {
                    return true;
                }
            }
            return false;
        }

        // --- NUEVA: Detectar huecos y devolver un string de advertencia (llamarla tras cada alta/edición) ---
        function categorias_huecos($todas)
        {
            $rangos = [];
            foreach ($todas as $cat) {
                $r = get_category_range($cat);
                $rangos[] = $r;
            }
            // Ordenar por mínimo
            usort($rangos, function ($a, $b) {
                return $a['min'] <=> $b['min'];
            });
            $huecos = [];
            $ultimo_max = max(0, $rangos[0]['min']); // en vez de $rangos[0]['min']
            foreach ($rangos as $r) {
                if ($r['min'] > $ultimo_max) {
                    $huecos[] = [max(0, $ultimo_max), $r['min'] - 1];
                }
                $ultimo_max = max($ultimo_max, $r['max'] + 1);
            }
            // Si el último max no llega a PHP_INT_MAX puede haber hueco al final (pero solo importa si tenés 'mayor'/'mayor_igual')
            if (!empty($huecos)) {
                $txt = "Atención: existen metros cuadrados NO cubiertos entre: ";
                $txt .= implode(", ", array_map(function ($h) {
                    return "{$h[0]} y {$h[1]} m²";
                }, $huecos));
                return $txt;
            }
            return "";
        }


        // ----------- AGREGAR NUEVA --------------
        if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['accion']) && $_POST['accion'] === 'nueva_categoria') {
            $tipo = $_POST['tipo'] ?? '';
            $valor = $_POST['valor'] ?? '';
            $min = $_POST['min'] ?? '';
            $max = $_POST['max'] ?? '';
            $ut = floatval($_POST['ut'] ?? 0);
            $detalle = trim($_POST['detalle'] ?? '');

            if (!$tipo || ($tipo == 'entre' && ($min === '' || $max === '')) || ($tipo != 'entre' && $valor === '') || $ut <= 0) {
                $error_cat = "Complete correctamente todos los campos.";
            } else {
                $max_id = 0;
                foreach ($categorias as $c) {
                    if ($c['id'] > $max_id) $max_id = $c['id'];
                }
                $new_id = $max_id + 1;
                $nuevo = [
                    'id' => $new_id,
                    'tipo' => $tipo,
                    'ut' => $ut,
                    'detalle' => $detalle
                ];
                if ($tipo == 'entre') {
                    $nuevo['min'] = intval($min);
                    $nuevo['max'] = intval($max);
                } else {
                    $nuevo['valor'] = intval($valor);
                }
                // Validación de solapamiento
                if (categorias_solapadas($nuevo, $categorias)) {
                    $error_cat = "¡Rango o valor superpuesto con otra categoría!";
                } else {
                    $categorias[] = $nuevo;
                    file_put_contents($categorias_json_path, json_encode($categorias, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
                    $msg_cat = "¡Categoría agregada!";
                }
            }
        }

        // ---------- ELIMINAR ----------
        if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['accion']) && $_POST['accion'] === 'eliminar_categoria') {
            $del_id = intval($_POST['eliminar_id'] ?? 0);
            $categorias = array_values(array_filter($categorias, function ($c) use ($del_id) {
                return $c['id'] != $del_id;
            }));
            file_put_contents($categorias_json_path, json_encode($categorias, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
            $msg_cat = "Categoría eliminada.";
        }

        // ------------ EDITAR ---------------
        if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['accion']) && $_POST['accion'] === 'editar_categoria') {
            $edit_id = intval($_POST['edit_id'] ?? 0);
            $tipo = $_POST['tipo'] ?? '';
            $valor = $_POST['valor'] ?? '';
            $min = $_POST['min'] ?? '';
            $max = $_POST['max'] ?? '';
            $ut = floatval($_POST['ut'] ?? 0);
            $detalle = trim($_POST['detalle'] ?? '');

            foreach ($categorias as &$c) {
                if ($c['id'] == $edit_id) {
                    $c['tipo'] = $tipo;
                    $c['ut'] = $ut;
                    $c['detalle'] = $detalle;
                    unset($c['valor'], $c['min'], $c['max']);
                    if ($tipo == 'entre') {
                        $c['min'] = intval($min);
                        $c['max'] = intval($max);
                    } else {
                        $c['valor'] = intval($valor);
                    }
                    // Validación solapamiento sobre el resto
                    if (categorias_solapadas($c, $categorias, $edit_id)) {
                        $error_cat = "¡Rango o valor superpuesto con otra categoría!";
                    }
                    break;
                }
            }
            unset($c);
            if (!$error_cat) {
                file_put_contents($categorias_json_path, json_encode($categorias, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
                $msg_cat = "¡Categoría editada!";
            }
        }
        // Recargar
        $categorias = json_decode(file_get_contents($categorias_json_path), true) ?? [];

        function descCat($cat)
        {
            switch ($cat['tipo']) {
                case 'menor':
                    return "&lt; {$cat['valor']} m²";
                case 'menor_igual':
                    return "&le; {$cat['valor']} m²";
                case 'mayor':
                    return "&gt; {$cat['valor']} m²";
                case 'mayor_igual':
                    return "&ge; {$cat['valor']} m²";
                case 'igual':
                    return "= {$cat['valor']} m²";
                case 'entre':
                    return "Entre {$cat['min']} y {$cat['max']} m²";
                default:
                    return "";
            }
        }
        ?>

        <div class="card mt-5">
            <div class="card-header bg-primary text-white">Categorías</div>
            <div class="card-body">
                <?php if ($msg_cat): ?>
                    <div class="alert alert-success"><?= $msg_cat ?></div>
                <?php endif; ?>
                <?php if ($error_cat): ?>
                    <div class="alert alert-danger"><?= $error_cat ?></div>
                <?php endif; ?>

                <!-- Formulario agregar nueva -->
                <form method="post" class="row g-2 mb-3">
                    <input type="hidden" name="accion" value="nueva_categoria">
                    <div class="col-md-2">
                        <label class="form-label">Tipo</label>
                        <select name="tipo" class="form-select" required onchange="mostrarRangos(this, 'nuevo')">
                            <option value="">Tipo</option>
                            <option value="menor">&lt;</option>
                            <option value="menor_igual">&le;</option>
                            <option value="mayor">&gt;</option>
                            <option value="mayor_igual">&ge;</option>
                            <option value="entre">Entre</option>
                            <option value="igual">=</option>
                        </select>
                    </div>
                    <div class="col-md-2 nuevo-valor" style="display:none;">
                        <label class="form-label">Valor</label>
                        <input type="number" name="valor" class="form-control">
                    </div>
                    <div class="col-md-2 nuevo-min" style="display:none;">
                        <label class="form-label">Mín</label>
                        <input type="number" name="min" class="form-control">
                    </div>
                    <div class="col-md-2 nuevo-max" style="display:none;">
                        <label class="form-label">Máx</label>
                        <input type="number" name="max" class="form-control">
                    </div>
                    <div class="col-md-2">
                        <label class="form-label">UT</label>
                        <input type="number" step="0.01" name="ut" class="form-control" required>
                    </div>
                    <div class="col-md-3">
                        <label class="form-label">Detalle</label>
                        <input type="text" name="detalle" class="form-control">
                    </div>
                    <div class="col-md-1 align-self-end">
                        <button class="btn btn-success btn-sm" type="submit">Agregar</button>
                    </div>
                </form>

                <!-- Tabla de categorías -->
                <div class="table-responsive">
                    <table class="table table-sm table-bordered align-middle">
                        <thead>
                            <tr>
                                <th>ID</th>
                                <th>Tipo</th>
                                <th>Valor/Mín</th>
                                <th>Máx</th>
                                <th>UT</th>
                                <th>Detalle</th>
                                <th>Descripción</th>
                                <th>Acción</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($categorias as $cat): ?>
                                <tr>
                                    <form method="post" class="row g-1">
                                        <input type="hidden" name="accion" value="editar_categoria">
                                        <input type="hidden" name="edit_id" value="<?= $cat['id'] ?>">
                                        <td><?= $cat['id'] ?></td>
                                        <td>
                                            <select name="tipo" class="form-select form-select-sm" onchange="mostrarRangos(this, '<?= $cat['id'] ?>')" required>
                                                <option value="menor" <?= $cat['tipo'] == 'menor' ? 'selected' : '' ?>>&lt;</option>
                                                <option value="menor_igual" <?= $cat['tipo'] == 'menor_igual' ? 'selected' : '' ?>>≤</option>
                                                <option value="mayor" <?= $cat['tipo'] == 'mayor' ? 'selected' : '' ?>>> </option>
                                                <option value="mayor_igual" <?= $cat['tipo'] == 'mayor_igual' ? 'selected' : '' ?>>≥</option>
                                                <option value="entre" <?= $cat['tipo'] == 'entre' ? 'selected' : '' ?>>Entre</option>
                                                <option value="igual" <?= $cat['tipo'] == 'igual' ? 'selected' : '' ?>>=</option>
                                            </select>
                                        </td>
                                        <td>
                                            <input type="number" name="valor" value="<?= $cat['valor'] ?? '' ?>" class="form-control form-control-sm valor-<?= $cat['id'] ?>" <?= $cat['tipo'] == 'entre' ? 'style="display:none"' : '' ?>>
                                            <input type="number" name="min" value="<?= $cat['min'] ?? '' ?>" class="form-control form-control-sm min-<?= $cat['id'] ?>" <?= $cat['tipo'] == 'entre' ? '' : 'style="display:none"' ?>>
                                        </td>
                                        <td>
                                            <input type="number" name="max" value="<?= $cat['max'] ?? '' ?>" class="form-control form-control-sm max-<?= $cat['id'] ?>" <?= $cat['tipo'] == 'entre' ? '' : 'style="display:none"' ?>>
                                        </td>
                                        <td>
                                            <input type="number" step="0.01" name="ut" value="<?= $cat['ut'] ?>" class="form-control form-control-sm" required>
                                        </td>
                                        <td>
                                            <input type="text" name="detalle" value="<?= htmlspecialchars($cat['detalle']) ?>" class="form-control form-control-sm">
                                        </td>
                                        <td><?= descCat($cat) ?></td>
                                        <td>
                                            <button class="btn btn-primary btn-sm" type="submit">Guardar</button>
                                    </form>
                                    <form method="post" style="display:inline;">
                                        <input type="hidden" name="accion" value="eliminar_categoria">
                                        <input type="hidden" name="eliminar_id" value="<?= $cat['id'] ?>">
                                        <button class="btn btn-danger btn-sm" type="submit" onclick="return confirm('¿Seguro?')">Eliminar</button>
                                    </form>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                    <?php
                    $huecosMsg = categorias_huecos($categorias);
                    if ($huecosMsg) {
                        echo '<div class="alert alert-warning mt-2">' . $huecosMsg . '</div>';
                    }
                    ?>

                </div>
            </div>
        </div>
    </div>
    <script>
        function mostrarRangos(select, id) {
            if (select.value === "entre") {
                document.querySelectorAll('.valor-' + id).forEach(e => e.style.display = 'none');
                document.querySelectorAll('.min-' + id).forEach(e => e.style.display = '');
                document.querySelectorAll('.max-' + id).forEach(e => e.style.display = '');
            } else {
                document.querySelectorAll('.valor-' + id).forEach(e => e.style.display = '');
                document.querySelectorAll('.min-' + id).forEach(e => e.style.display = 'none');
                document.querySelectorAll('.max-' + id).forEach(e => e.style.display = 'none');
            }
        }
        // Para la fila de alta
        document.querySelector('select[name="tipo"]').addEventListener('change', function() {
            if (this.value === "entre") {
                document.querySelector('.nuevo-valor').style.display = "none";
                document.querySelector('.nuevo-min').style.display = "";
                document.querySelector('.nuevo-max').style.display = "";
            } else {
                document.querySelector('.nuevo-valor').style.display = "";
                document.querySelector('.nuevo-min').style.display = "none";
                document.querySelector('.nuevo-max').style.display = "none";
            }
        });
    </script>
</body>

</html>