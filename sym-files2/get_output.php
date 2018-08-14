<?php
  /*
    Copyright (C) 2012 Robert Jensen, Thomas Andersen and Kenneth Nielsen
    
    The CINF Data Presentation Website is free software: you can
    redistribute it and/or modify it under the terms of the GNU
    General Public License as published by the Free Software
    Foundation, either version 3 of the License, or
    (at your option) any later version.
    
    The CINF Data Presentation Website is distributed in the hope
    that it will be useful, but WITHOUT ANY WARRANTY; without even
    the implied warranty of MERCHANTABILITY or FITNESS FOR A
    PARTICULAR PURPOSE.  See the GNU General Public License for more
    details.
    
    You should have received a copy of the GNU General Public License
    along with The CINF Data Presentation Website.  If not, see
    <http://www.gnu.org/licenses/>.
  */

include("../common_functions_v2.php");
date_default_timezone_set("Europe/Copenhagen");
include("graphsettings.php");
$db = std_db();
$output_id = $_GET["output_id"];
usleep(100000);
$query = "select CONVERT(output USING ascii) from plot_com_out where id=$output_id";

$stmt = $db->prepare($query);
$stmt->execute();

if ($stmt->rowCount() == 1){
  $line = $stmt->fetch(PDO::FETCH_BOTH);;
  print_r($line[0]);
} else {
  echo("no output");
}
?>