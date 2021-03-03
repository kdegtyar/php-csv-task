# php-csv-task
PHP script for importing CSV files into PostgreSQL database.

The data from CSV is imported into the table named _users_.
Each user is uniquely identified by email address.

## Requirements
* PHP version 7.2 or newer
* PostgreSQL Database Server version 9.5 or newer

## Command Line Arguments

* **--file [csv file name]** – the name of the CSV to be parsed
* **--create_table** – this will cause the PostgreSQL users table to be built (and no further action will be taken)
* **--dry_run** – this will be used with the **--file** directive in case we want to run the script but not insert into the DB. All other functions will be executed, but the database won't be altered
* **-u** – PostgreSQL username
* **-p** – PostgreSQL password
* **-h** – PostgreSQL host
* **--help** – output the above list of directives with details

## Structure of _CSV_ file columns

* **name** - 1st column in CSV file containing the **name** of the user
* **surname** - 2nd column in CSV file containing the user's **surname**
* **email** - 3rd column in CSV file containing the user's **email**

## Structure of _users_ table in PostgreSQL

* **id SERIAL PRIMARY KEY** - automatically generated primary key without relation to CSV
* **name VARCHAR(64)** - corresponds to **name** (1st) column in CSV file
* **surname VARCHAR(64)** - corresponds to **surname** (2nd) column in CSV file
* **email VARCHAR(64) UNIQUE** - corresponds to **email** (3rd) column in CSV file
