# Space Booking Portal

Workspace discovery and booking portal built with **PHP**, **MySQL**, and **vanilla JavaScript**. Users can browse Indian workspaces by category, availability, capacity, and location; pricing updates instantly based on the requested slot and booking duration.

---

## ✨ Features

- Real-time filtering by space type, date, time range, headcount, and location.
- Live availability checks with conflict detection against existing bookings.
- Dynamic pricing formulas per space category and duration threshold (INR).
- Location auto-complete suggestions powered by the locations table.
- Booking modal with price summary, client validation, and server-side safeguards.
- Confirmation toast with a “My Bookings” modal to review recent reservations.
- Ready-to-use Indian seed data (Mumbai, Bengaluru, Delhi, Hyderabad).

---

## 🚀 Quick Start

**New to this project?** Start here:

1. 📖 Read the **[Installation Guide](INSTALLATION.md)** for step-by-step setup instructions
2. 🔧 Ensure XAMPP (Apache + MySQL) is installed and running
3. 🗄️ Import `database/schema.sql` into MySQL (via phpMyAdmin or command line)
4. 🌐 Open `http://localhost/bookin1211` in your browser

For detailed troubleshooting, see [docs/TROUBLESHOOTING.md](docs/TROUBLESHOOTING.md).

---

## 🧰 Tech Stack

| Layer        | Technology / Notes                                   |
|--------------|------------------------------------------------------|
| Frontend     | HTML5, vanilla JS (`assets/js/app.js`), CSS (`assets/css/styles.css`) |
| Backend      | PHP 8+ (PDO, JSON APIs)                              |
| Database     | MySQL 8 compatible                                   |
| Server       | Apache via XAMPP (served from `htdocs/bookin1211`)   |

---

## 📂 Project Structure

```
bookin1211/
├── api/
│   ├── booking.php        # Booking mutations + validation
│   ├── filter.php         # Filtered listings + suggestions
│   └── test-db.php        # Connection & schema health check
├── assets/
│   ├── css/styles.css     # Layout & components
│   └── js/app.js          # UI logic, AJAX, booking flow
├── config/
│   └── db.php             # PDO config + data helpers
├── database/
│   └── schema.sql         # Schema + seed data
├── partials/              # Shared header/footer
└── index.php              # Main portal shell
```

---

## 🚀 Getting Started

### 1. Prerequisites

- **XAMPP** (Apache + PHP 8.1+, MySQL 8+)
- Browser (Chrome/Edge recommended for dev tools)
- Optional: MySQL CLI or phpMyAdmin for DB management

### 2. Clone / Copy into `htdocs`

```text
C:\xampp\htdocs\bookin1211
```

### 3. Import the Database Schema

Option A – phpMyAdmin:
1. Visit http://localhost/phpmyadmin.
2. Create DB `booking_db` (or reuse an existing DB name of your choice).
3. Import `database/schema.sql`.

Option B – MySQL CLI:

```bash
mysql -u root -p < database/schema.sql
```

### 4. Configure Credentials (optional)

`config/db.php` defaults to:

| Value      | Default   |
|------------|-----------|
| Host       | `localhost` |
| Port       | `3306`    |
| Database   | `booking_db` |
| User       | `root`    |
| Password   | *(empty)* |

Override via environment variables (recommended for deployments):

```text
BOOKING_DB_HOST, BOOKING_DB_PORT, BOOKING_DB_NAME,
BOOKING_DB_USER, BOOKING_DB_PASS
```

### 5. Start Services

1. Launch Apache and MySQL from the XAMPP control panel.
2. Navigate to `http://localhost/bookin1211`.

### 6. Verify the Connection (Recommended)

Visit the lightweight health endpoint:

```
http://localhost/bookin1211/api/test-db.php
```

It returns JSON detailing connection status, missing tables, and record counts.

---

## 🧭 Booking Workflow

1. **Filter** – choose space type, date, time range, headcount, and search by city/area.
2. **Review results** – pricing updates instantly; cards show multipliers applied.
3. **Book** – hit “Book now”, fill in attendee details, and submit.
4. **Confirmation** – on success, a toast appears and “My Bookings” modal slides in with the latest entry.

---

## 💰 Pricing Logic

Total price combines:

| Component               | Details                                              |
|-------------------------|------------------------------------------------------|
| Base Rate               | `hourly_rate * duration`                             |
| Category Multiplier     | Meeting 1.00, Day Office 1.05, Co-working 0.90, Private 1.20, Custom 1.30 |
| Duration Multiplier     | ≤2h: 1.00, >2-4h: 1.10, >4-6h: 1.20, >6h: 1.35        |

The API returns a breakdown inside the `pricing` object for UI display.

---

## 🔌 API Overview

| Endpoint                 | Method | Description                                      |
|--------------------------|--------|--------------------------------------------------|
| `/api/filter.php`        | POST   | Filter spaces by provided criteria               |
| `/api/filter.php?action=suggest&term=ban` | GET | Location auto-complete suggestions         |
| `/api/booking.php`       | POST   | Submit booking request                           |
| `/api/test-db.php`       | GET    | Connection & schema diagnostics                  |

All responses are JSON with a `success` boolean and descriptive `message`.

---

## 🗄️ Database Tables (Quick Reference)

- `space_types` – seed data for categories.
- `locations` – city/area join table used for suggestions.
- `spaces` – workspace inventory with type, location, capacity, and base rate.
- `space_availability` – optional day-specific availability windows.
- `bookings` – confirmed reservations with customer & pricing info.

See `database/schema.sql` for full definitions and seed values.

---

## 🛠️ Troubleshooting

Common issues and fixes are documented in [`docs/TROUBLESHOOTING.md`](docs/TROUBLESHOOTING.md). Highlights:

- `Database connection failed` – check XAMPP MySQL, credentials, and schema import.
- Booking rejected – verify date/time validity and availability window.
- API returns invalid JSON – ensure no stray PHP output, use the bundled code.
- “Body stream already read” in JS – resolved in `assets/js/app.js` (read response once).

---

## 🧪 Development Tips

- Use the browser console (F12) – network logs echo form payloads and API responses.
- PHP linting: from XAMPP shell run `php -l path/to/file.php`.
- The JS console logs the booking payload and any server debug messages if errors occur.
- Seed data can be reloaded anytime by re-running `database/schema.sql` (will drop tables).

---

## 📈 Roadmap Ideas

- Persist "My Bookings" per user session (local storage or server-side).
- Admin dashboard for managing spaces, availability, and bookings.
- Export calendar invites or integration with Google Calendar/Outlook.
- Internationalization for currencies & locales.

---

## 📚 Documentation

| Document | Description |
|----------|-------------|
| **[INSTALLATION.md](INSTALLATION.md)** | Complete step-by-step installation and setup guide |
| **[docs/TROUBLESHOOTING.md](docs/TROUBLESHOOTING.md)** | Common issues, error solutions, and debugging tips |
| **README.md** (this file) | Project overview, features, and architecture |

---

Happy booking! 🎉

