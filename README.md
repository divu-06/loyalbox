# LoyalBox – Customer Loyalty Rewards System

LoyalBox is a PHP and MySQL web application for a factory that sells boxed products. Customers earn points by entering a unique code concealed inside a product box, then redeem those points for rewards. Factory administrators manage code batches, customers, rewards, and redemption fulfilment.

## Features

### Customer
- Register and sign in with an email and password.
- View the current points balance and transaction history.
- Enter a product-box code to claim points.
- Browse active rewards and redeem available rewards when the account has enough points and stock is available.

### Factory administrator
- View customer, code, points, and redemption statistics.
- Generate unique product-code batches and download their codes as a CSV.
- View recent batch counts and void unused codes in a batch.
- Search customers and block or unblock customer accounts.
- Create and update rewards, costs in points, stock, and active status.
- Monitor claimed codes, customer activity alerts, and redemptions; mark pending redemptions fulfilled.

## Purchase proof and code reuse

Each physical box is assumed to contain one concealed, one-time code under a scratch-off label. A customer types that code into the claim page. Each generated code contains 26 characters from a 32-symbol alphabet, giving 130 bits of random input, and is displayed in grouped form to make manual entry easier.

The application stores only a SHA-256 hash of each code, not the plaintext code. Plaintext codes are included in the CSV download once at batch generation time. A database update changes a code from `unused` to `claimed` only when it is still unused. The point-ledger table also has a unique constraint on `code_id`. Together, these controls prevent a code from crediting points more than once, including simultaneous claim attempts. Invalid, already-claimed, and void codes use the same customer-facing error message.

This mechanism demonstrates purchase-code verification. It cannot independently detect a code photographed or copied before the first legitimate claim; physical concealment and factory monitoring remain important.

## Technology stack

- PHP 8 (tested environment PHP 8.2.4)
- MariaDB 10.4.28 / MySQL-compatible database
- PDO with prepared statements
- Bootstrap 5.3.3 stylesheet via CDN
- Vanilla JavaScript for small browser interactions
- Apache and XAMPP for the local demonstration
- Git and GitHub for version control

## Requirements

- XAMPP with Apache, PHP, and MariaDB/MySQL.
- PHP extensions including PDO MySQL and mbstring.
- A browser and internet access for the Bootstrap CDN stylesheet.

## Local setup (macOS with XAMPP)

1. Place or clone this repository into XAMPP's document root:

   ```bash
   cd /Applications/XAMPP/xamppfiles/htdocs
   git clone https://github.com/divu-06/loyalbox.git
   cd loyalbox
   ```

2. Start Apache and MySQL from XAMPP, or use:

   ```bash
   sudo /Applications/XAMPP/xamppfiles/xampp startapache
   sudo /Applications/XAMPP/xamppfiles/xampp startmysql
   ```

3. Create the database and dedicated application accounts. For example, using an administrative MariaDB/MySQL account:

   ```sql
   CREATE DATABASE loyalbox CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
   CREATE USER 'loyalbox_app'@'localhost' IDENTIFIED BY 'choose-a-local-password';
   CREATE USER 'loyalbox_app'@'127.0.0.1' IDENTIFIED BY 'choose-a-local-password';
   GRANT ALL PRIVILEGES ON loyalbox.* TO 'loyalbox_app'@'localhost';
   GRANT ALL PRIVILEGES ON loyalbox.* TO 'loyalbox_app'@'127.0.0.1';
   FLUSH PRIVILEGES;
   ```

   Choose your own local password. If the accounts already exist, update their credentials and grants rather than recreating them.

4. Import the schema:

   ```bash
   /Applications/XAMPP/xamppfiles/bin/mysql -u root loyalbox < database/schema.sql
   ```

5. Create a local environment file and edit it:

   ```bash
   cp .env.example .env
   ```

   Example values (replace the password placeholder locally):

   ```env
   DB_HOST=127.0.0.1
   DB_PORT=3306
   DB_NAME=loyalbox
   DB_USER=loyalbox_app
   DB_PASS=replace_with_your_local_password
   APP_ENV=development
   ```

   The app base path defaults to `/loyalbox`. Add `APP_BASE` to `.env` when installing under a different URL path.

6. Create an administrator from the command line. The script refuses non-CLI requests and prompts for the password without echoing it:

   ```bash
   /Applications/XAMPP/xamppfiles/bin/php database/create_admin.php "Factory Admin" admin@example.com
   ```

7. Optional: insert the demonstration rewards:

   ```bash
   /Applications/XAMPP/xamppfiles/bin/mysql -u root loyalbox < database/seed_demo.sql
   ```

8. Open `http://localhost/loyalbox/`, register a customer account, then sign in with the administrator account to generate code batches and manage the system.

## Tests

The observed test plan and results are in [docs/TEST_PLAN.md](docs/TEST_PLAN.md).

Run the automated checks from the repository root:

```bash
php tests/test_codes.php
php tests/test_claim.php
php tests/test_redeem.php
```

The test plan also documents browser checks, database integrity checks, and simultaneous claim/redemption race tests. Results recorded there were observed in the local XAMPP environment; rerun the tests after changing code or schema.

## Project documentation

- [Architecture](ARCHITECTURE.md) – application structure, schema, and request flows.
- [Assumptions and scope](ASSUMPTIONS.md) – assumptions, trade-offs, and exclusions.
- [AI interaction log](AI_LOG.md) – AI-assisted decisions, mistakes, and corrections.
- [Test plan and results](docs/TEST_PLAN.md) – automated and manual checks.

## Demo and deployment status

The project was tested locally using XAMPP. No public deployment URL is currently documented; the local demonstration URL is `http://localhost/loyalbox/`.

## Security notes

- Never commit `.env`, database passwords, generated CSVs, or real product codes.
- `.env.example` contains placeholders only. The root `.htaccess` denies direct access to `.env` and SQL files under Apache.
- Public registration creates customers only; administrator creation is a command-line operation.
- Before public deployment, use HTTPS, production-grade secret management, backups, secure server configuration, and a deployment-specific security review.
