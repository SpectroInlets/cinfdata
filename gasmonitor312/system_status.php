<?php
  /*
    Copyright (C) 2012-2014 Robert Jensen, Thomas Andersen and Kenneth Nielsen
    
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

include("graphsettings.php");
include("../common_functions_v2.php");
echo(html_header());
$con = std_dbi();


### functions

function general_status($device_number){
    /* Produce the generel system status table

     Args:
        device_number (int): The device number of the generel system status in
            the status_b312gasalarm table
    */
    global $con;
    # Get all log entries of the last day for the generel status
    $query = "
    SELECT * FROM status_b312gasalarm
    WHERE device = $device_number AND time >= DATE_ADD(NOW(), INTERVAL -1 DAY)
    ORDER BY time DESC
    ";
    $result = mysqli_query($con, $query);

    # Make a nice table of them
    echo("<table class=\"nicetable\" border=1>\n");
    echo(
        "<tr>\n" .
        "<th>Time</th>\n<th>Standard checkin</th>\n<th>Status strings</th>\n" .
        "</tr>\n"
    );

    # Iterate over result rows and produce a table row for eahc
    while($row = mysqli_fetch_array($result)) {
        # Start row and insert time
        echo("<tr>\n");
        echo("<td>${row['time']}</td>\n");

        # Produce checkin status cell
        if ($row['check_in'] == 1){
            echo("<td class=\"all_good\">True</td>\n");
        } else {
            echo("<td class=\"alert\">False</td>\n");
        }

        # Produce status cell. All the status message are stored as a json
        # encoded array, so decode it
        $status_array = json_decode($row['status']);

        # Put all the status string in quotes and tie together around ", "
        foreach ($status_array as $key => $value){
            $status_array[$key] = "\"" . $value . "\"";
        }
        $status_str = implode(", ", $status_array);

        # and output 
        if ($status_str == "\"All OK\""){
            echo("<td class=\"all_good\">" . $status_str . "</td>\n");
        } else {
            echo("<td class=\"alert\">" . $status_str . "</td>\n");
        }
        echo("</tr>");
    }
    echo("</table>\n");

}


function power_status($device_number){
    /* Produce the system power status table

     Args:
        device_number (int): The device number of the system power status in
            the status_b312gasalarm table
    */

    global $con;
    
    # Get all log entries for the last day for power
    $query = "
    SELECT * FROM status_b312gasalarm
    WHERE device = $device_number AND time >= DATE_ADD(NOW(), INTERVAL -1 DAY)
    ORDER BY time DESC";
    $result = mysqli_query($con, $query);

    # Make a nice table of them
    echo("<table class=\"nicetable\" border=1>\n");
    echo("<tr><th>Time</th><th>Standard checkin</th><th>Status</th></tr>\n");

    # Iterate over result rows and produce one table row for each
    while($row = mysqli_fetch_array($result)) {
        # Start table row and output time
        echo("<tr>\n");
        echo("<td>${row['time']}</td>\n");

        # Output checkin status
        if ($row['check_in'] == 1){
            echo("<td class=\"all_good\">True</td>\n");
        } else {
            echo("<td class=\"alert\">False</td>\n");
        }

        # Output status
        if ($row['status'] == "\"OK\""){
            echo("<td class=\"all_good\">" . $row['status'] . "</td>\n");
        } else {
            echo("<td class=\"alert\">" . $row['status'] . "</td>\n");
        }

        echo("</tr>\n");  # End row
    }
    echo("</table>\n");

}


# Constant codename translation (used for detector status table)
$codename_translation = 
  Array(
	'1 Pumperum' => 'B312_gasalarm_H2_pumproom',
	'2 Hall' => 'B312_gasalarm_H2_hall_west',
	'3 Hall' => 'B312_gasalarm_H2_hall_east',
	'4 LAB' => 'B312_gasalarm_H2_chemlab',
	'5 Pumperum' => 'B312_gasalarm_CO_pumproom',
	'6 Hall' => 'B312_gasalarm_CO_hall_west',
	'7 Hall' => 'B312_gasalarm_CO_hall_east',
	'8 LAB' => 'B312_gasalarm_CO_chemlab',
	'9 NY LAB' => 'B312_gasalarm_H2_microscopy_west',
	'10 VENT' => 'B312_gasalarm_H2_vent',
	'11 NY LAB' => 'B312_gasalarm_CO_microscopy_west',
	'12 NY LAB' => 'B312_gasalarm_CO_microscopy_east',
	);

function detector_status($sql_result){
    /* Produce the detector status table */

    global $codename_translation;
    
    # Table and header
    echo("<table class=\"nicetable\" border=1>\n");
    echo("<tr><th>Time</th><th>Detector</th><th>Codename</th><th>Standard checkin</th><th>Inhibit</th><th>Status</th></tr>\n");

    # Iterate over SQL result rows and produce one table row for each
    while($row = mysqli_fetch_array($sql_result)) {
        # Start table row and output time and device
        echo("<tr>\n");
        echo("<td>${row['time']}</td>\n");
        echo("<td>${row['device']}</td>\n");

        # Parse the status json to get the status array
        $status_array = json_decode($row['status'], $assoc=True);

        # Output the codename cell
        $codename_key = "${row['device']} ${status_array['codename']}";
        if (array_key_exists($codename_key, $codename_translation)){
            echo("<td>" . $codename_translation[$codename_key] . "</td>\n");
        } else {
            echo("<td>" . $status_array['codename'] . "</td>\n");
        }

        # Output the check-in field
        if ($row['check_in'] == '1'){
            echo("<td class=\"all_good\">True</td>\n");
        } else {
            echo("<td class=\"alert\">False</td>\n");
        }

        # Output the inhibit field
        if ($status_array['inhibit']){
            echo("<td class=\"alert\">True</td>\n");
        } else {
            echo("<td class=\"all_good\">False</td>\n");
        }

        # Extract status strings, quote and gather them in sigle comma
        # separated string
        $status_string_array = $status_array['status'];
        foreach ($status_string_array as $key => $value){
            $status_string_array[$key] = "\"" . $value . "\"";
        }
        $status_string = implode(", ", $status_string_array);

        # Output status cell
        if ($status_string == "\"OK\""){
            echo("<td class=\"all_good\">" . $status_string . "</td>\n");
        } else {
            echo("<td class=\"alert\">" . $status_string . "</td>\n");
        }

        echo("</tr>\n");  # End table row
    }
    echo("</table>\n\n");
}


##### Main document


### System status CO and H2 Alarm
echo("\n\n<h1>System status <span style=\"color:blue;\">CO and H2</span> Gas Alarm Central</h1>\n");

# Generel System Status
echo("\n<h2>Generel status log entries of the last day</h2>\n");
general_status(254);

# System Power Status
echo("\n<h2>Power status log entries of the last day</h2>\n");
power_status(255);


### System status H2S Alarm
echo("\n\n<h1>System status <span style=\"color:blue;\">H2S</span> Gas Alarm Central</h1>\n");

# Generel System Status
echo("\n<h2>Generel status log entries of the last day</h2>\n");
general_status(120);

# System Power Status
echo("\n<h2>Power status log entries of the last day</h2>\n");
power_status(121);


### Detector status (last reported)
echo("\n<h1>Detector status (last reported status)</h1>\n");
$query = "
SELECT t1.*
FROM status_b312gasalarm t1
WHERE t1.time = (SELECT MAX(t2.time)
                 FROM status_b312gasalarm t2
                 WHERE t2.device = t1.device AND device < 119);
";
$result = mysqli_query($con, $query);
detector_status($result);


### Detector status (last 2 days)
echo("\n<h1>Detector status (all, from last two days)</h1>\n");
$query = "
SELECT * FROM status_b312gasalarm
WHERE device < 119 AND time >= DATE_ADD(NOW(), INTERVAL -2 DAY)
ORDER BY time DESC
";
$result = mysqli_query($con, $query);
detector_status($result);

echo(html_footer());
?>