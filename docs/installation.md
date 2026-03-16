# Installation

## Requirements

- PHP 8.2 or higher
- Composer 2.0+

## Install via Composer

```bash
composer require strictlyphp/domantra
```

## Optional Dependencies

For Redis-based DTO caching:

```bash
# Using phpredis extension
pecl install redis

# Or using Predis
composer require predis/predis
```

The default in-memory cache (`DtoCacheHandlerInMemory`) requires no additional dependencies and is suitable for testing or single-request lifecycles.
