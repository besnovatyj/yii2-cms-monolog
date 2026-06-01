<?php


/*
 * Copyright (c) 2026 Besnovatyj. Licensed under the MIT License.
 */

use Besnovatyj\Backend\Widgets\grid\ActionColumn;
use Besnovatyj\Monolog\entities\MonologLog;
use yii\data\ArrayDataProvider;
use yii\grid\GridView;
use yii\helpers\Html;
use yii\web\View;

/**
 * @var View $this
 * @var MonologLog $log
 * @var ArrayDataProvider $dataProvider
 * @var int $fullSize
 */

$this->title = 'History: ' . $log->getName();
$this->params['breadcrumbs'][] = ['label' => 'Monolog Logs', 'url' => ['index']];
$this->params['breadcrumbs'][] = $log->getName();
?>

<div class="monolog-log-history">
    <div class="card">
        <div class="card-header d-md-flex justify-content-md-between">
            <div><?= Html::encode($this->title) ?></div>
            <div class="card-tools">
                <?= Html::a('Back to Logs', ['index'], ['class' => 'btn btn-sm btn-secondary']) ?>
            </div>
        </div>
        <div class="card-body">
            <div class="alert alert-info">
                <strong>Channel:</strong> <?= Html::encode($log->getChannelKey()) ?><br>
                <strong>Total history + zip size:</strong> <?= Yii::$app->formatter->asShortSize($fullSize) ?>
            </div>

            <?= GridView::widget([
                'layout' => '{items}',
                'tableOptions' => ['class' => 'table table-striped'],
                'options' => ['class' => 'grid-view table-responsive'],
                'dataProvider' => $dataProvider,
                'columns' => [
                    [
                        'attribute' => 'name',
                        'format' => 'raw',
                        'value' => static function (MonologLog $log) {
                            return Html::tag('div', join("\n", [
                                Html::tag('strong', Html::encode($log->getName())),
                                '<br/>',
                                Html::tag('small', Html::encode($log->getFilePath()), ['class' => 'text-muted']),
                            ]));
                        },
                    ],
                    [
                        'attribute' => 'size',
                        'format' => 'shortSize',
                        'headerOptions' => ['class' => 'sort-ordinal'],
                    ],
                    [
                        'attribute' => 'updatedAt',
                        'format' => 'relativeTime',
                        'headerOptions' => ['class' => 'sort-numerical'],
                    ],
                    [
                        'class' => ActionColumn::class,
                        'template' => '{view} {zip} {download} {delete}',
                        'urlCreator' => static function ($action, MonologLog $log) {
                            return [$action, 'slug' => $log->getSlug()];
                        },
                        'buttons' => [
                            'view' => static function ($url, MonologLog $log) {
                                if ($log->getIsZip()) {
                                    return '';
                                }
                                return Html::a('View', $url, [
                                    'class' => 'btn btn-xs btn-primary',
                                ]);
                            },
                            'zip' => static function ($url, MonologLog $log) {
                                if ($log->getIsZip()) {
                                    return '';
                                }
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
    </div>
</div>
