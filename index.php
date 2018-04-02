<!DOCTYPE html>
<html lang="en" dir="ltr">

<!--  Example php & HTML to display METAR data -->

<?php  // **** INITIALIZE SCRIPT ****
require('getmetar.php');

date_default_timezone_set('America/Chicago');
$myStation = new Weather('KTIK');  // <============ set 4-letter station ID

?>
<head>
<meta charset="utf-8" />
<title>Wx METAR</title>

<style type="text/css" media="screen">
body {
	margin: 20px;
	font: normal 12px monospace;
	background: #fff;
	color: #000;
	}
ul {
	margin: 0;
	padding: 0;
	}
ul li {
	margin: 0;
	padding: 0;
	display: block;
	list-style: none;
	}
</style>
</head>

<body>
<h2>Weather @ <?php echo $myStation->get_station(); ?></h2>

<p>Formatted output:</p>
<ul>
<?php 

if (is_null($myStation->get_errors())) {
	$wxLabel = array(  //  property key => label value
		'age'         => 'Age',
		'temperature' => 'Temperature',
		'windchill'   => 'Wind Chill',
		'heatindex'   => 'Heat Index',
		'dewpoint'    => 'Dew Point',
		'humidity'    => 'Humidity',
		'pressure'    => 'Pressure',
		'wind'        => 'Wind',
		'visibility'  => 'Visibility',
		'clouds'      => 'Sky',
		'conditions'  => 'Wx');
	foreach ($wxLabel as $property => $label) {
		$myStation->print_formatted_wx($property, $label, 30, '<li>', "</li>\n");
	}
}
else {
	echo '<li>' . $myStation->get_errors() . "</li>\n";
}

?>
</ul>

<p>&nbsp;</p>
<p>Simple list of all properties and values:<br><br>

<?php $myStation->list_properties(); ?></p>

</body>
</html>