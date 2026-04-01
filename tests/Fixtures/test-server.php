<?php

declare(strict_types=1);

$uri = $_SERVER['REQUEST_URI'] ?? '/';
$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
$body = file_get_contents('php://input');

// Custom header route
if (str_starts_with($uri, '/headers')) {
    header('X-Custom-Header: test-value');
    echo json_encode(['ok' => true]);
    return;
}

// Status code route
if (preg_match('#^/status/(\d+)$#', $uri, $matches)) {
    http_response_code((int) $matches[1]);
    echo json_encode(['status' => (int) $matches[1]]);
    return;
}

// Echo headers route
if (str_starts_with($uri, '/echo-headers')) {
    $headers = [];
    foreach ($_SERVER as $key => $value) {
        if (str_starts_with($key, 'HTTP_')) {
            $name = str_replace('_', '-', ucwords(strtolower(substr($key, 5)), '_'));
            $headers[$name] = $value;
        }
    }
    header('Content-Type: application/json');
    echo json_encode(['headers' => $headers]);
    return;
}

// Default: echo method, uri, body
header('Content-Type: application/json');
echo json_encode([
    'method' => $method,
    'uri' => $uri,
    'body' => $body,
]);
