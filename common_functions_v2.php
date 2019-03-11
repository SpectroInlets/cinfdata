<?php
date_default_timezone_set("Europe/Copenhagen");

/** Returns a handle to the standard database
    @return object 
  */
function std_db(){
  $xml=simplexml_load_file("../site_settings.xml");
  $conn_str = 'mysql:host=' . $xml->db_host . ';dbname=' . $xml->db_database . ';charset=utf8';
  try{
      $pdo = new PDO(
          $conn_str,
          $xml->db_user,
          $xml->db_password,
          array(PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION));
      #die(json_encode(array('outcome' => true)));
  }
  catch(PDOException $ex){
      die(json_encode(array('outcome' => false, 'message' => 'Unable to connect')));
  }
    
  return $pdo;
}

function single_sql_value($db, $query, $column){
    $stmt = $db->prepare($query);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    $value = $row[$column];
    return($value);
}

function latest_sql_row($db, $query){
  // Returns the lastest row from the result of the query
  if (strpos($query, " desc") == false){
    $query .= " desc";
  }
  if (strpos($query, " limit ") == false){
    $query .= " limit 1";
  }
  $stmt = $db->prepare($query);
  $row = $stmt->fetch(PDO::FETCH_ASSOC);
  #$result  = mysql_query($query, $db);
  #$row = mysql_fetch_array($result);
  return($row);
}

function get_xy_values($query,$db,$offset=0){
    $result  = mysql_query($query,$db);
    while ($row = mysql_fetch_array($result)){
        $data["y"][] = $row[1] + $offset;
        $data["x"][] = $row[0];
    }
    return($data);
}

function xscale($max,$min,$manual,$log){
    if ($log){
        $max = log10($max);
        $min = log10($min);
    }
    $xscale["max"] = ($max == "") ? 2 : $max; //Default values if no values is given: 2 and 1
    $xscale["min"] = ($min == "") ? 1 : $min;
    $xscale["manual"] = $manual==="checked"; // If a manual scale is chosen the variable
                                             // $manual will have the value "checked"
    return($xscale);
}

/** Returns the default values for a date-xscale or an array of the manually selected scale
    If non-default values are provided, these will simply be returned
    The return value is formatted as a string suitable for injection into an sql-query
    @param string $from The from-value to be returned. If an emptry string is given, the default value will be returned
    @param string $to The to-value to be returned. If an emptry string is given, the default value will be returned
    @param string $defaulthours The default amount of hours to go back in time if defaults are to be returned
    @return array
  */
function date_xscale($from="",$to="",$defaulthours=24){
  $xscale["to"] = date('Y-m-d H:i',time() + 60); // Default, 1 minute into the future, to be shure get the whole plot                         
  $xscale["from"] = date('Y-m-d H:i',time() - 60 * 60 * $defaulthours);
  $xscale["from"] = ($from == "") ? $xscale["from"] : $from; // If we get an argument, skip the defaults
  $xscale["to"] = ($to == "") ? $xscale["to"] : $to;
  return($xscale);
}


/** Formats a given number as a scientific number (exponentials of 10 notation) in HTML 
    @param real $number The number to be html-formatted
    @return string
  */
function science_number($number){
    if($number == 0){
        $result = 0;
    }
    else {
        $exponent = floor(log10($number));
        $digits = $number * pow(10,-1*$exponent);
        $digits = round($digits,2);
        $result = $digits . " &times " . "10<sup>" . $exponent . "</sup>";
    }
    return($result);
}

/** Makes sure that a string only contains a-zA-Z0-9 \{} characters and replace
    the string with a warning if that is not the case
    $str string
    @return string
 */
function weed($str){
  $expressions = Array('/\"/', '/&/', '/=/', '/Æ/', '/Ø/','/Å/','/æ/','/ø/',
		       '/å/');
  foreach($expressions as $expr){
    $str = preg_replace($expr, "?", $str);
  }
  return $str;
}

/** Returns strings with standard html-header and -footer
 *  @return string
 */

function html_header($root="../", $title="Data viewer", $includehead="", $charset="UTF-8", $width=null, $html5=false){
  if ($width != null){
    $width = " style=\"width:{$width}px\" ";
  } else {
    $width = " style=\"max-width:95%\" ";
  }
  if(is_it_christmas()){
    $header = html_header_normal($root, $title, $includehead, $charset, $width, $html5);
  } else {
    $header = html_header_normal($root, $title, $includehead, $charset, $width, $html5);
  }
  return $header;
}

function html_footer($root="../", $valid_html5=false){
  if(is_it_christmas()){
    $footer = html_footer_normal($root, $valid_html5);
  } else {
    $footer = html_footer_normal($root, $valid_html5);
  }
  return $footer;
}


function html_header_normal($root, $title, $includehead, $charset, $width, $html5){

  $header = "";
  if ($html5){
    $header = $header . "<!DOCTYPE html>\n";
  } else {
    $header = $header . "<!DOCTYPE HTML PUBLIC \"-//W3C//DTD HTML 4.01 Transitional//EN\" \"http://www.w3.org/TR/html4/loose.dtd\">\n";
  }
  $header = $header . "<html>\n";
  $header = $header . "  <head>\n";
  $header = $header . "    <meta http-equiv=\"Content-Type\" content=\"text/html; charset={$charset}\">\n";
  $header = $header . "    <title>Spectro Biogas Data</title>\n";
  $header = $header . "    <link rel=\"StyleSheet\" href=\"{$root}css/style.css\" type=\"text/css\" media=\"screen\">\n";
  if ($root == "../"){
    # $header = $header . "    <script type=\"text/javascript\" src=\"dygraph/dygraph-dev.js\"></script>\n";
    $header = $header . "    <script type=\"text/javascript\" src=\"https://cdnjs.cloudflare.com/ajax/libs/dygraph/2.0.0/dygraph.js\"></script>\n";
    $header = $header . "    <link rel=\"stylesheet\" type=\"text/css\" href=\"https://cdnjs.cloudflare.com/ajax/libs/dygraph/2.0.0/dygraph.css\">\n";
  }
  $header = $header . "    <script type=\"text/javascript\" src=\"{$root}js/update.js\"></script> \n";
  $header = $header . "    <script type=\"text/javascript\" src=\"{$root}js/toogle.js\"></script>\n";
  $header = $header . "    <script type=\"text/javascript\" src=\"{$root}js/output.js\"></script>\n";
  $header = $header . $includehead . "\n";
  $header = $header . "  </head>\n";
  $header = $header . "  <body>\n";
  $header = $header . "    <div class=\"container\" $width>\n";
  $header = $header . "    <div class=\"caption\">\n";
  $header = $header . "      {$title}\n";
  $header = $header . "      <a href=\"/\"><img class=\"logo\" height=\"61\" src=\"{$root}images/spectro_bio_logo.png\"></a>\n";
  $header = $header . "        <div class=\"header_utilities\">\n";
 # $header = $header . "          <a class=\"header_links\" href=\"https://cinfwiki.fysik.dtu.dk/cinfwiki/Software/DataWebPageUserDocumentation\">Help</a><br>\n";
#  $header = $header . "          <a class=\"header_links\" href=\"test_configuration_file.php\">Config</a>\n";
  $header = $header . "        </div>\n";
  $header = $header . "    </div>\n";
  $header = $header . "    <div class=\"plotcontainer\">\n";

  return($header);
}


function header_v2(){
  $header = "";
  $header = $header . "<!DOCTYPE HTML PUBLIC \"-//W3C//DTD HTML 4.01 Transitional//EN\" \"http://www.w3.org/TR/html4/loose.dtd\">";
  $header = $header . "<html>\n";
  $header = $header . "  <head>\n";
  $header = $header . "    <title>SurfCat data logging</title>\n";
  $header = $header . "    <link rel=\"StyleSheet\" href=\"../css/style.css\" type=\"text/css\" media=\"screen\">\n";
  $header = $header . "  </head>\n";
  $header = $header . "  <body>\n";
  $header = $header . "    <div class=\"container\">\n";
  $header = $header . "      <div class=\"caption\">Data viewer\n";
  $header = $header . "        <a href=\"/\"><img class=\"logo\" src=\"../images/cinf_logo_beta_greek.png\"></a>\n";
  $header = $header . "        <div class=\"header_utilities\">\n";
  $header = $header . "          <a class=\"header_links\" href=\"https://cinfwiki.fysik.dtu.dk/cinfwiki/Software/DataWebPageUserDocumentation\">Help</a><br>\n";
  $header = $header . "          <a class=\"header_links\" href=\"test_configuration_file.php\">Config</a>\n";
  $header = $header . "        </div>\n";
  $header = $header . "      </div>\n";

  return($header);
}

function html_footer_normal($root, $valid_html5){
  $footer = "";
  $footer = $footer . "      </div>\n";
  if ($valid_html5){
    $footer = $footer . "      <div class=\"copyright\" style=\"clear:both\">\n";
    #$footer = $footer . "        <div style=\"float:left;width:100px\">&nbsp;</div>\n";
#    $footer = $footer . "        <div style=\"float:right;width:100px\"><img src=\"{$root}images/badge-w3c-valid-html5_h30.png\" height=\"20\" style=\"padding:5px\" alt=\"Valid HTML5\" title=\"Valid HTML5\"></div>\n";
    #$footer = $footer . "        <div style=\"margin:0 auto;width:300px\">Spectro Biogas ApS</div>\n";
    $footer = $footer . "        <p class=\"copyrighttext\">Spectro Biogas ApS, Ole Maaløes Vej 3, 2200 København, Denmark</p>\n";
    #$footer = $footer . "        <p>Spectro Biogas ApS, Ole Maaløes Vej 3, 2200 København, Denmark</p>\n";
    $footer = $footer . "      </div>\n";
  } else {
    $footer = $footer . "      <div class=\"copyright\" style=\"clear:both\">...</div>\n";
  }
  $footer = $footer . "    </div>\n";
  $footer = $footer . "  </body>\n";
  $footer = $footer . "</html>\n";

#

  return($footer);
}

function html_footer_v2(){
  $footer = "";
  $footer = $footer . "      <div class=\"copyright\">...</div>\n";
  $footer = $footer . "    </div>\n";
  $footer = $footer . "  </body>\n";
  $footer = $footer . "</html>\n";
  return($footer);
}

function html_code_header($file){
    $header = "";
    $header = $header . "<head><title>SurfCat data logging</title>\n";
    $header = $header . "<link rel=\"StyleSheet\" href=\"../css/screen.css\" type=\"text/css\" media=\"screen\">\n";
    $header = $header . "</head>\n";
    $header = $header . "<body>\n";
    $header = $header . "<div class=\"container\">\n";
    $header = $header . "<div class=\"caption\">Code viewer: ".$file."\n";
    $header = $header . "<a href=\"/\"><img class=\"logo\" src=\"../images/cinf_logo_beta_greek.png\"></a>\n";
    $header = $header . "</div>\n";
    return($header);
}

function html_header_x($root, $title, $includehead, $charset, $width, $html5){
  $files = Array();
  if ($handle = opendir("{$root}images/xmas-shift")) {
    while (false !== ($entry = readdir($handle))) {
      if ($entry != "." && $entry != "..") {
	$files[] = $entry;
      }
    }
    closedir($handle);
  }
  
  $rand_img = $files[array_rand($files)];

  $header = "";
  $header = $header . "<!DOCTYPE HTML PUBLIC \"-//W3C//DTD HTML 4.01 Transitional//EN\" \"http://www.w3.org/TR/html4/loose.dtd\">\n";
  $header = $header . "<html>\n";
  $header = $header . "  <head>\n";
  $header = $header . "    <meta http-equiv=\"Content-Type\" content=\"text/html; charset={$charset}\">\n";
  $header = $header . "    <title>SurfCat data logging</title>\n";
  $header = $header . "    <link rel=\"StyleSheet\" href=\"{$root}css/style.css\" type=\"text/css\" media=\"screen\">\n";
  if ($root == "../"){
    #$header = $header . "    <script type=\"text/javascript\" src=\"dygraph/dygraph-dev.js\"></script>\n";    
    #$header = $header . "    <script type=\"text/javascript\" src=\"dygraph-combined.js\"></script>\n";
    $header = $header . "    <script type=\"text/javascript\" src=\"https://cdnjs.cloudflare.com/ajax/libs/dygraph/2.0.0/dygraph.js\"></script>\n";
    $header = $header . "    <link rel=\"stylesheet\" type=\"text/css\" href=\"https://cdnjs.cloudflare.com/ajax/libs/dygraph/2.0.0/dygraph.css\">\n";
  }
  $header = $header . "    <script type=\"text/javascript\" src=\"{$root}js/update.js\"></script> \n";
  $header = $header . "    <script type=\"text/javascript\" src=\"{$root}js/toogle.js\"></script>\n";
  $header = $header . "    <script type=\"text/javascript\" src=\"{$root}js/output.js\"></script>\n";
  $header = $header . $includehead . "\n";
  $header = $header . "  </head>\n";
  $header = $header . "  <body>\n";
  $header = $header . "    <div class=\"container\" $width>\n";
  $header = $header . "    <div class=\"caption_alt\">\n";
#  $header = $header . "      <img src=\"../images/fir_branch_with_cones.png\" height=\"42\" width=\"42\">\n";
  $header = $header . "      {$title}\n";
  $header = $header . "      <a href=\"/\"><img class=\"logo_alt\" src=\"{$root}images/cinf_logo_beta_greek.png\" alt=\"CINF data viewer\"></a>\n";
  $header = $header . "        <div class=\"header_utilities\">\n";
  $header = $header . "          <a class=\"header_links\" href=\"https://cinfwiki.fysik.dtu.dk/cinfwiki/Software/DataWebPageUserDocumentation\">Help</a><br>\n";
  $header = $header . "          <a class=\"header_links\" href=\"test_configuration_file.php\">Config</a>\n";
  $header = $header . "        </div>\n";
  $header = $header . "        <div class=\"header_decorations_shift\">\n";
  $header = $header . "        <img class=\"header_img_shift\" src=\"{$root}images/xmas-shift/{$rand_img}\" title=\"Merry Christmas\" alt=\"CINF data viewer\">\n";
  $header = $header . "        </div>\n";
  $header = $header . "        <div class=\"header_decorations_static\">\n";
  $header = $header . "        <img class=\"edition\" src=\"{$root}images/xmas_edition.png\" alt=\"Merry Christmas\">\n";
  $header = $header . "        </div>\n";
  $header = $header . "    </div>\n";
  $header = $header . "    <div class=\"plotcontainer\">\n";

  return($header);
}


function html_footer_x($root, $valid_html5){
  $footer = "";
  $footer = $footer . "      </div>\n";
  $footer = $footer . "      <div class=\"copyright_decorations\" style=\"clear:both\"></div>\n";
  $footer = $footer . "    </div>\n";
  $footer = $footer . "  </body>\n";
  $footer = $footer . "</html>\n";
  return($footer);
}


function indent($level, $str){
  foreach (explode("\n", $str) as $line){
    echo(str_repeat(" ", $level) . $line) . "\n";
  }
}

function right_float_menu($items, $indent){
  indent($indent, "" .
	 "<!-- RIGHT FLOAT MENU -->\n" . 
	 "<div class=\"right_float_menu\">\n" .
	 "  <table cellspacing=0>");
  foreach($items as $key => $value){
    indent($indent + 4, "" .
	   "<tr>\n" . 
	   "  <td><a href=\"$value\">$key</a></td>\n" . 
	   "</tr>"
	   );
      }
  indent($indent, "" .
	 "  </table>\n" . 
	 "</div>\n");

}

function is_it_christmas()
{
  /* It is x-mas from 14/11 to 31/12 */
  $month = (int) date("n");
  switch ($month){
  case 12:
    return true;
  case 11:
    $day = (int) date("d");
    if ($day >=14){
      return true;
    } else {
      return false;
    }
  default:
    return false;
  }
}

function pprint($var){
  echo("<pre>");
  print_r($var);
  echo("</pre>");
}

?>
