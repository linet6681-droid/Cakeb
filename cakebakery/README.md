# Online Cake & Bakery Shop  (PHP + MySQL, UI matched to Milestone 2 mock-ups)

## Setup (XAMPP / WAMP): database name **cakeb**
1. Delete your old `htdocs/cakebakery` folder and copy this whole folder to `htdocs/cakebakery`.
2. Start **Apache and MySQL** in the XAMPP Control Panel.
3. Create the database, EITHER way:
   - **Installer (easiest):** open `http://localhost/cakebakery/install.php`, enter your admin name/email/password, click Install. It creates the database `cakeb`, all tables, the 7 delivery destinations and sample products, then locks itself. Delete `install.php` afterwards.
   - **phpMyAdmin:** Import > choose `sql/cakeb_with_admin.sql` > Go. This creates `cakeb` and an admin account **admin@cakeb.local / Admin@12345** (change it after the first login). Then delete `install.php`.
4. MySQL password? Copy `config/secrets.example.php` to `config/secrets.php` and set `define('DB_PASS', '...');` (XAMPP default: user `root`, empty password, nothing to do).
5. Customer site: `http://localhost/cakebakery/`  |  Admin: `http://localhost/cakebakery/admin/login.php`
6. Optional demo data: log in as admin, open `/cakebakery/seed_demo.php` once, then delete it (demo customers use password `Password123!`).

## Screens <-> Chapter 2 figures
| Mock-up | File |
|---|---|
| 2.2.1 Login | `login.php` (+ `register.php`) |
| 2.2.2 Product catalogue | `index.php` |
| 2.2.3 Cart & checkout | `cart.php` |
| 2.2.4 Order management | `admin/orders.php` |
| 2.2.5 Product management | `admin/products.php` |
| 2.2.6 Reports | `admin/reports.php` |
Extra customer pages: `register.php`, `my_orders.php`, `track.php` (order tracking + M-Pesa retry).
Extra admin pages in the sidebar: `admin/index.php`, `admin/customers.php`, `admin/payments.php`.

## What changed in this version
- **Logout**: a visible Logout button in the header of the customer pages and the admin panel (and in the admin sidebar). It is a POST form with a CSRF token; the session is destroyed on the server, the cookie is cleared, and customers go to `login.php`, admins to `admin/login.php`. Signed-in pages are sent with `Cache-Control: no-store`, so the Back button cannot show them again. Admin pages also re-check the account in the database on every request.
- **Admin login**: `admin/login.php` accepts administrator accounts only. Customers get a 403 on every admin page and admin action.
- **Product pages**: every product card links to `product.php?id=...` (image, name, price, full description from the database, Add to cart). No login or cart needed to read it. Admin > Products has a multi-line description box.
- **Admin rights**: cancel orders, delete *delivered* orders (confirmation first), delete customers (confirmation first), full order detail page (`admin/order_view.php`), customer list with unlock/delete, product add/edit/delete.
  - *Deleting a delivered order* hides it from the lists and the customer's history, but keeps the order, items and payment record (`deleted_at`), so sales totals and M-Pesa receipts stay available for accounting.
  - *Deleting a customer* with no orders removes the row. With order history, name/email/phone/password are erased and the order contact details blanked, but the orders and payment records are kept. Customers with unfinished orders cannot be deleted.
- **Delivery fees by destination**: Within CBD Ksh 0, Between Allsops and Juja 100, Thika 150, Murang'a 250, Nyeri 350, Nakuru 400, Mombasa 500 (Pickup at the shop is free). The fee is always read from the `delivery_zones` table on the server; fee/total fields sent by a browser are ignored. Each order stores its destination and fee, so old orders never change. Existing databases are upgraded automatically (new columns + new destinations) the first time any page loads.
- **M-Pesa through the real Daraja API** (no fake confirmations): see "Daraja setup" below.

## Daraja setup (M-Pesa Express / STK Push)
Secrets live in **`config/secrets.php`** (copy `config/secrets.example.php`). That file is never uploaded to GitHub (`.gitignore`) and browsers cannot read the `config/` folder (`.htaccess`). Environment variables with the same names also work on a hosting panel.

**A. Test first in the Safaricom sandbox**
1. Create an account at developer.safaricom.co.ke > *My Apps* > *Add a new app* (tick **Lipa Na M-Pesa Sandbox**). Copy the Consumer Key and Secret.
2. In *APIs > M-Pesa Express > Simulate* copy the sandbox Passkey (the shortcode is `174379`).
3. Put these in `config/secrets.php`: `MPESA_MODE` `'sandbox'`, `MPESA_CONSUMER_KEY`, `MPESA_CONSUMER_SECRET`, `MPESA_PASSKEY`, `MPESA_SHORTCODE`.
4. **Public callback URL.** Safaricom must be able to reach `https://.../pay_callback.php`. On a laptop use a tunnel, e.g. `ngrok http 80` (or Cloudflare Tunnel) and set  
   `MPESA_CALLBACK_URL` = `https://YOUR-TUNNEL.ngrok-free.app/cakebakery/pay_callback.php`  
   and `MPESA_CALLBACK_SECRET` = a long random string (the shop adds `?k=...` to the URL and rejects callbacks without it).
5. Log in as admin > **Payments > Test M-Pesa connection** (must say "Connected") and check the readiness list on that page.
6. Place an order with M-Pesa. In the sandbox no real phone rings: finish the payment with the Daraja *Simulator*. The order page also asks Safaricom for the result (STK Query), so it updates even if the callback cannot reach you.
7. Logs: `logs/mpesa.log` (secrets are masked). XAMPP "SSL certificate problem": download cacert.pem from curl.se/ca/cacert.pem, set `curl.cainfo` in php.ini, restart Apache.

**B. Go live (real phones, real money)**
1. Register a Paybill or Till and complete Safaricom **Go Live** on the portal. Safaricom gives you production keys and a passkey.
2. In `config/secrets.php`: `MPESA_MODE` `'live'`, the production keys, your `MPESA_SHORTCODE`, `MPESA_PASSKEY`, and `MPESA_TXN_TYPE` (`CustomerPayBillOnline` for Paybill, `CustomerBuyGoodsOnline` for Till).
3. Host on a server with HTTPS and use that server's address in `MPESA_CALLBACK_URL` (no tunnel needed).

**How an order is marked paid** (`services/PaymentService.php`): only from Safaricom's result. The checkout creates the order and sends the prompt; the payment stays *Pending*. A success callback is accepted only if the CheckoutRequestID (and MerchantRequestID) are ours, a receipt number is present and the amount equals the order total. Then the receipt, amount, payer number and time are saved, the customer gets an SMS and the admin gets a PAYMENT ALERT. Repeated callbacks do nothing. Cancelled (1032), timed out (1037), low balance, wrong PIN and network errors show a clear message and a **Try M-Pesa again** button that re-uses the same order (a new number can be typed). A second prompt is not sent within 60 seconds.

**Local testing without Safaricom**: `tests/mock_daraja.php` imitates the Daraja endpoints so the code can be tested offline (see its header). It is a developer tool, not Safaricom, and is not used by the shop unless you set `MPESA_BASE_URL`.

## SMS
Set `SMS_ENABLED_SETTING` and the Africa's Talking keys in `config/secrets.php`. Messages are always logged in the `notifications` table.
The font (Poppins) loads from Google Fonts, so the first load needs internet.

## Troubleshooting
- *Unknown database 'cakeb'* : open `install.php` (the app redirects there automatically).
- *Cannot reach MySQL* : start MySQL in XAMPP.
- Tests: `php tests/run_tests.php` (dev database).  Backup: `sh tools/backup.sh` or `tools\backup.bat`.

## Documents (docs/)
- `Cake_Bakery_Final_Documentation.docx` : Chapters 1-5 + references (Java wording removed; Chapters 3-5 added). In Word, right-click the Contents page > Update Field.
- `Cake_Bakery_User_Manual.docx` : customer and administrator guide.
- `Chapter4_Testing.docx` : stand-alone test chapter (also included in the final documentation).

## Tests
- `php tests/run_tests.php`: 53 logic tests (orders, stock, delivery fees, payment rules, delete rules). Dev database only.
- `php tests/e2e_test.php http://localhost/cakebakery ADMIN_EMAIL ADMIN_PASSWORD [http://127.0.0.1:8098]`: 145 web-level checks (logout, access control, product pages, delivery fees, every admin action, M-Pesa against the local mock). Creates throw-away rows prefixed ZZ.
