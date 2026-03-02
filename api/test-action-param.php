<?php
/**
 * Test if action parameter is being received
 */

header('Content-Type: application/json; charset=utf-8');

$method = $_SERVER['REQUEST_METHOD'];
$action = $_GET['action'] ?? $_POST['action'] ?? '';

$result = [
    'test' => 'action parameter test',
    'method' => $method,
    'action' => $action,
    'action_empty' => empty($action),
    'GET_params' => $_GET,
    'POST_params' => $_POST,
    'REQUEST_URI' => $_SERVER['REQUEST_URI'] ?? '',
    'QUERY_STRING' => $_SERVER['QUERY_STRING'] ?? '',
];

echo json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
