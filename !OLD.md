# Monolog Logs Viewer - Документация

## Обзор

Модуль для просмотра логов Monolog в красивом формате с поддержкой JSON подсветки, пагинации и управления файлами.

## Функционал

### 1. Просмотр логов
- **JSON формат**: Все логи записываются в JSON формате (настроено в `app/common/config/components.php`)
- **Пагинация**: Большие файлы загружаются частями (по 100 записей на страницу)
- **Подсветка**: Красивая подсветка JSON с раскрытием деталей
- **Цветовая индикация**: Уровни логирования (DEBUG, INFO, WARNING, ERROR и т.д.) выделены цветом

### 2. Управление файлами
- **Просмотр** списка всех лог-файлов (текущих и ротированных)
- **Архивирование** - переименование файла с добавлением timestamp
- **ZIP архивация** - сжатие лог-файла в ZIP
- **Удаление** файлов с подтверждением
- **Скачивание** файлов
- **История ротации** - просмотр всех ротированных версий файла

### 3. Статистика
- Подсчет количества записей по каждому уровню логирования
- Отображение размера файлов
- Последнее время обновления

## Структура компонентов

```
app/modules/settings/
├── entities/
│   └── MonologLog.php                    # Сущность лог-файла
├── repositories/
│   └── MonologFileLogRepository.php      # Работа с файловой системой
├── services/
│   └── MonologLogManageService.php       # Бизнес-логика
├── controllers/backend/
│   └── MonologLogController.php          # Контроллер
└── views/backend/monolog-log/
    ├── index.php                         # Список основных логов
    ├── index-history.php                 # Список ротированных логов
    ├── index-zip.php                     # Список ZIP архивов
    ├── view.php                          # Просмотр лога с подсветкой
    ├── history.php                       # История ротации
    └── _counts.php                       # Счётчики уровней
```

## Как это работает

### Запись логов (JSON формат)

Настроено в `app/common/config/components.php`:

```php
$fileHandler->setFormatter(new \Monolog\Formatter\JsonFormatter(
    \Monolog\Formatter\JsonFormatter::BATCH_MODE_NEWLINES,
    true, // includeStacktraces
    true  // ignoreEmptyContextAndExtra
));
```

Каждая строка в файле - это отдельный JSON объект:

```json
{
  "message": "User logged in",
  "context": {"userId": 123},
  "level": 200,
  "level_name": "INFO",
  "channel": "yii2-cms",
  "datetime": "2026-01-18T10:30:56.789012+00:00",
  "extra": {...}
}
```

### Чтение логов (пагинация)

`MonologLog::getRecentEntries($limit, $offset)`:
- Читает файл построчно
- Возвращает последние N записей
- Декодирует JSON для каждой строки
- Не загружает весь файл в память

### Ротация файлов

**ВАЖНО:** `RotatingFileHandler` НЕ создаёт базовый файл без даты! Он сразу создаёт файлы с датой:
- `monolog-2026-01-18.log` - текущий файл (сегодняшний)
- `monolog-2026-01-17.log` - вчерашний файл
- `monolog-2026-01-16.log` - позавчерашний файл

Формат имени файла: `{filename}-{date}` где date по умолчанию = `Y-m-d`

## Использование в админке

1. **Главное меню** → Settings → Monolog Logs
2. **Просмотр списка** - все текущие лог-файлы
3. **Клик View** - просмотр содержимого с подсветкой
4. **Пагинация** - навигация по страницам (100 записей на страницу)
5. **Раскрытие деталей** - клик на стрелку для просмотра context, extra и full JSON

## Производительность

- **JSON парсинг** быстрее регулярных выражений
- **Постраничная загрузка** - не грузит весь файл сразу
- **Кэширование счётчиков** - подсчёт уровней кэшируется с зависимостью от файла
- **Без консольных команд** - всё работает через PHP

## API эндпоинты

### Основные действия
- `GET /settings/backend/monolog-log/index` - список логов
- `GET /settings/backend/monolog-log/view?slug=X&page=1` - просмотр
- `POST /settings/backend/monolog-log/archive?slug=X` - архивировать
- `POST /settings/backend/monolog-log/delete?slug=X` - удалить
- `GET /settings/backend/monolog-log/download?slug=X` - скачать
- `POST /settings/backend/monolog-log/zip?slug=X` - создать ZIP

### AJAX
- `GET /settings/backend/monolog-log/get-entries?slug=X&page=1` - JSON API для записей

## Безопасность

- Проверка timestamp при удалении (защита от race conditions)
- Подтверждение удаления/архивации
- Валидация путей к файлам
- Защита от directory traversal
