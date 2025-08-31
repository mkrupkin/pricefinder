<?php
header('Content-Type: application/json');
echo json_encode([
    'message' => 'PriceFinder API is running',
    'version' => '1.0.0',
    'endpoints' => [
        '/api/analyze' => 'Product search endpoint',
        '/api/auth' => 'Authentication endpoint'
    ]
]);
?>