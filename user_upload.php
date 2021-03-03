<?php
/* Command line tool to accept a CSV file as input and processes it.
 * The parsed file data is inserted into a PostgreSQL database.
 * 
 * MIT License: see LICENSE.md file.
 * Author: Kateryna Degtyariova, katya.d@gmail.com
 * March 2021
 */

function display_help($details)
{
  echo "CSV to PostgreSQL user data uploader\n";
  echo "Usage information:\n".
       "  php user_upload.php [--file <csv file name>]\n".
       "                      [--create_table]\n".
       "                      [--dry_run]\n".
       "                      [-u <username>]\n".
       "                      [-p <password>]\n".
       "                      [-h <host>]\n".
       "                      [--help]\n\n";
       
  if (!$details)
  {
    echo "Use --help to get the directives details\n";
    return;
  }
  
  echo " --file <csv file name> – the name of the CSV to be parsed\n".
       " --create_table - this will cause the PostgreSQL users table to be \n".
       "                  built (and no further action will be taken)\n".
       " --dry_run      - this will be used with the --file directive in case\n".
       "                  we want to run the script but not insert into\n".
       "                  the DB. All other functions will be executed,\n".
       "                  but the database won\'t be altered\n".
       " -u <username>  - user name to connect to PostgreSQL\n".
       " -p <password>  - password to connect to PostgreSQL\n".
       " -h <host>      - host address of PostgreSQL server\n\n".
       " --help – output the above list of directives with details\n\n";
}


// Describe the short options format for getopt()
$short_opts = "u:".  // PostgreSQL username (value required)
              "p:".  // PostgreSQL password (value required)
              "h:";  // PostgreSQL host     (value required)
              
// Describe the long options format for getopt()
$long_opts = array(
               "file:",          // CSV file name (value required)
               "create_table",   // create table  (no value)
               "dry_run",        // dry run       (no value)
               "help"            // display help  (no value)
             );

$opts = getopt($short_opts, $long_opts);
if (count($opts) == 0)
{
  // Display short help and exit if run without options
  display_help(false);
  return 0;
}
else if (isset($opts["help"]))
{
  // Display extended help and exit if run with --help
  display_help(true);
  return 0;
}

var_dump($opts);
?>
