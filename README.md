# Gatepass Pro - Smart Visitor Management System

Gatepass Pro is a secure, contactless, and responsive Visitor Gatepass Management System designed to modernize reception desks and lobby entries. It enables visitors to scan a lobby QR code with their cell phones, fill out their details, receive immediate digital tickets with confirmation statuses, and triggers SMTP email updates to both administrators and visitors.

---

## 🚀 Key Features

* **Contactless QR-Code Registration:** A printed Lobby QR Code is scanned by visitors' mobile phones, which redirects them to a responsive, mobile-friendly registration form.
* **Instant Digital Ticket Generation:** After form submission, a digital pass with a unique identifier (`CNX-YYYYMMDD-XXXX`) and an active verification QR Code is displayed on the visitor's screen.
* **Real-time Status Verification:** Security guards or admins can scan the visitor's ticket QR code to verify details and perform quick status updates: Approve, Reject, Check In, and Check Out.
* **Automatic Gmail/SMTP Dispatch:** Connects with Gmail SMTP via PHPMailer to send customized HTML confirmation passes directly to visitors and administrators.
* **Command Dashboard & Telemetry:** Monitors visitor flows, pending approvals, and active visitor counts from a centralized premium dark-mode console.
* **Auditable Log History:** Logs every check-in/check-out timestamp, with advanced search/filtering, and optimized print layouts for paper reports.
* **Self-Contained PHPMailer:** Uses a preloaded, composer-free, local installation of PHPMailer.

---

## 📂 Project Organization

The repository features a clean and highly organized modular directory structure:

```text
c:\xampp\htdocs\gatepass\
├── assets/
│   ├── css/
│   │   └── style.css          # Premium layout styling, fonts, and print directives
│   └── js/
│       └── main.js           # Client-side utility and auto-alert dismissal scripts
├── config/
│   └── database.php           # PDO Database connection and settings manager
├── includes/
│   ├── auth.php               # Login authorization and session handlers
│   ├── footer.php             # Page layout footer structures and scripts
│   ├── header.php             # Page layouts, CDN imports (Tailwind, FontAwesome, QRCode.js)
│   └── mailer.php             # PHPMailer SMTP client wrappers and HTML email templates
├── libs/
│   └── PHPMailer/             # PHPMailer core libraries (Exception, PHPMailer, SMTP)
├── admin/
│   ├── dashboard.php          # Command dashboard (KPIs, active list, and approvals)
│   ├── history.php            # Complete audit logging, filtering, and printing
│   ├── login.php              # Secure admin login form
│   ├── logout.php             # Session destroyer
│   ├── qr-generator.php       # Entrance Lobby QR-poster generator (printable)
│   └── settings.php           # SMTP configuration, network IP settings, and profile settings
├── index.php                  # Public entrance page (check status, quick access)
├── register.php               # Visitor mobile registration form
├── success.php                # Visitor ticket screen and scan verification QR code
├── verify.php                 # Security guard verification and check-in terminal
├── database.sql               # Database schema and seed queries
└── README.md                  # This guide
```

---

## ⚙️ Installation & Setup

Follow these simple steps to host the Gatepass System on your local XAMPP stack:

### 1. Repository Setup
1. Open the XAMPP Control Panel and start the **Apache** and **MySQL** services.
2. Copy or extract this system folder (`gatepass`) inside the XAMPP web root directory:
   `C:\xampp\htdocs\gatepass`

### 2. Database Creation & Seeding
1. Open your web browser and navigate to the phpMyAdmin panel:
   [http://localhost/phpmyadmin/](http://localhost/phpmyadmin/)
2. Click on the **Import** tab at the top.
3. Click **Choose File** and select the schema script located in the project folder:
   `C:\xampp\htdocs\gatepass\database.sql`
4. Click **Import** (or **Go** at the bottom) to execute the schema and populate default credentials.

### 3. Administrator Credentials
* **Username:** `admin`
* **Password:** `Jd$izdadJd$izdad`
* *Note: It is highly recommended to update your username and password immediately on the **Settings** page after logging in.*

### 4. Running the System
* **Admin Dashboard:** [http://localhost/gatepass/admin/](http://localhost/gatepass/admin/)
* **Visitor Welcome Portal:** [http://localhost/gatepass/](http://localhost/gatepass/)

---

## 📬 SMTP Email Configuration

For automated email notifications to function, you must configure a Gmail SMTP relay:

1. Log in to the Admin Dashboard and navigate to the **Settings** page.
2. Scroll to the **SMTP Mail Server Settings** section.
3. Supply the following parameters:
   * **SMTP Host Server:** `smtp.gmail.com`
   * **SMTP Port:** `587`
   * **Encryption Protocol:** `TLS`
   * **SMTP User/Username:** *Your Gmail address (e.g. youraccount@gmail.com)*
   * **SMTP App Password:** *A 16-character Google App Password (not your standard login password)*
4. Click **Save SMTP Credentials**.

> [!TIP]
> **How to create a Google App Password:**
> 1. Open your Google Account Settings and navigate to **Security**.
> 2. Ensure **2-Step Verification** is enabled.
> 3. Click **App passwords** (or search "App passwords" in the search box).
> 4. Generate a new app password for "GatePass".
> 5. Copy the 16-digit code provided and paste it into the settings page.

---

## 📱 Mobile Cellphone Access
To test scanning the QR codes and filling out forms using a real cellphone connected to the same local network:

1. Determine your computer's local Wi-Fi / LAN IP address (e.g. `192.168.1.15`).
2. Log in as an admin, go to **Settings** and update the **Server IP / Domain** field to this local IP address (e.g., replace `localhost` with `192.168.1.15`).
3. Save configurations.
4. Go to the **Entrance QR Poster** page (`admin/qr-generator.php`) and scan the Lobby QR code using your cellphone.
5. Make sure your phone is connected to the same Wi-Fi network as your server computer. The form will load instantly!
