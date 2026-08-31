<?php

require_once '../includes/db.php';
require_once '../includes/auth.php';

require_role(['Administrator', 'Staff']);

$test_data = [
    'daily_revenue' => [
        [
            'date' => '2026-08-01',
            'revenue' => 3500
        ],
        [
            'date' => '2026-08-02',
            'revenue' => 4200
        ],
        [
            'date' => '2026-08-03',
            'revenue' => 2800
        ],
        [
            'date' => '2026-08-04',
            'revenue' => 5100
        ],
        [
            'date' => '2026-08-05',
            'revenue' => 4600
        ],
        [
            'date' => '2026-08-06',
            'revenue' => 3900
        ],
        [
            'date' => '2026-08-07',
            'revenue' => 6200
        ]
    ]
];

$python = 'python';

$command = escapeshellcmd(
    $python . ' ../python/dashboard_analytics.py'
);

$process = proc_open(
    $command,
    [
        0 => ['pipe', 'r'],
        1 => ['pipe', 'w'],
        2 => ['pipe', 'w']
    ],
    $pipes
);

if (!is_resource($process)) {
    die('Could not start Python.');
}

fwrite(
    $pipes[0],
    json_encode($test_data)
);

fclose($pipes[0]);

$output = stream_get_contents($pipes[1]);
$error = stream_get_contents($pipes[2]);

fclose($pipes[1]);
fclose($pipes[2]);

$return_code = proc_close($process);

header('Content-Type: application/json');

echo $output;