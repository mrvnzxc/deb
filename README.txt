=====================================================
 BUS TICKET BOOKING SYSTEM - SETUP INSTRUCTIONS
=====================================================

REQUIREMENTS
------------
- XAMPP (Apache + MySQL)


STEP 1: SAVE THE FOLDER IN HTDOCS
---------------------------------
1. Copy this whole project folder.
2. Paste it inside the XAMPP htdocs folder:
       C:\xampp\htdocs\
3. Rename the folder to:  deb
   The final path should look like this:
       C:\xampp\htdocs\deb\
   (inside it you should see index.php, config.php,
    bus_ticket_booking.sql, and the admin, customer,
    assets and includes folders)


STEP 2: START XAMPP
-------------------
1. Open the XAMPP Control Panel.
2. Click "Start" on Apache.
3. Click "Start" on MySQL.
   Both should turn green.


STEP 3: PASTE THE SQL IN PHPMYADMIN
-----------------------------------
1. Open your browser and go to:
       http://localhost/phpmyadmin
2. Click the "SQL" tab at the top.
3. Open the file  bus_ticket_booking.sql  (inside the deb folder)
   using Notepad.
4. Press Ctrl + A to select everything, then Ctrl + C to copy.
5. Go back to phpMyAdmin and paste it (Ctrl + V) in the SQL box.
6. Click the "Go" button (bottom right).
7. You should now see the database "bus_ticket_booking"
   on the left side.

NOTE: You don't need to create the database first. The SQL file
creates it for you. Running it again will DELETE all old data
and reset everything back to the sample data.


STEP 4: OPEN THE SYSTEM
-----------------------
Go to this link in your browser:
       http://localhost/deb


LOGIN CREDENTIALS
-----------------
On the login page, pick "Admin" or "Customer" first, then log in.

  ADMIN
    Email:    admin@busticket.com
    Password: password

  CUSTOMER
    Email:    juan@gmail.com
    Password: password

You can also create new customer accounts using "Register here"
on the login page.


TROUBLESHOOTING
---------------
- "Database connection failed"
    -> Make sure MySQL is started in XAMPP and that you ran the
       SQL file in phpMyAdmin (Step 3).
    -> If your MySQL root account has a password, open config.php
       and put it in DB_PASS.

- "Not Found" / page doesn't open
    -> Make sure Apache is started and the folder is exactly
       C:\xampp\htdocs\deb
