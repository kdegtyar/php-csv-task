<?php
/* Command line tool to accept a CSV file as input and processes it.
 * The parsed file data is inserted into a PostgreSQL database.
 * 
 * MIT License: see LICENSE file.
 * Author: Kateryna Degtyariova, katya.d@gmail.com
 * March 2021
 */

$term_colors = array(
  'RED'  => "\x1B[31m",
  'GRN'  => "\x1B[32m",
  'YEL'  => "\x1B[33m",
  'BLU'  => "\x1B[34m",
  'MAG'  => "\x1B[35m",
  'CYN'  => "\x1B[36m",
  'WHT'  => "\x1B[37m",
  'RESET'=> "\x1B[0m"
);


/*
 * Helper function that detects if terminal is TTY and sets up the colors
 * or disables them.
 */
function setup_terminal()
{
  global $term_colors;
  
  if (stream_isatty(STDOUT) && stream_isatty(STDERR))
    return; // colors are enabled, do not suppress them
  
  foreach($term_colors as $k => $v)
    $term_colors[$k] = ""; // suppress colors by resetting their values
}

/*
 * Helper function to print error messages
 * 
 * Parameters:
 *   msg - string, message to be printed
 *   stderr - bool, print into STDERR instead of STDOUT
 * 
 */
function errmsg($msg, $stderr = FALSE)
{
  global $term_colors;
  
  $out = $term_colors['RED']."ERROR: ".$term_colors['RESET'].$msg."\n";
  if ($stderr)
    fwrite(STDERR, $out);
  else
    fwrite(STDOUT, $out);
}

/*
 * Variable to show the status messages for debugging.
 * Set to FALSE to suppress printing status
 */
$show_status = FALSE;

function statmsg($msg, $stdout = FALSE)
{
  global $show_status, $term_colors;
  if ($show_status == FALSE)
    return;
  
  $out = "";
  
  if (is_array($msg))
  {
    foreach($msg as $k => $v)
      $out .= $term_colors['CYN']."[ $k : ".$term_colors['RESET'].$v.
              $term_colors['CYN']." ] ".$term_colors['RESET'];
  }
  else
    $out = $msg;
  
  $out = $term_colors['YEL']."STATUS: ".$term_colors['RESET'].$out."\n";
  if ($stdout)
    fwrite(STDOUT, $out);
  else
    echo $out;
}


/*
 * Helper function to print info messages
 * 
 * Parameters:
 *   msg - string, message to be printed
 *   stdout - bool, print into STDERR instead of STDOUT
 * 
 */
function infomsg($msg, $stdout = FALSE)
{
  global $term_colors;
  
  $out = $term_colors['GRN']."INFO: ".$term_colors['RESET'].$msg."\n";
  
  if ($stdout)
    fwrite(STDOUT, $out);
  else
    echo $out;
}

/*
 * Class that loads and validates the CSV file from the filesystem
 */
class CsvFile
{
  
  public $file;      // File handle
  public $file_name; // File name
  public $line_num;  // Current line number in CSV 
  
  /*
   * Constructor for CsvFile class
   * 
   * Parameters:
   *  fname - the CSV file name to be processed. Can be the absolute or
   *          relative path to the file.
   * 
   * Throws error if the file could not be opened.
   * 
   */
  function __construct($fname)
  {
    $this->file_name = $fname;
    if (($this->file = @fopen($fname, "r")) == FALSE)
      throw new Exception("File $fname could not be opened");
    $this->line_num = 0;
  }
  
  
  /*
   * File handle is not implicitly closed, so we need to do it in
   * the destructor.
   */
  function __destruct()
  {
    fclose($this->file);
    statmsg("Closed ".$this->file_name);
  }
  
  
  /* 
   * Performs the validation:
   *  - name and surname should be capitalized e.g. from "john" to "John"
   *  - emails should be set to lowercase
   *  - emails should be valid addresses
   * 
   * Parameters:
   *  data - array with numerical indexes where
   *     0 - name 
   *     1 - surname
   *     2 - email
   * 
   * Returns:
   *  validated row where indexes are strings on success
   *  FALSE on failure
   */ 
  function csv_row_validate($data)
  {
    // Make sure the array size is not too small
    if (count($data) < 3)
      return FALSE;
      
    $row['name'] = ucfirst(strtolower(trim($data[0])));
    $row['surname'] = ucfirst(strtolower(trim($data[1])));
    
    if (($email = filter_var(strtolower(trim($data[2])), 
                             FILTER_VALIDATE_EMAIL)) !== FALSE)
      $row['email'] = $email;
    else
    {
      errmsg("Email validation failed: ".$data[2]);
      return FALSE;
    }
      
    return $row;
  }
  

  /*
   * Read one row from CSV file. The header is skipped because it does
   * not contain data.
   * The CSV file can be very large and we do not want to read the entire
   * file into the memory. Therefore, reading it one line at a time.
   * 
   * Returns:
   *   validated data array for inserting into the database on success
   *   empty array() if processing error happened
   *   FALSE when end of file is reached
   */
  function get_csv_row()
  {
    // Read the header and ignore it
    if ($this->line_num == 0 && fgets($this->file) == FALSE)
      return FALSE;

    // Read next line in CSV file
    if (($raw_csv = fgets($this->file)) !== FALSE)
    {
      // All leading and trailing spaces removed
      $raw_csv = trim($raw_csv);
      
      $this->line_num++;
      if (strlen($raw_csv) == 0)
        return array();  // Just an empty line, no error is needed
      
      if (($csv_array = str_getcsv($raw_csv)) == FALSE)
      {
        errmsg("Wrong CSV line format: $raw_csv");
        return array();
      }
      if (($data_validated = $this->csv_row_validate($csv_array)) == FALSE)
      {
        errmsg("$this->file_name : line $this->line_num has invalid data: ".
               "[$raw_csv]");
        return array();
      }
      
      return $data_validated;
    }
    
    return FALSE;
  }
}


/* 
 * Class that provides functionality for uploading CSV into 
 * PostgreSQL database
 */ 
class CsvUpload
{
  public $opts; // Options after parsing are kept here
  
  public $conn; // PDO Connection handle, which will be automatically destroyed
                // on error or on normal exit. So, it is quite safe.
                
  public $stmt; // Prepared statement handle for data inserts
                
  public $csv_file; // CsvFile object
  
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
         "                  but the database won\'t be altered. The status\n".
         "                  and validated data will be printed.\n".
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
      
      // Set the error mode to throw exceptions in addition to error codes
      $this->conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    }
    catch(PDOException $ex)
    {
      errmsg("Connection error: ".$ex->getMessage());
      return FALSE;
    }

    statmsg("Connected to ".(isset($host) ? $host : "default host"));
    //var_dump($this->opts);
    return TRUE;
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
    
    try
    {
      $this->conn->query("CREATE TABLE users(id SERIAL PRIMARY KEY,".
                         "name VARCHAR(255), surname VARCHAR(255),".
                         "email VARCHAR(255) UNIQUE)");
    }
    catch (PDOException $ex)
    {
      
      errmsg($ex->getMessage());
      errmsg("No changes are made in the database");
      return 1;
    }
    
    infomsg("Table 'users' is successfully created");
    
    return 0;
  }
  
  
  /*
   * Inserting one row of data into 'users' table
   * 
   * Parameters:
   *   data - array, data to insert
   * 
   * Returns:
   *   FALSE - if unrecoverable error happens
   *   0 - if recoverable error happens
   *   N - integer, number of inserted rows
   */ 
  function insert_db_data($data)
  {
    if (!isset($this->stmt))
    {
      // Prepare a statement only once
      $sql = "INSERT INTO users (name, surname, email) ".
             "VALUES (:name, :surname, :email)";
      try
      {
        $this->stmt = $this->conn->prepare($sql);
      }
      catch(PDOException $ex)
      {
        errmsg($ex->getMessage());
        return FALSE;
      }
    }
    
    if (!isset($this->stmt))
    {
      errmsg("SQL is not prepared");
      return FALSE;
    }
    
    try
    {
      $this->stmt->execute($data);
    }
    catch(PDOException $ex)
    {
      errmsg($ex->getMessage());
      /* Probably the unique data was duplicating, 
         we can ignore this error and try again later */
      return 0;
    }
    
    return $this->stmt->rowCount();
  }
  
  /*
   * Import CSV file line by line.
   * 
   * Parameters:
   *   fname - string, the file name
   *   dry_run - bool, the dry_run directive
   */
  function import_csv($fname, $dry_run = FALSE)
  {
    if (!$this->connect())
      return 1;
    
    try
    {
      $this->csv_file = new CsvFile($fname);
    }
    catch(Exception $ex)
    {
      errmsg($ex->getMessage());
      return 1;
    }
    
    statmsg("Opened $fname for reading");
    
    $processed_count = 0;
    
    // Reading CSV line by line till the end
    while(($data = $this->csv_file->get_csv_row()) !== FALSE)
    {
      if(count($data) == 0)
        continue; // Current line could not be processing, skip to the next
        
      statmsg($data);
              
      if ($dry_run == FALSE)
      {
        // Write data into the table only if --dry_run is not specified
        if (($res = $this->insert_db_data($data)) === FALSE)
          return 1; // Something bad happened, cannot continue
          
        if ($res)
          $processed_count++;
      }
      else
        $processed_count++; // For dry_run just increment
    }
    infomsg("Processed $processed_count out of ".$this->csv_file->line_num." lines");
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
    else if ($fname = $this->getoption("file"))
    {
      if (($dry_run = $this->getoption("dry_run")) !== FALSE)
      {
        // Enable STATUS messages
        $GLOBALS["show_status"] = TRUE;
      }
      
      return $this->import_csv($fname, $dry_run);
    }
    else if ($this->getoption("dry_run"))
    {
      echo "The option --dry_run requires --file to be specified\n";
      return 1;
    }
    
    echo "No action is taken. ".
         "Please run with --help to check the command line options.\n";
  }
  
}

setup_terminal();

$csv_uploader = new CsvUpload();
return $csv_uploader->process();

?>
