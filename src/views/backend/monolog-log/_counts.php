<?php


/*
 * Copyright (c) 2026 Besnovatyj. Licensed under the MIT License.
 */

use Besnovatyj\Monolog\entities\MonologLog;
use yii\helpers\Html;

/**
 * @var MonologLog $log
 */

$counts = $log->getCounts();

$badges = [
    'DEBUG' => 'bg-secondary',
    'INFO' => 'bg-info',
    'NOTICE' => 'bg-primary',
    'WARNING' => 'bg-warning',
    'ERROR' => 'bg-danger',
    'CRITICAL' => 'bg-danger',
    'ALERT' => 'bg-danger',
    'EMERGENCY' => 'bg-dark',
];

if (empty($counts)) {
    echo Html::tag('span', 'No entries', ['class' => 'badge bg-light']);
    return;
}

foreach ($counts as $level => $count) {
    $badgeClass = $badges[$level] ?? 'badge-light';
    echo Html::tag('span', Html::encode($level) . ': ' . $count, ['class' => "badge {$badgeClass} me-1"]);
}
