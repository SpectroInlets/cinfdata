<?php
include("../common_functions_v2.php");
$dbi = std_dbi();

# pylint output from database
$id = $_GET['id'];
$commit = $_GET['commit'];
$query = "select pytlin_output_json from pylint where id=$id";
$github_link = "";
$github_raw_link = "";

# Get messages from the database
$rows = $dbi->query($query);
$row = $rows->fetch_row();
$messages = json_decode($row[0]);


# Organize messages by line
$by_line = Array();
foreach($messages as $message){
  $key = $message->line;
  if (array_key_exists($key, $by_line)){
    $by_line[$key][] = $message;
  } else {
    $by_line[$key] = Array($message);
  }
}

# Get file source code, first form path
$path = $messages[0]->path;
$path = str_replace("/home/kenni/pylint_pyexplabsys/PyExpLabSys/", "", $path, $count=1);
# Get contents and split by newline
$github_link = "https://github.com/CINF/PyExpLabSys/blob/$commit/$path";
$github_raw_link = "https://raw.githubusercontent.com/CINF/PyExpLabSys/$commit/$path";
$contents = file_get_contents($github_raw_link);

$lines = preg_split ('/$\R?^/m', $contents);

# PAGE START
echo(html_header());

echo("<h2>$path\"</h2>\n");
echo("<h3><a href=\"$github_link\">Github link</a></h3>\n");

# Output lines with messages as mouse overs
echo("<table class=\"nicetable\">\n");
echo("<tr><th>#</th><th style=\"width:40%\">Messages</th><th>Code</th></tr>\n");
for ($i=1; $i <= count($lines); $i++) {
  $line = $lines[$i-1];
  $line = str_replace(" ", "&nbsp;", $line);
  if (array_key_exists($i, $by_line)){
    $text = "";
    foreach($by_line[$i] as $message){
      $type = substr(strtoupper($message->type), 0, 1);
      $message_text = htmlspecialchars($message->message);
      $text .= "{$type}: {$message_text} ({$message->symbol})<br>";
    }
    echo("<tr><td>$i</td><td>$text</td><td><span style=\"background-color:#F7819F\"><code>{$line}</code></span></td></tr>\n");
  } else {
    echo("<tr><td>$i</td><td></td><td><code>{$line}</code></td></tr>\n");    
  }
}
echo("</table>");

echo(html_footer());
?>