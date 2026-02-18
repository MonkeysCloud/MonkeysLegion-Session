# MonkeysLegion Session

## 1. Session Composition (The Core)

A session instance in MonkeysLegion will consist of the following data points:

| Component         | Description                                            | Example / Value                    |
| :---------------- | :----------------------------------------------------- | :--------------------------------- |
| **Session ID**    | Unique, cryptographically secure identifier.           | `ml_sess_8f2d9c1a...`              |
| **Payload**       | Persistent data stored for the user.                   | `{"user_id": 42, "role": "admin"}` |
| **Flash Data**    | Temporary data that is deleted after the next request. | `{"status": "Profile updated!"}`   |
| **Created At**    | Timestamp of when the session was first generated.     | `1707782400` (Unix)                |
| **Last Activity** | Timestamp updated on every request (for idle timeout). | `1707782900` (Unix)                |
| **Expiration**    | Hard cutoff time when the session becomes invalid.     | `1707786000` (Unix)                |

---

## 2. Security Fingerprinting (Validation)

To prevent session hijacking, we store and verify these browser/network traits:

- **User-Agent (UA):** The browser string. If it changes mid-session, we immediately **kill** the session (High-risk indicator).
- **IP Address:** The user's network address.
  - _Strict Mode:_ Invalidate session if IP changes.
  - _Relaxed Mode:_ Ignore (better for mobile users moving between Wi-Fi and 5G).
- **CSRF Token:** A unique token linked to this specific Session ID to prevent Cross-Site Request Forgery.

---

## 3. Session State Lifecycle

1. **Generation:** Create ID → Set Cookie → Initialize Storage.
2. **Validation:** Check Cookie ID → Match with Driver → Verify IP/UA.
3. **Update:** Refresh `Last Activity` → Write new Payload/Flash data.
4. **Destruction:** Clear Storage → Expire Cookie.

---

## 4. Architecture Overview

### SessionDriverInterface

| Method      | Arguments                 | Returns      | Description                                       |
| :---------- | :------------------------ | :----------- | :------------------------------------------------ |
| **open**    | `path`, `name`            | `bool`       | Initialize the storage resource.                  |
| **close**   | -                         | `bool`       | Close the storage resource.                       |
| **read**    | `id`                      | `?array`     | Retrieve session data (payload + metadata) by ID. |
| **write**   | `id`, `payload`, `metadata` | `bool`       | Save serialized session data and metadata.        |
| **destroy** | `id`                      | `bool`       | Delete the session data from storage.             |
| **gc**      | `maxLifetime`             | `int/false`  | Garbage Collection: Delete sessions older than X. |
| **lock**    | `id`, `timeout`           | `bool`       | Acquire an exclusive lock on the session ID.      |
| **unlock**  | `id`                      | `bool`       | Release the lock so other requests can proceed.   |

### SessionManager Details

The `SessionManager` is the high-level class your application code will actually interact with. It manages the **Session Object** (the data) and coordinates with the **Driver** (the storage).

#### Responsibilities of the Manager

1. **Bootstrapping:** Reading the Session ID from the request cookies and asking the Driver for the data.
2. **Serialization:** Turning the PHP array into a string (and back) so the Driver can save it.
3. **Flash Management:** Handling data that only lasts for one "hop" (request).
4. **Security Checks:** Comparing the User-Agent or IP before allowing access.

#### SessionManager Methods

| Method                               | Description                                                    |
| :----------------------------------- | :------------------------------------------------------------- |
| **start($id)**                       | Resumes or starts a new session with given ID.                 |
| **save()**                           | Saves the current session data to the driver.                  |
| **getId()**                          | Get the current Session ID.                                    |
| **get($key, $default)**              | Retrieve data (supports dot notation).                         |
| **set($key, $value)**                | Store data in the session.                                     |
| **has($key)**                        | Returns `true` if the key is present.                          |
| **forget($key)**                     | Remove a key from the session.                                 |
| **flash($key, $value)**              | Store data for the next request only.                          |
| **getFlash($key, $default)**         | Retrieve flash data.                                           |
| **reflash()**                        | Keep all flash data for the next request.                      |
| **regenerate($destroy)**             | Change the Session ID (Crucial for login to prevent fixation). |
| **setIpAddress($ip)**                | Set the user's IP address for validation.                      |
| **setUserAgent($ua)**                | Set the user's browser string for validation.                  |
| **setUserId($id)**                   | Associate a User ID with the session.                          |

---

## 5. Session Middleware: The Request Lifecycle

The Middleware bridges the HTTP Request/Response with the Session Manager. It ensures data is consistent and atomic.

| Phase           | Action              | Detail                                                                             |
| :-------------- | :------------------ | :--------------------------------------------------------------------------------- |
| **1. Extract**  | `getCookie()`       | Look for the session cookie (e.g., `ML_SESS`) in the Request.                      |
| **2. Start**    | `manager->start()`  | The Manager triggers `driver->lock()` and `driver->read()`.                        |
| **3. Inject**   | `withAttribute()`   | The Session object is attached to the Request for use in Controllers.              |
| **4. Process**  | `handler->handle()` | The actual application logic runs (Routes, Controllers, Actions).                  |
| **5. Capture**  | `getResponse()`     | The Middleware catches the resulting Response object.                              |
| **6. Commit**   | `manager->save()`   | The Manager triggers `driver->write()` and `driver->unlock()`.                     |
| **7. Finalize** | `set-cookie`        | If the session is new or regenerated, add the `Set-Cookie` header to the Response. |

### Logic Flow Diagram (Conceptual)

1. **Request In** ↓
2. **SessionMiddleware::process()**
   → [Manager] → [Driver] → (LOCK & READ)
   ↓
3. **Controller / Logic** → `$request->getAttribute('session')->set('key', 'value')`
   ↓
4. **SessionMiddleware (Post-Process)**
   → [Manager] → [Driver] → (WRITE & UNLOCK)
   ↓
5. **Response Out** (with Set-Cookie header)

Since we are using **Atomic Locking**, the Middleware must be careful. If an exception occurs during the "App Phase" (Step 4), the Middleware should still trigger the **Unlock** mechanism in a `finally` block to ensure the session isn't stuck in a locked state for other requests. (probably re throw in catch if user didn't already catch that occurred exception)

---

## 6. Project Structure

Since we are aligning with the **monkeyslegion-** ecosystem, the architecture needs to be modular, interface-driven, and ready for PSR-11 (Dependency Injection).

```text
monkeyslegion-session/
├── src/
│   ├── Contracts/
│   │   ├── SessionInterface.php       # The Manager's API
│   │   └── SessionDriverInterface.php # The Storage Contract (with lock/unlock)
│   ├── Drivers/
│   │   ├── DatabaseDriver.php         # PDO/DB implementation
│   │   ├── RedisDriver.php            # PhpRedis/Predis implementation
│   │   └── FileDriver.php             # Local filesystem implementation
│   ├── Exceptions/
│   │   ├── SessionException.php
│   │   └── SessionLockException.php   # If a lock cannot be acquired (timeout)
│   ├── Factory/
│   │   └── DriverFactory.php          # Factory to create driver instances
│   ├── Middleware/
│   │   └── SessionMiddleware.php      # PSR-15 Middleware logic
│   ├── SessionBag.php                 # The Data container (handles Flash/Payload)
│   └── SessionManager.php             # The "Brain" (Coordinates Bag + Drivers)
├── tests/                             # Unit and Integration tests
├── composer.json
└── readme.md
```

### Component Breakdown

#### 1. The `SessionBag`

Instead of putting all logic in the Manager, a `SessionBag` holds the actual data array. It handles the "Dot Notation" (e.g., `$session->get('user.profile.name')`) and manages which keys are **Flash** (one-time use) vs. **Persistent**. It uses `put()` for storing attributes and `flash()` for temporary data.

#### 2. The `SessionManager` (The Orchestrator)

This class should be injected into your Middleware or Controllers.

- It uses the `DriverInterface` to fetch data.
- It uses a `Serializer` to decode that data into the `SessionBag`.
- **Important:** It must handle the `regenerate()` method, which creates a new ID but keeps the data (crucial to prevent Session Fixation attacks during login).

#### 3. The `Driver` Implementations

- **Database:** Needs a table with `id` (string), `payload` (text), and `last_activity` (integer/timestamp).
- **Redis:** Should use `EXPIRE` to let Redis handle the "Garbage Collection" automatically.
- **File:** Needs `flock()` to handle the **Atomic Locking**.

---

## Roadmap

### Phase 1: Core Foundation

- [x] Implement `SessionInterface` contract
- [x] Implement `DriverInterface` contract
- [x] Create `SessionBag` with dot notation support
- [x] Create `SessionManager` orchestrator
- [x] Add basic exception handling

### Phase 2: Driver Implementations

- [x] Implement `FileDriver` with `flock()` support
- [x] Implement `DatabaseDriver` with PDO
- [x] Implement `RedisDriver` with atomic operations
- [x] Add driver-specific tests

### Phase 3: Middleware & Integration

- [x] Implement PSR-15 `SessionMiddleware`
- [x] Add atomic locking with `finally` block
- [x] Cookie management (secure, httpOnly, sameSite)

### Phase 4: Security Features

- [/] User-Agent validation (implemented in Middleware/Manager)
- [/] IP address validation (implemented in Middleware/Manager)
- [ ] CSRF token generation and validation
- [x] Session fixation prevention (via `regenerate()`)

### Phase 5: Advanced Features

- [x] Flash data management
- [x] Garbage collection automation
- [ ] Session encryption option
- [x] PSR-11 container integration (via Factory/Service providers)

### Phase 6: Documentation & Release

- [x] Complete API documentation
- [x] Usage examples and tutorials
- [ ] Performance benchmarks
- [ ] v1.0.0 stable release

---

## Contributing

**Welcome to contribute!**

This is an open-source project and we appreciate any help from the community. Whether it's a bug fix, new feature, or documentation improvement, all contributions are valued.

### How to Contribute

1. **Fork** the repository
2. **Clone** your fork locally
3. **Create** a new branch for your feature/fix (`git checkout -b feature/amazing-feature`)
4. **Make** your changes
5. **Test** your changes thoroughly
6. **Commit** your changes (`git commit -m 'Add amazing feature'`)
7. **Push** the branch to your fork
8. **Submit** a Pull Request

### Guidelines

- Follow PSR-12 coding standards
- Write unit tests for new features (Or it gonna need to be tested by us before merge)
- Update documentation as needed
- Keep commits atomic and well-described
- Be respectful and constructive in discussions

We look forward to your contributions!
