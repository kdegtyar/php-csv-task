<?php
/* Command line tool to accept a CSV file as input and processes it.
 * The parsed file data is inserted into a PostgreSQL database.
 * 
 * MIT License: see LICENSE file.
 * Author: Kateryna Degtyariova, katya.d@gmail.com
 * March 2021
 */


/* 
 * Class that provides functionality for uploading CSV into 
 * PostgreSQL database
 */ 
class CsvUpload
{
  public $opts; // Options after parsing are kept here
  
  public $conn; // PDO Connection handle, which will be automatically destroyed
                // on error or on normal exit. So, it is quite safe.
  
  function __construct()
  {
    // Describe the short options format for getopt()
    $short_opts = "u:". // PostgreSQL username  (value required)
                  "p:". // PostgreSQL password  (value required)
                  "h:"; // PostgreSQL host      (value required)
                  
    // Describe the long options format for getopt()
    $long_opts = array(
                   "file:",          // CSV file name (value required)
                   "create_table",   // create table  (no value)
                   "dry_run",        // dry run       (no value)
                   "help"            // display help  (no value)
                 );

    $this->opts = getopt($short_opts, $long_opts);
    
  }
  
  
  /*
   * Print help information if required.
   * 
   * Parameters:
   * details - bool. If true it displays the extended information.
   *           Otherwise the short command line options.
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
  
  
  /*
   * Getting an option value and check if the option exists.
   * 
   * Parameters:
   * name - string. Option name to check
   * 
   * Returns:
   *  value of the option with added slashes if necessary. If option
   *        does not have a value TRUE will be returned
   *  FALSE if the option does not exist
   * 
   */
  function getoption($name, $slashes = FALSE)
  {
    if (isset($this->opts[$name]))
    {
      if (is_bool($this->opts[$name]))
        return TRUE;
        
      return $slashes ? addslashes($this->opts[$name]) : $this->opts[$name];
    }
      
    return FALSE;
  }

  /*
   * Connecting to PostgreSQL database.
   * The function uses the opts member array and assume that the user gave all
   * details correct. If some credentials are omitted we assume it was
   * the user's intention and the default values should be used.
   * 
   * Returns:
   *  TRUE when connection is successful
   *  FALSE if connection could not be made
   */
  function connect()
  {
    $connect_str = "pgsql:";
    
    if ($host = $this->getoption("h"))
      $connect_str .= "host='".$host."' ";

    $user = $this->getoption("u");
    $pwd = $this->getoption("p");

    try
    {
      // PDO is working with many databases, better than pg_connect()
      $this->conn = new PDO($connect_str, $user, $pwd);
    }
    catch(PDOException $ex)
    {
      $outstr = "Connection error: ".$ex->getMessage()."\n";
      fwrite(STDOUT, $outstr);
      return FALSE;
    }

    echo "Connected\n";
    //var_dump($this->opts);
    return TRUE;
  }
  
  
  /* 
   * Print the database error information into STDOUT
   * 
   * Parameters:
   *  stderr - causes to print error into STDERR
   */
  function print_db_error($stderr = FALSE)
  {
    $info = $this->conn->errorInfo();
    $outstr = "Error creating 'users' table\n".
              "[SQLSTATE: $info[0]][Error Code:$info[1]]$info[2]\n";
    if ($stderr)
      fwrite(STDERR, $outstr);
    else
      fwrite(STDOUT, $outstr);
  }
  
  
  /* 
   * Creates a new users table into the database
   * 
   * Returns:
   *  0 - Success
   *  1 - Failure
   */
  function create_table()
  {
    if (!$this->connect())
      return 1;
    
    if ($this->conn->query("CREATE TABLE users(id SERIAL PRIMARY KEY,".
                         "name VARCHAR(64), surname VARCHAR(64),".
                         "email VARCHAR(64) UNIQUE)") == FALSE)
    {
      $this->print_db_error();
      fwrite(STDOUT, "No changes are made in the database\n");
      return 1;
    }
    
    echo "Table 'users' is successfully created\n";
    
    return 0;
  }
  
  
  /* 
   * Processing options and performing the requested operations depending
   * on what was requested by the user.
   * 
   * Returns:
   *  0 - Success
   *  1 - Failure
   */
  function process()
  {
    if (count($this->opts) == 0)
    {
      // Display short help and exit if run without options
      $this->display_help(FALSE);
      return 0;
    }
    else if ($this->getoption("help"))
    {
      // Display extended help and exit if run with --help
      $this->display_help(TRUE);
      return 0;
    }
    else if ($this->getoption("create_table"))
    {
      // Create users table and do nothing else
      return $this->create_table();
    }
    
    echo "No action is taken. ".
         "Please run with --help to check the command line options.\n";
  }
  
}

$csv_uploader = new CsvUpload();
return $csv_uploader->process();


//var_dump($opts);
?>
