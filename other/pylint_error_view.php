<?php
include("../common_functions_v2.php");
$dbi = std_dbi();

# pylint output from database
$symbol = $_GET['symbol'];
$commit = $_GET['commit'];
# NOTE: Unsafe
$query = "select pytlin_output_json from pylint " .
  "where commit=\"$commit\" " .
  "and isfile=1";


# Get messages from the database
$rows = $dbi->query($query);
$file_counts = Array();
while ($row = $rows->fetch_row()){
  $messages = json_decode($row[0]);
  if ($messages == null){
    continue;
  }
  foreach ($messages as $message){
    if ($message->symbol == $symbol){
      if (!array_key_exists($message->path, $file_counts)){
	$file_counts[$message->path] = 0;
      }
      $file_counts[$message->path] += 1;
    }
  }
}


/* # PAGE START */
echo(html_header());

echo("<h2>Error statistics for specific error per file</h2>\n");
echo("<table>");
echo("<tr><td>Error symbol:</td><td><b>$symbol</b></td></tr>");
echo("<tr><td>Commit:</td><td><b>$commit</b></td></tr>");
echo("</table>");

arsort($file_counts);
echo("<table class=\"nicetable\">\n");
echo("<tr><th>Filepath</th><th>Count</th></tr>\n");
foreach ($file_counts as $filepath => $count){
  $relative_path = str_replace("/home/kenni/pylint_pyexplabsys/PyExpLabSys/", "", $filepath);
  $relative_path = str_replace("pylint_pyexplabsys/PyExpLabSys/", "", $relative_path);
  echo("<tr><td>$relative_path</td><td>$count</td></tr>");
}
echo("</table>\n");


echo(html_footer());
?>