<?php
#include("graphsettings.php");
include("../common_functions_v2.php");
$dbi = std_dbi();

echo(html_header());
$full = isset($_GET['full']);

# Transactions section
echo("<h1>Transactions</h1>\n\n");

if ($full){
  echo("<p>All transactions. <form method=\"post\" action=\"/other/pizza_transactions.php\" ><input type=\"submit\" value=\"Limit to last 7 months\" /></form></p>");
} else {
  echo("<p>Last 7 months transactions. <form method=\"post\" action=\"/other/pizza_transactions.php?full\" ><input type=\"submit\" value=\"Show full listing\" /></form></p>");
}



# echo("<p>Last 7 months listing. Show <form action=\"https://cinfdata.fysik.dtu.dk\"><input type=\"submit\" value=\"Show full listing\" /></form></p>");


# Start table
echo("<table class=\"nicetable\">\n");

# Headers
$headers = Array("ID", "User ID", "Time", "Amount");
echo("<tr>\n");
foreach($headers as $header){
  echo("<th>" . $header . "</th>");
}
echo("<tr>\n");

# Rows

if ($full){
  $query = "select * from pizza_transactions WHERE user_id != \"test\" order by time desc";
} else {
  $query = "select * from pizza_transactions WHERE user_id != \"test\" AND time > DATE_SUB(now(), INTERVAL 7 MONTH) order by time desc";
}

$result = $dbi->query($query);
while($row = $result->fetch_row()){
  echo("<tr>\n");

  foreach($row as $item){
    echo("<td>" . htmlentities($item) . "</td>");
  }

  echo("</tr>\n");
}

# End table
echo("</table>\n");

echo(html_footer());
?>