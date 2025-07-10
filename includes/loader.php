<?php
function load_json($path) {
    if (!file_exists($path)) return null;
    $json = file_get_contents($path);
    return json_decode($json, true);
}
?>
