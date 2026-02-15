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

## 3. Basic Usage

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

## 6. The Complete Flow

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
