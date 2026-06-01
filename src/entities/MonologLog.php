<?php


/*
 * Copyright (c) 2026 Besnovatyj. Licensed under the MIT License.
 */

namespace Besnovatyj\Monolog\entities;

use SplFileInfo;
use Yii;
use yii\base\BaseObject;
use yii\caching\FileDependency;
use yii\helpers\Inflector;
use yii\helpers\StringHelper;

/**
 * Сущность для представления Monolog лог-файла
 */
class MonologLog extends BaseObject
{
    private $_file;
    private $_slug;
    private $_size;
    private $_dateTimeStamp;
    private LogFileName $_parsed;

    /**
     * @param SplFileInfo $file
     * @param string|null $dateTimeStamp
     * @param array $config
     */
    public function __construct(SplFileInfo $file, ?string $dateTimeStamp = null, array $config = [])
    {
        $this->_file = $file;
        $this->_slug = Inflector::slug($file->getFilename());
        $this->_size = $file->getSize();
        $this->_dateTimeStamp = $dateTimeStamp;
        $this->_parsed = LogFileName::parse($file->getFilename());
        parent::__construct($config);
    }

    /** Разобранное имя файла (классификация, ключ канала). */
    public function getParsed(): LogFileName
    {
        return $this->_parsed;
    }

    /** Логический ключ канала/группы (для истории). */
    public function getChannelKey(): string
    {
        return $this->_parsed->getBase();
    }

    /** current | history | zip */
    public function getKind(): string
    {
        return $this->_parsed->getKind();
    }

    public function getIsCurrent(): bool
    {
        return $this->_parsed->isCurrent();
    }

    /**
     * Full filename with extension
     */
    public function getName(): string
    {
        return $this->_file->getFilename();
    }

    /**
     * Filename without extension, but with dot at the end
     */
    public function getBaseName(): string
    {
        return $this->_file->getBasename($this->getExtension());
    }

    /**
     * Filename extension
     */
    public function getExtension(): string
    {
        return $this->_file->getExtension();
    }

    /**
     * Path to the file without filename
     */
    public function getPath(): string
    {
        return $this->_file->getPath();
    }

    public function getSlug(): string
    {
        return $this->_slug;
    }

    /**
     * Path to the file with filename and extension
     */
    public function getFilePath(): string
    {
        return $this->_file->getPathname();
    }

    public function getDateTimeStamp(): ?string
    {
        return $this->_dateTimeStamp;
    }

    public function getIsZip(): bool
    {
        return StringHelper::endsWith($this->getFilePath(), '.zip');
    }

    public function getSize(): int
    {
        return $this->_size;
    }

    public function getUpdatedAt(): int
    {
        return $this->_file->getMTime();
    }

    public function getDownloadName(): string
    {
        return $this->getName();
    }

    /**
     * Подсчет сообщений каждого из возможных уровней важности из JSON логов
     *
     * @param bool $force
     * @return array - ['ERROR' => 3, 'INFO' => 5, ...]
     */
    public function getCounts(bool $force = false): array
    {
        if ($this->_parsed->getIsZip()) {
            return [];
        }

        $key = $this->getFilePath() . '#monolog-counts';
        if (!$force && ($counts = Yii::$app->cache->get($key)) !== false) {
            return $counts;
        }

        $counts = [];
        if ($h = fopen($this->_file->getPathname(), 'r')) {
            while (($line = fgets($h)) !== false) {
                $line = trim($line);
                if (empty($line)) {
                    continue;
                }

                // Декодируем JSON строку
                $logEntry = json_decode($line, true);
                if (json_last_error() === JSON_ERROR_NONE && isset($logEntry['level_name'])) {
                    $level = $logEntry['level_name'];
                    if (!isset($counts[$level])) {
                        $counts[$level] = 0;
                    }
                    $counts[$level]++;
                }
            }
            fclose($h);
            Yii::$app->cache->set($key, $counts, null, new FileDependency([
                'fileName' => $this->getFilePath(),
            ]));
        }

        return $counts;
    }

    /**
     * Читает последние N записей из лог-файла (для пагинации больших файлов)
     *
     * @param int $limit - количество записей
     * @param int $offset - смещение с конца файла
     * @return array - массив декодированных JSON записей
     */
    public function getRecentEntries(int $limit = 100, int $offset = 0): array
    {
        $entries = [];
        $allLines = [];

        if ($h = fopen($this->_file->getPathname(), 'r')) {
            while (($line = fgets($h)) !== false) {
                $line = trim($line);
                if (!empty($line)) {
                    $allLines[] = $line;
                }
            }
            fclose($h);
        }

        // Получаем записи с конца файла с учетом offset
        $totalLines = count($allLines);
        $start = max(0, $totalLines - $offset - $limit);
        $end = max(0, $totalLines - $offset);
        $selectedLines = array_slice($allLines, $start, $end - $start);

        // Декодируем JSON для каждой строки
        foreach (array_reverse($selectedLines) as $line) {
            $logEntry = json_decode($line, true);
            if (json_last_error() === JSON_ERROR_NONE) {
                $entries[] = $logEntry;
            } else {
                // Если не удалось декодировать, добавляем как есть
                $entries[] = [
                    'message' => $line,
                    'level_name' => 'UNKNOWN',
                    'datetime' => null,
                    'parse_error' => true
                ];
            }
        }

        return $entries;
    }

    /**
     * Получает общее количество записей в логе
     *
     * @return int
     */
    public function getTotalEntries(): int
    {
        $count = 0;
        if ($h = fopen($this->_file->getPathname(), 'r')) {
            while (($line = fgets($h)) !== false) {
                if (!empty(trim($line))) {
                    $count++;
                }
            }
            fclose($h);
        }
        return $count;
    }
}
