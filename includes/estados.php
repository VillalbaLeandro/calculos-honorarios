<?php
require_once __DIR__ . '/loader.php';

function get_estados() {
    return load_json(__DIR__ . '/../data/estados_obra.json');
}
?>
