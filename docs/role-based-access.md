# Role-Based Access

Domantra supports property-level access control on DTOs using the `RequiresAuthenticatedUser` attribute.

## `RequiresAuthenticatedUser` Attribute

Apply to DTO properties to restrict visibility based on user role:

```php
use StrictlyPHP\Domantra\Query\Attributes\RequiresAuthenticatedUser;
use StrictlyPHP\Domantra\Domain\CachedDtoInterface;

readonly class UserDto implements CachedDtoInterface
{
    public function __construct(
        public UserId $id,
        public string $username,

        #[RequiresAuthenticatedUser]
        public string $email,

        #[RequiresAuthenticatedUser(roles: ['admin', 'moderator'])]
        public string $internalNotes,
    ) {}

    public function getCacheKey(): string { return (string) $this->id; }
    public function getTtl(): int { return 3600; }
}
```

## Behavior

The `DtoTransformer` filters properties based on the role passed to `QueryBus::handle()`:

| Attribute | Role = `null` | Role = `'user'` | Role = `'admin'` |
|-----------|--------------|-----------------|-------------------|
| None | Visible | Visible | Visible |
| `#[RequiresAuthenticatedUser]` | Hidden | Visible | Visible |
| `#[RequiresAuthenticatedUser(roles: ['admin'])]` | Hidden | Hidden | Visible |

### Rules

- **No attribute**: always visible
- **`#[RequiresAuthenticatedUser]`** (empty roles): hidden when `$role` is `null`, visible for any authenticated role
- **`#[RequiresAuthenticatedUser(roles: ['admin'])]`**: visible only if `$role` is in the specified array

## Usage

Pass the role when handling a query:

```php
// Unauthenticated — email and internalNotes hidden
$response = $queryBus->handle(new UserId('user-123'));

// Authenticated user — email visible, internalNotes hidden
$response = $queryBus->handle(new UserId('user-123'), role: 'user');

// Admin — everything visible
$response = $queryBus->handle(new UserId('user-123'), role: 'admin');
```
