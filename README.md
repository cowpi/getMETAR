# getmetar.php
A php script to decode a METAR weather observation statement.

METAR stands for **Me**teorological **T**erminal **A**viation **R**outine weather report. These reports are conducted approximately every hour at hundreds of airports and weather stations around the world. For an example:

`KTIK 020256Z AUTO 04009KT 10SM OVC037 01/M04 A3010 RMK AO2 SLP209 T00091045 53009`

Every METAR is formed using certain formatting rules and must include the airport/weater station ID, time, wind speed, cloud cover, temperature, pressure, current weather, etc.

Years ago, METARs were a simple text file accessed through the National Oceanic and Atmospheric Administration website. Around 2013, they reorganized their internet presence and separated various departments into their own websites. Now, METARs are formatted in an XML file at  [Aviation Weather Center](https://aviationweather.gov).

METARs can be accessed and decoded online at [AWC - ADDS METARs](https://aviationweather.gov/metar "Aviation Weather Center"). Station IDs can be found [ADDS Station Table](https://aviationweather.gov/docs/metar/stations.txt).

Although the weather data can be parsed from the XML file, this script decodes the raw METAR.
