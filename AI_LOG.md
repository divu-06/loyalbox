# AI Log

How AI tools were used to build LoyalBox, 26 to 27 November 2026. The main assistant was Claude. A second assistant (ChatGPT) was used once to cross-check a step. Ten key interactions are listed, including four cases where the AI was wrong.

## 1. Assignment analysis and stack decision

**Prompt (summary):** I gave the assignment PDF, my skills (PHP, MySQL, HTML, CSS, JavaScript), my setup (MacBook Air M4, XAMPP) and the two-day deadline. I asked for the best stack, PHP versus React and Node, the minimum scope, a schema, a fraud approach, a schedule and what to avoid, and told it not to write code yet.

**AI response:** PHP 8, MySQL with PDO, Bootstrap 5 and XAMPP. A scope of customer claim, history and redeem, plus admin batch generation, customers, rewards and monitoring. A ledger for points, hashed codes and an atomic claim query. A two-day schedule.

**What I decided or changed:** I approved the stack because I already know it and can explain every line in a demo. I changed three things: QR codes optional and typing first, longer random codes (about 128 bits), and sample rewards plus a way to create an admin without committing a password. I also asked for row locks on both the user and the reward during redemption.

**Outcome:** These decisions are the basis of ARCHITECTURE.md and ASSUMPTIONS.md.

## 2. Database schema and the points ledger

**Prompt (summary):** Turn the approved plan into SQL for MariaDB.

**AI response:** Seven tables with foreign keys, `UNIQUE` on `code_hash`, `code_id` and `redemption_id`, `CHECK` constraints on points, and a ledger where earn rows are positive and redeem rows negative. It simplified its earlier sketch by keeping the points value only on the batch and by using `login_attempts` for failed claims too.

**What I checked:** The file imported without errors into MariaDB 10.4 and all 7 tables were listed.

**Outcome:** The constraints became the second line of defence for fraud prevention. See ARCHITECTURE.md section 4.

## 3. Database user host (a gap in the AI's setup, caught before it failed)

**What happened:** The AI's setup created the database user only for `localhost`, while `.env` connects to `127.0.0.1`. Depending on how MySQL resolves host names, that can end in "Access denied".

**How it was caught:** A review of the setup steps before running them (second AI plus my own check of `.env`).

**Fix:** I created the user for both `localhost` and `127.0.0.1`. The connection test then connected and listed all 7 tables.

**Lesson:** Check that the connection settings in `.env` match the database account that was created.

## 4. Authentication foundation

**Prompt (summary):** Build registration, login, logout, role guards, CSRF protection, rate limiting and an admin seed script.

**AI response:** Helper files for sessions, CSRF and flash messages, plus register, login and logout pages. Role hard-coded to `customer` at registration. Admin only from a command-line script. The user is re-read from the database on every request, so a blocked account is rejected immediately.

**What I checked:** I ran the manual authentication tests (registration, duplicate email, short password, wrong role, logged-out access, five wrong passwords, blocking a logged-in user). All passed. See docs/TEST_PLAN.md, Milestone 2.

## 5. Mistake: a `require_once` placed above `declare(strict_types=1)`

**What the AI got wrong:** The AI told me to add a `require_once` "at the top" of `includes/ratelimit.php` and did not say where. I put it above `declare(strict_types=1);`, and PHP refused to run the file: "strict_types declaration must be the very first statement".

**How I noticed:** Running `php -l` on every PHP file before opening the browser.

**Fix:** I moved the `require_once` below the `declare` line and re-ran `php -l`.

**Lesson:** Run `php -l` after every AI edit, and ask the AI for the exact position of any inserted line.

## 6. Mistake: a second AI claimed missing functions that existed

**What happened:** After I pasted the Milestone 3 plan into a second AI, it reported six "integration errors": `csrf_verify`, `flash`, `get_flash`, `url` and `client_ip` missing and `require_role` returning nothing. It supplied a patch.

**How I noticed it was wrong:** I compared the list with my actual files. All the functions were already defined, and `require_role()` already returned the user. The patch referred to functions that do not exist in my code (`verify_csrf`, `start_secure_session`), so the second AI was reviewing a different codebase.

**Fix:** I did not apply the patch. It would have redeclared `csrf_verify()` (a fatal error) and changed `redirect()` so that every relative redirect broke. The one valid point was an extra `require_once` for `db.php`, which led to the problem in entry 5.

**Lesson:** Verify an AI's claims against the real files before applying its fixes.

## 7. One-time code claim and the race test

**Prompt (summary):** Implement claiming a code so that it can be used only once, even with simultaneous requests.

**AI response:** Instead of "read the status, then update it" (which two requests could both pass), one conditional statement: `UPDATE product_codes SET status='claimed' ... WHERE code_hash=? AND status='unused'`, then a check that exactly one row changed, followed by a ledger insert protected by `UNIQUE(code_id)`, all in a transaction. It also wrote a script that launches several processes at the same instant.

**What I checked:** Nine automated claim tests passed. In the race test, six simultaneous claims for the same 50-point code gave one winner and five losers, with exactly one ledger row. See docs/TEST_PLAN.md, Milestone 4.

## 8. Redemption with row locks, and a race test that tested nothing at first

**Prompt (summary):** Implement redemption so that a customer cannot spend the same points twice, and stock cannot go negative.

**AI response:** A transaction that locks the user row and then the reward row, reads the balance only after the lock, decreases stock only if it is above zero, and writes a negative ledger row. Ten automated tests passed.

**What went wrong in testing:** My first race run printed "Not enough points" six times. The AI's instructions assumed the customer had exactly 60 points, but earlier browser tests had lowered the balance to 30, so no request could win and nothing about concurrency had been tested. I noticed that the output had no winner and no change in the database.

**Fix:** I added points directly to reach 60 and ran it again: one winner, five losers, balance 10, one redemption, stock down by one. I also ran a reconciliation query: the sum of redemption costs (160) equalled the sum of ledger debits (160).

**Lesson:** Check the starting data before trusting a test, and record test data inserted by hand.

## 9. Mistake: a helper function named `stat()` crashed the admin dashboard

**What the AI got wrong:** In the admin dashboard code the AI defined a helper called `stat()`. PHP already has a built-in function with that name.

**How I noticed:** The admin dashboard returned HTTP 500. The PHP error log said "Cannot redeclare stat()" at line 19 of `admin/dashboard.php`.

**Fix:** I renamed it to `stat_card()` with one `sed` command, checked it with `php -l`, and reloaded the page.

**Lesson:** Check unfamiliar names against PHP built-ins, or use project-specific names.

## 10. Mistake: a verification query that added earned and redeemed points together

**What the AI got wrong:** To check the dashboard figures, the AI gave me a query that summed the points of all earn and redeem rows in one number. The output showed "earned 20" and "redeemed 190", which cannot both be true.

**How it was caught:** Reading the output next to the data: 190 points redeemed requires at least 190 earned. The 20 was the net balance, because redeem rows are negative.

**Fix:** The dashboard already used separate sums (210 earned, 190 redeemed, 210 - 190 = 20). I recorded this as bug B5 in docs/TEST_PLAN.md and used separate sums for the check.

**Lesson:** Do not trust a number just because a query ran; ask whether it can be true.

## What I learned about working with AI

- Run a syntax check (`php -l`) after every AI edit, and open the page in the browser: a file that parses can still crash at run time.
- Check one AI's claims against the real files before applying another AI's patch.
- Test the dangerous cases on purpose: duplicate claim, simultaneous requests, blocked user, missing CSRF token, direct URL access without the right role.
- Check test data before trusting a test result.
- The AI cannot see my machine, so errors in paths, names or starting data are mine to catch.
