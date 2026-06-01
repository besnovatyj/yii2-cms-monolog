<?php

/*
 * Copyright (c) 2026 Besnovatyj. Licensed under the MIT License.
 */

return [
    // Monolog Logs
    [
        'label' => 'Monolog Logs',
        'iconClass' => 'bi bi-filetype-json me-1',
        'url' => ['/Monolog/backend/monolog-log/index'],
        'active' => static function () {
            return str_contains(\Yii::$app->request->url, 'Monolog/backend/monolog-log');
        },
        '_meta' => [
            'placements' => [
//                [
//                    'location' => 'left-sidebar',
//                    'group' => 'Logs',
//                    'groupIcon' => 'bi bi-clock-history',
//                    'priority' => 100,
//                    'groupPriority' => 100,
//                ],
                [
                    'location' => 'right-sidebar',
                    'group' => 'Logs',
                    'groupIcon' => 'bi bi-clock-history',
                    'priority' => 100,
                    'groupPriority' => 100,
                ],
            ],
        ],
    ],
];
