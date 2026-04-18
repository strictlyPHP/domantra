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
    ExpansionPolicy::ByDefault
);
```

### Handling Queries

```php
$response = $queryBus->handle(new UserId('user-123'));
// Returns ModelResponse with $response->item (stdClass)

$response = $queryBus->handle(new ListUsersQuery(page: 1, perPage: 10));
// Returns PaginatedModelResponse with $response->items, $response->page, etc.
```

`handle()` accepts two further optional arguments: `role` (see [Role-Based Filtering](#role-based-filtering)) and `expand` (see [Selective Expansion](#selective-expansion)).

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

To enable expansion for a handler, pass an `ExpansionPolicy` when registering:

```php
use StrictlyPHP\Domantra\Query\ExpansionPolicy;

// Expand automatically whenever the TeamId appears in a response DTO
// and the caller has not explicitly narrowed the expand list.
$queryBus->registerHandler(TeamId::class, new GetTeamByIdHandler(), ExpansionPolicy::ByDefault);

// Register for expansion but require each call site to opt in by naming
// the source-DTO property in the `$expand` list. Useful when the same
// value object appears in DTOs where expansion is not wanted.
$queryBus->registerHandler(TeamId::class, new GetTeamByIdHandler(), ExpansionPolicy::OnRequest);
```

`ExpansionPolicy::Disabled` (the default when the argument is omitted) means the handler is only reachable via a direct `$queryBus->handle()` call and is never used for expansion.

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

### Exception Contract

Handlers registered with an `ExpansionPolicy` other than `Disabled` **must** signal a missing record by throwing an exception that implements `StrictlyPHP\Domantra\Query\Exception\ItemNotFoundExceptionInterface`. Expansion catches these and substitutes `null` for the expanded value, so a dangling reference degrades gracefully instead of failing the whole response (which matters especially for paginated endpoints, where one missing row would otherwise take down the page).

Any other exception bubbles out of `QueryBus::handle()` and fails the enclosing request.

`ItemNotFoundException` ships implementing this interface, so existing handlers that already throw it need no change:

```php
use StrictlyPHP\Domantra\Query\Exception\ItemNotFoundException;

class GetTeamByIdHandler implements SingleHandlerInterface
{
    public function __invoke(object $query): Team
    {
        $team = $this->repo->find((string) $query);
        if ($team === null) {
            throw new ItemNotFoundException((string) $query);
        }
        return $team;
    }
}
```

If you share a handler with an HTTP route and it throws a framework exception on a miss (for example `League\Route\Http\Exception` 404), either have that exception implement `ItemNotFoundExceptionInterface` or wrap the miss in a dedicated exception that does:

```php
use StrictlyPHP\Domantra\Query\Exception\ItemNotFoundExceptionInterface;

class TeamNotFoundException extends \RuntimeException implements ItemNotFoundExceptionInterface
{
}
```

Handlers that throw a non-matching exception will bubble through expansion — this is intentional, so unexpected failures are surfaced rather than swallowed.

### Selective Expansion

By default, every reference whose handler was registered with `ExpansionPolicy::ByDefault` is expanded. Handlers registered with `ExpansionPolicy::OnRequest` are only expanded when the call site names them in the `expand` list. Pass an `expand` list to `QueryBus::handle()` to narrow the selection or to opt-in handlers registered with `OnRequest`:

```php
// Expand every handler registered with ExpansionPolicy::ByDefault (default).
$queryBus->handle($userId);
$queryBus->handle($userId, expand: null);

// Expand nothing, even if eligible handlers are registered.
$queryBus->handle($userId, expand: []);

// Expand only the listed source-DTO properties. Handlers registered with
// ExpansionPolicy::OnRequest will expand here as long as their property
// is named in the list.
$queryBus->handle($userId, expand: ['profileId']);
```

Names in the list refer to **the original property on the source DTO**, not the derived output key. Given `public ProfileId $profileId`, pass `'profileId'` (not `'profile'`). Given `public TeamId $team`, pass `'team'` (not `'teamExpanded'`).

Mapping an external query parameter such as `?expand=profile` to a source-DTO field is a controller concern. Consumers migrating from JSON:API or Stripe — where `expand`/`include` match the public output key — typically keep a small translation table in the controller so the public URL contract stays stable even if the source DTO gets renamed:

```php
$outputKeyToSourceField = [
    'profile' => 'profileId',
    'team'    => 'teamId',
];

// Absent/empty `?expand=` → pass null so the bus keeps its documented
// default of expanding every eligible reference. Sending `[]` instead
// would silently flip the default to "expand nothing".
$raw = $request->getQueryParam('expand');
if ($raw === null || $raw === '') {
    $expand = null;
} else {
    $requested = array_filter(explode(',', $raw));
    $expand = array_values(array_intersect_key($outputKeyToSourceField, array_flip($requested)));
}

$response = $queryBus->handle(new UserId($id), expand: $expand);
```

Authorization still wins: names referring to handlers registered with `ExpansionPolicy::Disabled` (or never registered at all) are silently skipped. Names that do not match any property on the DTO are also silently ignored. Paginated responses apply the same `expand` list to every item in the collection.

Expansion only runs when the named property holds an object. Naming a property whose value is `null` (e.g. `public ?ProfileId $profileId = null`) is a silent skip — no expanded key will appear on the response for that field. Consumers rendering output should treat the expanded key as optional.
