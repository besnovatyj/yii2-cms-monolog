<?php


/*
 * Copyright (c) 2026 Besnovatyj. Licensed under the MIT License.
 */

namespace Besnovatyj\Monolog\repositories;

use Besnovatyj\Monolog\entities\LogFileName;
use Besnovatyj\Monolog\entities\MonologLog;
use Exception;
use FilesystemIterator;
use InvalidArgumentException;
use Yii;

/**
 * Репозиторий Monolog лог-файлов.
 *
 * Единый источник истины — один проход по каталогу @runtime/logs;
 * вся классификация (current | history | zip) делается детерминированным
 * парсером {@see LogFileName}, а не набором хрупких регэкспов.
 */
class MonologFileLogRepository
{
    public string|false $logsPath;

    public function __construct()
    {
        $this->logsPath = Yii::getAlias('@runtime/logs');
    }

    /**
     * Все лог-файлы модуля (current + history + zip), один скан каталога.
     *
     * @return MonologLog[]
     */
    public function findLogsAll(): array
    {
        $out = [];
        if (!is_dir($this->logsPath)) {
            return $out;
        }
        foreach (new FilesystemIterator($this->logsPath) as $file) {
            if (!$file->isFile() || !LogFileName::looksLikeLog($file->getFilename())) {
                continue;
            }
            $out[] = new MonologLog($file);
        }
        return $out;
    }

    /**
     * Текущие (активные) файлы — по одному на канал.
     *
     * @return MonologLog[]
     */
    public function findLogsMain(): array
    {
        return array_values(array_filter(
            $this->findLogsAll(),
            static fn(MonologLog $log) => $log->getKind() === LogFileName::KIND_CURRENT
        ));
    }

    /**
     * Ротированные/принудительно ротированные файлы (не zip), доступны онлайн.
     *
     * @return MonologLog[]
     */
    public function findLogsHistory(): array
    {
        return array_values(array_filter(
            $this->findLogsAll(),
            static fn(MonologLog $log) => $log->getKind() === LogFileName::KIND_HISTORY
        ));
    }

    /**
     * ZIP-архивы.
     *
     * @return MonologLog[]
     */
    public function findLogsZip(): array
    {
        return array_values(array_filter(
            $this->findLogsAll(),
            static fn(MonologLog $log) => $log->getKind() === LogFileName::KIND_ZIP
        ));
    }

    /**
     * Файл по стабильному slug (ищется среди всех файлов любого вида).
     *
     * @throws NotFoundException
     */
    public function getBySlug(string $slug): MonologLog
    {
        foreach ($this->findLogsAll() as $log) {
            if ($log->getSlug() === $slug) {
                return $log;
            }
        }
        throw new NotFoundException('File not found.');
    }

    /**
     * История канала, к которому принадлежит файл $slug:
     * все его НЕ-current файлы (ротация + zip) того же логического канала.
     *
     * @return MonologLog[]
     * @throws NotFoundException
     */
    public function findHistoryBySlug(string $slug): array
    {
        $target = $this->getBySlug($slug);
        $channel = $target->getChannelKey();

        $selected = [];
        foreach ($this->findLogsAll() as $log) {
            if ($log->getChannelKey() === $channel
                && $log->getKind() !== LogFileName::KIND_CURRENT
            ) {
                $selected[] = $log;
            }
        }
        return $selected;
    }

    /**
     * @throws Exception
     */
    public function deleteFile(string $filePath): bool
    {
        if (!is_file($filePath)) {
            throw new InvalidArgumentException('Файл не существует.');
        }
        if (!unlink($filePath)) {
            throw new Exception('Не удалось удалить файл: ' . $filePath);
        }
        return true;
    }
}
