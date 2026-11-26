# Manual Test Plan: Authentication (Milestone 2)

Tested on: 26 Nov 2026, XAMPP (MariaDB 10.4, PHP 8.2), Chrome/Safari

| # | Test | Expected | Actual | Result |
|---|------|----------|--------|--------|
| 1 | Register new customer | Dashboard, balance 0 |Customer dashboard, balance 0 | Pass|
| 2 | Duplicate email | Error shown (UNIQUE constraint) |That email is already registered |Pass |
| 3 | Short password | Validation error |Validation error |Pass |
| 4 | Customer login | Customer dashboard |Customer dashboard |Pass |
| 5 | Customer opens admin URL | 403 |403 Forbidden |Pass |
| 6 | Admin login | Admin dashboard |Admin dashboard |Pass |
| 7 | Logged-out access to dashboard | Redirect to login |Redirect to login |Pass |
| 8 | 5 wrong passwords | Locked out for 15 min |Too many failed attempts. Please try again in 15 minutes. |Pass |
| 9 | Admin blocks user mid-session | Kicked out immediately |Kicked out immediately |Pass |
| 10 | Unblock + clear attempts | Login works |Can log in again |Pass |
| 11 | Name `<b>test</b>` | Shown as literal text (XSS escaped) |<b>test</b> |Pass |
| 12 | Login form without CSRF token | Request rejected |Rejected |Pass |