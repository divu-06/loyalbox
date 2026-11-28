# LoyalBox: Customer Loyalty Rewards System

LoyalBox lets a factory reward customers who buy its boxed products. Every box carries a unique hidden code. The customer types the code into the website, earns points, and spends the points on rewards the factory owner manages.

**Deployed link:** none. The project runs locally on XAMPP (see the setup instructions below).

## What it does

**Customers**
- Register and log in
- Dashboard with the current points balance
- Claim points by entering the unique code found inside a product box
- Transaction history (points earned and spent)
- Browse available rewards and redeem them with points

**Factory owner (admin)**
- Secure admin login
- Dashboard with statistics (customers, codes generated, unused, claimed, points earned and redeemed, pending redemptions, low-stock alerts)
- Generate batches of unique product codes and download them once as a CSV for printing
- Void the unused codes of a batch (for a lost file or a recalled batch)
- Manage customers: search, view balances, block and unblock
- Manage rewards: add, edit, set point cost and stock, activate or deactivate
- Monitor claimed codes (customer, product, points, IP address), redemptions (mark as fulfilled), and unusual activity

## How the three design questions are answered

| Question | Answer |
|---|---|
| How does a customer prove they bought a product? | Each box has a random 130-bit code printed inside (for example under a scratch panel). Entering a valid, unused code is the proof of purchase. |
| How are fraud and code reuse prevented? | Only SHA-256 hashes of codes are stored. A claim is one atomic `UPDATE ... WHERE status='unused'`, so a code can be claimed exactly once even when two requests arrive at the same instant. Database constraints, rate limiting, CSRF tokens and server-side checks add further layers. |
| How does the owner view, manage and monitor rewards? | Admin dashboard, batch generation, customer and reward management, and a monitor page for claims, redemptions and suspicious activity. |

## Tech stack

| Layer | Choice |
|---|---|
| Backend | PHP 8 (plain PHP, no framework) |
| Database | MySQL / MariaDB (InnoDB), accessed with PDO |
| Frontend | HTML, Bootstrap 5 (CDN), a little JavaScript |
| Local server | XAMPP (Apache, PHP 8.2, MariaDB 10.4) |
| Version control | Git and GitHub |

Free tools only: no paid APIs and no Composer packages.

## Security highlights

- Passwords hashed with `password_hash()` (bcrypt) and checked with `password_verify()`
- Prepared statements for every query (real prepared statements, emulation off)
- Product codes: 26 random symbols (130 bits) from a cryptographically secure generator; only the SHA-256 hash is stored
- One-time claim enforced by an atomic SQL update inside a transaction, plus `UNIQUE(code_id)` on the points ledger
- Redemption locks the user row and the reward row (`SELECT ... FOR UPDATE`) before checking balance and stock, so simultaneous requests cannot double-spend
- Points live in a ledger table (earn rows positive, redeem rows negative, enforced by a `CHECK` constraint); the balance is always the sum of the ledger
- CSRF tokens on every form (including logout), session ID regenerated at login, `HttpOnly` and `SameSite` cookies
- Role check on every admin and customer page; blocked users are rejected on their next request
- Rate limiting: 5 failed logins or 5 failed claims per IP in 15 minutes
- Identical error messages for wrong email or password, and for invalid, used or voided codes
- All output escaped; CSV export protected against spreadsheet formula injection
- `.env` and `.sql` files blocked by `.htaccess`; secrets never committed (`.env.example` holds placeholders only)
- Admin accounts can only be created from the command line, never through the website

## Setup and run (macOS with XAMPP)

**Prerequisites:** XAMPP with PHP 8.x and MariaDB/MySQL, and Git.

1. **Get the code** into the XAMPP web folder. The project must live in a folder named `loyalbox`, because the default base path is `/loyalbox`.
   ```bash
   cd /Applications/XAMPP/xamppfiles/htdocs
   git clone https://github.com/divu-06/loyalbox.git
   cd loyalbox
   ```

2. **Start the services:**
   ```bash
   sudo /Applications/XAMPP/xamppfiles/xampp startmysql
   sudo /Applications/XAMPP/xamppfiles/xampp startapache
   ```

3. **Create the database and an application user** (choose your own password; use letters and numbers only):
   ```bash
   /Applications/XAMPP/xamppfiles/bin/mysql -u root
   ```
   ```sql
   CREATE DATABASE loyalbox CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
   CREATE USER 'loyalbox_app'@'localhost' IDENTIFIED BY 'your_password';
   CREATE USER 'loyalbox_app'@'127.0.0.1' IDENTIFIED BY 'your_password';
   GRANT ALL PRIVILEGES ON loyalbox.* TO 'loyalbox_app'@'localhost';
   GRANT ALL PRIVILEGES ON loyalbox.* TO 'loyalbox_app'@'127.0.0.1';
   FLUSH PRIVILEGES;
   EXIT;
   ```

4. **Configure the environment:**
   ```bash
   cp .env.example .env
   nano .env        # set DB_PASS to the password from step 3
   ```
   `.env` is ignored by Git and must never be committed.

5. **Create the tables and the demo rewards:**
   ```bash
   /Applications/XAMPP/xamppfiles/bin/mysql -u root loyalbox < database/schema.sql
   /Applications/XAMPP/xamppfiles/bin/mysql -u root loyalbox < database/seed_demo.sql
   ```
   Run `seed_demo.sql` only once; it adds four sample rewards.

6. **Create the admin account** (you choose the password; it is typed hidden and never stored in the code):
   ```bash
   /Applications/XAMPP/xamppfiles/bin/php database/create_admin.php "Factory Owner" admin@loyalbox.local
   ```

7. **Open the app:** <http://localhost/loyalbox/>
   - Admin: log in with the email and password from step 6
   - Customer: use "Register" on the login page

## Try it (demo flow)

1. Log in as admin, open **Codes**, generate a batch (for example "Demo Soap", 10 points, quantity 5). A CSV downloads with the plaintext codes. This is the only time they exist.
2. Log out, register a customer, open **Claim**, and enter one code from the CSV. The balance increases.
3. Enter the same code again, or as another customer. It is rejected.
4. Open **Rewards** and redeem one (the customer needs enough points).
5. Log in as admin, open **Monitor** to see the claim and the pending redemption, and mark it as fulfilled. Open **Customers** to block a user.

## Tests

Automated checks (run from the project folder):
```bash
/Applications/XAMPP/xamppfiles/bin/php tests/test_codes.php     # code generation and hashing
/Applications/XAMPP/xamppfiles/bin/php tests/test_claim.php     # one-time claim logic
/Applications/XAMPP/xamppfiles/bin/php tests/test_redeem.php    # redemption logic
```
The claim and redemption tests create and remove their own temporary data. `tests/race_claim.php` and `tests/race_redeem.php` fire simultaneous requests to prove that only one request can win. The browser flows are covered by the manual test plan with recorded results: [docs/TEST_PLAN.md](docs/TEST_PLAN.md).

## Project structure

```
loyalbox/
├── admin/          dashboard, generate (codes), customers, rewards, monitor
├── customer/       dashboard, claim, rewards, history
├── includes/       config, db, auth, csrf, helpers, ratelimit, codes, claim, redeem, header/footer
├── database/       schema.sql, seed_demo.sql, create_admin.php (command line only)
├── tests/          automated tests and race-condition scripts
├── docs/           TEST_PLAN.md
├── login.php, register.php, logout.php, index.php
├── .env.example    placeholder configuration (copy to .env)
└── README.md, ASSUMPTIONS.md, ARCHITECTURE.md, AI_LOG.md
```

## Documentation

- [ASSUMPTIONS.md](ASSUMPTIONS.md): every gap in the requirements and the choice made
- [ARCHITECTURE.md](ARCHITECTURE.md): stack reasons, data model and diagrams
- [AI_LOG.md](AI_LOG.md): key AI interactions and the AI's mistakes
- [docs/TEST_PLAN.md](docs/TEST_PLAN.md): test plan and results

## Known limitations

- No email verification or password reset (it would need an email service)
- Rate limiting is per IP address, so people sharing one IP share a counter
- A photographed or stolen code that is claimed first cannot be detected by software alone; the scratch panel and the monitor page reduce the risk
- Redemptions can be marked fulfilled but not cancelled or refunded
- Admin actions (blocking, reward edits) are not written to an audit log
- Runs over plain HTTP locally; use HTTPS in production (the session cookie becomes `Secure` automatically)
- Bootstrap loads from a CDN, so the machine needs internet access
