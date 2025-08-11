<?php
require_once __DIR__ . '/loader.php';

function get_categorias() {
    return load_json(__DIR__ . '/../data/categorias.json');
}

/**
 * Genera la descripción de la categoría a partir de los campos 'min' y 'max'.
 * @param array $cat Un array de categoría con 'min' y 'max'.
 * @return string La descripción en HTML.
 */
function descripcion_categoria_por_rango($cat) {
    $min = $cat['min'] ?? null;
    $max = $cat['max'] ?? null;

    if ($min !== null && $max !== null) {
        // Ejemplo: "Entre 61 y 150 m²"
        return "Entre " . htmlspecialchars($min) . " y " . htmlspecialchars($max) . " m²";
    } elseif ($min !== null) {
        // Ejemplo: "> 351 m²" o "351 m² en adelante"
        return "Mayor o igual a " . htmlspecialchars($min) . " m²";
    } elseif ($max !== null) {
        // Ejemplo: "< 60 m²" o "Hasta 60 m²"
        return "Hasta " . htmlspecialchars($max) . " m²";
    } else {
        return "";
    }
}

/**
 * Detecta la categoría correcta basándose en los metros cuadrados ingresados.
 * Se adapta al nuevo formato del JSON que usa 'min' y 'max'.
 * @param float $m2 Los metros cuadrados ingresados.
 * @param array $categorias La lista de categorías.
 * @return array|null Retorna el array de la categoría si se encuentra, o null si no.
 */
function detectar_categoria_por_m2($m2, $categorias) {
    foreach ($categorias as $cat) {
        $min = $cat['min'] ?? 0;
        $max = $cat['max'] ?? null;

        if ($m2 >= $min && ($max === null || $m2 <= $max)) {
            return $cat;
        }
    }
    return null;
}