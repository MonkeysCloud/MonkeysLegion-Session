# MonkeysLegion Session v2

Secure, driver-based HTTP session management for the MonkeysLegion framework. Ground-up rebuild for PHP 8.4 with property hooks, typed Bag architecture, and zero hard dependencies beyond PSR-7/15.

## Features

| Feature                         | Status                                                                                    |
| ------------------------------- | ----------------------------------------------------------------------------------------- |
| **Multi-Driver Storage**        | File, Database (via `ConnectionManagerInterface`), Redis — switchable via `DriverFactory` |
| **Bag Architecture**            | `AttributeBag`, `FlashBag`, `MetadataBag` — each implements `SessionBagInterface`         |
| **Atomic Locking**              | Per-session `lock()` / `unlock()` on every driver to prevent race conditions              |
| **AES-256-GCM Encryption**      | Optional payload encryption with key-ring rotation support                                |
| **CSRF Protection**             | Auto-generated tokens + `VerifyCsrfToken` PSR-15 middleware                               |
| **Flash Data**                  | One-hop flash messages with `reflash()`, `keep()`, and `now()`                            |
| **Session Fixation Prevention** | `regenerate()` and `invalidate()` for safe login flows                                    |
| **Dot Notation Access**         | Nested `get()` / `set()` via `user.profile.name` style keys                               |
| **Service Provider**            | `SessionServiceProvider` for PSR-11 DI registration                                       |
| **PHP 8.4 Native**              | Property hooks (`$id`, `$isStarted`, `$name`), `readonly` constructors, typed constants   |

## Requirements

- **PHP 8.4** or higher
- `psr/http-message` ^2.0
- `psr/http-server-middleware` ^1.0
- `psr/http-server-handler` ^1.0

## Installation

```bash
composer require monkeyscloud/monkeyslegion-session
```

## Architecture

```text
monkeyslegion-session/
├── config/
│   ├── session.mlc                    # MLC configuration format
│   └── session.php                    # PHP configuration format
├── src/
│   ├── Bags/
│   │   ├── AttributeBag.php           # Session attribute storage with dot notation
│   │   ├── FlashBag.php               # One-hop flash message container
│   │   └── MetadataBag.php            # Timestamps and usage tracking
│   ├── Cli/
│   │   └── Command/
│   │       └── ConfigPublisher.php    # CLI command to publish config files
│   ├── Contracts/
│   │   ├── DataHandlerInterface.php   # Serialization / encryption contract
│   │   ├── SessionBagInterface.php    # Bag contract (initialize, clear, getStorageKey)
│   │   ├── SessionDriverInterface.php # Storage driver contract (with lock/unlock)
│   │   └── SessionInterface.php       # Session manager API contract
│   ├── Drivers/
│   │   ├── DatabaseDriver.php         # Database storage via ConnectionManagerInterface
│   │   ├── FileDriver.php             # Filesystem storage with flock() locking
│   │   └── RedisDriver.php            # Redis storage with EXPIRE-based GC
│   ├── Exceptions/
│   │   ├── SessionException.php       # Named constructors for all error cases
│   │   └── SessionLockException.php   # Lock acquisition failure
│   ├── Factory/
│   │   └── DriverFactory.php          # Creates drivers from config arrays
│   ├── Middleware/
│   │   ├── SessionMiddleware.php      # PSR-15 session lifecycle middleware
│   │   └── VerifyCsrfToken.php        # CSRF token validation middleware
│   ├── EncryptedSerializer.php        # AES-256-GCM with key-ring rotation
│   ├── NativeSerializer.php           # Standard PHP serialize/unserialize
│   ├── SessionBag.php                 # Legacy data container (attributes + flash)
│   ├── SessionManager.php             # Session orchestrator (implements SessionInterface)
│   └── SessionServiceProvider.php     # PSR-11 DI registration
└── tests/
```

---

## Quick Start

### 1. Register the Service Provider

The `SessionServiceProvider` binds `SessionDriverInterface` → `FileDriver` by default. Register it with the DI container:

```php
use MonkeysLegion\Session\SessionServiceProvider;

// In your application bootstrap
$provider = new SessionServiceProvider();
$provider->register($containerBuilder);
```

To swap drivers, override the binding in your container definitions:

```php
use MonkeysLegion\Session\Contracts\SessionDriverInterface;
use MonkeysLegion\Session\Drivers\RedisDriver;

$builder->bind(SessionDriverInterface::class, RedisDriver::class);
```

### 2. Add Middleware

Register `SessionMiddleware` in your PSR-15 pipeline to enable automatic session lifecycle management:

```php
use MonkeysLegion\Session\Middleware\SessionMiddleware;

$pipeline->add(SessionMiddleware::class);
```

For CSRF protection on state-changing requests, add the `VerifyCsrfToken` middleware **after** the session middleware:

```php
use MonkeysLegion\Session\Middleware\VerifyCsrfToken;

$pipeline->add(VerifyCsrfToken::class);
```

### 3. Use in Controllers

The session is injected as a request attribute by the middleware:

```php
class ProfileController
{
    public function show(ServerRequestInterface $request): ResponseInterface
    {
        /** @var \MonkeysLegion\Session\SessionManager $session */
        $session = $request->getAttribute('session');

        // Read data (dot notation supported)
        $name = $session->get('user.profile.name', 'Guest');

        // Write data
        $session->set('last_visited', time());

        // Flash a one-time message
        $session->flash('status', 'Welcome back!');

        // ...
    }
}
```

---

## Session Manager API

The `SessionManager` implements `SessionInterface` and is the primary class your application interacts with.

### Property Hooks (PHP 8.4)

```php
// Read-only session ID (throws SessionException if set while started)
$manager->id;               // string

// Check if session is active
$manager->isStarted;        // bool
```

### Lifecycle Methods

| Method                      | Returns  | Description                                                                                                                                                     |
| --------------------------- | -------- | --------------------------------------------------------------------------------------------------------------------------------------------------------------- |
| `start(?string $id)`        | `bool`   | Resume an existing session or start a new one. Acquires a driver lock, reads storage, initializes the `SessionBag`, and auto-generates a CSRF token if missing. |
| `save()`                    | `bool`   | Serialize attributes, write to the driver, and release the lock.                                                                                                |
| `regenerate(bool $destroy)` | `bool`   | Change the session ID (prevents fixation). Optionally destroy old data.                                                                                         |
| `invalidate()`              | `bool`   | Flush all data and regenerate the ID (full session reset).                                                                                                      |
| `getName()`                 | `string` | Get the session cookie name (default: `ml_session`).                                                                                                            |
| `getId()`                   | `string` | Alias for the `$id` property hook.                                                                                                                              |
| `isStarted()`               | `bool`   | Alias for the `$isStarted` property hook.                                                                                                                       |

### Data Access

| Method                                     | Description                             |
| ------------------------------------------ | --------------------------------------- |
| `get(string $key, mixed $default = null)`  | Retrieve data (dot notation supported). |
| `set(string $key, mixed $value)`           | Store data in the session.              |
| `has(string $key)`                         | Check if a key exists.                  |
| `forget(string $key)`                      | Remove a key.                           |
| `pull(string $key, mixed $default = null)` | Get a value and immediately delete it.  |
| `all()`                                    | Return all session attributes.          |

### Flash Data

| Method                                  | Description                                                          |
| --------------------------------------- | -------------------------------------------------------------------- |
| `flash(string $key, mixed $value)`      | Store data for the **next request** only.                            |
| `getFlash(string $key, mixed $default)` | Retrieve flash data from the previous request.                       |
| `reflash()`                             | Keep **all** flash data for one more request.                        |
| `keep(string ...$keys)`                 | Keep **specific** flash keys for one more request.                   |
| `now(string $key, mixed $value)`        | Flash data available immediately, expires at end of current request. |

### Security

| Method                                     | Description                                                  |
| ------------------------------------------ | ------------------------------------------------------------ |
| `token()`                                  | Get the current CSRF token.                                  |
| `regenerateToken()`                        | Regenerate the CSRF token (80 hex chars via `random_bytes`). |
| `setIpAddress(?string $ip)`                | Set the user's IP for fingerprinting.                        |
| `setUserAgent(?string $ua)`                | Set the user's browser string for fingerprinting.            |
| `setUserId(string\|int\|null $id)`         | Associate a user ID with the session.                        |
| `setRequestInfo(?string $ip, ?string $ua)` | Convenience: set IP and User-Agent at once.                  |

---

## Bag Architecture

Version 2 introduces a segmented Bag system that implements `SessionBagInterface`. Each bag manages a distinct concern:

### SessionBagInterface

```php
interface SessionBagInterface
{
    public string $name { get; }                     // Bag identifier (property hook)
    public function initialize(array &$array): void; // Initialize with reference to storage
    public function getStorageKey(): string;          // Storage key prefix (e.g. _attributes)
    public function clear(): array;                   // Clear and return previous contents
}
```

### AttributeBag

Persistent key/value storage with dot notation:

```php
$bag = new AttributeBag();
$bag->set('user.preferences.theme', 'dark');
$bag->get('user.preferences.theme');  // 'dark'
$bag->has('user.preferences');        // true
$bag->pull('temp_key');               // get + forget
$bag->forget('user.preferences.theme');
```

### FlashBag

One-hop data with fine-grained retention:

```php
$flash = new FlashBag();

// Set flash for next request
$flash->set('success', 'Profile updated!');

// Set flash available now, gone after this request
$flash->now('error', 'Something failed.');

// Keep specific keys alive for one more hop
$flash->keep('success', 'warning');

// Keep everything alive
$flash->reflash();

// Auto-cleanup: call before save
$flash->clearOldData();
```

### MetadataBag

Session timestamps and usage tracking:

```php
$meta = new MetadataBag();

$meta->getCreatedAt();     // Unix timestamp of session creation
$meta->getLastUsedAt();    // Last activity timestamp

// Throttle DB writes: only update last_used_at every N seconds
$meta->setUpdateThreshold(300);
$meta->stampNew();         // Conditionally updates based on threshold
```

---

## Storage Drivers

All drivers implement `SessionDriverInterface` with atomic locking:

### SessionDriverInterface

| Method    | Arguments                      | Returns      | Description                                       |
| --------- | ------------------------------ | ------------ | ------------------------------------------------- |
| `open`    | `$path`, `$name`               | `bool`       | Initialize the storage resource.                  |
| `close`   | —                              | `bool`       | Close the storage resource.                       |
| `read`    | `$id`                          | `?array`     | Retrieve session data (payload + metadata) by ID. |
| `write`   | `$id`, `$payload`, `$metadata` | `bool`       | Save serialized data and metadata.                |
| `destroy` | `$id`                          | `bool`       | Delete the session from storage.                  |
| `gc`      | `$maxLifetime`                 | `int\|false` | Garbage collect sessions older than N seconds.    |
| `lock`    | `$id`, `$timeout = 30`         | `bool`       | Acquire exclusive lock on the session.            |
| `unlock`  | `$id`                          | `bool`       | Release the session lock.                         |

### File Driver

Uses `flock()` for atomic locking. Sessions stored as individual files:

```php
use MonkeysLegion\Session\Drivers\FileDriver;

$driver = new FileDriver(
    path: '/var/sessions',
    ttl: 7200
);
```

### Database Driver

Stores sessions in a SQL table via `ConnectionManagerInterface`:

```php
use MonkeysLegion\Session\Drivers\DatabaseDriver;

$driver = new DatabaseDriver($connectionManager, [
    'table'    => 'sessions',
    'lifetime' => 7200,
]);
```

#### Migration

```sql
CREATE TABLE sessions (
    session_id    VARCHAR(255) PRIMARY KEY NOT NULL,
    payload       TEXT,
    flash_data    TEXT,
    created_at    INTEGER NOT NULL,
    last_activity INTEGER NOT NULL,
    expiration    INTEGER NOT NULL,
    user_id       INTEGER NULL,
    ip_address    VARCHAR(45) NULL,
    user_agent    TEXT NULL
);
```

### Redis Driver

Uses `EXPIRE` for automatic garbage collection:

```php
use MonkeysLegion\Session\Drivers\RedisDriver;

$driver = new RedisDriver(
    redis: $redisInstance,
    prefix: 'session:',
    ttl: 7200
);
```

### Driver Factory

Create drivers from configuration arrays dynamically:

```php
use MonkeysLegion\Session\Factory\DriverFactory;

$factory = new DriverFactory();
$driver  = $factory->make('file', ['path' => '/var/sessions', 'lifetime' => 7200]);
$driver  = $factory->make('redis', ['redis' => $redis, 'prefix' => 'sess:', 'lifetime' => 3600]);
```

---

## Payload Encryption

Session data can optionally be encrypted at rest using AES-256-GCM with key-ring rotation support.

### NativeSerializer (Default)

Standard PHP `serialize()` / `unserialize()`:

```php
use MonkeysLegion\Session\NativeSerializer;

$handler = new NativeSerializer();
```

### EncryptedSerializer

Wraps any `DataHandlerInterface` with AES-256-GCM encryption. Supports multiple keys for zero-downtime key rotation:

```php
use MonkeysLegion\Session\EncryptedSerializer;
use MonkeysLegion\Session\NativeSerializer;

$handler = new EncryptedSerializer(
    serializer: new NativeSerializer(),
    keys: [
        'v2' => 'new-256-bit-key-here',   // Current key (used for encryption)
        'v1' => 'old-256-bit-key-here',    // Legacy key (used for decryption fallback)
    ]
);

// Inject into the SessionManager
$manager = new SessionManager($driver, $handler);
```

**Key rotation** works transparently: new writes always use the first key in the ring. Reads attempt the tagged key first, then fall back through all keys. Remove old keys once all sessions have been re-encrypted.

---

## CSRF Protection

The session middleware auto-generates a CSRF token (`_token`) when a session starts.

### Rendering in Templates

```html
<form method="POST" action="/profile">
  <input type="hidden" name="_csrf" value="{{ session.token() }}" />
  <!-- ... -->
</form>
```

### VerifyCsrfToken Middleware

The middleware checks tokens on all non-read methods (`POST`, `PUT`, `PATCH`, `DELETE`):

- `_csrf` field in the parsed body
- `X-CSRF-TOKEN` header
- `X-XSRF-TOKEN` header (fallback)

Read methods (`GET`, `HEAD`, `OPTIONS`) pass through automatically.

```php
use MonkeysLegion\Session\Middleware\VerifyCsrfToken;

// Register after SessionMiddleware
$pipeline->add(VerifyCsrfToken::class);
```

---

## Configuration

Publish the config file using the CLI command:

```bash
php mlc session:publish
```

### MLC Format (`config/session.mlc`)

```text
session {
    default env(SESSION_DRIVER, 'file')

    drivers {
        file {
            path => env(SESSION_FILE_PATH, base_path('var/sessions'))
            lifetime => env(SESSION_LIFETIME, 7200)
        }
        database {
            table => env(SESSION_TABLE, 'sessions')
            lifetime => env(SESSION_LIFETIME, 7200)
        }
        redis {
            connection => env(REDIS_SESSION_CONNECTION, 'default')
            lifetime => env(SESSION_LIFETIME, 7200)
        }
    }

    cookie_name => env(SESSION_COOKIE_NAME, 'ml_session')
    cookie_lifetime => env(SESSION_COOKIE_LIFETIME, 7200)
    cookie_path => env(SESSION_COOKIE_PATH, '/')
    cookie_domain => env(SESSION_COOKIE_DOMAIN, '')
    cookie_secure => env(SESSION_COOKIE_SECURE, true)
    cookie_httponly => env(SESSION_COOKIE_HTTPONLY, true)
    cookie_samesite => env(SESSION_COOKIE_SAMESITE, 'Lax')

    encrypt => env(SESSION_ENCRYPT, false)

    keys {
        main_key => env(APP_KEY, null)
    }
}
```

### PHP Format (`config/session.php`)

```php
return [
    'session' => [
        'default' => $_ENV['SESSION_DRIVER'] ?? 'file',
        'drivers' => [
            'file' => [
                'path'     => $_ENV['SESSION_FILE_PATH'] ?? base_path('var/sessions'),
                'lifetime' => (int) ($_ENV['SESSION_LIFETIME'] ?? 7200),
            ],
            'database' => [
                'table'    => $_ENV['SESSION_TABLE'] ?? 'sessions',
                'lifetime' => (int) ($_ENV['SESSION_LIFETIME'] ?? 7200),
            ],
            'redis' => [
                'connection' => $_ENV['REDIS_SESSION_CONNECTION'] ?? 'default',
                'lifetime'   => (int) ($_ENV['SESSION_LIFETIME'] ?? 7200),
            ],
        ],
        'cookie_name'     => $_ENV['SESSION_COOKIE_NAME'] ?? 'ml_session',
        'cookie_lifetime' => (int) ($_ENV['SESSION_COOKIE_LIFETIME'] ?? 7200),
        'cookie_path'     => $_ENV['SESSION_COOKIE_PATH'] ?? '/',
        'cookie_domain'   => $_ENV['SESSION_COOKIE_DOMAIN'] ?? '',
        'cookie_secure'   => (bool) ($_ENV['SESSION_COOKIE_SECURE'] ?? true),
        'cookie_httponly'  => (bool) ($_ENV['SESSION_COOKIE_HTTPONLY'] ?? true),
        'cookie_samesite'  => $_ENV['SESSION_COOKIE_SAMESITE'] ?? 'Lax',
        'encrypt'          => (bool) ($_ENV['SESSION_ENCRYPT'] ?? false),
        'keys' => [
            'main_key' => $_ENV['APP_KEY'] ?? null,
        ],
    ],
];
```

---

## Middleware Lifecycle

The `SessionMiddleware` manages the full request/response lifecycle:

| Phase           | Action                     | Detail                                                           |
| --------------- | -------------------------- | ---------------------------------------------------------------- |
| **1. Extract**  | `getCookieParams()`        | Read the session ID from the configured cookie name.             |
| **2. Start**    | `manager->start()`         | Lock → Read → Initialize Bag → Generate CSRF token if missing.   |
| **3. Metadata** | `populateMetadata()`       | Extract IP (`REMOTE_ADDR` / `X-Forwarded-For`) and `User-Agent`. |
| **4. Inject**   | `withAttribute('session')` | Attach the `SessionManager` to the PSR-7 request.                |
| **5. Process**  | `handler->handle()`        | Application logic runs (routes, controllers).                    |
| **6. Commit**   | `manager->save()`          | Serialize → Write → Unlock (in a `finally` block for safety).    |
| **7. Cookie**   | `withAddedHeader()`        | Set the `Set-Cookie` header with secure defaults.                |

---

## Security Posture

- **Atomic locking** — prevents concurrent request race conditions via `flock()` / Redis `SETNX` / row-level locks
- **CSPRNG session IDs** — `random_bytes(20)` (40 hex chars) for all session identifiers
- **CSRF token generation** — `random_bytes(40)` (80 hex chars) for CSRF tokens
- **Session fixation prevention** — `regenerate(destroy: true)` on login
- **AES-256-GCM encryption** — authenticated encryption with key-ring rotation
- **IP & User-Agent fingerprinting** — stored per-session for anomaly detection
- **Timing-safe comparisons** — `hash_equals` for all CSRF token checks
- **Graceful lock release** — `finally` block ensures unlock even on exceptions

## Error Handling

The `SessionException` class provides named constructors for clear, debuggable error messages:

```php
SessionException::alreadyStarted();            // "Session has already been started."
SessionException::notStarted();                // "Session has not been started yet."
SessionException::invalidId($id);              // "Invalid session ID: ..."
SessionException::serializationFailed($msg);   // "Failed to serialize session data."
SessionException::deserializationFailed($msg); // "Failed to deserialize session data."
SessionException::driverFailed($op, $msg);     // "Session driver operation '...' failed."
SessionException::securityValidationFailed($r);// "Session security validation failed: ..."
SessionException::expired();                   // "Session has expired."
```

## Testing

```bash
composer test
composer phpstan
```

## License

MIT © [MonkeysCloud](https://monkeys.cloud)
