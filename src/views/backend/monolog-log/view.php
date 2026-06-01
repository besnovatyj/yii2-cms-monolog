<?php


/*
 * Copyright (c) 2026 Besnovatyj. Licensed under the MIT License.
 */

use Besnovatyj\Monolog\entities\MonologLog;
use yii\helpers\Html;
use yii\helpers\Json;
use yii\web\View;

/**
 * @var View $this
 * @var MonologLog $log
 * @var array $entries
 * @var int $total
 * @var int $pages
 * @var int $currentPage
 * @var int $perPage
 */

$this->title = 'View Monolog Log: ' . $log->getName();
$this->params['breadcrumbs'][] = ['label' => 'Monolog Logs', 'url' => ['index']];
$this->params['breadcrumbs'][] = $log->getName();

// Функция для получения класса badge по уровню логирования
$getLevelClass = function ($level) {
    return match (strtoupper($level)) {
        'DEBUG' => 'bg-secondary',
        'INFO' => 'bg-info',
        'NOTICE' => 'bg-primary',
        'WARNING' => 'bg-warning',
        'ERROR', 'CRITICAL', 'ALERT' => 'bg-danger',
        'EMERGENCY' => 'bg-dark',
        default => 'bg-light text-dark',
    };
};
?>
<p>
    <?= Html::a('Download', ['download', 'slug' => $log->getSlug()], ['class' => 'btn btn-sm btn-secondary']) ?>
    <?php if ($log->getIsCurrent()): ?>
        <?= Html::a('Rotate', ['rotate', 'slug' => $log->getSlug()], [
            'class' => 'btn btn-sm btn-warning',
            'data' => ['method' => 'post', 'confirm' => 'Начать текущий лог заново? Содержимое уйдёт в историю.']
        ]) ?>
    <?php endif; ?>
    <?= Html::a('Zip', ['zip', 'slug' => $log->getSlug()], [
        'class' => 'btn btn-sm btn-info',
        'data' => ['method' => 'post', 'confirm' => 'Упаковать в ZIP-архив?']
    ]) ?>
    <?= Html::a('Delete', ['delete', 'slug' => $log->getSlug(), 'since' => $log->getUpdatedAt()], [
        'class' => 'btn btn-sm btn-danger',
        'data' => ['method' => 'post', 'confirm' => 'Are you sure?']
    ]) ?>
</p>
<div class="monolog-log-view">
    <div class="card">
        <div class="card-header"><?= Html::encode($log->getName()) ?></div>
        <div class="card-body">
            <div class="row mb-3">
                <div class="col-md-6">
                    <p><strong>File path:</strong> <?= Html::encode($log->getFilePath()) ?></p>
                    <p><strong>Size:</strong> <?= Yii::$app->formatter->asShortSize($log->getSize()) ?></p>
                </div>
                <div class="col-md-6">
                    <p><strong>Total entries:</strong> <?= number_format($total) ?></p>
                    <p><strong>Last modified:</strong> <?= Yii::$app->formatter->asRelativeTime($log->getUpdatedAt()) ?>
                    </p>
                </div>
            </div>

            <div class="alert alert-info">
                <strong>Showing:</strong> <?= count($entries) ?> of <?= number_format($total) ?> entries
                (Page <?= $currentPage ?> of <?= $pages ?>)
            </div>

            <?php if (empty($entries)): ?>
                <div class="alert alert-warning">No log entries found.</div>
            <?php else: ?>
                <div class="log-entries">
                    <?php foreach ($entries as $index => $entry): ?>
                        <div class="log-entry card mb-2 <?= isset($entry['parse_error']) ? 'border-danger' : '' ?>">
                            <div class="card-header py-2 <?= $getLevelClass($entry['level_name'] ?? 'UNKNOWN') ?>">
                                <div class="row align-items-center">
                                    <div class="col-auto">
                                        <span
                                            class="badge bg-dark">#<?= ($currentPage - 1) * $perPage + $index + 1 ?></span>
                                    </div>
                                    <div class="col-auto">
                                        <strong><?= Html::encode($entry['level_name'] ?? 'UNKNOWN') ?></strong>
                                    </div>
                                    <div class="col">
                                        <small class="text-white">
                                            <?php if (isset($entry['datetime'])): ?>
                                                <?= Html::encode($entry['datetime']) ?>
                                            <?php endif; ?>
                                        </small>
                                    </div>
                                    <div class="col-auto">
                                        <button class="btn btn-sm btn-light toggle-details" type="button">
                                            <i class="bi bi-chevron-down"></i>
                                        </button>
                                    </div>
                                </div>
                            </div>
                            <?php
                            $message = (string)($entry['message'] ?? '');
                            // Многострочные сообщения (напр. вывод миграций) в списке
                            // схлопываем до первой строки; полный текст — в раскрываемом
                            // блоке .log-details под кнопкой .toggle-details.
                            $messageLines = preg_split('/\r\n|\r|\n/', $message, 2);
                            $messageFirstLine = $messageLines[0];
                            $messageIsMultiline = preg_match('/\r\n|\r|\n/', rtrim($message)) === 1;
                            ?>
                            <div class="card-body">
                                <div class="log-message mb-2">
                                    <strong>Message:</strong>
                                    <pre class="mb-0"><?= Html::encode($messageIsMultiline ? $messageFirstLine . ' …' : $message) ?></pre>
                                    <?php if ($messageIsMultiline): ?>
                                        <small class="text-muted">Полный текст — по кнопке развернуть ↓</small>
                                    <?php endif; ?>
                                </div>

                                <div class="log-details" style="display: none;">
                                    <?php if ($messageIsMultiline): ?>
                                        <div class="log-message mb-2">
                                            <strong>Full message:</strong>
                                            <pre class="mb-0"><?= Html::encode($message) ?></pre>
                                        </div>
                                    <?php endif; ?>

                                    <?php if (isset($entry['context']) && !empty($entry['context'])): ?>
                                        <div class="mb-2">
                                            <strong>Context:</strong>
                                            <pre
                                                class="json-highlight"><code><?= Html::encode(Json::encode($entry['context'], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)) ?></code></pre>
                                        </div>
                                    <?php endif; ?>

                                    <?php if (isset($entry['extra']) && !empty($entry['extra'])): ?>
                                        <div class="mb-2">
                                            <strong>Extra:</strong>
                                            <pre
                                                class="json-highlight"><code><?= Html::encode(Json::encode($entry['extra'], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)) ?></code></pre>
                                        </div>
                                    <?php endif; ?>

                                    <div class="mb-2">
                                        <strong>Full JSON:</strong>
                                        <pre
                                            class="json-highlight"><code><?= Html::encode(Json::encode($entry, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)) ?></code></pre>
                                    </div>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>

        <div class="card-footer">
            <nav aria-label="Log pagination">
                <ul class="pagination mb-0">
                    <?php if ($currentPage > 1): ?>
                        <li class="page-item">
                            <?= Html::a('&laquo; Previous', ['view', 'slug' => $log->getSlug(), 'page' => $currentPage - 1], ['class' => 'page-link']) ?>
                        </li>
                    <?php endif; ?>

                    <?php
                    $startPage = max(1, $currentPage - 2);
                    $endPage = min($pages, $currentPage + 2);
                    ?>

                    <?php if ($startPage > 1): ?>
                        <li class="page-item">
                            <?= Html::a('1', ['view', 'slug' => $log->getSlug(), 'page' => 1], ['class' => 'page-link']) ?>
                        </li>
                        <?php if ($startPage > 2): ?>
                            <li class="page-item disabled"><span class="page-link">...</span></li>
                        <?php endif; ?>
                    <?php endif; ?>

                    <?php for ($i = $startPage; $i <= $endPage; $i++): ?>
                        <li class="page-item <?= $i === $currentPage ? 'active' : '' ?>">
                            <?= Html::a($i, ['view', 'slug' => $log->getSlug(), 'page' => $i], ['class' => 'page-link']) ?>
                        </li>
                    <?php endfor; ?>

                    <?php if ($endPage < $pages): ?>
                        <?php if ($endPage < $pages - 1): ?>
                            <li class="page-item disabled"><span class="page-link">...</span></li>
                        <?php endif; ?>
                        <li class="page-item">
                            <?= Html::a($pages, ['view', 'slug' => $log->getSlug(), 'page' => $pages], ['class' => 'page-link']) ?>
                        </li>
                    <?php endif; ?>

                    <?php if ($currentPage < $pages): ?>
                        <li class="page-item">
                            <?= Html::a('Next &raquo;', ['view', 'slug' => $log->getSlug(), 'page' => $currentPage + 1], ['class' => 'page-link']) ?>
                        </li>
                    <?php endif; ?>
                </ul>
            </nav>
        </div>
    </div>
</div>

<?php
$this->registerCss(<<<CSS
.log-entry {
    transition: all 0.2s;
}
.log-entry:hover {
    box-shadow: 0 2px 8px rgba(0,0,0,0.1);
}
.log-message pre {
    background: #f8f9fa;
    padding: 10px;
    border-radius: 4px;
    white-space: pre-wrap;
    word-wrap: break-word;
}
.json-highlight {
    background: #282c34;
    color: #abb2bf;
    padding: 15px;
    border-radius: 4px;
    overflow-x: auto;
}
.json-highlight code {
    color: #abb2bf;
    font-family: 'Courier New', Courier, monospace;
    font-size: 13px;
}
.card-header.bg-danger,
.card-header.bg-warning {
    color: #fff;
}
.toggle-details {
    transition: transform 0.2s;
}
.toggle-details.active {
    transform: rotate(180deg);
}
CSS
);

$this->registerJs(<<<JS
$(document).on('click', '.toggle-details', function() {
    var btn = $(this);
    var card = btn.closest('.log-entry');
    var details = card.find('.log-details');

    details.slideToggle(200);
    btn.toggleClass('active');
});
JS
);
?>
