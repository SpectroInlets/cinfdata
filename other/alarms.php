<?php


include("../common_functions_v2.php");
require("SqlFormatter.php");
$dbi = std_dbi("alarm");

# Holds the data on existing alarms
$alarm_data = Array();
$message_out = "";

# Settings as constants
define("NUM_INPUTS", 26);

/* --- Functions --- */


/** Return red colored message of $alarm is true */
function msg($string, $alarm=false){
  if ($alarm){
    return "<p style=\"color:red\">" . htmlentities($string) . "</p>";
  } else {
    return "<p>" . htmlentities($string) . "</p>";
  }
}


/** Prepare a single row of data on an alarm for presentation in a HTML table

    This preparation consists if JSON decoding the queries and syntax
    highligthing them. The recipients and the email message are formatted with
    newlines. All rows have HTML entities replaced with excapes and the check
    and active is extracted.

    @param array @row A database row for one alarm in an array

    @return array An array of the following entities:
       Array($quiries, $recipients, $message, $escaped_row, $active);

 */
function prepare_table_data($row){
  # Format the quiries for a table cell
  $quiries = JSON_decode($row[1]);
  foreach($quiries as $key => $query){
    $quiries[$key] = SqlFormatter::format($query);
  }

  # Format the recipients, the message and the check for a table cell
  $recipients = implode("<br>", JSON_decode($row[6]));
  $message = str_replace("\n", "<br>", $row[5]);

  $escaped_row = Array();
  foreach($row as $key=>$value){
    $escaped_row[$key] = htmlentities($value);
  }

  $active = $row[11] == "1" ? "True"  : "False";

  return Array($quiries, $recipients, $message, $escaped_row, $active);

}


/** Produces the HTML for the existing alarms table */
function existing_alarms(){
  global $dbi;
  global $alarm_data;

  # Get the alarms
  $query = "SELECT * FROM alarm WHERE visible=1 order by id";
  $result = $dbi->query($query);

  # Start the table
  echo("<div style=\"width:45%;float:left\">");
  echo("<h1><a id=\"existing\"></a>Existing alarms</h1>\n");
  echo("<table border=\"1\" class=\"nicetable\">\n");
  echo("\n<tr>\n");
  echo("<th>ID</th>\n<th>Description</th>\n<th colspan=3>Actions</th>\n");
  echo("</tr>");

  # Loop over alarms
  while($row = $result->fetch_row()) {
    # Save the alarm data for use in the edit table
    $alarm_data[$row[0]] = $row;
    # Generate the single alarm row
    single_alarm_row($row);
  }

  # End the table
  echo("\n\n</table>\n");
  echo("</div>\n");
}


function single_alarm_row($row){
  /** Produces HTML for a single row of the existing alarms table

      @param array $row An array of the following elements:
          [id, quiries_json, parameters_json, check, no_repeat_interval, message,
	  recipients_json]

      @return void

  */
  # Format content for a table cell
  list($quiries, $recipients, $message, $escaped_row, $active) = prepare_table_data($row);

  # Produce table row
  echo("\n\n<tr>\n");
  echo("<td>{$escaped_row[0]}</td>\n");
  echo("<td>{$escaped_row[9]}</td>\n");
  echo("<td><form action=\"alarms.php\"><input name=\"action\" type=\"submit\" value=\"view {$row[0]}\"></form></td>\n");
  echo("<td><form action=\"alarms.php#edit_alarm\"><input name=\"action\" type=\"submit\" value=\"edit {$row[0]}\"></form></td>\n");
  echo("<td><form action=\"alarms.php#edit_alarm\"><input name=\"action\" type=\"submit\" value=\"delete {$row[0]}\"></form></td>\n");
  echo("</tr>");
  return;
}


/** Produce the HTML for the current alarm view

    @param string $alarm This string has the form "view 10" and therefore
       contains the id of the alarm to view

    @return void

*/
function view_alarm($action){
  # Get html table suitable data
  global $alarm_data;
  $row_number = (int) substr($action, 5);
  $row = $alarm_data[$row_number];
  list($quiries, $recipients, $message, $escaped_row, $active) = prepare_table_data($row);
  $message = str_replace("\n", "<br>", $escaped_row[5]);
  echo("\n<div style=\"width:50%; float:left; margin: 0% 0% 0% 5%;\">\n");
  echo("<h1>View alarm ${escaped_row[0]}\n</h1>");

  echo("<table class=\"nicetable\">\n");
  echo("<tr><td class=\"nicetableleftheader\">ID</td><td>${escaped_row[0]}</td></tr>");
  echo("<tr><td class=\"nicetableleftheader\">Description</td><td>${escaped_row[9]}</td></tr>");
  foreach($quiries as $key => $query){
    echo("<tr><td class=\"nicetableleftheader\">Query $key</td><td>$query</td></tr>");
  }
  echo("<tr><td class=\"nicetableleftheader\">Parameters</td><td>${escaped_row[2]}</td></tr>");
  echo("<tr><td class=\"nicetableleftheader\">Check</td><td>${escaped_row[3]}</td></tr>");
  echo("<tr><td class=\"nicetableleftheader\">No repeat interval</td><td>${escaped_row[4]}</td></tr>");
  echo("<tr><td class=\"nicetableleftheader\">Active</td><td>$active</td></tr>");
  echo("<tr><td class=\"nicetableleftheader\">Recipients</td><td>$recipients</td></tr>");
  echo("<tr><td class=\"nicetableleftheader\">Subject</td><td>${escaped_row[10]}</td></tr>");
  echo("<tr><td class=\"nicetableleftheader\">Message</td><td>$message</td></tr>");
  echo("</table>\n");
  echo("</div>\n");
}


/** Converts an JSON array to array of HTML compat. strings

    The strings are HTML escaped and any null values replaced by empty strings

    @param string $json
    @return array

*/
function from_json_to_array($json){
  $array = JSON_decode($json);
  for ($n = 0; $n <= NUM_INPUTS; $n++) {
    if (isset($array[$n])){
      $array[$n] = htmlentities($array[$n]);
    } else {
      $array[$n] = "";
    }
  }
  return $array;
}


/** Produce the HTML for the edit table

    @param null, string or array $row If this parameter is null, it means that
       the table is to be prefilled with default values for creating a new
       alarm. If it is "continue new" or "continue edit" all the values for
       the table is read from the page parameters. If it is an array the
       values are read in from that. In the case of an array, it is on the
       form: [id, quiries_json, parameters_json, check, no_repeat_interval, message,
       recipients_json]
    @return void

*/
function edit_table($row){
  # On continue edit or new, get the already filled in data from the url
  if ($row == "continue new" or $row == "continue edit"){
    $alarm_id = isset($_GET["alarm_id"]) ? $_GET["alarm_id"] : null;
    $check = isset($_GET["check"]) ? $_GET["check"] : "";
    $no_repeat_interval = isset($_GET["no_repeat_interval"]) ? $_GET["no_repeat_interval"] : 3600;
    $quiries = isset($_GET["quiries"]) ? $_GET["quiries"] : array_fill(0, NUM_INPUTS, "");
    $recipients = isset($_GET["recipients"]) ? $_GET["recipients"] : array_fill(0, NUM_INPUTS, "");
    $parameters = isset($_GET["parameters"]) ? $_GET["parameters"] : array_fill(0, NUM_INPUTS, "");
    $message = isset($_GET["message"]) ? $_GET["message"] : "";
    $description = isset($_GET["description"]) ? $_GET["description"] : "";
    $subject = isset($_GET["subject"]) ? $_GET["subject"] : "";
    $active = isset($_GET["active"]) ? $_GET["active"] : "";
  } elseif ($row === null){  # Add new alarm
    $alarm_id = null;
    $check = "";
    $no_repeat_interval = 3600;
    $quiries = array_fill(0, NUM_INPUTS, "");
    $recipients = array_fill(0, NUM_INPUTS, "");
    $parameters = array_fill(0, NUM_INPUTS, "");
    $message = "";
    $description = "";
    $subject = "";
    $active = "checked";
  } else {  # Edit existing
    $alarm_id = $row[0];
    $check = $row[3];
    $no_repeat_interval = $row[4];
    $quiries = from_json_to_array($row[1]);
    $recipients = from_json_to_array($row[6]);
    $parameters = from_json_to_array($row[2]);
    $message = $row[5];
    $description = $row[9];
    $subject = $row[10];
    $active = $row[11] == "1" ? "checked" : "";
  }

  echo("<p><u>Hover over input fields for help</u></p>");
  echo("<form action=\"alarms.php\">\n");
  echo("<input name=\"alarm_id\" type=\"hidden\" value=\"$alarm_id\">\n");

  # Output check, no_repeat_interval and message inputs
  echo("<table>\n");
  $help = htmlentities("A short description used only to identify the alarm. " .
		       "Please start with the setup name, to make it easier " .
		       "to scan the alarms. Example: \n\n" .
		       "Thethaprobe load lock pump down");
  echo("<tr>" .
       "<td>Description</td>" .
       "<td><input title=\"$help\" style=\"width:100%\" type=\"text\" name=\"description\" value=\"$description\"></td>" .
       "</tr>\n");
  $help = "This is the expression that is evaluated to determine when " .
    "an alarm should be sent. In the check the follow entities can be used: " .
    "\"<\", \">\", \">=\", \"<=\", \"==\", \"!=\", \"istrue\", \"isfalse\", " .
    "\"and\", \"or\", " .
    "\"q#\", \"p#\" and \"dqdt#\", where the ".
    "# is used to number placeholders for the query, parameter or slope of " .
    "a query respectively. Spaces are ignored.\n\n" .
    "Examples:\n" .
    "q0 > p0 and q1 < p1\n" .
    "dqdt0 > p0\n" .
    "q0 istrue\n" .
    "q0 istrue or q1 istrue\n" .
    "\nNOTE: istrue and isfalse expects MySQL booleans and therefore are " .
    "converted to == 1 and == 0 respectively";
  $help = htmlentities($help);
  echo("<tr>" .
       "<td>Check</td>" .
       "<td><input title=\"$help\" style=\"width:100%\" type=\"text\" name=\"check\" value=\"$check\"></td>" .
       "</tr>\n");
  $help = htmlentities("If the alarm condition continues to be true, do not " .
		       "send a new alarm email until after this amount of " .
		       "seconds has passed. The check for alarms is run once " .
		       "per minute. Is used to prevent flooding of your inbox."
		       );
  echo("<tr>" .
       "<td>No repeat interval [s]</td>" .
       "<td><input title=\"$help\" style=\"width:100%\" type=\"number\" name=\"no_repeat_interval\" value=\"$no_repeat_interval\"></td>" .
       "</tr>\n");
  $help = htmlentities("The email body. To this body will be appended the " .
		       "check that evaluated to true.");
  echo("<tr>" .
       "<td>Email body</td>" .
       "<td><textarea title=\"$help\" name=\"message\" rows=\"8\" cols=\"80\">" . htmlentities($message) . "</textarea></td>" .
       "</tr>\n");
  $help = htmlentities("The subject of the alarm email");
  echo("<tr>" .
       "<td>Subject</td>" .
       "<td><input title=\"$help\" style=\"width:100%\" type=\"text\" name=\"subject\" value=\"$subject\"></td>" .
       "</tr>\n");
  $help = htmlentities("Whether this alarm is being checked or not");
  echo("<tr>" .
       "<td>Active</td>" .
       "<td><input title=\"$help\" style=\"width:100%\" type=\"checkbox\" name=\"active\" value=\"checked\" $active></td>" .
       "</tr>\n");
  echo("</table>\n");

  # Output parameters, quiries and recipients table
  $parameter_help = "A numeric parameter used in the check. Can be int or float. " .
    "Exponential notation can be used.";
  $parameter_help = htmlentities($parameter_help);
  $recipient_help = htmlentities("An email adress for a recipient");
  $query_help = "A query to use in the check. The queries must return rows on " .
    "the form (unix timestamp, value). The queries must return exactly 1 row " .
    "EXCEPT if used for dqdt.";
  $query_help = htmlentities($query_help);
  echo("<br>\n");
  echo("<table class=\"nicetable\">\n");
  echo("<col width=\"3%\">" .
       "<col width=\"10%\">" .
       "<col width=\"20%\">" .
       "<col width=\"65%\">\n");
  echo("<tr><th>#</th><th>Parameter</th><th>Recipient</th><th>Query</th></tr>\n");
  for ($n = 0; $n <= NUM_INPUTS; $n++) {
    echo("<tr><td>$n</td>" .
	 "<td><input title=\"$parameter_help\" style=\"width:90%\" type=\"number\" name=\"parameters[]\" step=\"any\" value=\"{$parameters[$n]}\"></td>" .
	 "<td><input title=\"$recipient_help\" style=\"width:100%\" type=\"email\" name=\"recipients[]\" value=\"{$recipients[$n]}\"></td>" .
	 "<td><input title=\"$query_help\" style=\"width:100%\" type=\"text\" name=\"quiries[]\" value=\"{$quiries[$n]}\"></td>" .
	 "</tr>\n");
  }
  echo("</table>\n");

  # Output submit buttons
  if ($row === null or $row == "continue new"){
    echo("<input name=\"action\" type=\"submit\" value=\"Submit New\">\n");
  } else {
    echo("<input name=\"action\" type=\"submit\" value=\"Submit Edit\">\n");
  }
  echo("</form>\n");

  echo("<form action=\"alarms.php\">\n");
  echo("<input name=\"action\" type=\"submit\" value=\"Cancel\"\n>");
  echo("</form>\n");

}


/** Prepares the data in the URL for insertion in the MySQL db

    @return array associative array of data for database insertion
*/
function prepare_db_data(){
  $output = Array("check" => $_GET["check"],
		  "id" =>  $_GET["alarm_id"],
		  "message" => str_replace("\r\n", "\n", $_GET["message"]),
		  "no_repeat_interval" => (int) $_GET["no_repeat_interval"],
		  "description" => $_GET["description"],
		  "subject" => $_GET["subject"]);

  # Get the boolean active
  $output["active"] = isset($_GET["active"]) ? "1" : "0";

  # Cut the arrays to non-empty values and jsonify
  foreach(Array("parameters", "recipients", "quiries") as $key){
    $array = Array();
    foreach($_GET[$key] as $value){
      if ($value == ""){break;}  // Stop when we reach an empty field
	if ($key == "parameters"){
	  // For parameters, convert to integer of float and check the type
	  $converted_value = $value  + 0;  // "Official" way to convert to either int or float
	  if (is_int($converted_value) or is_float($converted_value)){
	    $array[] = $converted_value;
	  } else {
	    break;
	  }
	} else {
	  $array[] = $value;
	}
    }
    $output[$key . "_json"] = JSON_encode($array);
  }

  # Checks for bad data
  if (!is_string($output["check"]) or $output["check"] == ""){
    return msg("A non-empty string must be given for the check", $alarm=true);
  }

  if (!is_string($output["message"])){
    return msg("A non-empty string must be given for the message", $alarm=true);
  }

  if (count(JSON_decode($output["recipients_json"])) < 1){
    return msg("At least 1 recipient must be set", $alarm=true);
  }

  return $output;
}


/** Insert a new alarm from the URL data into the database */
function insert_new(){
  global $dbi;
  global $message_out;

  # If the data is insufficient, set the screen message and return
  $data = prepare_db_data();
  if (is_string($data)){
    $message_out = $data;
    return false;
  }

  $query = "INSERT INTO alarm (" .
    "quiries_json, " .
    "parameters_json, " .
    "`check`, " .
    "no_repeat_interval, " .
    "message, " .
    "recipients_json, " .
    "locked, " .
    "description, " .
    "subject, " .
    "active" .
    ") VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
  $statement = $dbi->prepare($query);
  # bind parameters for markers, where (s = string, i = integer, d = double,
  #                                     b = blob)

  $locked = 0;
  $statement->bind_param('sssississi',
			 $data["quiries_json"],
			 $data["parameters_json"],
			 $data["check"],
			 $data["no_repeat_interval"],
			 $data["message"],
			 $data["recipients_json"],
			 $locked,
			 $data["description"],
			 $data["subject"],
			 $data["active"]
			 );

  if($statement->execute()){
    $message_out = msg("The new alarm was successfully added and given ID " .
		       "number: " . $statement->insert_id);
  } else {
    $message_out = msg("The following error occurred while " .
		       "trying to insert the new alarm: (" . $mysqli->errno .
		       ") " . $mysqli->error,
		       $alarm=true);
  }
  $statement->close();

  return true;
}


/** Delete an alarm by settings its visible int to 0

    @param int $alarm_number the id of the alarm
    @return void

*/
function delete_alarm($alarm_number){
  global $dbi;
  global $message_out;

  # If the data is insufficient, set the screen message and return
  $query = "UPDATE alarm SET visible=0 WHERE `id`=?";
  $statement = $dbi->prepare($query);
  $statement->bind_param('i', $alarm_number);
  if($statement->execute()){
    $message_out = "<p>Alarm " . $data["id"] . " successfully deleted</p>";
  } else {
    $message_out = msg("The following error occurred while trying to delete " .
		       "the alarm: (" . $mysqli->errno . ") " . $mysqli->error,
		       $alarm=true);
  }
  $statement->close();

}


/** Update an alarm database entry with data from the URL

    @return bool that indicates whether there was enough data to make the
       update
*/
function update_existing(){
  global $dbi;
  global $message_out;

  # If the data is insufficient, set the screen message and return
  $data = prepare_db_data();
  if (is_string($data)){
    $message_out = $data;
    return false;
  }

  $query = "UPDATE alarm SET " .
    "quiries_json=?, " .
    "parameters_json=?, " .
    "`check`=?, " .
    "no_repeat_interval=?, " .
    "message=?, " .
    "recipients_json=?, " .
    "description=?, " .
    "subject=?, " .
    "active=? " .
    "WHERE `id`=?";

  $statement = $dbi->prepare($query);

  # bind parameters for markers, where (s = string, i = integer, d = double,
  #                                     b = blob)
  $data["id"] = (int) $data["id"];
  $data["active"] = (int) $data["active"];
  $statement->bind_param('sssissssii',
			 $data["quiries_json"],
			 $data["parameters_json"],
			 $data["check"],
			 $data["no_repeat_interval"],
			 $data["message"],
			 $data["recipients_json"],
			 $data["description"],
			 $data["subject"],
			 $data["active"],
			 $data["id"]);
  if($statement->execute()){
    $message_out = "<p>Alarm " . $data["id"] . " was successfully updated</p>";
  } else {
    $message_out = msg("The following error occurred while trying to update " .
		       "the alarm: (" . $mysqli->errno . ") " . $mysqli->error,
		       $alarm=true);
  }
  $statement->close();

  return true;
}


/* --- Main Script --- */

# Parse action
$action = isset($_GET["action"]) ? $_GET["action"] : "new" ;
if (substr($action, 0, 4) === "edit"){
  $alarm_number = (int) substr($action, 5);
  $action = "edit";
} elseif ($action === "Submit New"){
  $db_result = insert_new();
  if (!$db_result){
    $action = "continue new";
  }
} elseif ($action === "Submit Edit"){
  $db_result = update_existing();
  if (!$db_result){
    $action = "continue edit";
  }
} elseif (substr($action, 0, 6) === "delete") {
  $alarm_number = (int) substr($action, 7);
  delete_alarm($alarm_number);
  $action = "new";
}

/* --- Main page output --- */

echo(html_header());
echo("\n\n\n");
echo($message_out);

echo("<form action=\"alarms.php\">\n");
echo("<input name=\"action\" type=\"submit\" value=\"Cancel\">\n");
echo("</form>\n");
echo("\n\n\n");

# Existing alarms
existing_alarms();

# View alarm
if (substr($action, 0, 4) == "view"){
  view_alarm($action);

}

echo("<div class=\"clear\"></div>\n");

if ($action == "new"){
  echo("<h1>Enter new alarm</h1>\n");
  edit_table(null);
} elseif ($action == "continue new"){
  echo("<h1>Enter new alarm</h1>\n");
  edit_table($action);
} elseif ($action == "continue edit"){
  echo("<h1><a id=\"edit_alarm\"></a>Edit alarm number $alarm_number</h1>\n");
  edit_table($action);
} else {
  echo("<h1><a id=\"edit_alarm\"></a>Edit alarm number $alarm_number</h1>\n");
  edit_table($alarm_data[$alarm_number]);
}

echo("\n\n\n");
echo(html_footer());

?>