<?php
// api/v1/custom_endpoint.php
header('Content-Type: application/json');

$method = $_SERVER['REQUEST_METHOD'];

switch ($method) {
    case 'GET':
        echo json_encode(['data' => 'response']);
        break;
    case 'POST':
        // معالجة البيانات
        break;
}
?>