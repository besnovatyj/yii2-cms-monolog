<?php


/*
 * Copyright (c) 2026 Besnovatyj. Licensed under the MIT License.
 */

use Besnovatyj\Backend\Widgets\grid\ActionColumn;
use Besnovatyj\Backend\Widgets\pagination\LinkPager;
use Besnovatyj\Monolog\entities\MonologLog;
use yii\data\ArrayDataProvider;
use yii\grid\GridView;
use yii\helpers\Html;
use yii\web\View;

/**
 * @var View $this
 * @var ArrayDataProvider $dataProvider
 */

$this->title = 'Monolog Logs';
$this->params['breadcrumbs'][] = 'Monolog Logs';
?>
<p>
    <?= Html::a('Logs', ['/Monolog/backend/monolog-log/index'], ['class' => 'btn btn-info', 'disabled' => 'disabled']) ?>
    >
    <?= Html::a('History', ['/Monolog/backend/monolog-log/index-history'], ['class' => 'btn btn-secondary']) ?> >
    <?= Html::a('Zip', ['/Monolog/backend/monolog-log/index-zip'], ['class' => 'btn btn-secondary']) ?>
</p>
<div class="card">
    <div class="card-header d-md-flex justify-content-md-between">
        <div><?= $this->title ?></div>
        <div class="card-tools">
            <span class="badge bg-info">JSON Format</span>
        </div>
    </div>
    <div class="card-body">
        <?= GridView::widget([
            'layout' => '{items}',
            'tableOptions' => ['class' => 'table'],
            'options' => ['class' => 'grid-view table-responsive'],
            'dataProvider' => $dataProvider,
            'columns' => [
                [
                    'attribute' => 'name',
                    'format' => 'raw',
                    'value' => static function (MonologLog $log) {
                        return Html::tag('h5', join("\n", [
                            Html::encode($log->getName()),
                            '<br/>',
                            Html::tag('small', Html::encode($log->getFilePath()), ['style' => 'font-size:50%;']),
                        ]));
                    },
                ], [
                    'attribute' => 'counts',
                    'format' => 'raw',
                    'headerOptions' => ['class' => 'sort-ordinal'],
                    'value' => function (MonologLog $log) {
                        return $this->render('_counts', ['log' => $log]);
                    },
                ], [
                    'attribute' => 'size',
                    'format' => 'shortSize',
                    'headerOptions' => ['class' => 'sort-ordinal'],
                ], [
                    'attribute' => 'updatedAt',
                    'format' => 'relativeTime',
                    'headerOptions' => ['class' => 'sort-numerical'],
                ], [
                    'class' => ActionColumn::class,
                    'template' => '{history} {view} {rotate} {zip} {download} {delete}',
                    'urlCreator' => static function ($action, MonologLog $log) {
                        return [$action, 'slug' => $log->getSlug()];
                    },
                    'buttons' => [
                        'history' => static function ($url) {
                            return Html::a('History', $url, [
                                'class' => 'btn btn-xs btn-secondary',
                            ]);
                        },
                        'view' => static function ($url, MonologLog $log) {
                            return Html::a('View', $url, [
                                'class' => 'btn btn-xs btn-primary',
                            ]);
                        },
                        'rotate' => static function ($url, MonologLog $log) {
                            return Html::a('Rotate', $url, [
                                'class' => 'btn btn-xs btn-warning',
                                'data' => ['method' => 'post', 'confirm' => 'Начать текущий лог заново? Содержимое уйдёт в историю.'],
                            ]);
                        },
                        'zip' => static function ($url, MonologLog $log) {
                            return Html::a('Zip', $url, [
                                'class' => 'btn btn-xs btn-info',
                                'data' => ['method' => 'post', 'confirm' => 'Упаковать в ZIP-архив?'],
                            ]);
                        },
                        'download' => static function ($url, MonologLog $log) {
                            return Html::a('Download', $url, [
                                'class' => 'btn btn-xs btn-secondary',
                            ]);
                        },
                        'delete' => static function ($url, MonologLog $log) {
                            return Html::a('Delete', array_merge($url, ['since' => $log->getUpdatedAt()]), [
                                'class' => 'btn btn-xs btn-danger',
                                'data' => ['method' => 'post', 'confirm' => 'Are you sure?'],
                            ]);
                        },
                    ],
                ],
            ],
        ]) ?>
    </div>
    <div class="card-footer clearfix">
        <nav aria-label="" class="nav-pagination">
            <?= LinkPager::widget([
                'pagination' => $dataProvider->getPagination(),
            ]) ?>
        </nav>
    </div>
</div>
