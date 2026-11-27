# LoyalBox Test Plan and Results

**Environment:** macOS (MacBook Air M4), XAMPP, PHP 8.2.4, MariaDB 10.4.28, Apache
**Tested:** 26 to 27 Nov 2026
**Approach:** automated PHP test scripts for the security-critical logic (code generation, claiming, redemption), plus a documented manual test plan for the browser flows. Every result below was observed on the local machine.

## How to run the automated tests

```bash
php tests/test_codes.php     # code generation and hashing (6 checks)
php tests/test_claim.php     # claim logic: one-time use, no double credit (9 checks)
php tests/test_redeem.php    # redemption logic: balance, stock, blocked users (10 checks)
```

The claim and redemption tests create their own temporary users, codes and rewards, and delete them when they finish.

Concurrency (race) tests fire several processes at the same instant:

```bash
T=$(php -r 'echo microtime(true)+2;')
for i in 1 2 3 4 5 6; do php tests/race_redeem.php <userId> <rewardId> $T & done; wait
```

`tests/race_claim.php <userId> "<code>" <startTime>` works the same way for codes.

---

## Milestone 1: Database and connection

| # | Test | Expected | Result |
|---|------|----------|--------|
| 1.1 | Import `database/schema.sql` | 7 tables created | Pass |
| 1.2 | PHP connects with the dedicated `loyalbox_app` user via `127.0.0.1` | Connected, 7 tables listed | Pass |
| 1.3 | Request `/loyalbox/.env` in the browser | 403 Forbidden | Pass |

## Milestone 2: Authentication

| # | Test | Expected | Result |
|---|------|----------|--------|
| 2.1 | Register a new customer | Customer dashboard, balance 0 | Pass |
| 2.2 | Register again with the same email | "That email is already registered" (UNIQUE constraint) | Pass |
| 2.3 | Register with a 5-character password | Validation error | Pass |
| 2.4 | Customer login | Customer dashboard | Pass |
| 2.5 | Customer opens the admin URL | 403 Forbidden | Pass |
| 2.6 | Admin login | Admin dashboard | Pass |
| 2.7 | Logged-out user opens a dashboard | Redirect to login | Pass |
| 2.8 | 5 wrong passwords, then the correct one | "Too many failed attempts" (15 min lock) | Pass |
| 2.9 | Admin blocks a user while they are logged in | User is kicked out on the next request | Pass |
| 2.10 | Unblock the user and clear attempts | Login works again | Pass |
| 2.11 | Register a name containing `<b>test</b>` | Shown as literal text (output escaped) | Pass |
| 2.12 | Submit the login form with the CSRF field removed | Request rejected | Pass |
| 2.13 | Passwords stored as bcrypt hashes | Hashes start with `$2y$10$` | Pass |

## Milestone 3: Batch generation and CSV export

| # | Test | Expected | Result |
|---|------|----------|--------|
| 3.1 | Logged-out user opens `admin/generate.php` | Redirect to login | Pass |
| 3.2 | Customer opens `admin/generate.php` | 403 | Pass |
| 3.3 | Quantity 0 or 5000, points 0 | Validation errors, nothing saved | Pass |
| 3.4 | Generate "Test Soap", 10 points, quantity 5 | CSV downloads with 5 codes | Pass |
| 3.5 | Inspect `product_codes` | 5 rows, status `unused`, 64-character hashes, no plaintext | Pass |
| 3.6 | Hash a code from the CSV and look it up | Exactly 1 matching row (batch 1, `unused`); `code_hash = <plaintext>` matches 0 rows | Pass |
| 3.7 | Product name `=1+1` | CSV shows `'=1+1` (formula injection blocked) | Pass |
| 3.8 | "Recent batches" table | Unused, claimed and void counts match the batch quantities | Pass |
| 3.9 | `php tests/test_codes.php` | 6 of 6 PASS (20,000 codes unique, format, 130 bits, normalisation, hash length, distinct hashes) | Pass |

## Milestone 4: Claim flow (one-time redemption of codes)

Automated: `php tests/test_claim.php` gave **9 of 9 PASS**:

- a valid code is accepted and worth the batch's points
- the same user cannot claim it again
- a different user cannot claim it either
- the balance shows no double credit
- exactly one ledger row exists for that code
- garbage input is rejected
- a well-formed but non-existent code is rejected
- lowercase, spaces and missing hyphens still work
- after 5 bad attempts even a valid code is blocked

Browser:

| # | Test | Expected | Result |
|---|------|----------|--------|
| 4.1 | Logged-out user opens `customer/claim.php` | Redirect to login | Pass |
| 4.2 | Admin opens `customer/claim.php` | 403 | Pass |
| 4.3 | Customer claims a real code from the CSV | Dashboard shows the points | Pass |
| 4.4 | Claim the same code again | "That code is invalid or has already been used." | Pass |
| 4.5 | A second customer claims the same code | Same error message | Pass |
| 4.6 | Enter a code in lowercase | Accepted (the field is also uppercased by CSS; the server normalises it as well) | Pass |
| 4.7 | 5 invalid codes in a row | "Too many failed attempts", claims blocked for 15 minutes | Pass |

Concurrency, double-claim race test:

- Setup: one fresh 50-point code; 6 processes (3 per user, users 2 and 3) fired at the same instant.
- Result: **1 WON, 5 lost.** The ledger gained exactly one row (50 points, one `code_id`).
- Why it holds: `UPDATE ... WHERE status='unused'` succeeds for only one request, and `UNIQUE(code_id)` on the ledger is a second safety net.

## Milestone 5: History, rewards and redemption

Automated: `php tests/test_redeem.php` gave **10 of 10 PASS**:

- too few points is rejected
- a valid redemption succeeds
- balance drops by the cost and stock by 1
- exactly one redemption row and one negative ledger row
- an out-of-stock reward is rejected
- an inactive reward is rejected
- a non-existent reward is rejected
- a blocked user cannot redeem
- a second redemption works (60 to 20)
- a third redemption is rejected, so the balance never goes negative

Browser (customer 2, topped up to 110 points with a direct test-data insert):

| # | Test | Expected | Result |
|---|------|----------|--------|
| 5.2 | Logged-out user opens `customer/rewards.php` | Redirect to login | Pass |
| 5.3 | Admin opens `customer/rewards.php` | 403 | Pass |
| 5.4 | Open the rewards page | Affordable rewards enabled; Gift Hamper shows "Need 90 more points" | Pass |
| 5.5 | Redeem the 10% coupon (30 points) | Balance 110 to 80, coupon stock decreases | Pass (stock showed 98 because one coupon had already been redeemed in an earlier browser attempt) |
| 5.6 | Open history | Earn rows and redeem rows, negatives in red | Pass |
| 5.7 | Refresh right after redeeming | No second redemption | Pass |
| 5.8 | Enable the disabled Gift Hamper button in dev tools and submit | "Not enough points", nothing changes (server-side check) | Pass |
| 5.9 | Submit with a valid CSRF token, then with the token field deleted | Valid form redeems normally; without the token: "Invalid or expired form" | Pass |

Concurrency, double-spend race test (customer with exactly 60 points, 6 simultaneous redemptions of the 50-point Water Bottle):

| # | Check | Expected | Result |
|---|-------|----------|--------|
| 5.10 | Process outcomes | 1 WON, 5 lost | Pass |
| 5.11 | Final balance | 10, never negative | Pass |
| 5.12 | Redemption rows for that reward | Exactly 1 | Pass |
| 5.13 | Reward stock | Down by exactly 1 (20 to 19) | Pass |

Note: the first attempt ran with a 30-point balance, so all 6 requests were correctly rejected ("Not enough points"). That verified no overspend but not concurrency, so the test was re-run after a direct test-data insert brought the balance to exactly 60.

Integrity check (ledger vs redemptions):

| # | Check | Expected | Result |
|---|-------|----------|--------|
| 5.14 | Sum of `redemptions.points_spent` for the customer equals the sum of negative ledger rows | Equal | Pass (160 = 160; balance 30 = 190 earned - 160 spent) |

Test data note: points were added directly in SQL three times (30, 30 and 100) to set up balances for the tests above. These rows have no product code behind them and exist only in the test database.

## Milestone 6: Admin management and monitoring

Status: **code written, not yet tested.** Fill in the Result column from what you actually see, and record real failures too.

| # | Test | Expected | Result |
|---|------|----------|--------|
| 6.1 | Customer opens `admin/customers.php`, `admin/rewards.php`, `admin/monitor.php` | 403 on each | Pending |
| 6.2 | Block customer 2 from the admin page while they are logged in elsewhere | Kicked out on the next request; login shows "blocked" | Pending |
| 6.3 | Unblock them | Login works | Pending |
| 6.4 | Tamper with a block form's `user_id` to the admin's id | "No change made"; admin stays active | Pending |
| 6.5 | Add a reward with cost 0 | Error, nothing added | Pending |
| 6.6 | Add "Test Mug", 20 points, stock 5 | Appears on the customer rewards page | Pending |
| 6.7 | Untick Active and save | Disappears from the customer page | Pending |
| 6.8 | Set stock to 0 on an active reward | Customer page shows "Out of stock" | Pending |
| 6.9 | Customer redeems a reward, then check Monitor | Pending redemption appears; "Mark fulfilled" changes it; history shows the badge | Pending |
| 6.10 | Customer claims a code, then check Monitor | Claim shows product, points and IP | Pending |
| 6.11 | Generate a 3-code batch, void it, then try to claim one code | Void count 3; claim gives the generic invalid message | Pending |
| 6.12 | Compare dashboard numbers with `SELECT status,COUNT(*) FROM product_codes GROUP BY status;` | Numbers match | Pending |
| 6.13 | Remove the CSRF field from an admin form | Request rejected | Pending |

## Bugs and setup issues found during testing

| # | What happened | How it was found | Fix |
|---|---------------|------------------|-----|
| B1 | `ratelimit.php` crashed with "strict_types declaration must be the very first statement" after a `require_once` was added above `declare(strict_types=1);` | `php -l` syntax check | Moved the `require_once` below the `declare` line |
| B2 | A hash check silently tested the wrong value because two shell commands were pasted onto one line | The hash was identical to the previous run (it was the placeholder's hash) | Ran the commands on separate lines; the real code then matched exactly 1 row |
| B3 | First redemption race test showed only "Not enough points" | Balance was 30, not the 60 the test needs, so nothing could win | Topped up to exactly 60 and re-ran: 1 WON, 5 lost |

## Known limitations (also listed in ASSUMPTIONS.md)

- Rate limiting is per IP address, so users sharing one IP (for example a college network) share the same counter.
- A code that is photographed or stolen and claimed first cannot be detected by software alone; the scratch panel and admin monitoring mitigate this.
- Bootstrap loads from a CDN, so the demo machine needs internet access.
- Redemptions can be marked fulfilled but not cancelled or refunded; that would need a reversal entry in the ledger.
