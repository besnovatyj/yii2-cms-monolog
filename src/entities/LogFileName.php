<?php


/*
 * Copyright (c) 2026 Besnovatyj. Licensed under the MIT License.
 */

namespace Besnovatyj\Monolog\entities;

/**
 * Детерминированный разбор имени лог-файла Monolog.
 *
 * Имя раскладывается по схеме:
 *
 *     <base>[-<Y-m-d>][.<YmdHis>].log[.<N>][.zip]
 *
 * где:
 * - base         — логический ключ канала/группы (monolog, monolog-auth, ...);
 * - rotateDate   — дата суточной ротации Monolog RotatingFileHandler (Y-m-d);
 * - archiveStamp — метка ручной ротации «в историю» (YmdHis), её ставит
 *                  принудительная ротация (copy+truncate);
 * - numIndex     — индекс нумерной ротации (на случай чужих файлов рядом);
 * - .zip         — упакованный архив.
 *
 * Классификация (без хрупкого сопоставления «сегодня» с целым путём):
 * - zip      — имя оканчивается на .zip;
 * - history  — не zip И (есть archiveStamp ИЛИ есть numIndex ИЛИ
 *              rotateDate < сегодня);
 * - current  — не zip И нет archiveStamp/numIndex И
 *              (rotateDate отсутствует ИЛИ rotateDate == сегодня).
 *
 * «История канала X» = все не-current файлы с тем же base.
 */
final class LogFileName
{
    public const KIND_CURRENT = 'current';
    public const KIND_HISTORY = 'history';
    public const KIND_ZIP = 'zip';

    private string $base;
    private ?string $rotateDate;
    private ?string $archiveStamp;
    private ?int $numIndex;
    private bool $isZip;
    private string $kind;

    private function __construct(
        string $base,
        ?string $rotateDate,
        ?string $archiveStamp,
        ?int $numIndex,
        bool $isZip,
        string $kind
    ) {
        $this->base = $base;
        $this->rotateDate = $rotateDate;
        $this->archiveStamp = $archiveStamp;
        $this->numIndex = $numIndex;
        $this->isZip = $isZip;
        $this->kind = $kind;
    }

    /**
     * Похоже ли имя на лог-файл, которым управляет этот модуль.
     * Модуль Monolog работает только со своей конвенцией: имя начинается с
     * "monolog" и содержит ".log" (опц. нумерная ротация и/или .zip).
     */
    public static function looksLikeLog(string $filename): bool
    {
        return (bool)preg_match('/^monolog.*\.log(\.\d+)?(\.zip)?$/i', $filename);
    }

    /**
     * Разбирает имя файла. $today — дата отсчёта (Y-m-d), по умолчанию сегодня.
     */
    public static function parse(string $filename, ?string $today = null): self
    {
        $today ??= date('Y-m-d');

        $name = $filename;

        $isZip = false;
        if (preg_match('/\.zip$/i', $name)) {
            $isZip = true;
            $name = substr($name, 0, -4);
        }

        $numIndex = null;
        if (preg_match('/\.log\.(\d+)$/', $name, $m)) {
            $numIndex = (int)$m[1];
            $name = substr($name, 0, -(strlen($m[1]) + 1));
        }

        // Снимаем .log; если его нет — это не наш лог, base = всё имя.
        $core = preg_match('/\.log$/', $name)
            ? substr($name, 0, -4)
            : $name;

        $archiveStamp = null;
        if (preg_match('/^(.*)\.(\d{14})$/', $core, $m)) {
            $core = $m[1];
            $archiveStamp = $m[2];
        }

        $rotateDate = null;
        if (preg_match('/^(.*)-(\d{4}-\d{2}-\d{2})$/', $core, $m)) {
            $core = $m[1];
            $rotateDate = $m[2];
        }

        $base = $core;

        if ($isZip) {
            $kind = self::KIND_ZIP;
        } elseif ($numIndex !== null || $archiveStamp !== null) {
            $kind = self::KIND_HISTORY;
        } elseif ($rotateDate !== null && $rotateDate < $today) {
            $kind = self::KIND_HISTORY;
        } else {
            $kind = self::KIND_CURRENT;
        }

        return new self($base, $rotateDate, $archiveStamp, $numIndex, $isZip, $kind);
    }

    /** Логический ключ канала/группы (для группировки истории). */
    public function getBase(): string
    {
        return $this->base;
    }

    public function getRotateDate(): ?string
    {
        return $this->rotateDate;
    }

    public function getArchiveStamp(): ?string
    {
        return $this->archiveStamp;
    }

    public function getNumIndex(): ?int
    {
        return $this->numIndex;
    }

    public function getIsZip(): bool
    {
        return $this->isZip;
    }

    public function getKind(): string
    {
        return $this->kind;
    }

    public function isCurrent(): bool
    {
        return $this->kind === self::KIND_CURRENT;
    }

    public function isHistory(): bool
    {
        return $this->kind === self::KIND_HISTORY;
    }
}
