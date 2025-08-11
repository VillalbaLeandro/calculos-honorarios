<?php
// =====================================================================
// 1. CONFIGURACIÓN INICIAL Y LECTURA DE DATOS
// =====================================================================
session_start();

if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    header('Location: index.php');
    exit;
}

// Rutas a los archivos de datos
$valor_ut_json_path = __DIR__ . '/data/valor_ut.json';
$categorias_json_path = __DIR__ . '/data/categorias.json';
$estados_json_path = __DIR__ . '/data/estados_obra.json';

// Cargar datos
$ut_data = json_decode(file_get_contents($valor_ut_json_path), true);
$categorias = json_decode(file_get_contents($categorias_json_path), true) ?? [];
usort($categorias, fn($a, $b) => ($a['min'] ?? 0) <=> ($b['min'] ?? 0));
$estados = json_decode(file_get_contents($estados_json_path), true) ?? [];

// Variables para el modo edición
$modo_edicion = false;
$categoria_a_editar = null;
$modo_edicion_estado = false;
$estado_a_editar = null;

// Recuperar mensajes de sesión para cada bloque
$msg_ut = $_SESSION['msg_ut'] ?? null;
unset($_SESSION['msg_ut']);

$msg_categoria = $_SESSION['msg_categoria'] ?? null;
$error_categoria = $_SESSION['error_categoria'] ?? null;
unset($_SESSION['msg_categoria'], $_SESSION['error_categoria']);

$msg_estado = $_SESSION['msg_estado'] ?? null;
$error_estado = $_SESSION['error_estado'] ?? null;
unset($_SESSION['msg_estado'], $_SESSION['error_estado']);

// =====================================================================
// 2. LÓGICA DE VALIDACIÓN (SIN CAMBIOS)
// =====================================================================
function validar_rango($nuevo_rango, $todas_las_categorias, $id_a_ignorar = null)
{
    $nuevo_min = $nuevo_rango['min'];
    $nuevo_max = $nuevo_rango['max'];

    if ($nuevo_min < 0) return "El valor mínimo no puede ser negativo.";
    if ($nuevo_max !== null && $nuevo_max < $nuevo_min) return "El valor máximo debe ser mayor o igual al mínimo.";

    foreach ($todas_las_categorias as $cat_existente) {
        if ($cat_existente['id'] === $id_a_ignorar) continue;

        $existente_min = $cat_existente['min'];
        $existente_max = $cat_existente['max'];

        $se_solapan = false;
        if ($nuevo_max === null && $existente_max === null) {
            $se_solapan = true;
        } elseif ($nuevo_max === null) {
            $se_solapan = $nuevo_min <= $existente_max;
        } elseif ($existente_max === null) {
            $se_solapan = $existente_min <= $nuevo_max;
        } else {
            $se_solapan = $nuevo_min <= $existente_max && $existente_min <= $nuevo_max;
        }

        if ($se_solapan) {
            return "El rango se superpone con la categoría ID {$cat_existente['id']} (rango: {$existente_min} - " . ($existente_max ?? '...') . ").";
        }
    }
    return "";
}

function encontrar_huecos($todas_las_categorias)
{
    if (count($todas_las_categorias) < 2) return "";
    $advertencia = "";
    for ($i = 0; $i < count($todas_las_categorias) - 1; $i++) {
        $actual = $todas_las_categorias[$i];
        $siguiente = $todas_las_categorias[$i + 1];
        if ($actual['max'] === null) {
            $advertencia .= "La categoría ID {$actual['id']} no tiene máximo y no es la última. ";
            continue;
        }
        if ($siguiente['min'] != $actual['max'] + 1) {
            $advertencia .= "Hay un hueco entre la categoría ID {$actual['id']} (termina en {$actual['max']}) y la ID {$siguiente['id']} (empieza en {$siguiente['min']}). ";
        }
    }
    return $advertencia;
}

// =====================================================================
// 3. CONTROLADOR DE ACCIONES (MANEJO DE POST y GET)
// =====================================================================
// ---- Acción para guardar UT ----
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['form_action']) && $_POST['form_action'] === 'guardar_ut') {
    $ut_data['valor_ut'] = floatval($_POST['valor_ut']);
    $ut_data['anio_valor_ut'] = intval($_POST['anio_valor_ut']);
    file_put_contents($valor_ut_json_path, json_encode($ut_data, JSON_PRETTY_PRINT));
    $_SESSION['msg_ut'] = "Valor UT actualizado correctamente."; // <- Cambio aquí
    header("Location: admin.php");
    exit;
}

// ---- Acción para guardar/editar categoría ----
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['form_action']) && $_POST['form_action'] === 'guardar_categoria') {
    $id = !empty($_POST['id']) ? intval($_POST['id']) : null;
    $min = intval($_POST['min']);
    $max = !isset($_POST['max_infinito']) && $_POST['max'] !== '' ? intval($_POST['max']) : null;
    $valor_m2 = floatval($_POST['valor_m2']);
    $detalle = trim($_POST['detalle']);

    $rango_propuesto = ['min' => $min, 'max' => $max];
    $error_validacion = validar_rango($rango_propuesto, $categorias, $id);

    if ($error_validacion) {
        $_SESSION['error_categoria'] = $error_validacion; // <- Cambio aquí
    } else {
        if ($id) {
            foreach ($categorias as &$c) {
                if ($c['id'] === $id) {
                    $c = ['id' => $id, 'min' => $min, 'max' => $max, 'valor_m2' => $valor_m2, 'detalle' => $detalle];
                    break;
                }
            }
            $_SESSION['msg_categoria'] = "Categoría ID $id actualizada."; // <- Cambio aquí
        } else {
            $nuevo_id = count($categorias) > 0 ? max(array_column($categorias, 'id')) + 1 : 1;
            $categorias[] = ['id' => $nuevo_id, 'min' => $min, 'max' => $max, 'valor_m2' => $valor_m2, 'detalle' => $detalle];
            $_SESSION['msg_categoria'] = "Categoría agregada con ID $nuevo_id."; // <- Cambio aquí
        }
        file_put_contents($categorias_json_path, json_encode($categorias, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
    }
    header("Location: admin.php");
    exit;
}

// ---- Acción para eliminar categoría ----
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['form_action']) && $_POST['form_action'] === 'eliminar_categoria') {
    $id = intval($_POST['id']);
    $categorias = array_values(array_filter($categorias, fn($c) => $c['id'] !== $id));
    file_put_contents($categorias_json_path, json_encode($categorias, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
    $_SESSION['msg_categoria'] = "Categoría ID $id eliminada."; // <- Cambio aquí
    header("Location: admin.php");
    exit;
}

// ---- Preparar para editar categoría (vía GET) ----
if ($_SERVER['REQUEST_METHOD'] === 'GET' && isset($_GET['action']) && $_GET['action'] === 'edit') {
    $id_a_editar = intval($_GET['id']);
    foreach ($categorias as $c) {
        if ($c['id'] === $id_a_editar) {
            $modo_edicion = true;
            $categoria_a_editar = $c;
            break;
        }
    }
}

// ---- Acción para guardar/editar estado de obra ----
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['form_action']) && $_POST['form_action'] === 'guardar_estado') {
    $id = !empty($_POST['id']) ? intval($_POST['id']) : null;
    $descripcion = trim($_POST['descripcion']);
    $porcentaje = floatval($_POST['porcentaje']);

    if ($porcentaje < 0 || $porcentaje > 100) {
        $_SESSION['error_estado'] = "El porcentaje debe estar entre 0 y 100."; // <- Cambio aquí
    } else {
        if ($id) {
            foreach ($estados as &$e) {
                if ($e['id'] === $id) {
                    $e['descripcion'] = $descripcion;
                    $e['porcentaje'] = $porcentaje;
                    break;
                }
            }
            $_SESSION['msg_estado'] = "Estado de obra ID $id actualizado."; // <- Cambio aquí
        } else {
            $nuevo_id = count($estados) > 0 ? max(array_column($estados, 'id')) + 1 : 1;
            $estados[] = ['id' => $nuevo_id, 'descripcion' => $descripcion, 'porcentaje' => $porcentaje];
            $_SESSION['msg_estado'] = "Estado de obra agregado con ID $nuevo_id."; // <- Cambio aquí
        }
        file_put_contents($estados_json_path, json_encode($estados, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
    }
    header("Location: admin.php");
    exit;
}

// ---- Acción para eliminar estado de obra ----
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['form_action']) && $_POST['form_action'] === 'eliminar_estado') {
    $id = intval($_POST['id']);
    $estados = array_values(array_filter($estados, fn($e) => $e['id'] !== $id));
    file_put_contents($estados_json_path, json_encode($estados, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
    $_SESSION['msg_estado'] = "Estado de obra ID $id eliminado."; // <- Cambio aquí
    header("Location: admin.php");
    exit;
}

// ---- Preparar para editar estado (vía GET) ----
if ($_SERVER['REQUEST_METHOD'] === 'GET' && isset($_GET['action']) && $_GET['action'] === 'edit_estado') {
    $id_estado_editar = intval($_GET['id']);
    foreach ($estados as $e) {
        if ($e['id'] === $id_estado_editar) {
            $modo_edicion_estado = true;
            $estado_a_editar = $e;
            break;
        }
    }
}

// =====================================================================
// 4. VISTA (HTML)
// =====================================================================
?>
<!DOCTYPE html>
<html lang="es">

<head>
    <meta charset="UTF-8">
    <title>Admin - Valor UT y Categorías</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css">
</head>

<body>
    <div class="container my-4">

        <h2 class="mb-3">Administrar Valor de la UT</h2>
        <div class="card mb-5">
            <div class="card-body">
                <form method="post" class="row g-3">
                    <input type="hidden" name="form_action" value="guardar_ut">
                    <div class="col-md-4">
                        <label for="valor_ut" class="form-label">Valor de la UT</label>
                        <input type="number" step="0.01" min="0" class="form-control" id="valor_ut" name="valor_ut" value="<?= htmlspecialchars($ut_data['valor_ut']) ?>" required>
                    </div>
                    <div class="col-md-4">
                        <label for="anio_valor_ut" class="form-label">Año</label>
                        <input type="number" min="2000" max="2100" class="form-control" id="anio_valor_ut" name="anio_valor_ut" value="<?= htmlspecialchars($ut_data['anio_valor_ut']) ?>" required>
                    </div>
                    <div class="col-md-4 align-self-end">
                        <button type="submit" class="btn btn-primary"><i class="bi bi-save"></i> Guardar Valor UT</button>
                    </div>
                </form>

                <?php if ($msg_ut): ?>
                    <div class="alert alert-success mt-3"><?= htmlspecialchars($msg_ut) ?></div>
                <?php endif; ?>

            </div>
        </div>

        <h2 class="mb-3">Administrar Categorías</h2>
        <div class="card mb-4">
            <div class="card-header bg-primary text-white">
                <?= $modo_edicion ? "Editando Categoría ID " . htmlspecialchars($categoria_a_editar['id']) : "Agregar Nueva Categoría" ?>
            </div>
            <div class="card-body">
                <form method="post">
                    <input type="hidden" name="form_action" value="guardar_categoria">
                    <input type="hidden" name="id" value="<?= $modo_edicion ? htmlspecialchars($categoria_a_editar['id']) : '' ?>">

                    <div class="row g-3">
                        <div class="col-md-3">
                            <label for="min" class="form-label">Mínimo (m²)</label>
                            <input type="number" name="min" class="form-control" required value="<?= $modo_edicion ? htmlspecialchars($categoria_a_editar['min']) : '' ?>">
                        </div>
                        <div class="col-md-3">
                            <label for="max" class="form-label">Máximo (m²)</label>
                            <input type="number" name="max" id="max_input" class="form-control" value="<?= $modo_edicion ? htmlspecialchars($categoria_a_editar['max'] ?? '') : '' ?>">
                            <div class="form-check mt-1">
                                <input class="form-check-input" type="checkbox" name="max_infinito" id="max_infinito" onchange="toggleMaxInput(this)" <?= ($modo_edicion && $categoria_a_editar['max'] === null) ? 'checked' : '' ?>>
                                <label class="form-check-label" for="max_infinito">Sin límite superior</label>
                            </div>
                        </div>
                        <div class="col-md-2">
                            <label for="valor_m2" class="form-label">Valor m²</label>
                            <input type="number" step="0.01" name="valor_m2" class="form-control" required value="<?= $modo_edicion ? htmlspecialchars($categoria_a_editar['valor_m2']) : '' ?>">
                        </div>
                        <div class="col-md-4">
                            <label for="detalle" class="form-label">Detalle</label>
                            <input type="text" name="detalle" class="form-control" value="<?= $modo_edicion ? htmlspecialchars($categoria_a_editar['detalle']) : '' ?>">
                        </div>
                    </div>
                    <div class="mt-3">
                        <button type="submit" class="btn btn-success"><i class="bi bi-save"></i> <?= $modo_edicion ? 'Guardar Cambios' : 'Agregar Categoría' ?></button>
                        <?php if ($modo_edicion): ?>
                            <a href="admin.php" class="btn btn-secondary">Cancelar Edición</a>
                        <?php endif; ?>
                    </div>
                </form>
            </div>
        </div>

        <div class="card mb-4">
            <div class="card-body">

                <?php if ($msg_categoria): ?>
                    <div class="alert alert-success"><?= htmlspecialchars($msg_categoria) ?></div>
                <?php endif; ?>
                <?php if ($error_categoria): ?>
                    <div class="alert alert-danger"><?= htmlspecialchars($error_categoria) ?></div>
                <?php endif; ?>

                <div class="table-responsive">
                    <table class="table table-bordered table-striped align-middle">
                        <thead class="table-dark">
                            <tr>
                                <th>ID</th>
                                <th>Rango (m²)</th>
                                <th>Valor m²</th>
                                <th>Detalle</th>
                                <th class="text-center">Acciones</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($categorias)): ?>
                                <tr>
                                    <td colspan="5" class="text-center">No hay categorías definidas.</td>
                                </tr>
                            <?php endif; ?>
                            <?php foreach ($categorias as $cat): ?>
                                <tr>
                                    <td><?= $cat['id'] ?></td>
                                    <td><?= $cat['min'] ?> - <?= $cat['max'] ?? '...' ?></td>
                                    <td><?= htmlspecialchars($cat['valor_m2']) ?></td>
                                    <td><?= htmlspecialchars($cat['detalle']) ?></td>
                                    <td class="text-center">
                                        <a href="?action=edit&id=<?= $cat['id'] ?>#form_categoria" class="btn btn-sm btn-warning" title="Editar"><i class="bi bi-pencil-fill"></i></a>
                                        <form method="post" style="display:inline;" onsubmit="return confirm('¿Estás seguro?');">
                                            <input type="hidden" name="form_action" value="eliminar_categoria">
                                            <input type="hidden" name="id" value="<?= $cat['id'] ?>">
                                            <button type="submit" class="btn btn-sm btn-danger" title="Eliminar"><i class="bi bi-trash-fill"></i></button>
                                        </form>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                <?php
                $advertencia_huecos = encontrar_huecos($categorias);
                if ($advertencia_huecos):
                ?>
                    <div class="alert alert-warning mt-2">
                        <strong><i class="bi bi-exclamation-triangle-fill"></i> Atención:</strong> <?= htmlspecialchars($advertencia_huecos) ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>

        <h2 class="mb-3">Administrar Estados de Obra</h2>
        <div class="card mb-4">
            <div class="card-header bg-primary text-white">
                <?= $modo_edicion_estado ? "Editando Estado ID " . htmlspecialchars($estado_a_editar['id']) : "Agregar Nuevo Estado de Obra" ?>
            </div>
            <div class="card-body">
                <form method="post">
                    <input type="hidden" name="form_action" value="guardar_estado">
                    <input type="hidden" name="id" value="<?= $modo_edicion_estado ? htmlspecialchars($estado_a_editar['id']) : '' ?>">

                    <div class="row g-3">
                        <div class="col-md-6">
                            <label for="descripcion" class="form-label">Descripción</label>
                            <input type="text" name="descripcion" class="form-control" required value="<?= $modo_edicion_estado ? htmlspecialchars($estado_a_editar['descripcion']) : '' ?>">
                        </div>
                        <div class="col-md-3">
                            <label for="porcentaje" class="form-label">Porcentaje</label>
                            <input type="number" name="porcentaje" class="form-control" step="0.01" min="0" max="100" required value="<?= $modo_edicion_estado ? htmlspecialchars($estado_a_editar['porcentaje']) : '' ?>">
                        </div>
                    </div>
                    <div class="mt-3">
                        <button type="submit" class="btn btn-success"><i class="bi bi-save"></i> <?= $modo_edicion_estado ? 'Guardar Cambios' : 'Agregar Estado' ?></button>
                        <?php if ($modo_edicion_estado): ?>
                            <a href="admin.php" class="btn btn-secondary">Cancelar Edición</a>
                        <?php endif; ?>
                    </div>
                </form>
            </div>
        </div>

        <div class="card mb-4">
            <div class="card-body">

                <?php if ($msg_estado): ?>
                    <div class="alert alert-success"><?= htmlspecialchars($msg_estado) ?></div>
                <?php endif; ?>
                <?php if ($error_estado): ?>
                    <div class="alert alert-danger"><?= htmlspecialchars($error_estado) ?></div>
                <?php endif; ?>

                <div class="table-responsive">
                    <table class="table table-bordered table-striped align-middle">
                        <thead class="table-dark">
                            <tr>
                                <th>ID</th>
                                <th>Descripción</th>
                                <th>Porcentaje</th>
                                <th class="text-center">Acciones</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($estados)): ?>
                                <tr>
                                    <td colspan="4" class="text-center">No hay estados de obra definidos.</td>
                                </tr>
                            <?php endif; ?>
                            <?php foreach ($estados as $estado): ?>
                                <tr>
                                    <td><?= $estado['id'] ?></td>
                                    <td><?= htmlspecialchars($estado['descripcion']) ?></td>
                                    <td><?= number_format($estado['porcentaje'], 2, ',', '.') ?>%</td>
                                    <td class="text-center">
                                        <a href="?action=edit_estado&id=<?= $estado['id'] ?>#form_estado" class="btn btn-sm btn-warning" title="Editar"><i class="bi bi-pencil-fill"></i></a>
                                        <form method="post" style="display:inline;" onsubmit="return confirm('¿Estás seguro?');">
                                            <input type="hidden" name="form_action" value="eliminar_estado">
                                            <input type="hidden" name="id" value="<?= $estado['id'] ?>">
                                            <button type="submit" class="btn btn-sm btn-danger" title="Eliminar"><i class="bi bi-trash-fill"></i></button>
                                        </form>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <div class="card">
            <div class="card-body">
                <form action="logout.php" method="post">
                    <button type="submit" class="btn btn-danger">Terminar Edición</button>
                </form>
            </div>
        </div>

    </div>

    <script>
        function toggleMaxInput(checkbox) {
            const maxInput = document.getElementById('max_input');
            maxInput.disabled = checkbox.checked;
            if (checkbox.checked) {
                maxInput.value = '';
            }
        }
        document.addEventListener('DOMContentLoaded', function() {
            const checkbox = document.getElementById('max_infinito');
            if (checkbox) {
                toggleMaxInput(checkbox);
            }
        });
    </script>
</body>

</html>