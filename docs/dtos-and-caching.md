# DTOs & Caching

## `CachedDtoInterface`

Every aggregate root must expose a DTO implementing this interface:

```php
use StrictlyPHP\Domantra\Domain\CachedDtoInterface;

readonly class UserDto implements CachedDtoInterface
{
    public function __construct(
        public UserId $id,
        public string $username,
        public string $email
    ) {}

    public function getCacheKey(): string
    {
        return (string) $this->id;
    }

    public function getTtl(): int
    {
        return 3600; // seconds
    }
}
```

- `getCacheKey()` — unique identifier for this entity (typically the string representation of its ID)
- `getTtl()` — cache time-to-live in seconds

## Aggregate `getDto()` Return Type

The `getDto()` method on your aggregate root must declare a **concrete** return type, not `CachedDtoInterface`:

```php
// Correct
public function getDto(): UserDto { ... }

// Wrong — will throw a RuntimeException
public function getDto(): CachedDtoInterface { ... }
```

The query bus uses reflection on this return type to resolve cache lookups.

## Cache Key Format

Cache keys are generated automatically:

```
resource-key:{class}:{cacheKey}:{fingerprint}
```

- `{class}` — the DTO class name with `\` replaced by `/`
- `{cacheKey}` — from `getCacheKey()`
- `{fingerprint}` — SHA256 hash of the DTO's property names and types

The fingerprint ensures cache invalidation when DTO properties change (e.g., adding or removing a field).

## Cache Implementations

### `DtoCacheHandlerInterface`

```php
interface DtoCacheHandlerInterface
{
    public function get(string $cacheKey, string $class): ?CachedDtoInterface;
    public function set(CachedDtoInterface $dto): void;
    public function delete(string $id, string $class): void;
}
```

### Available Implementations

| Class | Backend | Dependency |
|-------|---------|------------|
| `DtoCacheHandlerInMemory` | PHP array (per-request) | None |
| `DtoCacheHandlerRedis` | phpredis extension | `ext-redis` |
| `DtoCacheHandlerPredis` | Predis library | `predis/predis` (required) |

All three extend `AbstractDtoCacheHandler` which provides the key generation and fingerprinting logic.

## Caching Flow

1. **Command dispatch**: After a handler returns an aggregate, the `CommandBus` calls `$aggregate->getDto()` and passes it to `$cacheHandler->set()`
2. **Query dispatch**: The `AggregateRootHandler` first checks `$cacheHandler->get()`. On a hit, it returns the cached DTO. On a miss, it invokes the handler, caches the result, then returns it
