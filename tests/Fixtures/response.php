<?php
// run by ResponseTest in PHP's built-in server: Response exits, so it can't run in the test process
require __DIR__ . '/../../vendor/autoload.php';

use Enicore\Maris\Standalone\Response;

echo "output that is discarded";

match ($_GET['action'] ?? '') {
    'success' => Response::success(['a' => 1]),
    'successEmpty' => Response::success(),
    'error' => Response::error('Failed', ['b' => 2]),
    'terminate' => Response::terminate(403, 'Forbidden'),
    'terminateDefault' => Response::terminate(),
};

echo "not terminated";
