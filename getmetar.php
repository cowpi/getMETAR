<?php

//  This script retrieves a METAR XML file from the AWC website and decodes the weather data.
//
//  Public methods:
//    new Weather(stationID) -- 4-letter station ID string, i.e. KTIK
//    print_formatted_wx(property, label, width, beginning-tag, closing-tag) -- formats weather
//        data for monospaced fonts with dots padding between property and data value, such as
//        "label......value". Method requires the given class property, its descriptive label,
//        width or number of characters, beginning and closing tag. If property does not exist
//        or value is blank, nothing is printed.
//    list_properties() -- lists each class property and its value as string delimited by <br>
//    Various class properties:
//        get_metar(), get_station(), get_observed(), get_age(),
//        get_wind(), get_visibility(), get_conditions(), get_clouds(),
//        get_temperature(), get_dewpoint(), get_humidity(),
//        get_windchill(), get_heatindex(), get_pressure(),
//        get_errors()
//
//    Check get_errors(). If no errors, this property is null.
//
//    Mark Woodward, Apr 2018
//    Oklahoma City


class Weather {

	private $metar = null;           //  raw text of METAR
	private $station = null;         //  four-letter station ID
	private $observed = null;        //  observation date & time, format 'D M j, H:i T'
	private $age = null;             //  number of minutes since observation (or hh:mm)
	private $temperature = null;     //  in °F
	private $dewpoint = null;        //  in °F
	private $heatindex = null;       //  in °F
	private $windchill = null;       //  in °F
	private $humidity = null;        //  relative humidity %
	private $pressure = null;        //  pressure in inches
	private $wind = null;            //  compass direction and speed/gust in mph
	private $visibility = null;      //  visibility distance in miles
	private $clouds = null;          //  description of cloud cover
	private $conditions = null;      //  weather conditions
	private $errors = null;          //  description of any error

	public function __construct($stationID) {
		// This method initiates the setting of class properties.
		$this->station = $stationID;
		$this->metar = $this->load_metar($stationID);
		if ($this->metar != '') $this->process_metar();
	}

	private function load_metar($stationID) {
		// This method retrieves METAR information for a given stationID.
		// Returns metar raw text and sets observed and now properties.
		$fileData = $this->load_file($stationID);
		if ($fileData === false) {
			$this->errors = 'File not found';
			$metarData = '';
		}
		else {
			$fileData = new SimpleXMLElement($fileData);
			if ($fileData->{'data'}['num_results'] == 0) {     // zero results returned
				$this->errors = 'Station not found';
				$metarData = '';
			}
			else {
				$utc = $fileData->{'data'}->METAR->observation_time;
				$this->set_time_data(strtotime($utc));
				$metarData = trim($fileData->{'data'}->METAR->raw_text);
			}
		}
		return $metarData;
	}

	private function load_file($stationID) {
		// This method loads in the XML file from external server given the station ID.
		$fileName = 'https://aviationweather.gov/adds/dataserver_current/httpparam?dataSource=metars&requestType=retrieve&format=xml&hoursBeforeNow=3&mostRecent=true&stationString=' . $stationID;
		$ch = curl_init();
		curl_setopt($ch, CURLOPT_URL, $fileName);
		curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 2);
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
		curl_setopt($ch, CURLOPT_USERAGENT, ' Mozilla/1.22 (compatible; MSIE 2.0d; Windows NT)');
		$fileData = curl_exec($ch);
		curl_close($ch);
		return $fileData;
	}

	private function set_time_data($utc) {
		// This method formats observation time in the local time zone of server, the
		// current local time on server, and time difference since observation. $utc is a
		// UNIX timestamp for Universal Coordinated Time (Greenwich Mean Time or Zulu Time).
		$this->observed = date('D M j, H:i T', $utc);
		$now = time();
		$timeDiff = floor(($now - $utc) / 60);
		if ($timeDiff < 91) $this->age = "$timeDiff min";
		else {
			$min = $timeDiff % 60;
			if ($min < 10) $min = '0' . $min;
			$this->age = floor($timeDiff / 60) . ":$min hr";
		}
	}

	private function process_metar() {
		//   This method directs the examination of each group of the METAR. The problem
		// with a METAR is that not all the groups have to be there. Some groups could be
		// missing. And some groups have multiple parts. Fortunately, the groups must be
		// in a specific order. (This function also assumes that a METAR is well-formed,
		// that is, no typographical mistakes.)
		//   This function uses a function variable to organize the sequence in which to
		// decode each group. Each function checks to see if it can decode the current
		// METAR part. If not, then the group pointer is advanced for the next function
		// to try. If yes, the function decodes that part of the METAR and advances the
		// METAR pointer and group pointer. (If the function can be called again to
		// decode similar information, then the group pointer does not get advanced.)
		if ($this->metar != '') {
			$metarParts = explode(' ', $this->metar);
			$groupName = array('set_station','set_time','set_station_type','set_wind','set_var_wind','set_visibility','set_runway','set_conditions','set_cloud_cover','set_temperature','set_altimeter');
			$metarPtr = 1;  // set_station identity is ignored
			$group = 1;
			while ($group < count($groupName)) {
				$part = $metarParts[$metarPtr];
				$this->$groupName[$group]($part, $metarPtr, $group);  // $groupName is a function variable
				}
		}
		else $this->errors = 'Data not available';
	}

	private function set_station($part, &$metarPtr, &$group) {
		// Ignore station code. Script assumes this matches requesting $station.
		// This function is never called. It is here for completeness of documentation.
		if (strlen($part) == 4 and $group == 0) {
			$group++;
			$metarPtr++;
		}
	}

	private function set_time($part, &$metarPtr, &$group) {
		// Ignore observation time. It is set elsewhere from XML data.
		// Format is ddhhmmZ where dd = day, hh = hours, mm = minutes in UTC time.
		if (substr($part,-1) == 'Z') $metarPtr++;
		$group++;
	}

	private function set_station_type($part, &$metarPtr, &$group) {
		// Ignore station type if present.
		if ($part == 'AUTO' || $part == 'COR') $metarPtr++;
		$group++;
	}

	private function set_wind($part, &$metarPtr, &$group) {
		// Decodes wind direction and speed information.
		// Format is dddssKT where ddd = degrees from North, ss = speed, KT for knots,
		// or dddssGggKT where G stands for gust and gg = gust speed. (ss or gg can be a 3-digit number.)
		// KT can be replaced with MPH for meters per second or KMH for kilometers per hour. 

		if (preg_match('/^([0-9G]{5,10}|VRB[0-9]{2,3})(KT|MPS|KMH)$/', $part, $pieces)) {
			$part = $pieces[1];
			$unit = $pieces[2];
			if ($part == '00000') {
				$this->wind = 'calm';  // no wind
			}
			else {
				preg_match('/([0-9]{3}|VRB)([0-9]{2,3})G?([0-9]{2,3})?/', $part, $pieces);
				if ($pieces[1] == 'VRB') $direction = 'varies';
				else {
					$angle = (integer) $pieces[1];
					$compass = array('N','NNE','NE','ENE','E','ESE','SE','SSE','S','SSW','SW','WSW','W','WNW','NW','NNW');
					$direction = $compass[round($angle / 22.5) % 16];
				}
				$this->wind = $direction . ' ' . $this->speed($pieces[2], $unit);
				//  Add wind gust by removing speed unit
				if (isset($pieces[3])) $this->wind = substr($this->wind ,0, -4) . '/' . $this->speed($pieces[3], $unit);			
			}
			$metarPtr++;
		}
		$group++;
	}

		private function speed($part, $unit) {
			// Convert wind speed into miles per hour.
			// Some other common conversion factors (to 6 significant digits):
			//   1 mi/hr = 1.15080 knots  = 0.621371 km/hr = 2.23694 m/s
			//   1 ft/s  = 1.68781 knots  = 0.911344 km/hr = 3.28084 m/s
			//   1 knot  = 0.539957 km/hr = 1.94384 m/s
			//   1 km/hr = 1.852 knots    = 3.6 m/s
			//   1 m/s   = 0.514444 knots = 0.277778 km/s
			if ($unit == 'KT') $velocity = round(1.1508 * $part);         // from knots
			elseif ($unit == 'MPS') $velocity = round(2.23694 * $part);   // from meters per second
			else $velocity = round(0.621371 * $part);                     // from km per hour
			$velocity = "$velocity mph";
			return $velocity;
		}

	private function set_var_wind($part, &$metarPtr, &$group) {
		// Ignore variable wind direction information if present.
		// Format is fffVttt where V stands for varies from fff degrees to ttt degrees.
		if (preg_match('/([0-9]{3})V([0-9]{3})/', $part, $pieces)) $metarPtr++;
		$group++;
	}

	private function set_visibility($part, &$metarPtr, &$group) {
		// Decodes visibility information. This function will be called a second time
		// if visibility is limited to an integer mile plus a fraction part.
		// Format is mmSM for mm = statute miles, or m n/dSM for m = mile and n/d = fraction of a mile,
		// or just a 4-digit number nnnn (with leading zeros) for nnnn = meters. 
		static $integerMile = '';
		static $resetFlag = false;
		if ($resetFlag) {
			$integerMile = '';
			$resetFlag = false;
		}
		if (strlen($part) == 1) {  // visibility is limited to a whole mile plus a fraction part
			$integerMile = $part . ' ';
			$resetFlag = false;
			$metarPtr++;
		}
		elseif (substr($part,-2) == 'SM') {  // visibility is in miles
			$part = substr($part, 0, strlen($part) - 2);
			if (substr($part,0,1) == 'M') {
				$prefix = '&#x3C;';  // &#x3C; is used over &lt; to simplify counting characters for formatting
				$part = substr($part, 1);
			}
			else $prefix = '';
			if (($integerMile == '' && preg_match('[/]', $part, $pieces)) || $part == '1') $unit = ' mi';
			else $unit = ' mi';  // plural
			$this->visibility = $prefix . $integerMile . $part . $unit;
			$resetFlag = true;
			$metarPtr++;
			$group++;
		}
		elseif (substr($part, -2) == 'KM') {  // unknown (Reported by NFFN in Fiji)
			$metarPtr++;
			$group++;
		}
		elseif (preg_match('/^([0-9]{4})$/', $part, $pieces)) {  // visibility is in meters
			$distance = round($part/ 621.4, 1);          // convert to miles
			if ($distance > 5) $distance = round($distance);
			if ($distance <= 1) $unit = ' mi';
			else $unit = ' mi';  // plural
			$this->visibility = $distance . $unit;
			$metarPtr++;
			$group++;
		}
		elseif ($part == 'CAVOK') {  // good weather
			$this->visibility = '&#x3E;7 mi';  // or 10 km. &#x3E; is used over &gt; to simplify counting characters for formatting
			$this->conditions = '';
			$this->clouds = 'clear skies';
			$metarPtr++;
			$group += 4;  // can skip the next 3 groups
		}
		else {
			$group++;
		}
	}

	private function set_runway($part, &$metarPtr, &$group) {
		// Ignore runway information if present. Maybe called a second time.
		// Format is Rrrr/vvvvFT where rrr = runway number and vvvv = visibility in feet.
		if (preg_match('/^R([0-9]{1,3})/', $part, $pieces)) $metarPtr++;
		else $group++;
	}

	private function set_conditions($part, &$metarPtr, &$group) {
		// Decodes current weather conditions. This function maybe called several times
		// to decode all conditions. To learn more about weather condition codes, visit
		// section 12.6.8 of Present Weather Group of the Federal Meteorological Handbook No. 1
		static $conditions = '';
		static $wxCode = array(
			'VC' => 'nearby',
			'MI' => 'shallow',
			'PR' => 'partial',
			'BC' => 'patches of',
			'DR' => 'low drifting',
			'BL' => 'blowing',
			'SH' => 'showers',
			'TS' => 'thunderstorm',
			'FZ' => 'freezing',
			'DZ' => 'drizzle',
			'RA' => 'rain',
			'SN' => 'snow',
			'SG' => 'snow grains',
			'IC' => 'ice crystals',
			'PE' => 'ice pellets',
			'PL' => 'ice pellets',   // ?
			'GR' => 'hail',
			'GS' => 'small hail',   // and/or snow pellets
			'UP' => 'unknown',
			'BR' => 'mist',
			'FG' => 'fog',
			'FU' => 'smoke',
			'VA' => 'volcanic ash',
			'DU' => 'widespread dust',
			'SA' => 'sand',
			'HZ' => 'haze',
			'PY' => 'spray',
			'PO' => 'dust whirls',  // well-developed dust/sand whirls
			'SQ' => 'squalls',
			'FC' => 'tornado',      // funnel cloud, tornado, or waterspout
			'SS' => 'duststorm');   // sandstorm/duststorm
		if (preg_match('/^(-|\+|VC)?(TS|SH|FZ|BL|DR|MI|BC|PR|RA|DZ|SN|SG|GR|GS|PE|PL|IC|UP|BR|FG|FU|VA|DU|SA|HZ|PY|PO|SQ|FC|SS|DS)+$/', $part, $pieces)) {
			if (strlen($conditions) == 0) $join = '';
			else $join = '&#x26; ';
			if (substr($part, 0, 1) == '-') {
				$prefix = 'light ';
				$part = substr($part, 1);
			}
			elseif (substr($part, 0, 1) == '+') {
				$prefix = 'heavy ';
				$part = substr($part, 1);
			}
			else $prefix = '';  // moderate conditions have no descriptor
			$conditions .= $join . $prefix;
			// The 'showers' code 'SH' is moved behind the next 2-letter code to make the English translation read better.
			if (substr($part,0,2) == 'SH') $part = substr($part,2,2) . substr($part,0,2). substr($part, 4);
			while ($code = substr($part, 0, 2)) {
				$conditions .= $wxCode[$code] . ' ';
				$part = substr($part, 2);
			}
			$this->conditions = $conditions;
			$metarPtr++;
		}
		else {
			$group++;
		}
	}

	private function set_cloud_cover($part, &$metarPtr, &$group) {
		// Decodes cloud cover information. This function maybe called several times
		// to decode all cloud layer observations. Only the last layer is saved.
		// Format is SKC or CLR for clear skies, or cccnnn where ccc = 3-letter code and
		// nnn = altitude of cloud layer in hundreds of feet. 'VV' seems to be used for
		// very low cloud layers. (Other conversion factor: 1 m = 3.28084 ft)
		static $cloudCode = array(
			'SKC' => 'clear',  // clear skies
			'CLR' => 'clear',
			'FEW' => 'partly cloudy',
			'SCT' => 'scattered clouds',
			'BKN' => 'mostly cloudy',
			'OVC' => 'overcast',
			'VV'  => 'vertical visibility');
		if ($part == 'SKC' || $part == 'CLR') {
			$this->clouds = $cloudCode[$part];
			$metarPtr++;
			$group++;
		}
		else {
			if (preg_match('/^([A-Z]{2,3})([0-9]{3})/', $part, $pieces)) {  // codes for CB and TCU are ignored
				$this->clouds = $cloudCode[$pieces[1]];
				if ($pieces[1] == 'VV') {
					$altitude = (integer) 100 * $pieces[2];  // units are feet
					$this->clouds = "VV $altitude ft";
					}
				$metarPtr++;
			}
			else {
				$group++;
			}
		}
	}

	private function set_temperature($part, &$metarPtr, &$group) {
		// Decodes temperature and dew point information. Relative humidity is calculated. Also,
		// depending on the temperature, Heat Index or Wind Chill Temperature is calculated.
		// Format is tt/dd where tt = temperature and dd = dew point temperature. All units are
		// in Celsius. A 'M' preceeding the tt or dd indicates a negative temperature. Some
		// stations do not report dew point, so the format is tt/ or tt/XX.

		if (preg_match('/^(M?[0-9]{2})\/(M?[0-9]{2}|[X]{2})?$/', $part, $pieces)) {
			$tempC = (integer) strtr($pieces[1], 'M', '-');
			$tempF = round(1.8 * $tempC + 32);
			$this->temperature = $this->format_temp($tempF, $tempC);
			$this->set_wind_chill($tempF);
			if (strlen($pieces[2]) != 0 && $pieces[2] != 'XX') {
				$dewC = (integer) strtr($pieces[2], 'M', '-');
				$dewF = round(1.8 * $dewC + 32);
				$this->dewpoint = $this->format_temp($dewF, $dewC);
				$rh = round(100 * pow((112 - (0.1 * $tempC) + $dewC) / (112 + (0.9 * $tempC)), 8));
				$this->humidity = $rh . '%';
				$this->set_heat_index($tempF, $rh);
				}
			$metarPtr++;
			$group++;
		}
		else {
			$group++;
		}
	}

		private function format_temp($tempF, $tempC) {
			// Formats temperature output as: xx°F (yy°C)
			return "$tempF&#xB0;F";  // &#xB0; is used over &deg; to simplify counting characters for formatting
		}

		private function set_heat_index($tempF, $rh) {
			// Calculate Heat Index based on temperature in °F and relative humidity (65 = 65%)
			if ($tempF > 79 && $rh > 39) {
				$hiF = -42.379 + 2.04901523 * $tempF + 10.14333127 * $rh - 0.22475541 * $tempF * $rh;
				$hiF += -0.00683783 * pow($tempF, 2) - 0.05481717 * pow($rh, 2);
				$hiF += 0.00122874 * pow($tempF, 2) * $rh + 0.00085282 * $tempF * pow($rh, 2);
				$hiF += -0.00000199 * pow($tempF, 2) * pow($rh, 2);
				$hiF = round($hiF);
				$hiC = round(($hiF - 32) / 1.8);
				$this->heatindex = $this->format_temp($hiF, $hiC);
			}
		}

		private function set_wind_chill($tempF) {
			// Calculate Wind Chill Temperature based on temperature in °F and
			// wind speed in miles per hour
			if ($tempF < 51 && $this->wind != 'calm') {
				$pieces = explode(' ', $this->wind);
				$windspeed = (integer) $pieces[1];   // 2nd item in string, wind speed must be in miles per hour
				if ($windspeed > 3) {
					$chillF = 35.74 + 0.6215 * $tempF - 35.75 * pow($windspeed, 0.16) + 0.4275 * $tempF * pow($windspeed, 0.16);
					$chillF = round($chillF);
					$chillC = round(($chillF - 32) / 1.8);
					$this->windchill = $this->format_temp($chillF, $chillC);
				}
			}
		}

	private function set_altimeter($part, &$metarPtr, &$group) {
		// Decodes altimeter or barometer information.
		// Format is Annnn where nnnn represents a real number as nn.nn in inches of Hg, 
		// or Qpppp where pppp = hectoPascals.
		// Some other common conversion factors:
		//   1 millibar = 1 hPa
		//   1 in Hg = 0.02953 hPa
		//   1 mm Hg = 25.4 in Hg = 0.750062 hPa
		//   1 lb/sq in = 0.491154 in Hg = 0.014504 hPa
		//   1 atm = 0.33421 in Hg = 0.0009869 hPa
		if (preg_match('/^(A|Q)([0-9]{4})/', $part, $pieces)) {
			if ($pieces[1] == 'A') {
				$pressureIN = substr($pieces[2], 0, 2) . '.' . substr($pieces[2],2);  // units are inches Hg
				$pressureHPA = round($pressureIN / 0.02953);                          // convert to hectoPascals
			}
			else {
				$pressureHPA = (integer) $pieces[2];              // units are hectoPascals
				$pressureIN = round(0.02953 * $pressureHPA, 2);   // convert to inches Hg
			}
			$this->pressure = "$pressureIN in";
			$metarPtr++;
			$group++;
		}
		else {
			$group++;
		}
	}

	public function get_metar() {       return $this->metar; }
	public function get_station() {     return $this->station; }
	public function get_observed() {    return $this->observed; }
	public function get_age() {         return $this->age; }
	public function get_wind() {        return $this->wind; }
	public function get_visibility() {  return $this->visibility; }
	public function get_conditions() {  return $this->conditions; }
	public function get_clouds() {      return $this->clouds; }
	public function get_temperature() { return $this->temperature; }
	public function get_dewpoint() {    return $this->dewpoint; }
	public function get_humidity() {    return $this->humidity; }
	public function get_windchill() {   return $this->windchill; }
	public function get_heatindex() {   return $this->heatindex; }
	public function get_pressure() {    return $this->pressure; }
	public function get_errors() {      return $this->errors; }

	public function print_formatted_wx ($property, $label, $width, $beginTag, $endTag) {
		// This method formats weather data for monospaced fonts with dots padding between property
		// and data value, such as "label......value". Method requires the given class property,
		// its descriptive label, width or number of characters, beginning and ending tag.
		// If property does not exist or value is blank, nothing is printed.
		if (property_exists('Weather', $property)) {
			$value = trim($this->{$property});
			if (strlen($value) != 0) {
				$padLength = $width - strlen($label) - strlen($value);
				if (preg_match('/&#x/',$value)) $padLength += 5;
				if ($padLength <= 0) $padLength = 2;
				echo $beginTag . htmlentities($label) . str_repeat('.', $padLength) . $value . $endTag;
			}
		}
	}

    public function list_properties() {
    	// This method lists each class property and its value.
    	foreach ($this as $property => $value) {
    		echo "$property => $value<br>\n";
       }
    }

}  // end Weather class

?>