<?php
require_once __DIR__ . '/loader.php';

function get_categorias() {
    return load_json(__DIR__ . '/../data/categorias.json');
}

function descripcion_categoria($cat) {
    switch ($cat['tipo']) {
        case 'menor': return "&lt; {$cat['valor']} m²";
        case 'menor_igual': return "&le; {$cat['valor']} m²";
        case 'mayor': return "&gt; {$cat['valor']} m²";
        case 'mayor_igual': return "&ge; {$cat['valor']} m²";
        case 'igual': return "= {$cat['valor']} m²";
        case 'entre': return "Entre {$cat['min']} y {$cat['max']} m²";
        default: return "";
    }
}

function detectar_categoria($m2, $categorias) {
    foreach ($categorias as $cat) {
        switch ($cat['tipo']) {
            case 'menor':
                if ($m2 < $cat['valor']) return $cat;
                break;
            case 'menor_igual':
                if ($m2 <= $cat['valor']) return $cat;
                break;
            case 'mayor':
                if ($m2 > $cat['valor']) return $cat;
                break;
            case 'mayor_igual':
                if ($m2 >= $cat['valor']) return $cat;
                break;
            case 'igual':
                if ($m2 == $cat['valor']) return $cat;
                break;
            case 'entre':
                if ($m2 >= $cat['min'] && $m2 <= $cat['max']) return $cat;
                break;
        }
    }
    return null;
}
?>
