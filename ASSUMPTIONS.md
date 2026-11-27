# LoyalBox – Assumptions and Scope

This document records the assumptions used to keep the Customer Loyalty Rewards System deliverable within a short academic project. They describe the demonstration implementation, not guarantees about every factory's real operating process.

## Product and purchase assumptions

1. **One box has one unique code.** Each eligible factory-produced box receives one unique code. A customer proves possession of the product by entering the code concealed inside the box, under a scratch-off label.
2. **The code is entered manually.** The MVP uses a text field. QR scanning, camera access, barcode scanning, and automated code reading are outside the scope.
3. **The first successful claim wins.** A code can move from unused to claimed once. If a code is copied, photographed, or stolen and someone else claims it first, the software cannot independently determine who bought the box.
4. **A code batch has one product name and one points value.** Every code in that batch earns the same number of points. Changing a product's future batch value requires generating another batch.
5. **Codes are distributed physically by the factory.** The application generates the codes and exports them as a CSV; printing, secure transfer to packaging, and application of scratch labels are operational factory steps, not implemented modules.

## Account and administrator assumptions

6. **One email identifies one account.** The database enforces a unique email address. Public registration creates customer accounts only; users cannot select or submit the admin role during registration.
7. **Administrators are provisioned manually.** An authorized operator runs the CLI-only database/create_admin.php script to create an administrator. A self-service admin signup or multi-admin permission system is not part of the MVP.
8. **A blocked customer cannot continue using the application.** The authenticated user is re-read from the database on each request; a blocked account is logged out on its next request.
9. **Login and claim throttling is IP-based.** Five failed attempts within the 15-minute window cause throttling for that IP and attempt type. This is simple for the MVP, but users sharing a public or college network can affect one another.

## Points and reward assumptions

10. **The transaction ledger is the source of truth.** A customer's balance is calculated as the sum of their point transactions. Earn entries are positive and redemption entries are negative; there is no separate stored balance to keep synchronized.
11. **A successful code claim earns the points configured on its batch.** The claim and point-ledger insert are committed in one database transaction. A unique ledger reference to the product code is an additional duplicate-credit safeguard.
12. **A redemption is fulfilled manually.** Redeeming a reward checks active status, available stock, and the customer's balance, then creates a pending redemption and subtracts points in a transaction. An administrator can mark it fulfilled in the monitoring page.
13. **No cancellation/refund workflow is provided.** The database enum includes a cancelled status, but the current admin interface supports marking pending redemptions fulfilled; it does not implement cancellation, refunds, or compensating ledger entries.
14. **Reward stock is managed by an administrator.** Stock is a simple available quantity. The system decrements it when a redemption is accepted; delivery, shipping, coupon validation at checkout, and physical inventory synchronization are external processes.

## Technical and demonstration assumptions

15. **The target demonstration is local XAMPP.** The documented setup uses Apache, PHP, and MariaDB/MySQL on a local machine. A public production deployment is not assumed.
16. **Bootstrap is loaded from a CDN.** The browser needs internet access to load the stylesheet. The rest of the PHP application is served locally.
17. **The local database is a demonstration database.** Test-plan notes include direct SQL point adjustments and seeded rewards used to exercise test scenarios. Those are test data, not business transactions or a production migration.
18. **The application uses the configured local database account.** The sample .env.example is a template; each installation must supply its own database password and configuration in an ignored .env file.
19. **The browser submits standard PHP forms.** The application uses server-rendered pages, POST/redirect/GET for state changes, and small browser interactions; it is not a single-page application or a JSON API.

## Explicitly out of scope

- Customer email verification, password reset emails, SMTP notifications, and multi-factor authentication.
- QR/camera scanning and integration with packaging or factory production systems.
- Payment processing, shipping, delivery tracking, and coupon redemption at a point-of-sale system.
- Automated detection of copied physical codes before their first claim.
- Refund/cancellation processing and point reversals for fulfilled or cancelled rewards.
- Multi-role administration, staff audit approval workflows, and real-time monitoring.
- Cloud deployment, Docker orchestration, mobile apps, and external analytics integrations.

## Known limitations and operational risks

- IP-based rate limiting can affect multiple legitimate customers behind the same NAT or shared network.
- A plaintext code exists in the one-time CSV at batch creation. That file must be transferred and stored securely; if lost, the application cannot recover the plaintext because only hashes are stored.
- Concealment helps reduce copying but cannot prevent someone from photographing a code after revealing it.
- Admin monitoring flags threshold-based unusual activity; it does not prove fraud.
- The current local setup uses the root .htaccess protection and must be reviewed for the actual web-server configuration before deployment.
