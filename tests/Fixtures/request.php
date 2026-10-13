<?php
// run by RequestTest in PHP's built-in server: php://input has the posted body only in a real request
require __DIR__ . '/../../vendor/autoload.php';

echo json_encode((new Enicore\Maris\Standalone\Request())->all());
