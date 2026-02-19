# 🐵 MonkeysLegion Session Manager Usage

Welcome! This guide will help you manage user sessions in your application.

## 1. What is a Session?
A **Session** is a way to remember a user across multiple page visits.
-   **Standard Data**: Stored until you delete it or the session expires (e.g., User ID, Theme).
-   **Flash Data**: Stored **only for the next request** (e.g., "Success!" message after a form submit).

---

## 2. Setup (The Factory Way)

Use the `DriverFactory` to create the storage driver easily.

```php
use MonkeysLegion\Session\Factory\DriverFactory;
use MonkeysLegion\Session\SessionManager;

// 1. Create the Driver (where to store data)
$factory = new DriverFactory();
$driver = $factory->make('file', [
    'path' => __DIR__ . '/storage/sessions',
    'lifetime' => 3600 // 1 hour
]);

// 2. Create the Manager
$session = new SessionManager($driver);
```

---

## 3. Configuration & Installation

### Publishing Config
You can publish the default configuration file to your project's `config` directory using the following CLI command:

```bash
php ml session:publish
```
This will create `config/session.php` where you can customize drivers, lifetimes, and cookie settings.

---

## 4. Basic Usage

### Starting
You must start the session before using it.
```php
$session->start();
```

### Storing & Retrieving Data
Values stored here stick around until the session expires.

```php
// Save data
$session->set('username', 'HanSolo');
$session->set('role', 'pilot');

// Get data (second argument is default if missing)
$user = $session->get('username', 'Guest'); 

// Check if exists
if ($session->has('role')) {
    // ...
}

// Delete data
$session->forget('role');
```

---

## 4. Flash Data (Temporary Messages)

**Flash Data** is special. It is saved now but is meant to be shown **on the next page load** (like after a redirect). After that, it disappears automatically.

```php
// Page 1: processing_form.php
$session->flash('message', 'Profile updated!');
// Redirect to Page 2...

// Page 2: profile.php
echo $session->getFlash('message'); // Outputs: "Profile updated!"
// If you refresh Page 2 again, it will be null (gone).
```

-   `flash($key, $value)`: Set message for next request.
-   `getFlash($key)`: Get message.
-   `reflash()`: Keep all flash data for *one more* request.

---

## 5. Security & Metadata

### Metadata (Who is this?)
We automatically track IP and User Agent to help prevent session hijacking.
```php
$session->setUserId(42); // Link session to User #42
$session->save(); // Persist changes
```

### Regenerating ID (Important!)
To prevent **Session Fixation** attacks, you should change the Session ID when a user logs in or out.

**Create a new ID, keep the data:**
```php
$session->regenerate(); 
// The ID is now different, but 'username' is still 'HanSolo'.
// Make sure to call save() AFTER regenerating!
$session->save(); 
```

---

## 7. CSRF Protection

The session manager includes built-in CSRF (Cross-Site Request Forgery) protection.

### The Token
A unique token is automatically generated when a session starts.

```php
// Get the current CSRF token
$token = $session->token();

// Regenerate the token (useful after login)
$session->regenerateToken();
```

### Protection Middleware
To protect your routes, add the `VerifyCsrfToken` middleware to your PSR-15 pipeline. It will automatically check POST, PUT, PATCH, and DELETE requests for a valid token.

```php
use MonkeysLegion\Session\Middleware\VerifyCsrfToken;

// In your middleware stack (after SessionMiddleware)
$csrfMiddleware = new VerifyCsrfToken($sessionManager);
```

The middleware looks for the token in:
1. The `_token` field in the request body.
2. The `X-CSRF-TOKEN` HTTP header.
3. The `X-XSRF-TOKEN` HTTP header.

---

## 8. Middleware Integration (PSR-15)

To use sessions in a PSR-15 compatible framework, you should add the `SessionMiddleware` to your global middleware stack.

```php
use MonkeysLegion\Session\Middleware\SessionMiddleware;

$config = include 'config/session.php';
$middleware = new SessionMiddleware($sessionManager, $config);
```

### Recommended Order:
1. `SessionMiddleware` (Starts the session)
2. `VerifyCsrfToken` (Protects against CSRF)
3. Your Application Logic

---

## 9. The Complete Flow

Here is how a typical request looks:

```php
// 1. Start
$session->start();

// 2. Logic
$session->set('visited', true);

if ($loginSuccess) {
    $session->regenerate(); // New ID for security
    $session->setUserId($user->id);
}

// 3. Save & Close
// This writes data to the file/db and releases the lock.
$session->save(); 
```
