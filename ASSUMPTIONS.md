# Assumptions

The brief leaves many gaps. Each one is listed here with the choice made and the reason.

## Products and codes

| Gap | Assumption | Why |
|---|---|---|
| How does a customer prove a purchase? | Each box has a unique code printed inside the box (for example under a scratch panel). Knowing a valid code is the proof of purchase. | The code cannot be read on a shop shelf, so only someone who opened a bought box can see it. |
| Who creates the codes? | The factory owner generates them in batches in the admin panel, downloads a CSV, and gives it to the printer. | The factory controls its own printing; the app needs no printer integration. |
| What does a code look like? | 26 random symbols (130 bits) in groups of 5-5-5-5-6, from an alphabet without I, L, O and U. | 130 bits cannot be guessed or enumerated. The reduced alphabet avoids look-alike characters when typing from a box. |
| Are codes stored in the database? | Only the SHA-256 hash is stored. The plaintext exists once, in the CSV. | A database leak then reveals no usable codes. Codes are random and long, so a fast hash is enough. |
| What if the CSV is lost? | The plaintext cannot be recovered. The admin voids the unused codes of that batch and generates a new one. | This follows from hashing and is the accepted trade-off. |
| Do codes expire? | No. | Not needed for the demo; a `expires_at` column could be added later. |
| How many points is a code worth? | The points value is set per batch (per product). All codes in a batch give the same points. | One product has one value, which keeps the data model simple. |
| What if a customer mistypes a code? | The input is normalised (case, spaces, hyphens, O/0 and I/L/1 mix-ups) before hashing. | Typing from print is error-prone. |
| QR codes? | Not implemented. Customers type the code. A QR code could encode the same string later. | Out of scope for the two-day deadline, and the brief allows either way to claim. |
| Batch limits | At most 1,000 codes per batch and 10,000 points per code. | Prevents mistakes and oversized downloads. |

## Users and authentication

| Gap | Assumption | Why |
|---|---|---|
| Who can register? | Anyone can register, always as a customer. The role is hard-coded in the registration query. | Nobody can make themselves an admin through the website. |
| How is an admin created? | Only from the command line with `database/create_admin.php`. The password is typed hidden and is not in the code. | No public admin sign-up and no default password in the repository. |
| How many admins? | One factory owner role. No separate permission levels. | Matches the brief (one owner). |
| Email verification and password reset? | Not implemented. The email is the login name. | They need an email service; excluded for the deadline. |
| Password rules | Customers: 8 to 72 characters. Admin: at least 10. Stored with bcrypt. | bcrypt ignores bytes after 72, so a limit avoids silent truncation. |
| Blocked customers | They cannot log in, claim, or redeem. The block applies on their next request, even if they are logged in. | The account is re-read from the database on every request. |
| Can customers or admins be deleted? | No deletion, only blocking. | History must stay intact for the monitor and for audits. |

## Rewards and redemption

| Gap | Assumption | Why |
|---|---|---|
| What is a reward? | A physical item or coupon with a point cost and a stock count, set by the admin. | Simple and matches a factory giveaway. |
| How are rewards delivered? | Outside the system. A redemption starts as pending and the admin marks it fulfilled. | Shipping and payment are out of scope. |
| Cancellations and refunds | Not supported. | A safe reversal needs a compensating ledger entry and stock return; excluded for the deadline. |
| Deleting a reward | Not allowed; the admin deactivates it. | Past redemptions refer to it. |
| Price changes | A new cost applies to future redemptions only. Each redemption stores the points actually spent. | Keeps history correct. |
| Do points expire? | No. | Not required by the brief. |
| How is the balance calculated? | As the sum of the points ledger (earn rows positive, redeem rows negative). There is no stored balance column. | A balance column can drift out of sync; a ledger is auditable. |

## Fraud and abuse prevention

| Gap | Assumption | Why |
|---|---|---|
| Guessing codes | Rate limit of 5 failed claims per IP in 15 minutes, on top of a 130-bit search space. | Brute force is both blocked and mathematically hopeless. |
| Guessing passwords | Rate limit of 5 failed logins per IP in 15 minutes. Wrong email and wrong password give the same message. | Stops brute force and account enumeration. |
| Which IP is used? | `REMOTE_ADDR`, not `X-Forwarded-For`. | The forwarded header can be spoofed unless a trusted proxy sets it. |
| What does a failed claim reveal? | One message for invalid, already used and voided codes. | An attacker learns nothing about which codes exist. |
| Double claims and double spending | Prevented in the database (atomic update, unique constraints, row locks). | The database enforces correctness even under simultaneous requests. |
| A code that is photographed or stolen | Cannot be fully stopped by software. Mitigations: hidden code, rate limits, and the monitor page, which flags users with 10 or more claims in 24 hours and IPs with 5 or more failed claims in 24 hours. | Honest limitation; the thresholds are adjustable constants. |
| Admin audit log | Not implemented. Claims and redemptions are recorded, but admin edits (blocking, reward changes) are not. | Out of scope for the deadline. |

## Technical and scope decisions

| Gap | Assumption | Why |
|---|---|---|
| Technology | PHP 8, MySQL/MariaDB, Bootstrap 5, XAMPP. | Known to the developer, free, and the database provides transactions and constraints. See ARCHITECTURE.md. |
| Database engine | MariaDB 10.4 (shipped with XAMPP), InnoDB tables. | `CHECK` constraints, foreign keys and row locks need InnoDB. |
| Deployment | Local only. No deployed link. | Free PHP hosting is unreliable; the demo runs on localhost. |
| HTTPS | Not used locally. In production the session cookie becomes `Secure` automatically when HTTPS is on. | XAMPP's default is HTTP. |
| Front end libraries | Bootstrap loads from a CDN. | Avoids a build step; the demo machine needs internet. |
| Time zone | Timestamps use the database server's time. | Single-site demo. |
| Testing | Automated PHP scripts for the security-critical logic and a manual test plan with recorded results for the pages. | Meets the brief while staying inside the time limit. |
| Pagination | Admin and history lists show the latest 20 to 200 rows. | Enough for the demo. |
