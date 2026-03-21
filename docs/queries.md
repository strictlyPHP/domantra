# Queries

## Handler Types

Domantra supports three query handler interfaces:

### `SingleHandlerInterface`

Returns a single aggregate root by ID:

```php
use StrictlyPHP\Domantra\Query\Handlers\SingleHandlerInterface;
use StrictlyPHP\Domantra\Domain\AbstractAggregateRoot;

class GetUserByIdHandler implements SingleHandlerInterface
{
    public function __invoke(object $query): User
    {
        // Fetch from database by $query (a UserId)
        return User::create(/* ... */);
    }
}
```

The query object must implement `\Stringable` (used as a cache key).

### `PaginatedHandlerInterface`

Returns a `PaginatedIdCollection` of ID objects. Each ID is then resolved through its registered `SingleHandlerInterface`:

```php
use StrictlyPHP\Domantra\Query\Handlers\PaginatedHandlerInterface;
use StrictlyPHP\Domantra\Domain\PaginatedIdCollection;

class ListUsersHandler implements PaginatedHandlerInterface
{
    public function __invoke(object $query): PaginatedIdCollection
    {
        // Query database for user IDs
        $userIds = [new UserId('user-1'), new UserId('user-2')];

        return new PaginatedIdCollection(
            ids: $userIds,
            page: $query->page,
            perPage: $query->perPage,
            totalItems: 50
        );
    }
}
```

### `DtoHandlerHandlerInterface`

Returns a `CachedDtoInterface` directly, bypassing aggregate reconstruction. Used for lightweight lookups and DTO expansion:

```php
use StrictlyPHP\Domantra\Query\Handlers\DtoHandlerHandlerInterface;
use StrictlyPHP\Domantra\Domain\CachedDtoInterface;

class GetUserDtoHandler implements DtoHandlerHandlerInterface
{
    public function __invoke(object $query): UserDto
    {
        // Lightweight fetch, return DTO directly
        return new UserDto(/* ... */);
    }
}
```

## `QueryBus`

### Creating a Bus

```php
use StrictlyPHP\Domantra\Query\QueryBus;
use StrictlyPHP\Domantra\Query\AggregateRootHandler;
use StrictlyPHP\Domantra\Query\CachedDtoHandler;

$cacheHandler = new DtoCacheHandlerInMemory();

$queryBus = new QueryBus(
    new AggregateRootHandler($cacheHandler),
    new CachedDtoHandler($cacheHandler)
);
```

### Registering Handlers

```php
// Single entity lookup
$queryBus->registerHandler(UserId::class, new GetUserByIdHandler());

// Paginated list
$queryBus->registerHandler(ListUsersQuery::class, new ListUsersHandler());

// DTO handler with expansion enabled
$queryBus->registerHandler(
    TeamId::class,
    new GetTeamDtoHandler(),
    allowExpansion: true
);
```

### Handling Queries

```php
$response = $queryBus->handle(new UserId('user-123'));
// Returns ModelResponse with $response->item (stdClass)

$response = $queryBus->handle(new ListUsersQuery(page: 1, perPage: 10));
// Returns PaginatedModelResponse with $response->items, $response->page, etc.
```

### Response Types

- `ModelResponse` — wraps a single `stdClass` item. `getCode()` returns `200`.
- `PaginatedModelResponse` — wraps an array of items with `page`, `perPage`, and `totalItems`. Both implement `ResponseInterface` and `JsonSerializable`.

### Role-Based Filtering

Pass a role string to filter DTO properties based on `RequiresAuthenticatedUser` attributes:

```php
$response = $queryBus->handle(new UserId('user-123'), role: 'admin');
```

See [Role-Based Access](role-based-access.md).

## DTO Expansion

When a DTO property contains an object (e.g., a `TeamId`), the query bus can automatically resolve it to its full DTO representation.

To enable expansion for a handler, set `allowExpansion: true` when registering:

```php
$queryBus->registerHandler(TeamId::class, new GetTeamByIdHandler(), allowExpansion: true);
```

When a `UserDto` has a `public TeamId $team` property, the query bus will:
1. Detect the `TeamId` object
2. Find a registered handler for `TeamId`
3. Resolve it to the team's DTO (via `SingleHandlerInterface` or `DtoHandlerHandlerInterface`)
4. Keep the original property unchanged and add the expanded data as a **new property**

### Expanded Property Naming

The new property name is derived from the original property name:

- If the property name ends in `Id` (case-sensitive), the `Id` suffix is stripped: `teamId` → `team`
- Otherwise, `Expanded` is appended: `team` → `teamExpanded`

For example, given `public TeamId $teamId`, the result will contain both `teamId` (the original `TeamId` object) and `team` (the expanded DTO).

Given `public TeamId $team`, the result will contain both `team` (the original `TeamId` object) and `teamExpanded` (the expanded DTO).

The `id` property is excluded from expansion.
