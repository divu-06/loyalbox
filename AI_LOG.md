# LoyalBox – AI Assistance Log

This log records representative AI-assisted decisions and debugging interactions during the project. Prompt descriptions are summarized for readability, not presented as verbatim transcripts. The project owner checked suggestions against the actual files, tests, and local application before accepting them.

## 1. Scope and MVP planning

**Prompt / request:** Help design a customer loyalty system for boxed factory products, covering purchase proof, code-reuse prevention, and factory-owner administration, within a short academic project.

**AI contribution:** Proposed a server-rendered PHP/MySQL MVP, concealed one-time product codes, customer and administrator roles, a points ledger, reward redemption, and an admin monitoring area.

**Decision / verification:** The project kept the scope small: manual code entry, local XAMPP, no payments or shipping, and no separate frontend framework.

## 2. Product-code security design

**Prompt / request:** Recommend a code format and a way to prevent guessing, plaintext database exposure, and repeated claims.

**AI contribution:** Recommended 26 symbols from a 32-character alphabet (130 bits of random input), grouped for manual entry, generated using PHP random_int(), with only SHA-256 hashes stored.

**Decision / verification:** The implementation uses generate_code(), normalize_code(), and hash_code() in includes/codes.php. The code tests check uniqueness over 20,000 generated values, format, normalization, hash length, and distinct hashes. The browser claim and race tests are recorded in docs/TEST_PLAN.md.

## 3. Atomic one-time claim

**Prompt / request:** Explain how to stop two simultaneous requests from claiming the same product code.

**AI contribution:** Recommended an atomic conditional update from unused to claimed, checking that exactly one row changed, and placing the corresponding positive ledger entry in the same database transaction. A unique ledger reference to code_id provides a second guard.

**Decision / verification:** Implemented in includes/claim.php. The documented simultaneous test returned one winner and five losing attempts, with one ledger entry.

## 4. Safe point redemption

**Prompt / request:** Design reward redemption so users cannot overspend or redeem the final unit of stock more than once under concurrency.

**AI contribution:** Recommended a transaction, consistent row-lock ordering (user then reward), checking the balance from the ledger after locking the user, decrementing stock conditionally, and inserting redemption plus negative ledger entry atomically.

**Decision / verification:** Implemented in includes/redeem.php. The test plan records 10 automated checks and a simultaneous redemption test with one winner and five rejected requests.

## 5. AI mistake: incorrect repository review / missing helpers

**Prompt / request:** Review the authentication and helper code and identify missing functions or required fixes.

**AI mistake:** An AI response claimed that csrf_verify(), flash(), get_flash(), url(), client_ip(), and the role guard were missing, even though those functions were already present in the actual repository version being reviewed.

**How it was detected:** The project owner checked the actual files and function definitions rather than applying the suggested replacement blindly.

**Correction:** The existing implementations were retained. The separate dependency observation that the rate-limit file needed to load the database layer was relevant; the require_once was kept after declare(strict_types=1), as required by PHP syntax.

## 6. AI mistake: PHP built-in name collision

**Prompt / request:** Help add summary cards to the admin dashboard.

**AI mistake:** An AI-generated helper was named stat(), which conflicts with PHP's built-in stat() function. This caused an HTTP 500 error with a “Cannot redeclare stat()” message.

**How it was detected:** The browser showed HTTP 500 and the PHP error log identified the redeclaration.

**Correction:** The helper was renamed to stat_card(). The file was syntax-checked and the dashboard was tested again. This incident is also recorded as B4 in docs/TEST_PLAN.md.

## 7. AI mistake: incorrect points verification query

**Prompt / request:** Help verify the earned and redeemed points shown by the admin dashboard against the database.

**AI mistake:** An earlier query combined positive and negative ledger entries and labeled the net result (20) as total points earned, while showing 190 redeemed separately.

**How it was detected:** The values were inconsistent: a system that had redeemed 190 points must have earned at least 190 points. The dashboard's separate sums and the ledger transaction types were inspected.

**Correction:** The verification query was changed to sum type = earn separately from negative type = redeem entries. The confirmed values were 210 earned, 190 redeemed, and 20 net balance. This is recorded as B5 in docs/TEST_PLAN.md.

## 8. Test-driven debugging and documentation

**Prompt / request:** Help run and interpret PHP test scripts, manual browser checks, SQL integrity queries, and simultaneous race tests; then keep the project test plan current.

**AI contribution:** Helped structure test cases for code generation, one-time claims, redemption balances and stock, authorization, CSRF, void codes, and admin monitoring. It also helped document observed bugs and corrections.

**Decision / verification:** Test outcomes were marked Pass only after the project owner ran them locally. The current detailed results are in docs/TEST_PLAN.md; AI suggestions were not treated as proof of execution.
