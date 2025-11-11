# Installation & Setup Guide

Complete step-by-step guide to install and run the Space Booking Portal on your local machine.

## Prerequisites

Before you begin, ensure you have the following installed:

- **XAMPP** (includes Apache, MySQL, and PHP)
  - Download from: https://www.apachefriends.org/
  - Version: XAMPP 8.0 or higher recommended
- **Web Browser** (Chrome, Firefox, Edge, or Safari)
- **Text Editor** (optional, for editing configuration files)

## Step 1: Install XAMPP

1. Download XAMPP from the official website
2. Run the installer and follow the installation wizard
3. Install to the default location: `C:\xampp\`
4. During installation, select:
   - Apache
   - MySQL
   - PHP
   - phpMyAdmin (recommended)

## Step 2: Extract Project Files

1. Ensure your project folder is located at:
   ```
   C:\xampp\htdocs\bookin1211\
   ```

2. If you have the project in a different location, move it to the `htdocs` folder

3. Verify the folder structure:
   ```
   C:\xampp\htdocs\bookin1211\
   ├── api/
   ├── assets/
   ├── config/
   ├── database/
   ├── partials/
   ├── index.php
   └── README.md
   ```

## Step 3: Start XAMPP Services

1. Open **XAMPP Control Panel**
   - You can find it in the Start Menu or desktop shortcut

2. Start the following services:
   - Click **Start** button next to **Apache**
   - Click **Start** button next to **MySQL**
   
3. Verify both services show **Running** status (green indicator)

   ⚠️ **Note**: If Apache or MySQL fails to start:
   - Check if ports 80 (Apache) or 3306 (MySQL) are already in use
   - Close other applications using these ports
   - Try running XAMPP Control Panel as Administrator

## Step 4: Create Database

### Option A: Using phpMyAdmin (Recommended)

1. Open your web browser and go to:
   ```
   http://localhost/phpmyadmin
   ```

2. Click on **New** in the left sidebar to create a new database

3. Enter database name: `booking_db`

4. Select **Collation**: `utf8mb4_general_ci` (or leave default)

5. Click **Create**

6. Click on the `booking_db` database in the left sidebar

7. Click on the **Import** tab at the top

8. Click **Choose File** and select:
   ```
   C:\xampp\htdocs\bookin1211\database\schema.sql
   ```

9. Scroll down and click **Go**

10. You should see a success message: "Import has been successfully finished"

11. Verify tables were created:
    - Click on `booking_db` in the left sidebar
    - You should see 5 tables:
      - `bookings`
      - `locations`
      - `space_availability`
      - `spaces`
      - `space_types`

### Option B: Using MySQL Command Line

1. Open Command Prompt or PowerShell

2. Navigate to XAMPP MySQL directory:
   ```bash
   cd C:\xampp\mysql\bin
   ```

3. Connect to MySQL:
   ```bash
   mysql -u root -p
   ```
   (Press Enter when prompted for password, as default XAMPP has no password)

4. Run the schema file:
   ```sql
   source C:/xampp/htdocs/bookin1211/database/schema.sql
   ```

5. Verify installation:
   ```sql
   USE booking_db;
   SHOW TABLES;
   ```

6. Exit MySQL:
   ```sql
   exit;
   ```

## Step 5: Configure Database Connection (If Needed)

The default configuration should work with standard XAMPP setup. However, if you've changed MySQL settings:

1. Open the file:
   ```
   C:\xampp\htdocs\bookin1211\config\db.php
   ```

2. Locate the `get_db_config()` function (around line 16)

3. Update these values if needed:
   ```php
   'host' => 'localhost',        // Usually 'localhost'
   'port' => '3306',             // Usually '3306'
   'name' => 'booking_db',       // Database name
   'user' => 'root',              // MySQL username
   'pass' => '',                  // MySQL password (empty for default XAMPP)
   ```

4. Save the file

## Step 6: Verify Installation

### Test Database Connection

1. Open your browser and go to:
   ```
   http://localhost/bookin1211/api/test-db.php
   ```

2. You should see a JSON response like:
   ```json
   {
     "success": true,
     "message": "Database connection successful",
     "existing_tables": ["bookings", "locations", "space_availability", "spaces", "space_types"],
     "missing_tables": [],
     "record_counts": {
       "bookings": 0,
       "locations": 6,
       "space_availability": 12,
       "spaces": 6,
       "space_types": 5
     }
   }
   ```

### Test the Portal

1. Open your browser and go to:
   ```
   http://localhost/bookin1211
   ```

2. You should see the booking portal homepage

3. Try the following:
   - Select a space type from the dropdown
   - Pick a date (today or future)
   - Set a time range (e.g., 10:00 to 12:00)
   - Enter number of people
   - Type a location (e.g., "Mumbai" or "Bengaluru")
   - Click on a space card to open booking form
   - Fill in your details and submit

## Step 7: Common Issues & Solutions

### Issue: "Database connection failed"

**Solution:**
- Ensure MySQL is running in XAMPP Control Panel
- Verify database `booking_db` exists in phpMyAdmin
- Check database credentials in `config/db.php`

### Issue: "404 Not Found" or blank page

**Solution:**
- Verify Apache is running in XAMPP Control Panel
- Check that files are in `C:\xampp\htdocs\bookin1211\`
- Try accessing: `http://localhost/bookin1211/index.php`

### Issue: "No spaces available"

**Solution:**
- Verify database tables have data:
  - Go to phpMyAdmin
  - Check `spaces` table has 6 records
  - Check `space_availability` table has records
- Re-import `database/schema.sql` if tables are empty

### Issue: Port 80 or 3306 already in use

**Solution:**
- Close other web servers (IIS, Skype, etc.)
- Or change Apache port in XAMPP:
  - Click **Config** next to Apache
  - Select **httpd.conf**
  - Change `Listen 80` to `Listen 8080`
  - Access portal at: `http://localhost:8080/bookin1211`

### Issue: Booking not saving

**Solution:**
- Check browser console (F12) for JavaScript errors
- Verify database connection using test endpoint
- Check PHP error logs in: `C:\xampp\php\logs\php_error_log`
- Ensure `bookings` table exists and has correct structure

## Step 8: Daily Usage

### Starting the Project

1. Open **XAMPP Control Panel**
2. Start **Apache** and **MySQL** services
3. Open browser and go to: `http://localhost/bookin1211`

### Stopping the Project

1. In XAMPP Control Panel, click **Stop** for Apache and MySQL
2. Close the browser

### Viewing Bookings

1. Go to phpMyAdmin: `http://localhost/phpmyadmin`
2. Select `booking_db` database
3. Click on `bookings` table
4. Click **Browse** tab to see all bookings

## Step 9: Updating the Project

If you need to update the database schema:

1. **Backup your data** (export from phpMyAdmin)
2. Go to phpMyAdmin
3. Select `booking_db` database
4. Click **Import** tab
5. Select `database/schema.sql`
6. Check **Add DROP TABLE** option (if you want to reset)
7. Click **Go**

⚠️ **Warning**: This will delete all existing bookings if you check "Add DROP TABLE"

## Step 10: Production Deployment (Optional)

For production deployment, consider:

1. **Change database credentials** in `config/db.php`
2. **Set up proper error handling** (disable debug messages)
3. **Configure HTTPS** for secure connections
4. **Set up regular database backups**
5. **Use environment variables** for sensitive configuration

## Quick Reference

| Service | URL | Default Port |
|---------|-----|--------------|
| Portal | http://localhost/bookin1211 | 80 |
| phpMyAdmin | http://localhost/phpmyadmin | 80 |
| Test DB | http://localhost/bookin1211/api/test-db.php | 80 |

| Default Credentials | Value |
|---------------------|-------|
| MySQL Username | `root` |
| MySQL Password | *(empty)* |
| Database Name | `booking_db` |

## Need Help?

- Check `README.md` for feature documentation
- Check `docs/TROUBLESHOOTING.md` for detailed troubleshooting
- Review browser console (F12) for JavaScript errors
- Check PHP error logs in XAMPP

## Next Steps

After successful installation:

1. ✅ Test the booking flow end-to-end
2. ✅ Verify bookings are saved in database
3. ✅ Customize space types and locations if needed
4. ✅ Add more availability slots in `space_availability` table
5. ✅ Explore the codebase to understand the architecture

---

**Congratulations!** Your Space Booking Portal is now installed and ready to use! 🎉

