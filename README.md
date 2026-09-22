# 👑 THE CLUB 777® — Enterprise Gym Management, Touch POS & Biometric IoT ERP

![PHP Version](https://img.shields.io/badge/PHP-8.0%2B-777BB4?style=for-the-badge&logo=php&logoColor=white)
![Database](https://img.shields.io/badge/MySQL-MariaDB-003545?style=for-the-badge&logo=mysql&logoColor=white)
![PWA](https://img.shields.io/badge/PWA-100%25%20Offline%20Ready-5A0FC8?style=for-the-badge&logo=pwa&logoColor=white)
![IoT Biometric](https://img.shields.io/badge/Biometric-eSSL%20%7C%20ZKTeco%20ADMS-059669?style=for-the-badge)
![WhatsApp](https://img.shields.io/badge/WhatsApp-Multi--Device%20Baileys-25D366?style=for-the-badge&logo=whatsapp&logoColor=white)

A high-performance, commercial-grade **Gym Management, Touch POS, and Biometric Attendance System** built for modern fitness clubs, swimming facilities, and wellness centers. Features native **eSSL/ZKTeco ADMS Cloud Push**, **Realtime LAN socket integration**, **Node.js WhatsApp automation**, and **100% Offline PWA capability** with IndexedDB local caching.

---

## 🌟 Key Highlights & Core Features

### 1. 📟 Native eSSL & ZKTeco ADMS Biometric Gateway
* **Direct Cloud Push (`/iclock/cdata`):** No third-party middleware (like eTimeTrackLite) required. Machine connects directly over HTTP port 80.
* **Smart Face & Fingerprint Recognition:**
  * Tested on **eSSL Smart Terminal (SN: NYU7262500437)** with Firmware Ver `3.2.1.2.1.1`.
  * **Auto Check-In / Check-Out Toggle:** First punch of the day records Check-In; second punch records Check-Out and computes exact gym workout duration (minutes/hours).
  * **Duplicate Scan Buffer:** 5-minute intelligent window prevents double-counting if a member stands near the facial camera.
  * **Membership Expiry Alert:** Flags expired members upon facial recognition.
  * **Staff Shift Tracking:** Late arrival minutes and half-day thresholds automatically calculated based on assigned shifts.

### 2. 🛒 Touch POS & Retail Billing
* Touch-screen optimized barcode scanning & product catalog for supplements, drinks, and apparel.
* Multi-mode payment support (Cash, UPI, Card, Split Payment).
* **Thermal Receipt Printing (80mm & 58mm):** Instant browser print dialogs + branded PDF invoices via FPDF.
* **100% Offline Sales Engine:** Sales made when offline are queued in **IndexedDB** and auto-synced when the internet reconnects.

### 3. 👥 Comprehensive Membership & Member CRM
* Flexible membership plans (Monthly, Quarterly, Annual, Lifetime, Custom).
* Member profile hub with webcam photo capture, subscription freeze history, and fitness tracking.
* Lead management pipeline & trial pass booking with automated follow-ups.

### 4. 🏊 Swimming Pool, PT & Facility Modules
* **Swimming Pool Management:** Slot booking, capacity throttling, and swimmer attendance logs.
* **Personal Training (PT):** Trainer allocation, session tracking, package quotas, and commissions.
* **Diet & Workout Plans:** Curated diet charts and exercise logs assignable to members.

### 5. 🤖 Automated Multi-Device WhatsApp Gateway
* Built-in Node.js **Baileys Multi-Device** integration (`whatsapp_gateway/`).
* Instant WhatsApp dispatch on:
  * Check-In welcome with days remaining.
  * Workout completed with total duration.
  * Membership expiry and renewal reminders.
  * Direct PDF invoice dispatch.

### 6. ⚡ 100% Offline & Online Architecture
* **Zero External CDN Dependencies:** All CSS stylesheets, Chart.js (`chart.umd.min.js`), POS scripts, and barcode parsers are stored **locally**.
* **Service Worker v9 (`sw.js`):** Pre-caches application shell, icons, and static assets.
* **Non-blocking Fonts:** Uses system font stacks (`-apple-system`, `BlinkMacSystemFont`, `Segoe UI`, `Roboto`) if offline.

---

## 🏗️ System Architecture & Directory Structure

```plaintext
├── api/                     # Backend REST API Controllers
│   ├── attendance.php       # Live punch logs & manual overrides
│   ├── attendance_sync.php  # Real-time polling & summary statistics
│   ├── backup.php           # Database backup exporter
│   ├── devices.php          # Realtime LAN & TCP hardware manager
│   ├── members.php          # Member registration & profiles
│   ├── memberships.php      # Plans, subscriptions & freeze
│   ├── pos.php              # POS billing, sales & receipts
│   └── whatsapp.php         # WhatsApp gateway bridge
├── assets/                  # 100% Local Static Assets (Zero CDN)
│   ├── css/                 # variables.css, main.css, pos.css, print.css, enhanced.css
│   └── js/                  # app.js, pos.js, offline.js, scanner.js, chart.umd.min.js
├── config/                  # Database & System Configuration
│   ├── database.php         # PDO connection & schema auto-installer
│   ├── .env.example         # Database credentials template
│   └── .htaccess            # Security rules (prevents config browsing)
├── iclock/                  # eSSL & ZKTeco ADMS Cloud Push Gateway
│   ├── cdata.php            # Handshake (GET) & Live Punch receiver (POST)
│   ├── getrequest.php       # Heartbeat poller & remote command handler
│   ├── devicecmd.php        # Device command execution ACK
│   ├── index.php            # Fallback router
│   └── .htaccess            # Clean URL rewriting for /iclock/*
├── views/                   # UI View Components
│   ├── dashboard_view.php   # Analytics dashboard & KPIs
│   ├── devices_view.php     # Biometric hardware hub & setup guide
│   ├── pos_view.php         # Touch POS terminal
│   ├── members_view.php     # Member directory & enrollment
│   └── attendance_view.php  # Daily attendance & gate logs
├── whatsapp_gateway/        # Node.js Baileys Multi-Device WhatsApp Server
│   ├── server.js            # Express API + Baileys socket
│   └── package.json         # Node dependencies
├── gym_db_backup.sql        # Complete Production Database Dump (~3.8 MB)
├── index.php                # Front controller & main layout
├── manifest.json            # PWA Web App Manifest
├── sw.js                    # PWA Service Worker v9
└── .gitignore               # Excludes secrets (.env) & node_modules
```

---

## 📟 eSSL Smart Terminal Setup Guide (Cloud Settings)

To connect the **eSSL Face + Fingerprint Machine** (e.g. SN: `NYU7262500437`) to the software:

1. On the machine keypad, press **M/OK** to open the menu.
2. Navigate to: **Comm. ➔ Cloud Server Settings**.
3. Enter the following parameters:

| Setting Parameter | Exact Value to Enter |
| :--- | :--- |
| **Server Mode** | `ADMS` |
| **Enable Domain Name** | **ON** *(Green toggle)* |
| **Server Address** | `gym.ethicscomputer.in`<br>*(⚠️ Do NOT enter `http://` or trailing `/`)* |
| **Server Port** | `80` *(Scroll down to locate port)* |
| **Enable Proxy Server** | `OFF` |

4. Press **M/OK** to save and return to the home screen.
5. The cloud icon on the top status bar will turn **Green** upon successful handshake.

---

## 🚀 Installation & Deployment

### Option A: Local Deployment (XAMPP on Windows)

1. **Clone the Repository:**
   ```bash
   cd c:/xampp/htdocs
   git clone https://github.com/ethdevvinay/777gym.git GYM
   ```

2. **Start Services:**
   * Open **XAMPP Control Panel** and start **Apache** and **MySQL**.

3. **Import Database:**
   * Open `http://localhost/phpmyadmin/`.
   * Create a new database named `gym_db` (Collation: `utf8mb4_general_ci`).
   * Click **Import** ➔ Select `c:/xampp/htdocs/GYM/gym_db_backup.sql` ➔ Click **Go**.

4. **Access System:**
   * Open `http://localhost/GYM/` in your browser.
   * **Default Login:**
     * **Email:** `admin@fitzone.com`
     * **Password:** `admin123`

---

### Option B: Live Server Deployment (Hostinger / cPanel / VPS)

1. **Upload Files:**
   * Upload the codebase to your `public_html/` or subdomain directory (e.g. `gym.ethicscomputer.in`).

2. **Database Setup:**
   * In cPanel / Hostinger hPanel, create a new MySQL database and user.
   * Open **phpMyAdmin**, select your database, click **Import**, and select [`gym_db_backup.sql`](gym_db_backup.sql).

3. **Configure Environment:**
   * Create a file named `config/.env` (use [`config/.env.example`](config/.env.example) as reference):
     ```ini
     DB_HOST=localhost
     DB_USER=your_db_username
     DB_PASS=your_db_password
     DB_NAME=your_db_database
     DB_CHARSET=utf8mb4
     ```

4. **Verify ADMS Push URL:**
   * Test the handshake endpoint in browser:
     ```plaintext
     http://gym.ethicscomputer.in/iclock/cdata?SN=NYU7262500437&options=all
     ```
   * It should immediately respond with `GET_OPTION_FROM: NYU7262500437` and configuration flags.

---

## 📱 WhatsApp Multi-Device Gateway Setup

1. Open terminal inside `whatsapp_gateway/`:
   ```bash
   cd whatsapp_gateway
   npm install
   node server.js
   ```
2. Navigate to **Communication ➔ WhatsApp Gateway** in the Gym Admin Panel.
3. Scan the generated QR code using WhatsApp on your gym's official phone (**Linked Devices**).
4. All attendance check-ins, check-outs, receipts, and payment notifications will now dispatch automatically!

---

## 🛡️ Security & Cybersecurity Compliance

* **SQL Injection Prevention:** 100% prepared statements via PDO across all API endpoints.
* **XSS Defense:** Strict `htmlspecialchars()` sanitization on all output streams.
* **Credentials Protection:** Database passwords and Baileys session tokens are kept out of version control via `.gitignore`.
* **Zero Render-blocking:** Built-in system font fallbacks avoid CDN failure vulnerability during outages.

---

## 📄 License & Credits

Developed for **THE CLUB 777®**. All rights reserved.  
Maintained by **[ethdevvinay](https://github.com/ethdevvinay)**.
