# php-csv-task
PHP script for importing CSV files into PostgreSQL database.

The data from CSV is imported into the table named _users_.
Each user is uniquely identified by email address.

## Requirements
* PHP version 7.2 or newer
* PHP Data Objects (PDO) library must be present in PHP
* PostgreSQL module for PHP (In Ubuntu can be installed using: _**sudo apt-get install php7.2-pgsql**_)
* PostgreSQL Database Server version 9.5 or newer

## Command Line Arguments

* **--file [csv file name]** – the name of the CSV to be parsed
* **--create_table** – this will cause the PostgreSQL users table to be built (and no further action will be taken)
* **--dry_run** – this will be used with the **--file** directive in case we want to run the script but not insert into the DB. All other functions will be executed, but no connection database is attempted. The status information and validated data will be printed.
* **--force_connect** - force connecting to PosgreSQL with --dry_run to check the connection. No changes are made in the database.
* **-u** – PostgreSQL username
* **-p** – PostgreSQL password
* **-h** – PostgreSQL host
* **--help** – output the above list of directives with details

## Structure of _CSV_ file

* **name** - 1st column in CSV file containing the **name** of the user
* **surname** - 2nd column in CSV file containing the user's **surname**
* **email** - 3rd column in CSV file containing the user's **email**

First row inside the file is considered a header and therefore is skipped

## Structure of _users_ table in PostgreSQL

* **id SERIAL PRIMARY KEY** - automatically generated primary key without relation to CSV
* **name VARCHAR(255)** - corresponds to **name** (1st) column in CSV file
* **surname VARCHAR(255)** - corresponds to **surname** (2nd) column in CSV file
* **email VARCHAR(255) UNIQUE** - corresponds to **email** (3rd) column in CSV file

## Email validation rules

The email validation is done using
[filter_var($email, FILTER_VALIDATE_EMAIL) PHP function](https://www.php.net/manual/en/function.filter-var),
which applies the local-part email rules.

If unquoted, it may use any of these ASCII characters:
 * uppercase and lowercase Latin letters **A** to **Z** and **a** to **z**
 * digits **0** to **9**
 * printable characters  **\!#$%&'\*\+\-/=?^\_\`\{|\}**
 * dot **.**, provided that it is not the first or last character and provided also that it does not appear consecutively (e.g., **John..Doe@example.com** is not allowed).

More on email address local part can be found here: [https://en.wikipedia.org/wiki/Email_address#Local-part](https://en.wikipedia.org/wiki/Email_address#Local-part)

Therefore, email addresses like these are correct:

 * **mo'connor@cat.net.nz**
 * **sam!@walters.org** 
 * **user%example.com@example.org**
 
 The email addresses like these are incorrect:
 
 * **edward@jikes@com.au**
 * **just"not"right@example.com**
