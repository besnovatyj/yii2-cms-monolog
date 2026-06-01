# yii2-cms-monolog — просмотр и управление Monolog-логами

Модуль-вьювер логов, которые пишет Monolog в каталог `@runtime/logs`. Работает
**только** со своей конвенцией имён (`monolog*`). Для всех логов приложения —
отдельный модуль `besnovatyj/yii2-cms-logs` (та же логика, более широкий охват).

> Примечание: файл `MONOLOG_LOGS_README.md` в этом каталоге описывает старое
> поведение и устарел — актуальная логика здесь.

## Откуда берутся файлы (контракт с писателями)

Логи пишет `common\components\log\MonologTarget` через
`common\components\log\HandlerFactory` (хендлер `rotating_file`, JSON-формат —
одна запись на строку, `BATCH_MODE_NEWLINES`). Каналы объявляются декларативно
в `app/common/config/log.php` (core: `app`, `auth`, `modman`) и в
`Module::getLogChannels()` устанавливаемых модулей (modman собирает их в
`@config-dyn-gen/logChannelsConfigFile.php`).

`RotatingFileHandler` **всегда** пишет в датированный файл — статического
«текущего» файла не существует. «Текущий» = файл с сегодняшней датой.

## Конвенция имён файлов

```
<base>[-<Y-m-d>][.<YmdHis>].log[.zip]
```

- `base` — логический ключ канала: `monolog`, `monolog-auth`, `monolog-modman`.
  Конвенция: `monolog-{channel}.log` → при ротации `monolog-{channel}-Y-m-d.log`;
  глобальный канал — `monolog.log` → `monolog-Y-m-d.log`.
- `-<Y-m-d>` — суточная ротация Monolog.
- `.<YmdHis>` — метка принудительной ротации «в историю» (см. Rotate).
- `.zip` — ZIP-архив.

## Классификация: `entities/LogFileName`

Детерминированный парсер имени (а не набор хрупких регэкспов). Раскладывает имя
на `base`, `rotateDate`, `archiveStamp`, `isZip` и определяет вид:

- **zip** — имя оканчивается на `.zip`;
- **history** — есть `archiveStamp` ИЛИ `rotateDate < сегодня`;
- **current** — иначе (даты нет или она == сегодня).

`getBase()` — ключ канала; по нему группируется история (НЕ по slug).
`looksLikeLog()` — фильтр каталога: только `monolog*…\.log[.zip]`.

## Слои

- **`entities/MonologLog`** — обёртка над файлом. `getKind()`, `getChannelKey()`,
  `getIsCurrent()`, `getIsZip()`, чтение записей с пагинацией, подсчёт уровней
  (`getCounts` — по `level_name` JSON; для zip возвращает пусто). `slug` =
  `Inflector::slug(имя_файла)` — стабилен per-file, в т.ч. для zip.
- **`repositories/MonologFileLogRepository`** — один проход по каталогу,
  фильтрация по `kind`. `getBySlug()` ищет среди **всех** файлов любого вида.
  `findHistoryBySlug()` — все НЕ-current файлы того же канала (история + zip).
- **`services/MonologLogManageService`** — бизнес-операции (ниже).
- **`controllers/backend/MonologLogController`** + views.

## Страницы и действия

| Страница | Содержимое | Действия |
|---|---|---|
| `index` | текущие файлы (по одному на канал) | History, View, Rotate, Zip, Download, Delete |
| `index-history` | ротированные/принудительно ротированные `.log` | View, Zip, Download, Delete |
| `index-zip` | только `.zip` | Download, Delete |
| `history?slug=` | история конкретного канала (история + zip) | View, Zip, Download, Delete (View/Zip скрыты для zip) |
| `view?slug=` | просмотр записей с пагинацией (JSON) | Download, Rotate (если current), Zip, Delete |

Download доступен для файла на любой стадии.

## Rotate и Zip — механизм copy+truncate

В текущий файл прямо сейчас пишет открытый дескриптор Monolog, поэтому:

- **Rotate** (только текущий файл): копировать в `<имя>.<YmdHis>.log` (станет
  history), затем **обнулить** текущий. Дескриптор Monolog продолжает писать в
  тот же inode (теперь пустой) — записи не теряются и не «разъезжаются».
- **Zip**: упаковать в `<имя>.zip`. Для **текущего** файла оригинал не
  удаляется, а обнуляется (та же причина). Для остальных — упаковать и удалить.

## Связанные пакеты

`common\components\log\MonologTarget`, `common\components\log\HandlerFactory`,
`app/common/config/log.php`, `modules/modman` (сборщик каналов модулей).
