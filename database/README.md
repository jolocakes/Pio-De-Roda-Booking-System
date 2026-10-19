# Local XAMPP database setup

1. Start Apache and MySQL in the XAMPP Control Panel.
2. In phpMyAdmin, **export a backup first** if `pdrsystem` or any of its tables already contain data. This schema uses `CREATE ... IF NOT EXISTS` and does not drop or alter tables, but pre-existing tables with conflicting definitions need a reviewed migration rather than blind import.
3. Import `schema.sql` in phpMyAdmin. It creates the `pdrsystem` database and the required tables without deleting existing records.
4. The local XAMPP default is `127.0.0.1`, database `pdrsystem`, user `root`, empty password. For other environments, set `PDR_DB_HOST`, `PDR_DB_NAME`, `PDR_DB_USER`, and `PDR_DB_PASS` in the PHP/Apache environment. Do not commit credentials.
5. Open `http://localhost/pdrsystem/admin/setup.php` **from the local machine** to create the first administrator, then sign in at `admin/login.php`. Setup is disabled once an administrator exists and is restricted to localhost.
6. Serve the site through Apache. Do not open the customer HTML files with `file://`; PHP API requests require Apache.

Status values are `Pending`, `Confirmed`, `Rejected`, and `Cancelled`. Pending and confirmed bookings reserve the selected date in the current form workflow. Status history is retained in `reservation_status_history`.
