# MSIT 114 - Activity 21 - Improved SlimPHP API with Front-End

This project is the **slim backend API** for the RailManager system. It handles data processing, authentication, email verification, and image management for users and train entities.

---

## Requirements

1. PHP 8.1 or higher
2. MySQL 5.7+ or MariaDB 10.3+
3. Composer

---

## Installation & Setup

### Step 1: Clone the Repository
```bash
git clone https://github.com/jesselzapanta09/railmanager-backend-slim-api.git
cd railmanager-backend-slim-api
```

### Step 2: Install Dependencies
```bash
composer install
```

### Step 3: Import the Database

1. Open your MySQL client (e.g., MySQL Workbench, phpMyAdmin, or CLI).
2. Create a new database:
```sql
   CREATE DATABASE trainappdb;
```
3. Import the provided SQL file(**trainappdb.sql**)

### Step 4: Run the Server
```bash
php -S localhost:5000 -t public router.php
```

The API should now be running at `http://localhost:5000`.

---

# System Feafure

1. CRUD with image - assigned entity.
2. User profile — Can modify user information with picture.
3. User registration using email — Send verification link through email.
4. Verify user using email address — Cannot log in if account is not verified.
5. Forgot password using email — Send a reset password link through email.
6. Log in and log out using authorization.


## Author

**Jessel Zapanta** — MSIT 114