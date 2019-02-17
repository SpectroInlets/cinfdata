<html>
<body>

<H1>Cinfdata Server Status</H1>

<H2>PhP status</H2>
<?php
    # Define standard texts
    $not_found = "<span style=\"color:#FF0000;font-weight:bold\">Not Found</span>";
    $ok = "<span style=\"color:#04B404;font-weight:bold\">OK</span>";

    echo("<p>Php version:" . phpversion() . "</p>");

    #$yaml_support = function_exists("yaml_parse_file") ? $ok  : $not_found;
    #echo("<p>YAML support: " . $yaml_support . "</p>");
?>

<H2>MySQL connection</H2>


<?php
    
/*     $mysqli_support = class_exists("mysqli") ? $ok  : $not_found; */
/* echo("<p>MySQLi module installed: " . $mysqli_support . "</p>"); */

/* $user = "cinf_reader"; */
/* $sitesettings = yaml_parse_file(dirname(__FILE__) . "/../sitesettings.yaml"); */


/* /\* attempt mysql connections *\/ */
/* function find_connections(){ */
/*     global $sitesettings; */
/*     $user = "cinf_reader"; */

/*     echo("<p>Attempt direct connection</p>"); */
/*     $mysqli = new mysqli($host=$sitesettings["db_hostname"], $user, $user, $sitesettings["db_name"], $port=$sitesettings["db_port"]); */

/*     if (mysqli_connect_error()) { */
/*     die('<br>mysqli Connect Error (' . mysqli_connect_errno() . ') ' */
/*         . mysqli_connect_error()); */
/* } else { */
    
/*     return $sitesettings["db_hostname"] . ":" . $sitesettings["db_port"]; */
/* } */
/*     echo("Attempt dev settings (port forward)"); */
/*     $mysqli = new mysqli($sitesettings["dev_db_hostname"], $user, $user, $sitesettings["db_name"], $port=$sitesettings["dev_db_port"]); */
/* if (mysqli_connect_error()) { */
/*     die('mysqli Connect Error (' . mysqli_connect_errno() . ') ' */
/*         . mysqli_connect_error()); */
/* } else { */
/*     return $sitesettings["dev_db_hostname"] . ":" . $sitesettings["dev_db_port"]; */
/* } */

/*         return null; */

/* } */

/* $dbconnect = find_connections(); */
/* #print_r($dbconnect); */

/* if ($dbconnect != null){ */
/*     echo("<p>Database connection $ok</p>"); */
/*     echo("<p>Database connection used: $dbconnect</p>"); */
    
/* } else { */

/* } */
?>

<H2>Python status</H2>

<?php
$output = Array();
$return = exec("python3 --version 2>&1", $output, $status);
echo("<p>$output[0]</p>");
?>

<p>Status of required python3 modules</p>
<?php
$required_module_list = ['numpy', 'scipy', 'matplotlib', 'MySQLdb', 'yaml'];
foreach ($required_module_list as $module){
    $output = Array();
    $return = exec("python3 -c \"import $module\"", $output, $status);
    echo("<p>$module: " . ($status == 0 ? $ok : $not_found) . "</p>");
}
?>

<H2>All php information</H2>

<?php
     phpinfo();
?>

</body>
</html>