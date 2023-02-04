<?php

// error_reporting(E_ALL);
// ini_set('display_errors', true);

if (defined('STDIN') && isset($argv[1]) && $argv[1] === 'sitename') {
  die(basename(realpath(__DIR__.'/../../')));
}

function check_shell_exec()
{
  return function_exists('shell_exec');
}

function check_file_get_contents()
{
  return function_exists('file_get_contents');
}

function check_curl()
{
  return function_exists('curl_init');
}

function check_exists($path)
{
  return file_exists($path);
}

function check_writable($path)
{
  return is_writable($path);
}

function check_readable($path)
{
  return is_readable($path);
}

function check_db_connection($db_host, $db_name, $db_user, $db_pass)
{
  try {
    new PDO(
      sprintf(
        "mysql:host=%s;dbname=%s;charset=utf8mb4",
        $db_host,
        $db_name
      ),
      $db_user,
      $db_pass,
      [
        PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES   => false
      ]
    );

    return [
      'status' => true
    ];
  } catch (\PDOException $e) {
    $common = [
      2002 => sprintf('Could not connect to: \'%s\'', $db_host),
      1049 => sprintf('Unknown database: \'%s\'', $db_name),
      1044 => sprintf('Access denied for user \'%s\'@\'%s\' to database \'%s\'', $db_user, $db_host, $db_name),
      1045 => sprintf('Access denied for user \'%s\'@\'%s\' (using password: %s)', $db_user, $db_host, !empty($db_pass) ? 'yes' : 'no'),
    ];
    $code = (int) $e->getCode();
    $message = isset($common[$code]) ? $common[$code] : $e->getMessage();
    return [
      'status' => false,
      'message' => $message,
      'code' => $code
    ];
  }
}

function check_db_import()
{
  return file_exists('../sql/local.sql');
}

function db_import()
{
  include_once 'wp-config.php';

  $errors = [];

  if (!defined('DB_NAME')) {
    $errors['DB_NAME'] = 'DB_NAME is not defined.';
  }

  if (!defined('DB_USER')) {
    $errors['DB_USER'] = 'DB_USER is not defined.';
  }

  if (!defined('DB_PASSWORD')) {
    $errors['DB_PASSWORD'] = 'DB_PASSWORD is not defined.';
  }

  if (!defined('DB_HOST')) {
    $errors['DB_HOST'] = 'DB_HOST is not defined.';
  }

  if (!empty($errors)) {
    json(['errors' => $errors]);
  }

  $socketPath = ini_get('mysqli.default_socket');
  $ssh_entry_path = stristr($socketPath, 'Local', true) . 'Local/ssh-entry';
  $siteId = explode('/', stristr($socketPath, 'run'))[1];

  $ssh_file = $ssh_entry_path . '/' . $siteId . '.sh';

  $ssh_file = file_get_contents($ssh_file);

  // remove bits not needed
  $ssh_file = str_replace('exec $SHELL', '', $ssh_file);
  $ssh_file = str_replace('echo "Launching shell: $SHELL ..."', '', $ssh_file);

  // append command we will run
  $ssh_file .= PHP_EOL . 'wp db import ../sql/local.sql 2>&1';

  $result = shell_exec($ssh_file);

  file_put_contents('../db-import.log', $result);

  return strstr($result, 'Success:') !== false ? 'true' : '';
}

function exec_wp_cli_search_replace($from, $to)
{
  $socketPath = ini_get('mysqli.default_socket');
  $ssh_entry_path = stristr($socketPath, 'Local', true) . 'Local/ssh-entry';
  $siteId = explode('/', stristr($socketPath, 'run'))[1];

  $ssh_file = $ssh_entry_path . '/' . $siteId . '.sh';

  $ssh_file = file_get_contents($ssh_file);

  // remove bits not needed
  $ssh_file = str_replace('echo -n -e "\033]0;afc-wp Shell\007"', '', $ssh_file);
  $ssh_file = str_replace('exec $SHELL', '', $ssh_file);
  $ssh_file = str_replace('echo "Launching shell: $SHELL ..."', '', $ssh_file);

  $from = escapeshellarg($from);
  $to = escapeshellarg($to);

  return shell_exec($ssh_file . PHP_EOL . 'wp search-replace ' . $from . ' ' . $to . ' --all-tables 2>&1');
}

function get_wp_config()
{
  return file_get_contents('wp-config.php');
}

function write_wp_config($contents)
{
  return file_put_contents('wp-config.php', $contents);
}

function json($data = [])
{
  header('Content-Type: application/json;charset=utf-8');
  die(json_encode($data, JSON_PRETTY_PRINT));
}

/**
 * 
 */
$response = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest') {
  $_POST = json_decode(file_get_contents('php://input'), true);

  if (empty($_POST) || !isset($_POST['action']) || !isset($_POST['data'])) {
    $response['error'] = [
      'message' => 'Invalid POST data.'
    ];
    json($response);
  }

  if ($_POST['action']['do'] === 'landing-checks') {
    $response['checks'] = [];

    // functions
    $response['checks']['file_get_contants'] = check_file_get_contents();
    $response['checks']['shell_exec'] = check_shell_exec();
    $response['checks']['curl'] = check_curl();
    // public folder
    $response['checks']['public_exists'] = check_exists('../public');
    $response['checks']['public_writable'] = check_writable('../public');
    $response['checks']['public_readable'] = check_readable('../public');
    // wp-config.php
    $response['checks']['wp_config_readable'] = check_readable('../public/wp-config.php');
    $response['checks']['wp_config_writeable'] = check_writable('../public/wp-config.php');
    $response['checks']['wp_config_exists'] = check_exists('../public/wp-config.php');
    // wp-cli.phar
    //$response['checks']['wp_cli_readable'] = check_readable('../public/wp-cli.phar');
    //$response['checks']['wp_cli_writeable'] = check_writable('../public/wp-cli.phar');
    //$response['checks']['wp_cli_exists'] = check_exists('../public/wp-cli.phar');
    // db import
    $response['checks']['db_import_readable'] = check_readable('../sql/local.sql');
    $response['checks']['db_import_writeable'] = check_writable('../sql/local.sql');
    $response['checks']['db_import_exists'] = check_exists('../sql/local.sql');

    // ssh-entry
    $socketPath = ini_get('mysqli.default_socket');
    $ssh_entry_path = stristr($socketPath, 'Local', true) . 'Local/ssh-entry';
    $siteId = explode('/', stristr($socketPath, 'run'))[1];

    $ssh_file = $ssh_entry_path . '/' . $siteId . '.sh';

    $response['checks']['ssh_entry_exists'] = check_exists($ssh_file);
    $response['checks']['ssh_entry_readable'] = check_readable($ssh_file);
    $response['checks']['ssh_entry_writeable'] = check_writable($ssh_file);

    // $response['error'] = [
    //   'message' => 'This is not really an error'
    // ];

    json($response);
  }

  if ($_POST['action']['do'] === 'phpinfo') {
    ob_start();
    phpinfo();
    json(ob_get_clean());
  }

  if ($_POST['action']['do'] === 'database-connection-details') {
    include 'wp-config.php';
    json([
      'DB_NAME' => defined('DB_NAME') ? DB_NAME : '',
      'DB_USER' => defined('DB_USER') ? DB_USER : '',
      'DB_PASSWORD' => defined('DB_PASSWORD') ? DB_PASSWORD : '',
      'DB_HOST' => defined('DB_HOST') ? DB_HOST : '',
    ]);
  }

  if ($_POST['action']['do'] === 'database-connection-details-save') {
    $file = file_get_contents('wp-config.php');

    $file = preg_replace("#define\(\s?'DB_NAME',\s?'(.*)'\s?\);#",     "define( 'DB_NAME', '" . addslashes($_POST['data']['DB_NAME']) . "' );", $file);
    $file = preg_replace("#define\(\s?'DB_USER',\s?'(.*)'\s?\);#",     "define( 'DB_USER', '" . addslashes($_POST['data']['DB_USER']) . "' );", $file);
    $file = preg_replace("#define\(\s?'DB_PASSWORD',\s?'(.*)'\s?\);#", "define( 'DB_PASSWORD', '" . addslashes($_POST['data']['DB_PASSWORD']) . "' );", $file);
    $file = preg_replace("#define\(\s?'DB_HOST',\s?'(.*)'\s?\);#",     "define( 'DB_HOST', '" . addslashes($_POST['data']['DB_HOST']) . "' );", $file);

    json(file_put_contents('wp-config.php', $file));
  }

  if ($_POST['action']['do'] === 'database-connection-check') {
    $response = [];
    $errors = [];
    if (empty($_POST['data'])) {
      $response['error'] = [
        'message' => 'Invalid POST data.'
      ];
    } else {
      if (empty($_POST['data']['DB_HOST'])) {
        $errors['DB_HOST'] = 'Host is a required field.';
      }
      if (empty($_POST['data']['DB_NAME'])) {
        $errors['DB_NAME'] = 'Database name is a required field.';
      }
      if (empty($_POST['data']['DB_USER'])) {
        $errors['DB_USER'] = 'Database user is a required field.';
      }
      if (empty($_POST['data']['DB_PASSWORD'])) {
        $errors['DB_PASSWORD'] = 'Database password is a required field.';
      }

      if (!empty($errors)) {
        $response['errors'] = $errors;
      } else {
        $response = check_db_connection(
          $_POST['data']['DB_HOST'],
          $_POST['data']['DB_NAME'],
          $_POST['data']['DB_USER'],
          $_POST['data']['DB_PASSWORD']
        );
      }
    }

    json($response);
  }

  if ($_POST['action']['do'] === 'database-import') {
    set_time_limit(0);
    $result = db_import();
    if ($result !== 'true') {
      $response = $result;
    } else {
      $response = true;
    }
    json($response);
  }

  if ($_POST['action']['do'] === 'wp-find-replace-get-from') {
    include_once 'wp-config.php';

    $db = new PDO(
      sprintf(
        "mysql:host=%s;dbname=%s;charset=utf8mb4",
        DB_HOST,
        DB_NAME
      ),
      DB_USER,
      DB_PASSWORD,
      [
        PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES   => false
      ]
    );

    $res = $db->query('SELECT option_value FROM wp_options WHERE option_name = "siteurl" LIMIT 1');

    json(!empty($res) ? $res->fetchColumn() : '');
  }

  if ($_POST['action']['do'] === 'wp-find-replace') {
    set_time_limit(0);

    $from = $_POST['data']['from'];
    $to = $_POST['data']['to'];

    $result = exec_wp_cli_search_replace($from, $to);

    file_put_contents('../wp-search-replace.log', $result);

    $status = strstr($result, 'Success:') !== false;

    json([
      'status' => $status,
      'log' => $status === true ? strstr($result, 'Table') : $result
    ]);
  }

  if ($_POST['action']['do'] === 'local-config') {

    $socketPath = ini_get('mysqli.default_socket');
    $errorLogPath = ini_get('error_log');

    $sites_path = stristr($errorLogPath, 'logs', true);

    $siteId = explode('/', stristr($socketPath, 'run'))[1];
    $local_config_path = stristr($socketPath, 'Local', true) . 'Local';

    //
    if (file_exists($local_config_path.'/ssh-entry/'.$siteId.'.sh')) {
      copy($local_config_path.'/ssh-entry/'.$siteId.'.sh', '../site-shell.sh');
    }

    //
    $ssh_entry_path = stristr($socketPath, 'Local', true) . 'Local/ssh-entry';
    $ssh_file = $ssh_entry_path . '/' . $siteId . '.sh';
    $ssh_file = file_get_contents($ssh_file);
    // remove bits not needed
    $ssh_file = str_replace('echo -n -e "\033]0;afc-wp Shell\007"', '', $ssh_file);
    $ssh_file = str_replace('exec $SHELL', '', $ssh_file);
    $ssh_file = str_replace('echo "Launching shell: $SHELL ..."', '', $ssh_file);
    file_put_contents('../export-database.sh', $ssh_file.' wp db export --add-drop-table --allow-root ../sql/local.sql');
    file_put_contents('../import-database.sh', $ssh_file.' wp db import ../sql/local.sql');

    $sites_json = file_get_contents($local_config_path . '/sites.json');
    $sites = json_decode($sites_json, true);
    $site = isset($sites[$siteId]) ? $sites[$siteId] : [];

    $live_link = isset($site['liveLinkProSettings']) ? $site['liveLinkProSettings'] : false;
    if (is_array($live_link)) {
      $live_link['url'] = 'https://' . $live_link['subdomain'] . '.localsite.io/';
    }

    $profile = [
      "id" => "0",
      "firstname" => "User",
      "lastname" => "",
      "name" => "User",
      "email" => "",
      "photo_url" => "https://www.gravatar.com/avatar/?s=200&d=mp",
      "seats" => [],
      "active_pro_seat" => false,
      "__typename" => "User",
      "former_pro_seat" => false,
      "initials" => "?"
    ];
    if (file_exists($local_config_path . '/profiles-hub.json')) {
      $profile_json = file_get_contents($local_config_path . '/profiles-hub.json');
      $profile = json_decode($profile_json, true);
      $profile = isset($profile[0]) ? $profile[0] : [
        "id" => "0",
        "firstname" => "User",
        "lastname" => "",
        "name" => "User",
        "email" => "",
        "photo_url" => "https://www.gravatar.com/avatar/?s=200&d=mp",
        "seats" => [],
        "active_pro_seat" => false,
        "__typename" => "User",
        "former_pro_seat" => false,
        "initials" => "?"
      ];
    }

    $response = [
      'siteId' => $siteId,
      'mysqli.default_socket' => $socketPath,
      'local_config_path' => $local_config_path,
      'sites_path' => rtrim($sites_path, '/'),
      'public_path' => $sites_path . 'app/public',
      'sql_path' => $sites_path . 'app/sql',
      'logs' => [
        'php-error' => $sites_path . 'logs/php/error.log',
        'php-fpm' =>  $sites_path . 'logs/php/php-fpm.log',
        'apache-access' =>  $sites_path . 'logs/apache/access.log',
        'apache-error' =>  $sites_path . 'logs/apache/error.log',
        'apache-site-error' =>  $sites_path . 'logs/apache/site-error.log',
        'lightning' => $local_config_path . '/local-lightning.log'
      ],
      'site' => $site,
      'live_link' => $live_link,
      'profile' => $profile
    ];

    json($response);
  }

  if ($_POST['action']['do'] === 'cleardown') {
    unlink('local-setup.php');
    //unlink('wp-cli.phar');
    json(true);
  }

  json([
    'status' => false,
    'message' => 'Unknown action :(',
    'data' => $_POST
  ]);
}
?>
<!doctype html>
<html lang="en">

<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no">

  <title>Local Setup</title>

  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/4.6.0/css/bootstrap.min.css" integrity="sha512-P5MgMn1jBN01asBgU0z60Qk4QxiXo86+wlFahKrsQf37c9cro517WzVSPPV1tDKzhku2iJ2FVgL67wG03SGnNA==" crossorigin="anonymous" referrerpolicy="no-referrer" />
  <link href="https://cdnjs.cloudflare.com/ajax/libs/codemirror/5.32.0/codemirror.min.css" rel="stylesheet">
  <link href="https://cdnjs.cloudflare.com/ajax/libs/codemirror/5.32.0/theme/ttcn.min.css" rel="stylesheet">

  <style>
    [v-cloak] {
      display: none
    }

    .stats-bar {
      background-color: #e9ecef;
      display: flex;
      -ms-flex-wrap: wrap;
      flex-wrap: wrap;
      padding: 0.4rem 0.55rem;
      margin-bottom: 1rem
    }

    .pointer {
      cursor: pointer
    }

    .bg-info-light {
      background-color: #cce5ff;
    }

    .bg-ocean-green {
      background-color: #51BB7B;
    }

    .text-ocean-green-dark {
      color: #267048;
    }

    .text-small {
      font-size: 75%
    }

    .btn-xs {
      padding: .05rem .2rem;
      font-size: 75%
    }

    .media h5 {
      font-size: 16px;
      font-weight: bold
    }

    .media h5 code {
      font-size: 16px;
      font-weight: normal
    }

    .status-good,
    .status-bad {
      background-image: url("data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAAHMAAAAyCAIAAADgJXN4AAAAAXNSR0IB2cksfwAAAAlwSFlzAAALEwAACxMBAJqcGAAABLlJREFUeJztm0tuFDEQhmckbhAhTpPhLlnAbeAQuUPugMSO3ShbEEJigwRoVqbgZypuP6rK5e62hShZUWsm48fX1fWy+xBmlcvlB7XRs6gKpvfz8ZFacZ6H/eekCk30/P1MzU1267sCpt8eHr6+eUuNrvP/mY4ssN5/vnfDxZqpCQrVMz1q1PmXV68/3Z4+Pn9BfwE3GWgispg0sLrJQptotdRo8cU198wQt416/s30+Iwa4NIn9Hk80CxkaU5E8+58B6zcCG5TP7RyohkvO1+ze4bo/K+q/un/qd2eZiQLPSWsLz+cAJSuQbmVCFYerxk6BePQw5expkyv9y/pfzxZYCWm1ACUrx1k4+c0geu2DKytBVW9Kmzux4aR5QAAWG/e31DDBSP26Wxx/TVraJkn+ysZ6xQejD0V4SOUx3cHaoDLZB1YSRL3YnlsBZEM67XDiaIudlYx1rjB2rb6Lkjuu3MVg+bKfBV/ZXCPA8gSMhjTIlb6kL71YQ3LeFMgUrSMsdBXkgUwRB27koURULH67EAykB1uPhaSq6rWR1iFe7MrWWAtMgVWIt6T1Maiwq05H7qWf2WMkXciy9paw8rmdcVktFVzOcWSseZJQVF2IsvRVQ0rEV8XKwRwZXPJcIVcQA5di7IHWQQDNdvK5nWj6hTDlTUXRQbh34TQtSh7kIUdELQVXmu7CViUkYBKTBuxhh3IynaAY6wdyqmKN5Nbe3K8LVlOCmp2YHWvJcxEDxX6goFENiSrYt3IawnzcWhua0LMsiFZS5jlzrXc0qS5PeXdBdl4A6o/C5LDrN3sQD4xo+baQ9eiPJGNCyX9a1bjAXfNpV9oaXrcGtkB3ygpWTy8nS4b8cBwr1UUNbxdC+6Bx0u0zA1Xzgs6S1n90urEuALr9GCxwiYlkib9kusDM2CVi1graq5ENk6QjDhq/cxgB1BtUc2roLnOeJbYFR9h1lwVroDVof7ritFrSa0xTliQrcVJFri5pc61dWAwYDGv6rdNse0inu2BS+MJecEOxQFB1B0tqKQFvR1uIVPAboqseknX+KFQdhkeZqnbBITekp7Z4RayW0s0mmju/PGAugWLuMoYPFgqCQWyauSU+6Kawg53XEGLtHIdpAs9jzDsLJQrMnKZKt8KFEKCsViDmBrU6limqo0Gt1rrsqRSoEatFq6NtQNB3AcTLGZ6QtYVh0lVRHU7gG1uLQoeGA8ENdgSuQCuaqCFTiSy6lY2tLIYww43r0He/jIYysVZuQpfIfHVK9/qtuu0dqCmsPZtAtZcy1nE5Lc62fzQoNxmiAdCnM4u1a01lbpcX6BJLAPv9aJD5/lZ9cDAbHYgxEfekgfZtU2QHMSLG78T4TxNrx5ymccOQKBltGA2CHwcxn3X+QgpaMYtV1srWcvBrOOgTcPibEGWtw8sJzubemaaAO0nGwxwJ7ED4aqwHDaxWq3yBhPDxRDs4vxkgwh37KZhMkksmMnCJqzy7lI8SvxKH8bykw0VuDPkBfEMebVgqp4idg8kvC/pOcnBNUOGO48dCFGcBD3ilW831mpkw/LlmKaNsh3kEr3FzUeOtx4uH6Xr9NGl+6XujaS41J1l/DuM/6r8J7uV/AIqPULdDkbkxAAAAABJRU5ErkJggg==");
      background-repeat: no-repeat;
      background-color: #fff
    }

    .status-good {
      height: 50px;
      width: 50px;
      background-size: 100px;
      background-position: 0px 2px;
    }

    .status-bad {
      height: 50px;
      width: 50px;
      background-size: 100px;
      background-position: -54px 4px;
    }

    .vue-codemirror {
      width: 100%
    }

    .CodeMirror {
      font-family: monospace;
      height: calc(100vh - 250px);
      min-height: 300px;
      font-size: 12px;
      color: #000;
      direction: ltr
    }

    a {
      color: #267048;
      font-weight: bold
    }

    a.readmore.collapsed:before {
      content: 'Read more...';
      display: block;
    }

    a.readmore:before {
      display: none;
    }
  </style>
</head>

<body>
  <div id="app">
    <main class="container mb-3">

      <header>
        <nav class="mt-4 navbar navbar-dark bg-ocean-green navbar-expand-lg rounded-top ">
          <h1 class="navbar-brand p-0 m-0" style="display:contents">
            <img src="data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAAOkAAABdCAYAAABAbEGAAAAABHNCSVQICAgIfAhkiAAADp1JREFUeJztnUFoHNcZx/9Ot82q6wVtEg1ZgYVHEIHWi4NlosVuKNHeqtiHKmBBQgq2CynYvjQFH4pMcXLwIe5FDuQQ2ScfVLAvttVDYZVDorACRY0rrUABzVYBrTzbdgWr1Q71kvYgj7Iz783szOzM7Mz6+0EgmpU1szPze+97733vvUO//tsf/weCIALLC52+AIIgzCFJCSLgkKQEEXBIUoIIOCQpQQQckpQgAk6k0xdglXRCxLnBLGpP6/iLNA+pWmJ+JyOk8PaRU5DrFdxZn0OtoXTgSgnCXUIhabZ/BJdTEwc/p18axPtffKz5nXRCxNXj7+7/kBAhxpO4tjRDohKhJ/Dhrl5QAIhFokgnRM2xY7qfxXgS109eRCwS9fwaCcJLAi0pT1A7kKhENxBYSdsVVIVEJcJOICV1S1AVEpUIM4GT1G1BVUhUIqwESlKvBFUhUYkwEhhJjQTdayi4sz7n6G/Oby0zx0hUImwEQlIzQaeWZriJC1aYLtwjUYnQ03FJvRJUhUQlwk5HJRXjSZwfGmeOuyWoColKhJmOSWokiNuCqpCoRFjpiKR+C6pCohJhxHdJOyWoColKhA1fJXUiaCwS5bZbrZDtH+EeJ1GJMOGbpE4FvX7yIsR4kvlMVnZanvNyaoJEJULPT4Z/88s/eX0StwW9VbiPlYqkOVasljDy8hB6XzysOT7aN4yyssM9x2J5DUI0wZwj8WIcJ15+DV89+Qee/tCw/D0Jwgs8l9QLQXNb3zDHn/7QwJdPHpOoRNfhqaR+CapCohLdiGeS+i2oColKdBueSNopQVVIVKKbcF3STguqQqIS3YKrkgZFUBUS1T5CT4JWWAwYh9za+jBogurP89HJ3+Jo/FVb57mSegdj/SeY41K15PlyoeeHxg/uS+1pHY++/5oZdmqXWCSKUWEYo68MI/3SIPPspGoJKxUJj77/GnK94uq5hZ4ERvuGEYtED1Z6lKqlg4KzWC25cn8nB7OanxfLa7az2sR4EufEMcR+2gMAkOsV3Crcb/varOKKpH4IenbgNM4NZi0lGdxZn8ODzQXmfGERNZ0Qcf3kRc2x2Y0cZjdyrp3jzMBpTFq8nwCQlwu4891f25ZVXeRcvySrF+fkTYNcLK/hxrd3bf2dycEsI/u1pRnXC00j2s448qsGHUuOWH6hxpJsllGtoWBq6XMUq9vMZ89TZpLQk8DNzCVcGBq3de0ZIYWbmUuG96kV6jO/fvKiJUHVc372iw9tFSbNjPYNc4+F7Zm1JamfIa6dEMXod593UcV4Ejczl7j33gqxSNT0Phkh9CRsyalncjCLz978g63rjkWiXEmBffnDhONtJvxug85KORR3S/h5CyH2Ggry5TXDz1VReaGvGhrxrmO6cA8AmNBXvQ9B39JCvfdGBUqxuo2VysbBdxDjScOX/HJqArFIlGlSGJ336vF3TQUrKzuQ6xWI8aTh81V3LbBaWJuJONo37Fp/hx84krQTnURyvWLppbDC8yjq1dff4wq6WpEwu5Hjtq9ikehB21XP+aFxrFSkltJcTk1wn/lqRcJ8aZm5z7FIFGI8icnBrGbrkL2GYkssowIGYLckCTq2w90g9+La4XkKfbP9I9xQc35rGVMmHSC1hoLZjRw+zH+KPU7h02r51Wz/CLdGu7M+h6mlGe5zrzUUrFQkTC3N4NrSDMrPZjvdtrFLnlmoq34eppDXVk3qh6DqsIAQTdi5tJbUGgoWy2uankKnNert9UfICMNMaBbUGvWcOMYcWyyvHUQGrZCqJUwtzeBm5pLmuBhPIiOkkJcLls9rp1BeqUj44MtPkE6ItnpSeQLOby1rIqDRV4YNrztoWJZUbVt4XYNeOfaOaSnYDheGxvH+Fx9rBHIiaq2hQKqWuGGTurian+NoZmT7RyD0aAu8vYaC6VVrgqpI1RJmN3JM6Pv2kVPcl5133vktNry1gt2hjmO92ueyWpGw+K81raTCMBAOR62Hu1dff4/7sN0Ocb0SVIV3PU5DXyOy/SM4O3Da8TW6yViSHeOd3cg5qukfbi4wYW86ITLvBbAvL3Neyb1xXjNGBe07tFhew8p/NjTHwhTyWpI0I6S4bZrb63Out0F57T232HvW3uFhR9RYJIqjLYYDzg1muS+v3/Bqe6d9ALWGgrzM9pzr3w2186cZfVPDKzJCion28uW1g+ZOM6OveFshuIWlcPf8a79ijt1Zn+M+7HY7iaYL9zAr5SBEe61cmi1ahU1WQ98zA6e5YX9zGzUWiWJSzFpu93kBr2BdrUhttZf1YSPADmmkXxpk/t18ybvCV3MtOvGK1e2DwmGxvKaJ1MIS8raUNCOkmBphtSJxh0Pc6sWV6xVfSl0erUTti/bijC6UVcP+j05e1Ig6Kgwjth7tWCcSrxZtdzVGfdgIgCmwjh5mUy+9XgVSRR/qzpd+fOfyckHTI62GvEHvQGoZ7vJCgtucDZSCOMziFLPQl5ei9mBzAVK1xM0XDlq7p90Cg/fvrYw7djLUVQlryNuyJhV6tGFnsbrNlIpOhlnODWYdp6e5DW+WiVmN2sxeQ8HDZ3LOl5aZ3s8+D8L2dijusgWPXfShvR59mF22sLKjG+iFUzOZmlmtSKELeVtKqi8leWHL1dffs1WDZoRUYHo/VYSeBD7Mf6o5VmsouPH4Lv6cuWT4Ut749u5B7cKrLdwe722X2tN6239j92m9ZXpmM341XfShLi+MzZfXNOs4q+mGfs1ocYLtjCNZ0d7wy6kJw8TpVO9R7vEgZuUcwiHu8drTOp4YvGS8pUWDjhspcXZ7rf1Iw+OFurzOKrleYZoxXg/7tUvLmrRY3daEe+mEiNln/58RUqZjiOpn+oH9fHkNRw8nmVC6U9SaQtZmzMJ43sA87+XVF2rdiP6lN0r08BJ9qFt7FpLzrmOjuqV5p0f7hrn9LEGhpaSyUtF8oWPPBq/leoU7NKOHJ6pcr3R0aMIKrQTlXf8ZzgC+X+0xHrz2Z3Mh6wRe1FRr1HU/s51LYjzpaQ+vPtTdn+R/0eC3tQg9Cc+vrx1ahrv63jBgf9yUl/ZVrG5ze0SNNgoOKk4EFXoSGNNFFXZnbrgNb7ikVRJGK3g1E7ObgEHh4BW8UNcuvMysoNBS0tzWN0wqWEZIcTdRuvH4ruHQRVhEdSIoAG5eMy87x09qDYV5FrFI1PHqCgD/ZV7VScorHN7yUAI3hlGC3C61lHE0u5FjpGQa6VvLB714RkMXRm3UZiYHs9yMHjeQqiWmB7cZp4Ly5kzuNRTcXn/U3gW7QL5cYJ7DW8kTjmp4Xp4uL9Wy1lBQVnY0w09iPOlZL6o+1HVCkENeS5I+2FzAaN+waWdAcffHL2c2xthKVK8EBcxflHYE5dVMTpPY3ebh5gIzdptOiDg7cNr2JHpeJGT0Nx5uLjAF+7nBLFaWZmydsxW8UPfh5kLLjiB1KZlmxpInAimp5SGY6cJ97sRfI8yydsxC33Ld244W3paJbgs6v7Xs2ioS7VJrKNxJC3aTSS6nJri917xecYDfTEonRFywudesuj+t0R61vFDXSp6wunyo5m8FNOS1PJ9Urle4+akqb/QNMy+mkxr1xuO7njXii7vbzMC624IWq9uBCHOb4U1SV7/3rcJ909xVVRK70UKtoeABpxY/M3D6YMWHVojxpKYp8c/dbSZM14e6Rgue88jLBU0edlBDXlsrM0jVEqYL93H1+LvMZ+mEiGz/CHeCtB1R5XrF1fVlzfBC0KmlzwMR5jZTayjc56ZO5M/LBcyXljU9+eri1WeOnOLWoOraSGbMbuSQ6Usxz11dv+jh5gJ39ECMJ/H2kVPMPT4/NI68XDi4v9xcXRvJ8vOlZWayRBBDXtsLkeXlAm4V7nPD1cupCUjVEvMl22mjekXYBOUt0GyGfvHmvFxglhBRyQgpWxMBitVtywtMTxfucaOvdEI8GJZpvk4h2muY0aTPCeOFuqs7RUvXBexXOvo8ZKtJGPrFy82Q6xX87qubln9fj6N1d3Nb3xhKZfTiO22jNiPGkziWEE3/s9LOCpugbjFduNd2lGL3u6rrI5n1Z6jCGq3yAOyHsVO6taP0oe5eQ7E97Uw/TCbGk65P1m/37zled1cNa/VyqQJc4yyr0k6NemFonAlNjDDr3XteBVWZ3cihuLuNK6kJW0nywP79ub3+yPZ3VUW9knrHdEaREasVSTORATAKde2PS/MmsWc4/SudpK0V7I1qVDMRnNaodnoijX43LILyeqDdJC8X8MGXn2B2I2cpbXGxvIZrSzOYLtxz/F2lagm/z9/CnfU5y6MExeo2ri3NMDUowJ9Y3jzB2yp5ucBcj9u9vHZGRXi4smGTkVy1hsKtUQHzDZR48rdbk4ZFUBUxnmxrvNhO0kD6WVNBXZtIru9AViooKzuajho3yQgpHD38KpMuWGsoWK1IyLdYE4m3jpLTRAmhJ6FZrkfWzUPlncsO6uqSTnFt60M/RLXy4vJuSNgEJYhmXJMU8EdUu5CgRNhxdadvNYtDH9P/7IUI3nz1OP7+7++w899dzWdmu3GL8ST6or3csTQrkKBEN+CqpEBwRCVBiW7BdUmBzotKghLdhCeSAp0TlQQlug3PJAX8F5UEJboRTyUF/BX1xhsfkKBE19FWxpFVvMhM0udDnh04TYISXYkvkgLui6rf0ImXh0qCEt2Ab5ICzkV1OomaBCW6AV8lBZyJ6iYkKBE2fJcU6JyoJCgRRjoiKeC/qCQoEVY6Jingn6gkKBFmOiop4L2oJCgRdjouKeCdqCQo0Q0EQlLAXFSjhZFbQYIS3UBgJAXcmeRtBglKhJFASQp4JyoJSoSVwEkKuC8qCUqEmUBKCrgnKglKhJ3ASgqYi2pFOhKU6AYCLSnAF3WPs2ynfn4pCUp0C64u6eklYjyJC0PjkOs7hlsdZIQUzhw5BalaCswmvgTRLqGRlCCeVwIf7hLE8w5JShABhyQliIBDkhJEwCFJCSLg/B+6mlP82IdXzwAAAABJRU5ErkJggg==" />
            <span class="text-ocean-green-dark">Setup</span>
          </h1>
          <span v-cloak v-if="state.local && state.local.site" class="navbar-text text-ocean-green-dark ml-auto">
            <template v-if="state.local.site.services && state.local.site.services.php"><strong><a href="javascript:void(0)" @click="state.page = 'phpinfo'">PHP</a>:</strong> {{ state.local.site.services.php.version }} | </template>
            <template v-if="state.local.site.services && state.local.site.services.mysql"><strong>MySQL:</strong> {{ state.local.site.services.mysql.version }} | </template>
            <template v-if="state.local.site.services && state.local.site.services.apache"><strong>Apache:</strong> {{ state.local.site.services.apache.version }} | </template>
            <template v-if="state.local.site.services && state.local.site.services.nginx"><strong>NGINX:</strong> {{ state.local.site.services.nginx.version }} | </template>
            <template v-if="state.local.site.services && state.local.site.services.mailhog"><strong><a href="javascript:void(0)" @click="state.page = 'mailhog'">Mailhog</a>:</strong> {{ state.local.site.services.mailhog.version }}</template>
          </span>
        </nav>
        <!-- -->
        <div class="rounded-bottom stats-bar">
          <span class="text-muted" style="font-size: 90%; padding:2px">
            Fix a <a href="https://localwp.com" rel="noopener noreferer" target="_blank">Local</a> By Flywheel WordPress site in just a few clicks.
          </span>
          <span class="ml-auto" v-cloak>
            <span class="mr-4" v-if="state.page === 'process' && state.completed">
              <button type="button" :class="['btn btn-xs', state.step === 'landing' ? 'btn-primary' : 'btn-outline-primary']" @click="state.step = 'landing'">Checks</button>
              <button type="button" :class="['btn btn-xs', state.step === 'database-connection' ? 'btn-primary' : 'btn-outline-primary']" @click="state.step = 'database-connection'">Database Connection</button>
              <button type="button" :class="['btn btn-xs', state.step === 'database-import' ? 'btn-primary' : 'btn-outline-primary']" @click="state.step = 'database-import'">Import Database</button>
              <button type="button" :class="['btn btn-xs', state.step === 'wp-find-replace' ? 'btn-primary' : 'btn-outline-primary']" @click="state.step = 'wp-find-replace'">Search &amp; Replace</button>
            </span>
            <button v-if="['phpinfo', 'mailhog', 'local-login'].includes(state.page)" type="button" class="btn btn-xs btn-secondary" @click="state.page = 'process'">Back</button>
            <button type="button" class="btn btn-xs btn-warning" @click="state.debug = !state.debug">Toggle Debug</button>
          </span>
        </div>
      </header>

      <article v-cloak>
        <!-- section: debug -->
        <section v-if="state.debug">
          <strong>State:</strong>
          <button type="button" class="btn btn-xs btn-primary" @click="state.loading = !state.loading">Toggle Loading</button>
          <br>
          <p class="mb-1">The global object which holds the state of all things.</p>
          <pre class="bg-light rounded border p-2 text-small">{{ state }}</pre>
          <!-- <codemirror v-model="JSON.stringify(state, null, 2)" :options="state.cmOption" @input="() => state = state" class="border rounded"></codemirror> -->
        </section>

        <!-- section: main -->
        <section>
          <main v-if="state.page === 'process'">
            <div class="alert alert-danger" role="alert" v-if="state.error && state.error.message">
              {{ state.error.message }}
              <button type="button" class="close" aria-label="Close" @click="state.error = {}">
                <span aria-hidden="true">&times;</span>
              </button>
            </div>
            <step-landing v-if="state.step === 'landing'"></step-landing>
            <step-database-connection v-if="state.step === 'database-connection'"></step-database-connection>
            <step-database-import v-if="state.step === 'database-import'"></step-database-import>
            <step-wp-find-replace v-if="state.step === 'wp-find-replace'"></step-wp-find-replace>
            <step-complete v-if="state.step === 'complete'"></step-complete>
          </main>
          <main v-if="state.page === 'local-login'">
            <page-local-login></page-local-login>
          </main>
          <main v-if="state.page === 'phpinfo'">
            <page-phpinfo></page-phpinfo>
          </main>
          <main v-if="state.page === 'mailhog'">
            <page-mailhog></page-mailhog>
          </main>
        </section>

        <footer>
          <div class="card mt-3 border-danger" v-if="!state.loading && !['phpinfo', 'mailhog'].includes(state.page)">
            <div class="card-header bg-danger text-white font-weight-bold">Cleardown</div>
            <div class="card-body">
              <p class="card-text">
                When you're up and running, remove the <code>local-setup.php</code> and <code>wp-cli.phar</code> files from the public folder or click the button below.<br>
                <small class="text-muted">Dont worry, if you get in a kerfuffle you can always run <code>bash setup.sh</code> to bring them back in.</small>
              </p>
            </div>
            <div class="card-footer text-muted">
              <button type="button" class="btn btn-danger" @click="cleardown">Remove files</button>
            </div>
          </div>

          <div class="card mt-3" v-if="!state.loading && ['phpinfo', 'mailhog'].includes(state.page)">
            <div class="card-body bg-light rounded">
              <button type="button" class="btn btn-secondary" @click="state.page = 'process'">Go back to process</button>
            </div>
          </div>

          <div class="border bg-light p-2 mt-3 rounded text-center small" v-if="state.local && state.local.site">
            <strong>site id:</strong> <span class="text-muted">{{ state.local.site.id }}</span> |
            <strong>name:</strong> <span class="text-muted">{{ state.local.site.name }}</span> |
            <strong>domain:</strong> <span class="text-muted">{{ state.local.site.domain }}</span><br>
            <strong>Local Version:</strong> <span class="text-muted">{{ state.local.site.localVersion }}</span>
            <hr class="my-2">
            <strong class="text-muted">Created by <a href="https://github.com/lcherone" target="_blank">Lawrence Cherone</a>, with ☕ coffee and code.</strong>
          </div>
        </footer>
      </article>
    </main>
  </div>

  <!-- Step: landing -->
  <template id="step-landing-template">
    <div>
      <template v-if="!state.loading">

        <div class="jumbotron jumbotron-fluid rounded mb-3">
          <div class="container">
            <div class="px-3">
              <h1 class="display-4" v-if="state.local && state.local.profile">
                {{ state.completed ? 'Welcome back' : 'Hello' }},

                <template v-if="!state.local.profile.id || state.local.profile.id === '0'">
                  <a href="javascript:void(0)" class="text-muted font-weight-normal" @click="state.page = 'local-login'">{{ state.local.profile.firstname }}?</a>
                </template>
                <template v-else>{{ state.local.profile.firstname }}!</template>
              </h1>
              <template v-if="!state.completed">
                <p class="lead">
                  This single file app is intended for use with a <a href="https://localwp.com" rel="noopener noreferer" target="_blank">Local</a> By Flywheel instance.
                  It will help you finish the installation of the <code>{{ state.local.site.name }}</code> site after you have cloned or freshly pulled an update from the git repository.
                </p>
                <hr class="my-4">
                <p>
                  Scroll down each section and check and fix any issues, upon first successful setup you will be able to quickly manage each task and more.
                </p>
              </template>
              <template v-else>
                <p class="lead">
                  Scroll down each section and check and fix any issues, as you have setup at least once already you can use the links above to directly get to the function your after.
                </p>
                <p>
                  If you need to start again and go though all steps, <a href="javascript:void(0)" @click="reset">click here</a> to reset things.
                </p>
              </template>

              <p class="text-small">
                Any problems, toggle the debug by clicking the <button type="button" class="btn btn-xs btn-warning" @click="state.debug = !state.debug">Toggle Debug</button> button above, then copy and paste the JSON in to an email and send it to me, it should indicate the issue for a quick fix.
              </p>
            </div>
          </div>
        </div>

        <div class="card" v-if="!state.completed">
          <div class="card-header font-weight-bold">About and Compatibility</div>
          <div class="card-body">
            <p>This script is intended for use only with a <a href="https://localwp.com" rel="noopener noreferer" target="_blank">Local</a> By Flywheel instance, to help finish the installation of a WordPress site when the WordPress files have been pulled from a git repository.</p>
            <div id="accordion">
              <a href="javascript:void(0)" class="collapsed readmore pointer" id="headingOne" data-toggle="collapse" data-target="#collapseOne" aria-expanded="true" aria-controls="collapseOne"></a>
              <div id="collapseOne" class="collapse" aria-labelledby="headingOne" data-parent="#accordion">
                <p>The idea is you version control the <code>public</code> and <code>sql</code> directories and symbolic link them to the Local's instance path to replace the <code>public</code> and <code>sql</code> directories.</p>
                <p>It was created because unfortunately simply just dropping in or linking the <code>public</code> and <code>sql</code> directories inside a Local instance path won't work due to it not importing the db import.sql file and running the WP CLI search and replace fix on the database, which is only run when done via the import feature of the app, which expects a zip file.</p>
                <p>Yes, we could always just push the whole exported instance as a zip file to GitHub but that's simply not feasible.</p>
                <p>If you are using this script on anything other than a Local instance then it will not work. This script specificly looks for Local config files, which wont be present on a different WordPress setup.</p>
                <p>It is placed inside the <code>../public</code> directory and expects both <code>../public</code> and <code>../sql</code> directories to exist and be readable and writable, plus some additional PHP functions shown below to run the setup.</p>
              </div>
            </div>
          </div>
        </div>

        <div class="card mt-3">
          <div class="card-header font-weight-bold">Function Checks</div>
          <div class="card-body pb-1">
            <div class="media mb-3 pb-3 border-bottom">
              <div class="media-body">
                <h5 class="mt-0">Function: <code>file_get_contents()</code></h5>
                <div>The PHP function file_get_contents() must not be disabled for this app to run.</div>
                <div v-if="!state.checks.file_get_contants" class="rounded bg-light border p-2 mt-2 text-small">
                  This script is intended for use with <a href="https://localwp.com" rel="noopener noreferer" target="_blank">Local</a> By Flywheel. Which the file_get_contants function is enabled,
                  so to fix this issue you will need to manually make changes to your php.ini file, which you can get help with by searching <a href="https://www.google.com/search?q=how+to+edit+php.ini+disable-functions" rel="noopener noreferer" target="_blank"><code>how to edit php.ini disable-functions</code>.</a>
                </div>
              </div>
              <div :class="[state.checks.file_get_contants ? 'status-good' : 'status-bad']"></div>
            </div>
            <div class="media mb-3 pb-3 border-bottom">
              <div class="media-body">
                <h5 class="mt-0">Function: <code>shell_exec()</code></h5>
                <div>The PHP function shell_exec() must not be disabled for this app to run.</div>
                <div v-if="!state.checks.shell_exec" class="rounded bg-light border p-2 mt-2 text-small">
                  This script is intended for use with <a href="https://localwp.com" rel="noopener noreferer" target="_blank">Local</a> By Flywheel. Which the shell_exec function is enabled,
                  so to fix this issue you will need to manually make changes to your php.ini file, which you can get help with by searching <a href="https://www.google.com/search?q=how+to+edit+php.ini+disable-functions" rel="noopener noreferer" target="_blank"><code>how to edit php.ini disable-functions</code>.</a>
                </div>
              </div>
              <div :class="[state.checks.shell_exec ? 'status-good' : 'status-bad']"></div>
            </div>
            <div class="media mb-3">
              <div class="media-body">
                <h5 class="mt-0">Function: <code>curl_init()</code></h5>
                <div>The PHP function curl_init() must not be disabled for this app to run.</div>
                <div v-if="!state.checks.curl" class="rounded bg-light border p-2 mt-2 text-small">
                  This script is intended for use with <a href="https://localwp.com" rel="noopener noreferer" target="_blank">Local</a> By Flywheel. Which the curl_init function is enabled,
                  so to fix this issue you will need to manually make changes to your php.ini file, which you can get help with by searching <a href="https://www.google.com/search?q=how+to+edit+php.ini+disable-functions" rel="noopener noreferer" target="_blank"><code>how to edit php.ini disable-functions</code>.</a>
                </div>
              </div>
              <div :class="[state.checks.curl ? 'status-good' : 'status-bad']"></div>
            </div>
          </div>
        </div>

        <div class="card mt-3">
          <div class="card-header font-weight-bold">Directory Checks</div>
          <div class="card-body pb-1">
            <div class="media mb-3 pb-3 border-bottom">
              <div class="media-body">
                <h5 class="mt-0">Directory (exists): <code>../public</code></h5>
                <div>The ../public folder which contains the WordPress installation must exist.</div>
              </div>
              <div :class="[state.checks.public_exists ? 'status-good' : 'status-bad']"></div>
            </div>
            <div class="media mb-3 pb-3 border-bottom">
              <div class="media-body">
                <h5 class="mt-0">Directory (readable): <code>../public</code></h5>
                <div>The ../public folder which contains the WordPress installation must be readable.</div>
              </div>
              <div :class="[state.checks.public_readable ? 'status-good' : 'status-bad']"></div>
            </div>
            <div class="media mb-3">
              <div class="media-body">
                <h5 class="mt-0">Directory (writable): <code>../public</code></h5>
                <div>The ../public folder which contains the WordPress installation must be writable.</div>
              </div>
              <div :class="[state.checks.public_writable ? 'status-good' : 'status-bad']"></div>
            </div>
          </div>
        </div>

        <div class="card mt-3">
          <div class="card-header font-weight-bold">WordPress Config Checks</div>
          <div class="card-body pb-1">
            <div class="media mb-3 pb-3 border-bottom">
              <div class="media-body">
                <h5 class="mt-0">File (exists): <code>../public/wp-config.php</code></h5>
                <div>The ../public/wp-config.php file must exist.</div>
              </div>
              <div :class="[state.checks.wp_config_exists ? 'status-good' : 'status-bad']"></div>
            </div>
            <div class="media mb-3 pb-3 border-bottom">
              <div class="media-body">
                <h5 class="mt-0">File (readable): <code>../public/wp-config.php</code></h5>
                <div>The ../public/wp-config.php file must be readable.</div>
              </div>
              <div :class="[state.checks.wp_config_readable ? 'status-good' : 'status-bad']"></div>
            </div>
            <div class="media mb-3">
              <div class="media-body">
                <h5 class="mt-0">File (writeable): <code>../public/wp-config.php</code></h5>
                <div>The ../public/wp-config.php file must be writeable.</div>
              </div>
              <div :class="[state.checks.wp_config_writeable ? 'status-good' : 'status-bad']"></div>
            </div>
          </div>
        </div>

        <!-- <div class="card mt-3">
          <div class="card-header font-weight-bold">
            WordPress CLI
          </div>
          <div class="card-body pb-1">
            <div class="media mb-3 pb-3 border-bottom">
              <div class="media-body">
                <h5 class="mt-0">File (exists): <code>../public/wp-cli.phar</code></h5>
                <div>The ../public/wp-cli.phar file must exist.</div>
              </div>
              <div class="p-2 mr-3" v-if="!state.checks.wp_cli_exists">
                <button type="button" class="btn btn-sm btn-primary" @click="downloadWPCLI">
                  {{ state.wpCli.downloading ? 'Downloading...' : 'Download WP-CLI' }}
                </button>
              </div>
              <div :class="[state.checks.wp_cli_exists ? 'status-good' : 'status-bad']"></div>
            </div>
            <div class="media mb-3 pb-3 border-bottom">
              <div class="media-body">
                <h5 class="mt-0">File (readable): <code>../public/wp-cli.phar</code></h5>
                <div>The ../public/wp-cli.phar file must be readable.</div>
              </div>
              <div :class="[state.checks.wp_cli_readable ? 'status-good' : 'status-bad']"></div>
            </div>
            <div class="media mb-3">
              <div class="media-body">
                <h5 class="mt-0">File (writeable): <code>../public/wp-cli.phar</code></h5>
                <div>The ../public/wp-cli.phar file must be writeable.</div>
              </div>
              <div :class="[state.checks.wp_cli_writeable ? 'status-good' : 'status-bad']"></div>
            </div>
          </div>
        </div> -->

        <div class="card mt-3">
          <div class="card-header font-weight-bold">
            Database Import File
          </div>
          <div class="card-body pb-1">
            <div class="media mb-3 pb-3 border-bottom">
              <div class="media-body">
                <h5 class="mt-0">File (exists): <code>../sql/local.sql</code></h5>
                <div>The ../sql/local.sql file must exist. (stopping the instance will export the database to ../sql/local.sql)</div>
              </div>
              <div :class="[state.checks.db_import_exists ? 'status-good' : 'status-bad']"></div>
            </div>
            <div class="media mb-3 pb-3 border-bottom">
              <div class="media-body">
                <h5 class="mt-0">File (readable): <code>../sql/local.sql</code></h5>
                <div>The ../sql/local.sql file must be readable.</div>
              </div>
              <div :class="[state.checks.db_import_readable ? 'status-good' : 'status-bad']"></div>
            </div>
            <div class="media mb-3">
              <div class="media-body">
                <h5 class="mt-0">File (writeable): <code>../sql/local.sql</code></h5>
                <div>The ../sql/local.sql file must be writeable.</div>
              </div>
              <div :class="[state.checks.db_import_writeable ? 'status-good' : 'status-bad']"></div>
            </div>
          </div>
        </div>

        <div class="card mt-3">
          <div class="card-header font-weight-bold">
            SSH Entry File (Environment)
          </div>
          <div class="card-body pb-1">

            <template v-if="state.checks.ssh_entry_exists">
              <div class="media mb-3 pb-3 border-bottom">
                <div class="media-body">
                  <h5 class="mt-0">File (exists): <code>{{ state.local.local_config_path }}/ssh-entry/{{ state.local.siteId }}.sh</code></h5>
                  <div>
                    - This file must exist to be able to obtain the correct settings for <code>wp-cli.phar</code> to work.<br>
                    - <span>File <code>../site-shell.sh</code> was created. Use <code>bash site-shell.sh</code> to apply a shell with the Locals environment variables, which will allow you to use <code>wp</code> CLI commands.</span>
                  </div>
                </div>
                <div :class="[state.checks.ssh_entry_exists ? 'status-good' : 'status-bad']"></div>
              </div>
              <div class="media mb-3 pb-3">
                <div class="media-body">
                  <h5 class="mt-0">File (readable): <code>{{ state.local.local_config_path }}/ssh-entry/{{ state.local.siteId }}.sh</code></h5>
                  <div>This file must be readable to be able to obtain the correct settings for <code>wp-cli.phar</code> to work.</div>
                </div>
                <div :class="[state.checks.ssh_entry_readable ? 'status-good' : 'status-bad']"></div>
              </div>
            </template>
            <template v-else>
              <div class="media mb-3 pb-3">
                <div class="media-body">
                  <h5 class="mt-0">File does not exist</h5>
                  <div>
                    <p>The following file <code>{{ state.local.local_config_path }}/ssh-entry/{{ state.local.siteId }}.sh</code> must exist to be able to obtain the correct settings for <code>wp-cli.phar</code> to work.</p>
                    <h5 class="mt-0">To fix:</h5>
                    <p>
                      Open up the Local app and right click the site name in the sites list (the main list of sites), then click "Open Site Shell", this should then generate the missing file.
                    </p>
                    <div>
                      <img class="mt-3 img-fluid" src="https://i.imgur.com/E3vyMVn.gif" alt="" />
                    </div>
                    <div class="mt-3">
                      <button type="button" class="btn btn-sm btn-primary" @click="init(false)">
                        I've that it, check again
                      </button>
                    </div>
                    <small class="text-muted">If you click above and nothing happens then it has not created the file.</small>
                  </div>
                </div>
                <div class="status-bad"></div>
              </div>
            </template>
          </div>
        </div>

        <div class="card mt-3">
          <div class="card-body">
            <p class="card-text">
              If all conditions above have a green checkmark, click the button below.
            </p>
          </div>
          <div class="card-footer text-muted text-right">
            <button type="button" class="btn btn-success" @click="state.step = 'database-connection'" :disabled="!Object.values(state.checks).every(Boolean)">Let's continue!</button>
          </div>
        </div>

      </template>
      <loading v-else></loading>
    </div>

    <!-- <template v-if="errors['wp-config']">
      <div class="alert alert-danger" role="alert">
        {{ errors['wp-config'] }}
      </div>
      <div class="card border-bottom-0">
        <div class="card-header font-weight-bold bg-info-light">Ways to fix:</div>
        <div class="card-body p-0">
          <div id="accordion">
            <div class="card border-0">
              <div class="card-header pointer" id="headingOne" data-toggle="collapse" data-target="#collapseOne" aria-expanded="true" aria-controls="collapseOne">
                1. Run <code>bash setup.sh</code> again
              </div>
              <div id="collapseOne" class="collapse show border-bottom" aria-labelledby="headingOne" data-parent="#accordion">
                <div class="card-body">
                  <p>
                    If you are accessing this file its likely you have already run <code>bash setup.sh</code>, but the folder may not have been writable by the user when you did,
                    which you would have then got errors which you perhaps did not notice and fix.
                  </p>
                  <p>
                    You must make all files readable by changing their permissions so the user which runs Apache2 (who is: {{ defaults.www_user }}) can read them, so do the following.
                  </p>
                  <ul class="mt-2">
                    <li>
                      Jump on the command line and run: <code>sudo chown {{ defaults.www_user }}:{{ defaults.www_user }} ./public -Rf</code>
                      <ul>
                        <li>
                          <small>It must be done manually because we are using sudo (super user do) and thus you must enter your password.</small>
                        </li>
                      </ul>
                    </li>
                  </ul>
                  After doing so,<br>
                  <p>Run <code>bash setup.sh</code> again then refresh this page, if the problem persists try something below.
                </div>
              </div>
            </div>
            <div class="card border-0">
              <div class="card-header pointer" id="headingTwo" data-toggle="collapse" data-target="#collapseTwo" aria-expanded="true" aria-controls="collapseTwo">
                2. Copy <code>./setup/wp-config.php</code> to <code>./public/wp-config.php</code>
              </div>
              <div id="collapseTwo" class="collapse border-bottom" aria-labelledby="headingTwo" data-parent="#accordion">
                <div class="card-body">
                  Look inside the <code>./public</code> folder for <code>wp-config.php</code>, if it exists then its not readable, you must make it readable by changing its permissions so the user which runs Apache2 (who is: {{ defaults.www_user }}) can read it, you may also need to do this for all files so do the following.<br>
                  <ul class="mt-2">
                    <li>
                      Jump on the command line and run: <code>sudo chown {{ defaults.www_user }}:{{ defaults.www_user }} ./public -Rf</code>
                      <ul>
                        <li>
                          <small>It must be done manually because we are using sudo (super user do) and thus you must enter your password.</small>
                        </li>
                      </ul>
                    </li>
                  </ul>
                  If the file dose not exist, then either <a href="javascript:void(0)" data-toggle="collapse" data-target="#collapseOne">do #1</a> or manually move the file from ./setup/wp-conf.php to ./public/wp-config.php
                </div>
              </div>
            </div>
          </div>
        </div>
      </div>
      <div class="card mt-3">
        <div class="card-header">I've fixed it</div>
        <div class="card-body">
          <p class="card-text">
            If you have fixed the above issue, click the button below to continue.
          </p>
        </div>
        <div class="card-footer text-muted">
          <button type="button" class="btn btn-success" @click="reload()">Yup, have fixed it, let's continue!</button>
        </div>
      </div>
    </template> -->
  </template>

  <!-- Step: database-connection -->
  <template id="step-database-connection-template">
    <div>
      <template v-if="!state.loading">

        <div class="card mt-3">
          <div class="card-header font-weight-bold">Database Connection</div>
          <div class="card-body pb-1">
            <div class="form-group">
              <label for="input-db-hostname">Hostname</label>
              <input type="text" v-model="state.database.details.DB_HOST" class="form-control" id="input-db-hostname" placeholder="Enter database server hostname, e.g: localhost">
              <small class="form-text text-muted">Local Default: localhost</small>
            </div>
            <div class="form-group">
              <label for="input-db-database_name">Database Name</label>
              <input type="text" v-model="state.database.details.DB_NAME" class="form-control" id="input-db-database_name" placeholder="Enter database server database name, e.g: local">
              <small class="form-text text-muted">Local Default: local</small>
            </div>
            <div class="form-group">
              <label for="input-db-database_user">Database User</label>
              <input type="text" v-model="state.database.details.DB_USER" class="form-control" id="input-db-database_user" placeholder="Enter database server username, e.g: root">
              <small class="form-text text-muted">Local Default: root</small>
            </div>
            <div class="form-group">
              <label for="input-db-database_password">Database Password</label>
              <input type="text" v-model="state.database.details.DB_PASSWORD" class="form-control" id="input-db-database_password" placeholder="Enter database server user password, e.g: root">
              <small class="form-text text-muted">Local Default: root</small>
            </div>
            <div class="form-group">
              <label for="input-db-socket">Socket Path:</label>
              <input type="text" v-model="state.local['mysqli.default_socket']" readonly class="form-control-plaintext" id="input-db-socket">
            </div>
            <div class="form-group">
              <label for="input-db-import-file-path">SQL Import file path:</label>
              <input type="text" :value="state.local.sql_path + '/local.sql'" readonly class="form-control-plaintext" id="input-db-import-file-path">
              <small class="form-text text-muted">The Local MySQL dump file, upon next step this file will be imported.</small>
            </div>
            <div class="media p-3 border bg-light rounded mb-3">
              <div class="media-body">
                <h5 class="mt-0">
                  Connection Status:
                  <span class="badge badge-pill badge-success" v-if="state.database.connectable">Connected</span>
                  <span class="badge badge-pill badge-danger" v-else>Not Connected</span>
                </h5>
                <div v-if="state.database.connectable">Using the above connection credentials, we connected to the database successfully.</div>
                <div v-else>
                  <div>Using the above connection credentials, we could not connect to the database.</div>
                  <div class="mt-2">
                    <strong>Database server response error:</strong><br>
                    <span class="text-danger">{{ state.database.message }}</span>
                  </div>
                </div>
              </div>
              <div :class="['border', 'rounded', state.database.connectable ? 'status-good' : 'status-bad']"></div>
            </div>
          </div>
        </div>

        <div class="card mt-3">
          <div class="card-body">
            <p class="card-text">
              If successfully connected to the database, click the button below to save the details in <code>wp-config.php</code> and continue to the next step. If not, you must first fix the connection error.
            </p>
          </div>
          <div class="card-footer text-muted">
            <button type="button" class="btn btn-secondary float-left" @click="state.step = 'landing'">Go Back</button>
            <button type="button" class="btn btn-success float-right" @click="next()" :disabled="!state.database.connectable">
              {{ state.database.saving ? 'Saving..' : 'Let\'s continue!' }}
            </button>
          </div>
        </div>
      </template>
      <loading v-else></loading>
    </div>
  </template>

  <!-- Step: database-import -->
  <template id="step-database-import-template">
    <div>
      <template v-if="!state.loading">
        <div class="card mt-3">
          <div class="card-header font-weight-bold">Import Database</div>
          <div class="card-body pb-1">
            <p v-if="!state.database.import.running && !state.database.import.success">
              Click the import button below to attempt to import the following database export file: <code>{{ state.local.sql_path + '/local.sql' }}</code>
            </p>
            <div class="media mb-3 pb-3" v-if="!state.database.import.running && state.database.import.success">
              <div class="media-body">
                <h5 class="mt-0">Database imported successfully!</h5>
                <div>The database file was successfully imported, you can now continue to the next step.</div>
              </div>
              <div class="status-good"></div>
            </div>
            <div v-if="state.database.import.running" class="mt-2 mb-4">
              <p class="text-center text-muted">Importing database, please wait...</p>
              <div class="d-flex justify-content-center">
                <div class="spinner-grow text-center" role="status"></div>
              </div>
            </div>
            <div v-if="state.database.import.log !== ''">
              <div class="media mb-3">
                <div class="media-body">
                  <h5 class="mt-0 mb-3">
                    Import Error Log
                  </h5>
                  <pre class="bg-light rounded border p-2 text-small">{{ state.database.import.log }}</pre>
                </div>
              </div>
            </div>
          </div>
          <div class="card-footer text-muted">
            <button v-if="!state.database.import.running" type="button" class="btn btn-secondary float-left" @click="state.step = 'database-connection'">Go Back</button>
            <button v-if="!state.database.import.success" type="button" class="btn btn-success float-right" @click="importDatabase" :disabled="state.database.import.running">
              {{ state.database.import.running ? 'Importing database...' : 'Import database'}}
            </button>
            <button v-if="state.database.import.success" type="button" class="btn btn-success float-right" @click="state.step = 'wp-find-replace'">
              Let's continue!
            </button>
          </div>
        </div>
      </template>
      <loading v-else></loading>
    </div>
  </template>

  <!-- Step: wp-find-replace -->
  <template id="step-wp-find-replace-template">
    <div>
      <template v-if="!state.loading">

        <div class="card mt-3">
          <div class="card-header font-weight-bold">Search and Replace</div>
          <div class="card-body pb-1">
            <div v-if="!state.database.findReplace.running && !state.database.findReplace.success">
              <p>Run wp-cli search and replace function against the database to fix the site URL's.</p>
              <p>
                Note that the WordPress search and replace CLI tool does not specifically target only URL's,
                so care must be taken to enter the correct replacement value, else you may break the database and need to re-import.
              </p>
              <div class="form-group">
                <label for="input-db-find-replace-from">Find</label>
                <input type="text" v-model="state.database.findReplace.from" class="form-control" id="input-db-find-replace-from" placeholder="Enter string to find e.g: http://old.local">
                <small class="form-text text-muted">Detected by doing an actual query on the database, should generally not need changing.</small>
              </div>
              <div class="form-group">
                <label for="input-db-find-replace-to">Replace</label>
                <input type="text" v-model="state.database.findReplace.to" class="form-control" id="input-db-find-replace-to" placeholder="Enter string to replace e.g: http://new.local">
                <small class="form-text text-muted" v-if="!state.database.findReplace.to.includes(state.local.site.domain)">Populated with the current page URL/Origin, this may need changing if you will access the site using a different URL, like: http://{{ state.local.site.domain }}</small>
                <small class="form-text text-muted" v-else>Populated with the current page URL/Origin, this may need changing if you will access the site using a different URL, like:
                  http://localhost:<span v-if="state.local.site.services.apache && state.local.site.services.apache.ports.HTTP[0]">{{ state.local.site.services.apache.ports.HTTP[0] }}<span>
                      <span v-else-if="state.local.site.services.nginx && state.local.site.services.nginx.ports.HTTP[0]">{{ state.local.site.services.nginx.ports.HTTP[0] }}<span>
                </small>
              </div>
              <div class="media p-3 border bg-light rounded mb-3" v-if="state.database.findReplace.from === state.database.findReplace.to">
                <div class="media-body">
                  <h5 class="mt-0">
                    Already replaced?
                  </h5>
                  <div>It seems the result of the query to fetch the current value is the same value as the current url. If you have already done this step before on the same database import then there is no need to do it again.</div>
                </div>
              </div>
            </div>
            <div v-if="!state.database.findReplace.running && state.database.findReplace.success">
              <div class="media mb-3 pb-3">
                <div class="media-body">
                  <h5 class="mt-0">Search and replace complete!</h5>
                  <div>The search and replace process was successfully run, below is the log of what was replaced, you can now continue to the next step.</div>
                </div>
                <div class="status-good"></div>
              </div>
            </div>
            <div v-if="state.database.findReplace.running" class="mt-2 mb-4">
              <p class="text-center text-muted">Replacing strings in database, please wait...</p>
              <div class="d-flex justify-content-center">
                <div class="spinner-grow text-center" role="status"></div>
              </div>
            </div>
            <div class="mt-2 mb-4" v-if="state.database.findReplace.log !== ''">
              <div class="media mb-3">
                <div class="media-body">
                  <h5 class="mt-0 mb-3">
                    Log
                  </h5>
                  <pre class="bg-light rounded border p-2 text-small">{{ state.database.findReplace.log }}</pre>
                </div>
              </div>
            </div>
          </div>
          <div class="card-footer text-muted">
            <button type="button" class="btn btn-secondary float-left" @click="() => {state.step = 'database-import'; state.database.findReplace.success = false}">Go Back</button>

            <button v-if="state.database.findReplace.success || state.database.findReplace.from === state.database.findReplace.to" type="button" class="btn btn-success float-right ml-3" @click="state.step = 'complete'">
              Complete!
            </button>
            <button v-if="!state.database.findReplace.success" type="button" class="btn btn-success float-right" @click="findReplace" :disabled="state.database.findReplace.running || state.database.findReplace.from === state.database.findReplace.to">
              {{ state.database.findReplace.running ? 'Processing...' : 'Run Find and Replace' }}
            </button>
          </div>
        </div>
      </template>
      <loading v-else></loading>
    </div>
  </template>

  <!-- Step: complete -->
  <template id="step-complete-template">
    <div>
      <template v-if="!state.loading">
        <div class="card">
          <div class="card-header font-weight-bold">Setup complete</div>
          <div class="card-body">
            <p>
              <a :href="state.database.findReplace.to" target="_blank">Click here</a> to view the site.
            </p>
            <p class="text-small">
              If for some reason the site is still not working, toggle the debug by clicking the <button type="button" class="btn btn-xs btn-warning" @click="state.debug = !state.debug">Toggle Debug</button> button above, then copy and paste the JSON in to an email and send it to me, it should indicate the issue for a quick fix.
            </p>
          </div>
          <div class="card-footer text-muted">
            <button type="button" class="btn btn-secondary float-left" @click="reload">Go back to start</button>
          </div>
        </div>
      </template>
      <loading v-else></loading>
    </div>
  </template>

  <!-- Page: page-local-login-template -->
  <template id="page-local-login-template">
    <div>
      <template v-if="!state.loading">
        <div class="jumbotron jumbotron-fluid bg-danger rounded mb-3">
          <div class="container">
            <div class="px-3">
              <h1 class="display-4 text-white">
                Local Login
              </h1>
              <p class="lead text-light">
                For some personalisation, login or sign up to Local from within the Local app.
              </p>
              <hr class="my-4">
              <p class="text-white">
                Click the profile icon in the App, and then Click "LOG IN TO LOCAL" and follow the instructions.
              </p>
              <div class="text-center">
                <img class="mt-3 img-fluid" src="https://i.imgur.com/LHiBRqz.gif" alt="" />
              </div>
            </div>
          </div>
        </div>
        <div class="card">
          <div class="card-body">
            <button type="button" class="btn btn-success float-right" @click="reload">
              OK, I've signed up and logged in!
            </button>
          </div>
        </div>
      </template>
      <loading v-else></loading>
    </div>
  </template>

  <!-- Page: phpinfo -->
  <template id="page-phpinfo-template">
    <div>
      <template v-if="!state.loading">
        <div class="card">
          <div class="card-header font-weight-bold">PHP Info</div>
          <div class="card-body p-0">
            <iframe ref="phpinfo_iframe" frameborder="0" class="w-100"></iframe>
          </div>
        </div>
      </template>
      <loading v-else></loading>
    </div>
  </template>

  <!-- Page: mailhog -->
  <template id="page-mailhog-template">
    <div>
      <template v-if="!state.loading">
        <div class="card" v-if="state.local && state.local.site && state.local.site.services && state.local.site.services.mailhog && state.local.site.services.mailhog">
          <div class="card-body p-0">
            <div class="embed-responsive embed-responsive-16by9">
              <iframe class="embed-responsive-item" :src="`http://localhost:${state.local.site.services.mailhog.ports.WEB[0]}`"></iframe>
            </div>
          </div>
        </div>
        <div class="card" v-else>
          <div class="card-body p-0">
            Mailhog is not running or this script cannot get the Local configuration.
          </div>
        </div>
      </template>
      <loading v-else></loading>
    </div>
  </template>

  <!-- loading-template -->
  <template id="loading-template">
    <div>
      <div class="card">
        <div class="card-header font-weight-bold">Loading...</div>
        <div class="card-body">
          <div class="d-flex justify-content-center">
            <div class="spinner-grow text-center" role="status"></div>
          </div>
          <slot></slot>
        </div>
      </div>
    </div>
  </template>

  <!-- <template id="step-landing-template">
    <div class="step-landing"> -->

  <!-- <template v-if="errors['wp-config']">
        <div class="alert alert-danger" role="alert">
          {{ errors['wp-config'] }}
        </div>
        <div class="card border-bottom-0">
          <div class="card-header font-weight-bold bg-info-light">Ways to fix:</div>
          <div class="card-body p-0">
            <div id="accordion">
              <div class="card border-0">
                <div class="card-header pointer" id="headingOne" data-toggle="collapse" data-target="#collapseOne" aria-expanded="true" aria-controls="collapseOne">
                  1. Run <code>bash setup.sh</code> again
                </div>
                <div id="collapseOne" class="collapse show border-bottom" aria-labelledby="headingOne" data-parent="#accordion">
                  <div class="card-body">
                    <p>
                      If you are accessing this file its likely you have already run <code>bash setup.sh</code>, but the folder may not have been writable by the user when you did,
                      which you would have then got errors which you perhaps did not notice and fix.
                    </p>
                    <p>
                      You must make all files readable by changing their permissions so the user which runs Apache2 (who is: {{ defaults.www_user }}) can read them, so do the following.
                    </p>
                    <ul class="mt-2">
                      <li>
                        Jump on the command line and run: <code>sudo chown {{ defaults.www_user }}:{{ defaults.www_user }} ./public -Rf</code>
                        <ul>
                          <li>
                            <small>It must be done manually because we are using sudo (super user do) and thus you must enter your password.</small>
                          </li>
                        </ul>
                      </li>
                    </ul>
                    After doing so,<br>
                    <p>Run <code>bash setup.sh</code> again then refresh this page, if the problem persists try something below.
                  </div>
                </div>
              </div>
              <div class="card border-0">
                <div class="card-header pointer" id="headingTwo" data-toggle="collapse" data-target="#collapseTwo" aria-expanded="true" aria-controls="collapseTwo">
                  2. Copy <code>./setup/wp-config.php</code> to <code>./public/wp-config.php</code>
                </div>
                <div id="collapseTwo" class="collapse border-bottom" aria-labelledby="headingTwo" data-parent="#accordion">
                  <div class="card-body">
                    Look inside the <code>./public</code> folder for <code>wp-config.php</code>, if it exists then its not readable, you must make it readable by changing its permissions so the user which runs Apache2 (who is: {{ defaults.www_user }}) can read it, you may also need to do this for all files so do the following.<br>
                    <ul class="mt-2">
                      <li>
                        Jump on the command line and run: <code>sudo chown {{ defaults.www_user }}:{{ defaults.www_user }} ./public -Rf</code>
                        <ul>
                          <li>
                            <small>It must be done manually because we are using sudo (super user do) and thus you must enter your password.</small>
                          </li>
                        </ul>
                      </li>
                    </ul>
                    If the file dose not exist, then either <a href="javascript:void(0)" data-toggle="collapse" data-target="#collapseOne">do #1</a> or manually move the file from ./setup/wp-conf.php to ./public/wp-config.php
                  </div>
                </div>
              </div>
            </div>
          </div>
        </div>
        <div class="card mt-3">
          <div class="card-header">I've fixed it</div>
          <div class="card-body">
            <p class="card-text">
              If you have fixed the above issue, click the button below to continue.
            </p>
          </div>
          <div class="card-footer text-muted">
            <button type="button" class="btn btn-success" @click="reload()">Yup, have fixed it, let's continue!</button>
          </div>
        </div>
      </template>
      <template v-else>
        <div class="card">
          <div class="card-header">Database</div>
          <div class="card-body pb-0">
            <h5 class="card-title">Details</h5>
            <h6 class="card-subtitle mb-2 text-muted">Current database connection details.</h6>
            <div class="pt-2">
              <div class="form-group">
                <label for="input-db-hostname">Hostname</label>
                <input type="text" v-model="form.values.DB_HOST" class="form-control" id="input-db-hostname" placeholder="Enter database server hostname, e.g: localhost">
                <small class="form-text text-muted">We'll never share your email with anyone else.</small>
              </div>
              <div class="form-group">
                <label for="input-db-database_name">Database Name</label>
                <input type="text" v-model="form.values.DB_NAME" class="form-control" id="input-db-database_name" placeholder="Enter database server database name, e.g: local">
                <small class="form-text text-muted">We'll never share your email with anyone else.</small>
              </div>
              <div class="form-group">
                <label for="input-db-database_user">Database User</label>
                <input type="text" v-model="form.values.DB_USER" class="form-control" id="input-db-database_user" placeholder="Enter database server user name, e.g: root">
                <small class="form-text text-muted">We'll never share your email with anyone else.</small>
              </div>
              <div class="form-group">
                <label for="input-db-database_password">Database Password</label>
                <input type="text" v-model="form.values.DB_PASSWORD" class="form-control" id="input-db-database_password" placeholder="Enter database server user password, e.g: root">
                <small class="form-text text-muted">We'll never share your email with anyone else.</small>
              </div>
            </div>
          </div>
          <div class="card-body pt-0">
            <h5 class="card-title">Connection Status</h5>
            <h6 class="card-subtitle mb-2 text-muted">The current database connection status is:</h6>
            <p class="card-text">
              <span v-if="connectionStatus" class="text-success">Good</span>
              <span v-else class="text-danger">Could not connect to database, change connection details.</span>
            </p>
          </div>
        </div>
        <div class="card mt-3">
          <div class="card-header">Continue to Database Import</div>
          <div class="card-body">
            <p class="card-text">
              When you are ready, click the button below to continue to import the database.
            </p>
          </div>
          <div class="card-footer text-muted">
            <button type="button" class="btn btn-success" @click="reload()">I'm ready, let's continue!</button>
          </div>
        </div>
      </template> -->

  <!-- <form>
        <div class="form-group">
          <label for="exampleInputEmail1">Email address</label>
          <input type="email" class="form-control" id="exampleInputEmail1" aria-describedby="emailHelp" placeholder="Enter email">
          <small id="emailHelp" class="form-text text-muted">We'll never share your email with anyone else.</small>
        </div>
        <div class="form-group">
          <label for="exampleInputPassword1">Password</label>
          <input type="password" class="form-control" id="exampleInputPassword1" placeholder="Password">
        </div>
        <div class="form-check">
          <input type="checkbox" class="form-check-input" id="exampleCheck1">
          <label class="form-check-label" for="exampleCheck1">Check me out</label>
        </div>
        <button type="button" class="btn btn-primary" @click="submit()">Submit</button>
      </form> -->

  <!-- <div class="card mt-3">
        <div class="card-header">Upon Completion</div>
        <div class="card-body">
          <p class="card-text">
            When your all good and done, you should remove the <code>local-setup.php</code> and <code>wp-cli.phar</code> files.<br>
            <small>Dont worry, if you get in a kerfuffle again you can run <code>bash setup.sh</code> to bring them back in.</small>
          </p>
        </div>
        <div class="card-footer text-muted">
          <button type="button" class="btn btn-danger">Remove files</button>
        </div>
      </div>

      <div class="border bg-light p-2 mt-3 rounded text-center small">
        By Lawrence Cherone, <a href="https://github.com/lcherone" target="_blank">https://github.com/lcherone</a>
      </div>
    </div>
  </template> -->

  <script src="https://cdnjs.cloudflare.com/ajax/libs/jquery/3.6.0/jquery.min.js" integrity="sha512-894YE6QWD5I59HgZOGReFYm4dnWc1Qt5NtvYSaNcOP+u1T9qYdvdihz0PPSiiqn/+/3e7Jo4EaG7TubfWGUrMQ==" crossorigin="anonymous" referrerpolicy="no-referrer"></script>
  <script src="https://cdnjs.cloudflare.com/ajax/libs/popper.js/2.9.3/umd/popper.min.js" integrity="sha512-XLo6bQe08irJObCc86rFEKQdcFYbGGIHVXcfMsxpbvF8ompmd1SNJjqVY5hmjQ01Ts0UmmSQGfqpt3fGjm6pGA==" crossorigin="anonymous" referrerpolicy="no-referrer"></script>
  <script src="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/4.6.0/js/bootstrap.min.js" integrity="sha512-XKa9Hemdy1Ui3KSGgJdgMyYlUg1gM+QhL6cnlyTe2qzMCYm4nAZ1PsVerQzTTXzonUR+dmswHqgJPuwCq1MaAg==" crossorigin="anonymous" referrerpolicy="no-referrer"></script>
  <script src="https://cdnjs.cloudflare.com/ajax/libs/vue/2.6.14/vue.min.js" integrity="sha512-XdUZ5nrNkVySQBnnM5vzDqHai823Spoq1W3pJoQwomQja+o4Nw0Ew1ppxo5bhF2vMug6sfibhKWcNJsG8Vj9tg==" crossorigin="anonymous" referrerpolicy="no-referrer"></script>

  <script src="https://cdnjs.cloudflare.com/ajax/libs/codemirror/5.32.0/codemirror.min.js"></script>
  <script src="https://cdn.jsdelivr.net/npm/vue-codemirror@4.0.0/dist/vue-codemirror.js"></script>

  <script>
    Vue.use(VueCodemirror)

    const state = Vue.observable({
      debug: false,
      loading: true,
      page: 'process',
      step: 'landing',
      completed: localStorage.completed || false,
      error: {},
      checks: {},
      local: {},
      wpCli: {
        downloading: false
      },
      database: {
        connectable: false,
        saving: false,
        message: '',
        code: 0,
        details: {
          DB_NAME: "",
          DB_USER: "",
          DB_PASSWORD: "",
          DB_HOST: ""
        },
        import: {
          running: false,
          success: false,
          log: ''
        },
        findReplace: {
          running: false,
          success: false,
          from: '',
          to: window.location.origin || '',
          log: ''
        }
      },
      cmOption: {
        tabSize: 4,
        lineNumbers: true,
        theme: "ttcn"
      }
    })

    const mixin = {
      data: () => ({
        state
      }),
      methods: {
        api(action, data) {
          return $.ajax({
            type: "POST",
            url: "local-setup.php",
            headers: {
              'X-Requested-With': 'XMLHttpRequest'
            },
            contentType: 'application/json;charset=UTF-8',
            dataType: 'json',
            data: JSON.stringify({
              action,
              data
            })
          })
        },
        do(action, data) {
          data = data || []
          return this.api({
            'do': action
          }, data)
        },
        reload() {
          window.location.reload()
        },
        reset() {
          localStorage.removeItem('completed')
          this.reload()
        },
        cleardown() {
          this.do('cleardown').then(response => {
            window.location = window.location.origin
          })
        },
        scrollTop() {
          window.scrollTo(0, 0)
        },
        getLocal() {
          this.do('local-config').then(response => {
            this.state.local = response
          }).always(() => {
            this.state.loading = false
          })
        }
      }
    }

    const components = {
      /**
       * 
       */
      'loading': {
        template: '#loading-template'
      },
      /**
       * 
       */
      'step-landing': {
        template: '#step-landing-template',
        mixins: [mixin],
        created() {
          this.state.loading = true
        },
        mounted() {
          this.$nextTick(this.init)
        },
        methods: {
          init(scrollTop) {
            this.getLocal()

            this.do('landing-checks').then(response => {
              if (response.error) {
                this.state.error = response.error
              }
              if (response.checks) {
                this.state.checks = response.checks
              }
            }).always(() => {
              this.state.loading = false
              if (scrollTop !== false) this.$nextTick(this.scrollTop)
            })
          },
          downloadWPCLI() {
            this.state.wpCli.downloading = true
            this.do('download-wp-cli').then(response => {
              this.init(false)
            }).always(() => {
              this.state.wpCli.downloading = false
              this.state.loading = false
            })
          }
        }
      },
      /**
       * 
       */
      'step-database-connection': {
        template: '#step-database-connection-template',
        mixins: [mixin],
        watch: {
          'state.database.details': {
            handler: function(value) {
              if (value) this.checkConnection(value)
            },
            deep: true
          }
        },
        created() {
          this.state.loading = true
        },
        mounted() {
          this.$nextTick(this.init)
        },
        methods: {
          init() {
            this.do('database-connection-details').then(response => {
              this.$set(this.state.database, 'details', response)
              this.checkConnection(response)
            }).always(() => {
              this.state.loading = false
              this.$nextTick(this.scrollTop)
            })
          },
          checkConnection(details) {
            this.do('database-connection-check', details).then(response => {
              if (response.error) {
                this.state.error = response.error
              } else if (response.errors) {
                this.state.errors = response.errors
              } else if (typeof response.status !== 'undefined') {
                this.$set(this.state.database, 'connectable', response.status || false)
                this.$set(this.state.database, 'message', response.message || '')
                this.$set(this.state.database, 'code', response.code || 0)
              }
            }).always(() => {
              this.state.loading = false
            })
          },
          next() {
            if (!this.state.database.connectable) return;

            this.state.database.saving = true

            this.do('database-connection-details-save', this.state.database.details).then(response => {
              this.state.step = 'database-import'
            }).always(() => {
              this.state.database.saving = false
              this.state.loading = false
            })
          }
        }
      },
      /**
       * 
       */
      'step-database-import': {
        template: '#step-database-import-template',
        mixins: [mixin],
        created() {
          this.state.loading = true
        },
        mounted() {
          this.$nextTick(function() {
            this.state.loading = false
            if (this.state.database.details.DB_NAME === '') {
              this.do('database-connection-details').then(response => {
                this.$set(this.state.database, 'details', response)
                this.checkConnection(response)
              }).always(() => {
                this.state.loading = false
                this.scrollTop()
              })
            }
          })
        },
        methods: {
          checkConnection(details) {
            this.do('database-connection-check', details).then(response => {
              if (response.error) {
                this.state.error = response.error
              } else if (response.errors) {
                this.state.errors = response.errors
              } else if (typeof response.status !== 'undefined') {
                this.$set(this.state.database, 'connectable', response.status || false)
                this.$set(this.state.database, 'message', response.message || '')
                this.$set(this.state.database, 'code', response.code || 0)
              }
            }).always(() => {
              this.state.loading = false
            })
          },
          importDatabase() {
            this.state.database.import.running = true

            this.do('database-import').then(response => {
              if (response === true) {
                this.state.database.import.log = ''
                this.state.database.import.success = true
              } else {
                this.state.database.import.log = response
                this.state.error = {
                  message: 'Database import failed!'
                }
              }
            }).always(() => {
              this.state.database.import.running = false
            })
          }
        }
      },
      /**
       * 
       */
      'step-wp-find-replace': {
        template: '#step-wp-find-replace-template',
        mixins: [mixin],
        created() {
          this.state.loading = true
        },
        mounted() {
          this.$nextTick(this.getFrom)
        },
        methods: {
          getFrom() {
            this.do('wp-find-replace-get-from').then(response => {
              this.state.database.findReplace.from = response
            }).always(() => {
              this.state.loading = false
              this.$nextTick(this.scrollTop)
            })
          },
          findReplace() {
            this.state.database.findReplace.running = true

            this.do('wp-find-replace', this.state.database.findReplace).then(response => {
              this.state.database.findReplace.success = response.status
              this.state.database.findReplace.log = response.log
            }).always(() => {
              this.state.database.findReplace.running = false
            })
          }
        }
      },
      /**
       * 
       */
      'step-complete': {
        template: '#step-complete-template',
        mixins: [mixin],
        created() {
          this.state.loading = true
        },
        mounted() {
          this.$nextTick(function() {
            localStorage.completed = true
            this.state.loading = false
            this.$nextTick(this.scrollTop)
          })
        },
        methods: {

        }
      },
      /**
       * 
       */
      'page-local-login': {
        template: '#page-local-login-template',
        mixins: [mixin],
        created() {
          this.state.loading = true
        },
        mounted() {
          this.$nextTick(this.init)
        },
        methods: {
          init() {
            this.state.loading = false
          }
        }
      },
      /**
       * 
       */
      'page-phpinfo': {
        template: '#page-phpinfo-template',
        mixins: [mixin],
        data() {
          return {
            phpinfo: ''
          }
        },
        created() {
          this.state.loading = true
        },
        mounted() {
          this.$nextTick(this.init)
        },
        methods: {
          init() {
            this.do('phpinfo').then(response => {

              response = response.replace(/href="http:\/\/www.php.net\/"/g, 'href="javascript:void(0)"')

              this.state.loading = false

              this.$nextTick(function() {
                const iframe = this.$refs['phpinfo_iframe']
                const blob = new Blob([response], {
                  type: "text/html; charset=utf-8"
                })

                iframe.onload = function() {
                  iframe.style.height = 0;
                  iframe.style.height = iframe.contentWindow.document.documentElement.scrollHeight + 'px'
                }

                iframe.src = URL.createObjectURL(blob)
              })
            }).always(() => {
              this.$nextTick(this.scrollTop)
            })
          }
        }
      },
      /**
       * 
       */
      'page-mailhog': {
        template: '#page-mailhog-template',
        mixins: [mixin],
        created() {
          this.state.loading = true
        },
        mounted() {
          this.$nextTick(this.init)
        },
        methods: {
          init() {
            this.state.loading = false
          }
        }
      }
    }

    Vue.component('loading', components.loading)

    //
    new Vue({
      el: '#app',
      components,
      mixins: [mixin]
    });
  </script>
</body>

</html>