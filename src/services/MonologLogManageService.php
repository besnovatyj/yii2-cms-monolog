<?php


/*
 * Copyright (c) 2026 Besnovatyj. Licensed under the MIT License.
 */

namespace Besnovatyj\Monolog\services;

use Besnovatyj\Monolog\entities\MonologLog;
use Besnovatyj\Monolog\repositories\MonologFileLogRepository;
use DomainException;
use Exception;
use RuntimeException;
use yii\data\ArrayDataProvider;
use ZipArchive;

/**
 * Сервис управления Monolog логами.
 */
class MonologLogManageService
{
    public MonologFileLogRepository|null $repo;

    public function __construct(MonologFileLogRepository $repo)
    {
        $this->repo = $repo;
    }

    public function getDataProvider($target = null): ArrayDataProvider
    {
        $models = match ($target) {
            'all' => $this->repo->findLogsMain(),
            'history' => $this->repo->findLogsHistory(),
            'zip' => $this->repo->findLogsZip(),
            default => throw new DomainException("Target '$target' is not a valid target type"),
        };

        return new ArrayDataProvider([
            'allModels' => $models,
            'sort' => [
                'attributes' => [
                    'name',
                    'size' => ['default' => SORT_DESC],
                    'updatedAt' => ['default' => SORT_DESC],
                ],
            ],
            'pagination' => ['pageSize' => 0],
        ]);
    }

    /**
     * Принудительная ротация текущего файла: начать текущий лог заново,
     * уведя содержимое в историю.
     *
     * Механизм copy+truncate: открытый дескриптор Monolog продолжает писать
     * в тот же inode (теперь пустой) — записи не теряются и не «разъезжаются».
     * Снимок уходит в файл с меткой YmdHis (классифицируется как history).
     *
     * @throws RuntimeException
     */
    public function rotate(string $slug): void
    {
        $log = $this->repo->getBySlug($slug);
        if (!$log->getIsCurrent()) {
            throw new DomainException('Ротировать можно только текущий файл.');
        }

        $path = $log->getFilePath();
        $name = $log->getName();

        // Имя снимка: <имя-без-.log>.<YmdHis>.log
        $stem = preg_match('/\.log$/', $name) ? substr($name, 0, -4) : $name;
        $target = $log->getPath() . '/' . $stem . '.' . date('YmdHis') . '.log';

        if (!copy($path, $target)) {
            throw new RuntimeException('Rotate error: copy failed');
        }
        if (file_put_contents($path, '') === false) {
            throw new RuntimeException('Rotate error: truncate failed');
        }
    }

    /**
     * Упаковать файл в ZIP. Любой файл, в т.ч. текущий.
     *
     * Для текущего файла оригинал не удаляется, а обнуляется (copy+truncate-
     * семантика), чтобы не рвать открытый дескриптор Monolog. Для остальных —
     * оригинал удаляется после упаковки.
     *
     * @throws Exception
     */
    public function zip(string $slug): void
    {
        $log = $this->repo->getBySlug($slug);
        $path = $log->getFilePath();

        $zip = new ZipArchive();
        if ($zip->open($path . '.zip', ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
            throw new DomainException('Cannot open zipFile, do you have permission?');
        }
        $zip->addFile($path, basename($path));
        $zip->close();

        if ($log->getIsCurrent()) {
            if (file_put_contents($path, '') === false) {
                throw new RuntimeException('Zip error: truncate failed');
            }
        } else {
            $this->repo->deleteFile($path);
        }
    }

    /**
     * История канала, к которому относится файл $slug.
     *
     * @return array{log: MonologLog, fullSize: int, data_provider: ArrayDataProvider}
     * @throws Exception
     */
    public function getHistory(string $slug): array
    {
        $data = [];
        $data['log'] = $this->repo->getBySlug($slug);
        $allLogs = $this->repo->findHistoryBySlug($slug);

        $fullSize = 0;
        foreach ($allLogs as $log) {
            $fullSize += $log->getSize();
        }
        $data['fullSize'] = $fullSize;

        $data['data_provider'] = new ArrayDataProvider([
            'allModels' => $allLogs,
            'sort' => [
                'attributes' => [
                    'name',
                    'size' => ['default' => SORT_DESC],
                    'updatedAt' => ['default' => SORT_DESC],
                ],
                'defaultOrder' => ['updatedAt' => SORT_DESC],
            ],
            'pagination' => ['pageSize' => 0],
        ]);

        return $data;
    }

    /**
     * @throws Exception
     */
    public function find(string $slug): MonologLog
    {
        return $this->repo->getBySlug($slug);
    }

    /**
     * @throws Exception
     */
    public function deleteLog($slug, $since): bool
    {
        $log = $this->repo->getBySlug($slug);
        if (isset($since) && ($log->getUpdatedAt() != $since)) {
            throw new Exception('Delete error: file has updated.');
        }
        $this->repo->deleteFile($log->getFilePath());
        return true;
    }

    /**
     * Записи лога с пагинацией.
     *
     * @return array{entries: array, total: int, pages: int, currentPage: int, perPage: int}
     * @throws Exception
     */
    public function getLogEntries(string $slug, int $page = 1, int $perPage = 100): array
    {
        $log = $this->repo->getBySlug($slug);
        $total = $log->getTotalEntries();
        $pages = (int)ceil($total / $perPage);

        $offset = ($page - 1) * $perPage;
        $entries = $log->getRecentEntries($perPage, $offset);

        return [
            'entries' => $entries,
            'total' => $total,
            'pages' => $pages,
            'currentPage' => $page,
            'perPage' => $perPage,
        ];
    }
}
