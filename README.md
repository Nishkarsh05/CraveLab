# CraveLab — Recipe Sharing & Food Community Platform

A database-driven PHP/MySQL recipe and creator community project.

## Stack
- PHP 8+
- MySQL / MariaDB
- HTML, CSS and JavaScript
- XAMPP / Apache

## Run locally
1. Copy the `CraveLab` folder into `C:\xampp\htdocs\`.
2. Start Apache and MySQL in XAMPP.
3. Open phpMyAdmin at `http://localhost/phpmyadmin/`.
4. Create/import the database using `database.sql` if setting up a fresh database.
5. Check `includes/db.php` and update the database credentials if needed.
6. Open `http://localhost/CraveLab/`.

If you already have a working `cravelab_db`, **do not import the fresh-install schema over it**. Back up your current database first. The SQL file is a fresh-install template based on the columns referenced by this code; it is not a backup of your existing users or recipes.

## Features in this build
- Registration, login and logout
- Recipe creation, editing, deletion and device-based image uploads
- Recipe search and category filtering
- Recipe comments and saved recipes
- Personal profile and dashboard
- Public/private creator profiles
- Follow, unfollow and private follow requests
- Accept/decline follow requests and notifications
- Following feed and Fridge Roulette ingredient search
- Private recipe visibility checks across recipe browsing, saved recipes and Fridge Roulette

## Security included
- Prepared SQL statements for user-controlled values
- Session ID regeneration at login
- HttpOnly/SameSite session cookies
- CSRF validation for state-changing forms
- Owner-scoped recipe/profile updates and deletes
- Image MIME/size checks and randomized upload filenames
- Upload folders deny script execution

## Before public deployment
- Change the database credentials in `includes/db.php`; never use the XAMPP `root` account with an empty password on a public server.
- Use HTTPS.
- Keep PHP, MySQL and the hosting environment updated.
- Disable public error display and keep backups.
- Configure the hosting document root to this project folder.
- Test the site on the actual host; `localhost` is only accessible on the same computer.
