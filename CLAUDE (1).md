# Domino's Delivery Tracker — Claude Code Instructions

## Project Overview

A personal, private web app for tracking Domino's delivery shifts. Built with PHP + MySQL + vanilla JS.
Hosted on shared hosting (Net Nerd) via cPanel. The developer is new to PHP — keep explanations clear,
code well-commented, and always provide complete files ready to upload rather than partial snippets.

---

## Tech Stack

- **Backend**: PHP (no frameworks — plain PHP files)
- **Database**: MySQL (via cPanel's MySQL Databases tool)
- **Frontend**: HTML + CSS + vanilla JavaScript (no React, no build tools)
- **Map**: Leaflet.js with OpenStreetMap tiles
- **Geocoding**: Nominatim (OpenStreetMap's free geocoding API — no key, no signup required)

---

## Hosting Environment

- Shared hosting via cPanel (Net Nerd)
- Domain: `forgemill.co.uk`
- PHP and MySQL available out of the box
- No root access, no Docker, no persistent Python processes
- Files go in `public_html/tracker/`
- Database credentials stored in `config.php`

---

## Database Schema

### `users`
```sql
CREATE TABLE users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    username VARCHAR(50) NOT NULL UNIQUE,
    password_hash VARCHAR(255) NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);
```

### `shifts`
```sql
CREATE TABLE shifts (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    date DATE NOT NULL,
    start_time TIME,
    end_time TIME,
    notes TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id)
);
```

### `deliveries`
```sql
CREATE TABLE deliveries (
    id INT AUTO_INCREMENT PRIMARY KEY,
    shift_id INT NOT NULL,
    sequence INT NOT NULL,
    address VARCHAR(255) NOT NULL,
    postcode VARCHAR(10) NOT NULL,
    lat DECIMAL(10, 8),
    lng DECIMAL(11, 8),
    tip_amount DECIMAL(5, 2),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (shift_id) REFERENCES shifts(id)
);
```

---

## Config File (`config.php`)

Single source of truth for credentials and constants. Never expose this publicly — add a `.htaccess`
rule to deny direct access, or place it outside `public_html/` if the host allows.

```php
<?php
define('DB_HOST', 'localhost');
define('DB_NAME', 'your_db_name');
define('DB_USER', 'your_db_user');
define('DB_PASS', 'your_db_password');

// Domino's store coordinates — find your exact branch on Google Maps and update these
define('STORE_LAT', 52.9540);
define('STORE_LNG', -1.1550);
define('STORE_NAME', "Domino's");

define('SITE_NAME', 'ForgemillTracker');
?>
```

---

## File Structure

```
public_html/tracker/
├── config.php              <- credentials and constants (block public access)
├── .htaccess               <- deny direct access to config.php; general security rules
├── index.php               <- redirects to login or dashboard depending on session
├── login.php               <- login form
├── logout.php              <- destroys session, redirects to login
├── dashboard.php           <- overview: recent shifts, summary stats
├── shift_new.php           <- form to start a new shift
├── shift_view.php          <- single shift: map + delivery list + shift stats
├── delivery_add.php        <- add a delivery to an open shift
├── delivery_delete.php     <- delete a delivery (POST only)
├── shift_close.php         <- mark a shift as finished (set end_time)
├── api/
│   └── geocode.php         <- server-side proxy for Nominatim calls
├── assets/
│   ├── style.css
│   └── app.js
└── includes/
    ├── db.php              <- PDO connection singleton
    ├── auth.php            <- session check; redirects to login.php if not logged in
    └── helpers.php         <- haversine distance, formatting utilities
```

---

## Address Lookup Flow (Nominatim)

1. On the "add delivery" form, user types a street address. Town is pre-filled based on
   the store's location (e.g. hardcoded to "Derby" or whatever the store's town is).
2. User clicks "Find on map" — JS sends the query to `api/geocode.php`
3. `geocode.php` forwards the request to Nominatim with a proper User-Agent header
   (Nominatim requires a descriptive User-Agent — use `ForgemillTracker/1.0`)
4. Nominatim returns lat/lng — the first result is used
5. A pin drops on the Leaflet map preview so the user can confirm it looks right
6. Lat/lng are stored in hidden form fields
7. On submit, PHP saves the full delivery record to MySQL

### Nominatim endpoint
```
https://nominatim.openstreetmap.org/search
  ?q=42+Hartington+Street+Derby
  &format=json
  &limit=1
  &countrycodes=gb
```

### Important Nominatim rules
- Max 1 request per second (fine for manual one-at-a-time entry)
- Must send a descriptive `User-Agent` header — do this server-side in `geocode.php`
- Must not be used for bulk or automated querying
- All Nominatim calls must go through `api/geocode.php` — never call Nominatim
  directly from the browser, as browsers may strip or misformat the User-Agent

---

## Map (Leaflet.js)

- Loaded from CDN — no npm, no build step
- Each shift view shows a map centred on the store location
- Store marked with a distinct icon (different colour from delivery pins)
- Each delivery shown as a numbered marker in sequence order
- A polyline connects: store → delivery 1 → delivery 2 → ... → store
- Clicking a pin shows a popup: address, postcode, tip amount (if any)
- On the "add delivery" page, a smaller preview map shows the geocoded pin before saving

---

## Distance & Stats

Distance calculated using the **Haversine formula** in `helpers.php`.
Returns distance in miles between two lat/lng points.

Per shift, calculate:
- Total distance: store → d1 → d2 → ... → store (sum of all legs)
- Number of deliveries
- Total tips for the shift
- Average distance per delivery

Dashboard stats (across all shifts):
- Total shifts logged
- Total deliveries
- Total tips earned
- Average deliveries per shift
- Average miles per shift
- Most frequent postcode

---

## Authentication

- Session-based login (no OAuth, no registration page)
- Single user — seed directly via SQL:
  ```sql
  INSERT INTO users (username, password_hash)
  VALUES ('sam', '$2y$10$...'); -- generate hash with password_hash() in a one-off PHP script
  ```
- Passwords stored as bcrypt hashes using `password_hash()` and verified with `password_verify()`
- Every protected page must begin with `require 'includes/auth.php';`

---

## PHP Conventions

- Always use **PDO with prepared statements** — never interpolate user input into SQL
- Use `htmlspecialchars()` when outputting any user-supplied data to HTML
- Call `session_start()` at the top of every page that uses sessions
- All form submissions use **POST**, never GET
- Keep PHP logic at the top of each file, HTML output below
- Handle errors gracefully — `display_errors` should be off in production

---

## Style

- **Dark theme** — this is used at night after shifts
- **Mobile-first** — deliveries will often be added on a phone after a run
- Functional and clean — not a showpiece, just quick and easy to use
- Map should be tall and full-width on the shift view page
- Form inputs and buttons should be large enough for fat thumbs on mobile

---

## Out of Scope (for now)

- User registration (single hardcoded user only)
- PWA / offline support
- Automatic shift detection
- Any integration with Domino's systems
- Public-facing pages
