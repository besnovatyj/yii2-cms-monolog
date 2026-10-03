<?php

/*
 * Copyright (c) 2026 Besnovatyj. Licensed under the MIT License.
 */

use Besnovatyj\Contracts\adminMenu\AdminMenuLocation;
use Besnovatyj\Contracts\adminMenu\AdminMenuPlacement;

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
//                new AdminMenuPlacement(
//                    location: AdminMenuLocation::LeftSidebar,
//                    group: 'Logs',
//                    groupIcon: 'bi bi-clock-history',
//                    priority: 100,
//                    groupPriority: 100,
//                ),
                new AdminMenuPlacement(
                    location: AdminMenuLocation::RightSidebar,
                    group: 'Logs',
                    groupIcon: 'bi bi-clock-history',
                    groupPriority: 100,
                    priority: 100,
                ),
            ],
        ],
    ],
];
