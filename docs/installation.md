# Installation

## Requirements

- PHP 8.2 or higher
- Composer 2.0+

## Install via Composer

```bash
composer require strictlyphp/domantra
```

## Dependencies

Domantra requires `predis/predis` ^3.0, which is installed automatically with the package. This powers the `DtoCacheHandlerPredis` cache backend.

For the phpredis extension-based cache (`DtoCacheHandlerRedis`), install the extension separately:

```bash
pecl install redis
```

The in-memory cache (`DtoCacheHandlerInMemory`) requires no additional dependencies and is suitable for testing or single-request lifecycles.
