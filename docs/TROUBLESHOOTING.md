# Troubleshooting Guide

Use this checklist whenever the portal misbehaves. Steps progress from quickest checks to deeper investigations.

---

## 1. Quick Health Checks

1. **Verify services are running**  
   Open XAMPP Control Panel → ensure **Apache** and **MySQL** are green.

2. **Run the health endpoint**  
   Visit `http://localhost/bookin1211/api/test-db.php`.  
   - `success: true` → DB connection + schema OK.  
   - `missing_tables` shows any tables that must be imported.

3. **Clear browser cache**  
   Hard refresh (`Ctrl+Shift+R`) to ensure the latest JS/CSS are loaded.

---

## 2. Database Issues

| Symptom | Fixes |
|---------|-------|
| `Database connection failed: ...` on page load | - Check MySQL credentials in `config/db.php`.<br>- If using a different DB name, update `BOOKING_DB_NAME` or the default array.<br>- Confirm `booking_db` exists (`phpMyAdmin` → Databases). |
| `missing_tables` in health endpoint | Re-run `database/schema.sql`. This drops and recreates all tables with fresh seed data. |
| Bookings not saving but no error displayed | Check `bookings` table manually in phpMyAdmin. If empty, open browser console for API errors (see next section). |

---

## 3. Booking API Errors

1. Open **DevTools → Console**.
2. Submit a booking.  
   The JS logs `Submitting booking with data: { ... }` and any API error.

| Error Message | Probable Causes | Fixes |
|---------------|----------------|-------|
| `Select a date and time range to book a space.` | Missing date/start/end time in filters. | Select a date, start time, and end time before clicking **Book now**. |
| `The selected time slot is not available.` | - No matching availability record for that slot.<br>- Conflicts with existing booking. | Pick a different time range or date. Seed data includes sample availability; adjust `space_availability` table if needed. |
| `Failed to save booking to database: ...` | Insertion error (e.g., wrong column definitions, db user lacks permissions). | The exact PDO message is logged. Check schema, auto-increment columns, and database grants. |
| JS error `Server returned an invalid response.` | API returned non-JSON output (warnings, notices). | Enable `display_errors=Off` in PHP or fix the underlying warnings. Check Apache/PHP logs (`xampp/apache/logs/error.log`). |

---

## 4. Frontend Debugging Tips

- **Render issues** – inspect elements with DevTools, ensure CSS loaded (`Network` tab should show 200 for `styles.css` and `app.js`).
- **Stale scripts** – hard refresh or clear cache. If using different port/path, update script link in `partials/footer.php`.
- **Location suggestions missing** – confirm `locations` table has rows. API falls back to “No matches” when empty.

---

## 5. Resetting Seed Data

To restore the original dataset:

1. Backup any real bookings (optional).  
   `SELECT * FROM bookings;`

2. Re-run the seed script:  
   ```bash
   mysql -u root -p < database/schema.sql
   ```

   or via phpMyAdmin → Import.

3. Refresh the portal.

---

## 6. Where to Look for Logs

- **Browser Console** – AJAX payloads, warnings, validation messages.
- **Network tab** – inspect `api/booking.php` and `api/filter.php` responses.
- **Apache/PHP error log** – `C:\xampp\apache\logs\error.log`.
- **MySQL errors** – `C:\xampp\mysql\data\mysql_error.log` (if enabled).

---

Still stuck? Capture:

1. The error message (from UI or console).
2. Screenshot / copy of `api/test-db.php` output.
3. Relevant log snippets.

Share that info for quicker triage. Happy debugging! 🛠️

