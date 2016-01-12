<?php
/**
 * First step of setup: database creation (action of index.php)
 * @copyright  Copyright (c) 2014 PHPBack
 * @author       Ivan Diaz <ivan@phpback.org>
 * @author       Benjamin BALET<benjamin.balet@gmail.com>
 * @license      http://opensource.org/licenses/GPL-3.0 GPL-3.0
 * @link            https://github.com/ivandiazwm/phpback
 * @since         1.0
 */
define('APPLICATION_LOADED', true);
define('BASEPATH', '.');    //Make this script work with nginx

error_reporting(E_ALL);
ini_set('display_errors', 1);
include 'pretty_message.php';
include '../application/libraries/Hashing.php';

$hashing = new Hashing();

/**
 * Redirect to the initial form and pass to the page an array containing :
 * 1. Previous entered values
 * 2. Error messages if any
 * @param string $url URL of the action page
 * @param array $data array containing the previous posted values in the form
 */
function redirectPost($url, array $data) {
    echo '<html><head>
        <script type="text/javascript">
            function close() {
                document.forms["redirectpost"].submit();
            }
        </script>
        <title>Please Wait...</title>
    </head>
    <body onload="close();">
    Please Wait...<br />
    <form name="redirectpost" method="post" action="' . $url . '">';
    if (!is_null($data)) {
        foreach ($data as $k => $v) {
            echo '<input type="hidden" name="' . $k . '" value="' . $v . '"> ';
        }
    }
    echo "</form></body></html>";
    exit(1);
}

/**
 * An error was encountered, so send back to the initial form
 * @param string $errorMessage Error message sent back by the database driver 
 */
function exitOnError($errorMessage) {
    $data['error'] = $errorMessage;
    redirectPost('index.php', $data);
}

/**
 * Create the CodeIgniter database configuration file
 * @param type $hostname
 * @param type $username
 * @param type $password
 * @param type $database
 */
function createDbConfigFile($hostname, $username, $password, $database) {
    @chmod('../application/config', 0777);
    if (($file = fopen('../application/config/database.php', 'w+')) == FALSE) {
        exitOnError('ERROR #1: Config file could not be created');
    }
    $content = '<?php ' . PHP_EOL;
    $content .= '//Configuration generated by install script' . PHP_EOL;
    $content .= '$active_group = \'default\';' . PHP_EOL;
    $content .= '$active_record = TRUE;' . PHP_EOL;
    $content .= '$db[\'default\'][\'hostname\'] = \'' . $hostname . '\';' . PHP_EOL;
    $content .= '$db[\'default\'][\'username\'] = \'' . $username . '\';' . PHP_EOL;
    $content .= '$db[\'default\'][\'password\'] = \'' . $password . '\';' . PHP_EOL;
    $content .= '$db[\'default\'][\'database\'] = \'' . $database . '\';' . PHP_EOL;
    $content .= '$db[\'default\'][\'dbdriver\'] = \'mysqli\';' . PHP_EOL;
    $content .= '$db[\'default\'][\'dbprefix\'] = \'\';' . PHP_EOL;
    $content .= '$db[\'default\'][\'pconnect\'] = TRUE;' . PHP_EOL;
    $content .= '$db[\'default\'][\'db_debug\'] = TRUE;' . PHP_EOL;
    $content .= '$db[\'default\'][\'cache_on\'] = FALSE;' . PHP_EOL;
    $content .= '$db[\'default\'][\'cachedir\'] = \'\';' . PHP_EOL;
    $content .= '$db[\'default\'][\'char_set\'] = \'utf8\';' . PHP_EOL;
    $content .= '$db[\'default\'][\'dbcollat\'] = \'utf8_general_ci\';' . PHP_EOL;
    $content .= '$db[\'default\'][\'swap_pre\'] = \'\';' . PHP_EOL;
    $content .= '$db[\'default\'][\'autoinit\'] = TRUE;' . PHP_EOL;
    $content .= '$db[\'default\'][\'stricton\'] = FALSE;' . PHP_EOL;

    if (fwrite($file, $content) == FALSE) {
        fclose($file);
        exitOnError('ERROR #1: Config file could not be created');
    }
    fclose($file);
}


/* if started from commandline, wrap parameters to $_POST */
if (!isset($_SERVER["HTTP_HOST"]))
    parse_str($argv[1], $_POST);

if ($_POST['adminpass'] != $_POST['adminrpass'])
    exitOnError('Admin passwords do not match');

$server = new mysqli($_POST['hostname'], $_POST['username'], $_POST['password']);

if ($server->connect_error) {
    $str = mb_convert_encoding($server->connect_error, "UTF-8", "auto");
    exitOnError('ERROR #2: Server connection error (' . $server->connect_errno . ') ' . $str);
}

if ($_POST['database'] != "") {
    if (!file_exists('../application/config/database.php'))
        createDbConfigFile($_POST['hostname'], $_POST['username'], $_POST['password'], $_POST['database']);
    include '../application/config/database.php';
    if (!($_POST['hostname'] == $db['default']['hostname'] && $_POST['username'] == $db['default']['username'] && $_POST['password'] == $db['default']['password'] && $_POST['database'] == $db['default']['database']))
        exitOnError('Config file does not match with the given information');
    if ($server->select_db($_POST['database']) === FALSE)
        exitOnError("ERROR #3: Couldn't connect to database");
    $query = file_get_contents('database_tables.sql');
    if ($server->multi_query($query) === FALSE)
        exitOnError("ERROR #4: Couldn't create the tables");
}else {
    if (!file_exists('../application/config/database.php'))
        createDbConfigFile($_POST['hostname'], $_POST['username'], $_POST['password'], 'phpback');

    if ($server->select_db('phpback') === TRUE)
        exitOnError("ERROR #5: You already have a phpback database, please create another manually");
    if (!$server->query("CREATE DATABASE IF NOT EXISTS phpback CHARACTER SET utf8 COLLATE utf8_general_ci")) {
        exitOnError("ERROR #6: Could not create database");
    }
    if ($server->select_db('phpback') === FALSE)
        exitOnError("ERROR #5: Generated database connection error");
    $sql = file_get_contents('database_tables.sql');
    if ($server->multi_query($sql) === FALSE)
        exitOnError("ERROR #4: Couldn't create the tables");
}
do {
    if ($r = $server->store_result())
        $r->free();
}while ($server->more_results() && $server->next_result());

$result = $server->query("SELECT id FROM settings WHERE name='title'");

if ($result->num_rows == 1) {
    if (!@chmod('../install', 0777)) {
        echo "PLEASE DELETE install/ FOLDER MANUALLY. THEN GO TO yourwebsite.com/feedback/admin/ TO LOG IN.";
        exit;
    }

    //In case of success (by using previously set parameters), delete the content of installation folder
    unlink('index.php');
    unlink('install1.php');
    unlink('database_tables.sql');
    unlink('index2.php');
    unlink('install2.php');
    header('Location: ../admin');
    exit;
} else {
    $server->query("INSERT INTO users(id,name,email,pass,votes,isadmin,banned) VALUES('','" . $_POST['adminname'] . "','" . $_POST['adminemail'] . "','" . $hashing->hash($_POST['adminpass']) . "', 20, 3,0)");

    if (!@chmod('../install', 0777)) {
        $url = getBaseUrl();
        displayMessage("PLEASE DELETE install/index.php, install/install1.php AND install/database_tables.sql FILES MANUALLY.<br />
            THEN GO TO <a href='" . $url . "/install/index2.php'>yourwebsite.com/feedback/install/index2.php</a> TO CONTINUE THE INSTALLATION.");
        exit;
    }

    //In case of success, delete the installation files of the first step
    unlink('index.php');
    unlink('install1.php');
    unlink('database_tables.sql');
    header('Location: index2.php');
}
