<?php
#include("graphsettings.php");
include("../common_functions_v2.php");
$dbi = std_dbi();

# Load in pylint error message descriptions
$msgs_raw = file_get_contents("msgs_json");
$msgs = json_decode($msgs_raw, true);

echo(html_header());

# Header section

echo("<h1>PyExpLabSys Pylint statistics</h1>\n");
echo("<p>This page has statistics from the last Pylint run on all the Python
source code in PyExpLabSys. The page has statistics for the number of
errors <u><a style=\"color:blue\" href=\"#files\">per file</a></u> and
per <u><a style=\"color:blue\" href=\"#errors\">error type</a></u>.</p>\n");

# Files section
$query = "select id, time, identifier, value, commit from pylint " .
  "where time=(SELECT max(time) FROM pylint) " .
  "and commit=(SELECT commit FROM pylint order by time desc limit 1) " .
  "and isfile=1 order by value desc";
$result = $dbi->query($query);
$files = Array();
while ($row = $result->fetch_row()){
  $files[] = $row;
}
$commit = substr($files[0][4], 0, 7);
$commit_full = $files[0][4];
echo("<h2 id=\"files\">File statistics for commit <span title=\"$commit_full\">($commit)</span> from {$files[0][1]}</h2>\n");
echo("<h3>(Click file for details)</h3>\n");

# Make table for files
echo("<table class=\"nicetable\"\n");
echo("<tr><th>#</th><th>File</th><th>Number of errors</th></tr>\n");
$number = 1;
foreach($files as $file){
  $fileview_link = "<a href=\"pylint_file_view.php?id={$file[0]}&commit={$file[4]}\">{$file[2]}</a>";
  echo("<tr><td>$number</td><td>$fileview_link</td><td>{$file[3]}</td></tr>\n");
  $number += 1;
}
echo("</table>\n");
echo("\n");

# Error type section
echo("<h2 id=\"errors\">Error statistics for commit ($commit) from {$errors[0][0]}</h2>\n");
echo("<h3>(Click error code for details)</h3>\n");

# Make table for errors
$query = "select time, identifier, value from pylint where " .
  "time=(SELECT max(time) FROM pylint) and isfile=0 " .
  "and commit=(SELECT commit FROM pylint order by time desc limit 1) " .
  "order by value desc;";
$result = $dbi->query($query);
echo("<table class=\"nicetable\"\n");
echo("<tr><th>Error symbol</th><th>Number of errors</th></tr>\n");
while ($error = $result->fetch_row()){
  echo("<tr>\n");
  echo("<td><a href=\"pylint_error_view.php?symbol=$error[1]&commit=$commit_full\">{$error[1]}</a></td>\n");
  echo("<td>{$error[2]}</td>\n");
  echo("</tr>\n");
}
echo("</table>\n");
echo("\n");

echo(html_footer());
?>