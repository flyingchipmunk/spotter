<?php

/*
Spotter - Calculates skydive jump run and exit points
Copyright (C) 2010  Matthew C. Veno <matt@flyingchipmunk.com>

This program is free software; you can redistribute it and/or
modify it under the terms of the GNU General Public License
as published by the Free Software Foundation; either version 2
of the License, or (at your option) any later version.

This program is distributed in the hope that it will be useful,
but WITHOUT ANY WARRANTY; without even the implied warranty of
MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
GNU General Public License for more details.

You should have received a copy of the GNU General Public License
along with this program; if not, write to the Free Software
Foundation, Inc., 51 Franklin Street, Fifth Floor, Boston, MA  02110-1301, USA.
Also add information on how to contact you by electronic and paper mail.
*/

  // $Id: index.php 132 2013-03-07 05:52:38Z mveno $

include('config.inc.php');

# compatibility checks
$CONFIG['supports_bzip2'] = function_exists('bzdecompress') ? TRUE : FALSE;

$DEBUG = ($CONFIG['allowDebug'] == true && !empty($_REQUEST['debug']) && intval($_REQUEST['debug']) == 1) ? true : false;

$header = $body = $footer = $buffer = $raw = '';
$foundStart = $foundEnd = $updateCache = false;
$selectedOptionHtml = "selected='selected'";

$airSpeedKnots = intval($_REQUEST['airspeed']);
// let's make sure there's a default at least
$airSpeedKnots = $airSpeedKnots > 0
               ? $airSpeedKnots
               : $CONFIG['defaultAirspeed'];

$desiredSeparation = intval($_REQUEST['separation']);
// let's make sure there's a default at least 1000ft, REF: SIM 5-7 C
$desiredSeparation = $desiredSeparation >= $CONFIG['defaultSeparation']
                   ? $desiredSeparation
                   : $CONFIG['defaultSeparation'];

$includeCanopyDrift = $_REQUEST['canopyDrift'] == "exclude" ? false : true;

$selectedCanDftInc = $selectedCanDftExc = "";
if ($includeCanopyDrift)
{
    $selectedCanDftInc = $selectedOptionHtml;
}
else
{
    $selectedCanDftExc = $selectedOptionHtml;
}

$time = intval($_REQUEST['time']);
$compass = $_REQUEST['compass'] == 'magnetic' ? 'magnetic' : 'true';

$compassOffset = 0;
$selectedComTru = $selectedComMag = "";
if ($compass == 'magnetic')
{
    $compassOffset = $CONFIG['lzDeclination'];
    $selectedComMag = $selectedOptionHtml;
}
else
{
    $selectedComTru = $selectedOptionHtml;
}

$selected6 = $selected12 = $selected24 = "";
if ( $time == 12 )
{
    $selected12 = $selectedOptionHtml;
}
elseif ( $time == 24 )
{
    $selected24 = $selectedOptionHtml;
}
else
{
    $time = '06';
    $selected6 = $selectedOptionHtml;
}

$cacheFile = $CONFIG['dataDir']."/".$CONFIG['airportId']."_".$time.".dat";

if ($DEBUG) $body .= "<pre>";

// force cache update if it doesn't exist or if it's older than 1 day
if (file_exists($cacheFile) && ( (time() - filemtime($cacheFile))/60/60/24 < 1 ) )
{
	if ( $CONFIG['supports_bzip2'] )
	{
    	$raw = bzdecompress(file_get_contents($cacheFile));
    }
    else
    {
    	$raw = file_get_contents($cacheFile);
    }


    if ($DEBUG) $body .= $raw;

    // check to make sure cache is still valid for today
    $valid = array();
    preg_match('/VALID\s+(\d\d)(\d\d)(\d\d)(.)\s+FOR USE (\d\d)(\d\d)-(\d\d)(\d\d)(.)\s*/', $raw, $valid);
    if ($DEBUG) $body .= 'valid: '. print_r($valid,true);

    $offset = ($time == 12 || $time == 24) ? 1 : 0;

    // if the valid time is in the past, update cache
    if ($valid[1] - $offset != date('d'))
    {
        if ($DEBUG)
        {
            $body .= "update cache based only on day? YES! day: ".$valid[1]." - offset: $offset != ".date('d')."\n";
        }
        $updateCache = true;
    }
    elseif ( ( $valid[1] - $offset == date('d') ) &&
             ( time() > gmmktime( $valid[2], $valid[3], '0', gmdate("n"), $valid[1], gmdate("Y") ) ) )
    {
        if ($DEBUG)
        {
            $body .= "update cache based on day and past time? YES!\n";
            $body .= "time: " . time() . "\n";
            $body .= "gmmktime: " . gmmktime( $valid[2], $valid[3], '0', gmdate("n"), $valid[1], gmdate("Y") ) . "\n";
        }
        $updateCache = true;
    }
}
else
{
    $updateCache = true;
}

if ($DEBUG)
{
    $body .= "update cache? ";
    $body .= $updateCache == true ? 'yes' : 'no';
    $body .= "\n";
}

if ($updateCache)
{
    if ($DEBUG) $body .= "updating cache...\n";

    $raw = '';

    $url = $CONFIG['baseUrl'].'?&region=all&layout=off&fcast='.$time.'&level=low';

    if ($DEBUG) $body .= "url: " . $url . "\n";

    $handle = fopen($url, "r");

    // First parse the raw block of data
    if ($handle)
    {
        while (!feof($handle))
        {
            $buffer = fgets($handle, 4096);
            if ( strpos($buffer, "<pre>") !== false )
            {
                $foundStart = true;
            }

            if ( strpos($buffer, "</pre>") !== false )
            {
                $foundEnd = true;
            }

            if ( $foundStart && !$foundEnd )
            {
                $raw .= $buffer;
            }
        }
        fclose($handle);
    }

    if ($DEBUG) $body .= $raw;

    // update cache file
    $handle = fopen($cacheFile,"w");
    if ($handle)
    {
		if ( $CONFIG['supports_bzip2'] )
		{
        	fwrite($handle,bzcompress($raw));
        }
        else
        {
        	fwrite($handle,$raw);
        }
        fclose($handle);
    }
    else
    {
        echo "<h2>Error updating cache!</h2>";
        exit;
    }

    if ( !$foundStart || !$foundEnd )
    {
        // Something went wrong, end the show
        echo "<h2>Error retrieving data!</h2>";
        exit;
    }
}


// Parse the header data
$basedOn = $valid = $winds = $windsHeader = array();
preg_match('/DATA BASED ON\s+(\d\d)(\d\d)(\d\d)(.)\s/', $raw, $basedOn);
if ($DEBUG) $body .= 'basedOn: '. print_r($basedOn,true);

preg_match('/VALID\s+(\d\d)(\d\d)(\d\d)(.)\s+FOR USE (\d\d)(\d\d)-(\d\d)(\d\d)(.)\s*/', $raw, $valid);
if ($DEBUG) $body .= 'valid: '. print_r($valid,true);

$airportId = $CONFIG['airportId'];
preg_match("/$airportId\s+(\d\d)(\d\d)\s+(\d\d)(\d\d)(.)(\d\d)\s+(\d\d)(\d\d)(.)(\d\d)\s+(\d\d)(\d\d)(.)(\d\d)\s+(\d\d)(\d\d)(.)(\d\d)\s+/", $raw, $winds);
if ($DEBUG) $body .= 'winds: '. print_r($winds,true);

preg_match('/FT.+?18000/', $raw, $windsHeader);
if ($DEBUG) $body .= 'windsHeader: '. print_r($windsHeader,true);

// Prepare date/time stamps
// usage:: gmmktime  ([ int $hour= gmdate("H")  [, int $minute= gmdate("i")  [, int $second= gmdate("s")
//                    [, int $month= gmdate("n")  [, int $day= gmdate("j")  [, int $year= gmdate("Y") ]]]]]] )
$validDate = date("l, d-M-y H:i:s T", gmmktime($valid[2], $valid[3], '0', gmdate("n"), $valid[1], gmdate("Y") ) );
if ($DEBUG) $body .= "validDate: $validDate\n";

$forUseFromDate = date("H:i:s T", gmmktime($valid[5], $valid[6], '0', gmdate("n"), $valid[1], gmdate("Y") ) );
$forUseToDate = date("H:i:s T", gmmktime($valid[7], $valid[8], '0', gmdate("n"), $valid[1], gmdate("Y") ) );
$basedOnDate = date("l, d-M-y H:i:s T", gmmktime($basedOn[2], $basedOn[3], '0', gmdate("n"), $basedOn[1], gmdate("Y") ) );
if ($DEBUG) $body .= "basedOnDate: $basedOnDate\n";
if ($DEBUG) $body .= "For use: $forUseFromDate to $forUseToDate\n";


// Prepare 3/6/9/12
$dir = $speed = $temp = array();

$dir['3k'] = (int)$winds[1] * 10;
$speed['3k'] = (int)$winds[2];
$temp['3k'] = '';

$dir['6k'] = (int)$winds[3] * 10;
$speed['6k'] = (int)$winds[4];
$temp['6k'] = $winds[5] == '-' ? (int)$winds[6] * -1 : (int)$winds[6];

$dir['9k'] = (int)$winds[7] * 10;
$speed['9k'] = (int)$winds[8];
$temp['9k'] = $winds[9] == '-' ? (int)$winds[10] * -1 : (int)$winds[10];

$dir['12k'] = (int)$winds[11] * 10;
$speed['12k'] = (int)$winds[12];
$temp['12k'] = $winds[13] == '-' ? (int)$winds[14] * -1 : (int)$winds[14];


// Light & Variable winds? means < 5 knots
$lightAndVar = array( '3k' => false,
                      '6k' => false,
                      '9k' => false,
                      '12k' => false );

foreach ( $dir as $elevation => $degrees )
{
    if ( $dir[$elevation] == 990 && $speed[$elevation] == 0 )
    {
        $lightAndVar[$elevation] = true;
        if ($DEBUG) $body .= "light and variable at $elevation!\n";
    }
}

if ($DEBUG)
{
    $body .= "dir:\n";
    $body .= print_r($dir, true);
    $body .= "speed:\n";
    $body .= print_r($speed, true);
    $body .= "\n";
}

// Do we need to adjust any numbers due to coding?
foreach ( $dir as $elevation => $degrees )
{
    if ( $degrees > 360 && $degrees >= 510 && $degrees <= 860 )
    {
        $dir[$elevation] -= 50;
        $speed[$elevation] += 100;
    }
    else if ( $degrees == 360 )
    {
        $dir[$elevation] = 0;
    }
}

// Convert knots to mph
foreach ( $speed as $elevation => $knots )
{
    $speed[$elevation] = $knots * 1.15077945;
}

// Convert celsius to fahrenheit
foreach ( $temp as $elevation => $celsius )
{
    $temp[$elevation] = (212-32)/100 * $celsius + 32;
}

// Convert to magnetic north?
if ($compass == 'magnetic')
{
    foreach ( $dir as $elevation => $degrees )
    {
        $dir[$elevation] -= $compassOffset;
    }
}

if ($DEBUG)
{
    $body .= "dir after:\n";
    $body .= print_r($dir,true);
    $body .= "speed after:\n";
    $body .= print_r($speed,true);
}


// Calculate spot
$spotDir = $spotSpeed = $freefallDrift = $canopyDrift = 0;

$lightAndVarCount = $spotDir = $spotSpeed = 0;
foreach ( $dir as $elevation => $degrees )
{
    if ($lightAndVar[$elevation] === false)
    {
        $spotDir += $dir[$elevation];
        $spotSpeed += $speed[$elevation];
    }
    else
    {
        $lightAndVarCount++;
    }
}

$spotDir = $spotDir / (4 - $lightAndVarCount);
$spotSpeed = $spotSpeed / 4;
$canopyDrift = $lightAndVar['3k'] === true
             ? (1/60) * 3
             : ( ( ($speed['3k']*2 + $speed['3k']/3) /3) /60) * 3;

$freefallDrift = $spotSpeed / 60;

// Calculate separation time
$airSpeedMph = $airSpeedKnots * 1.15077945;  // 1 knot = 1.15077945 mph
$feetPerSecond = 0.681818182; // 1 foot per mph = 0.681818182 seconds
$separationTime = ceil($desiredSeparation/(($airSpeedMph - $speed['12k'])/$feetPerSecond));

function toRad($degrees)
{
    return $degrees * (pi()/180);
}

function toDeg($radians)
{
    return $radians * (180/pi());
}

/**
 * Convert from decimal degrees to degrees minutes seconds
 */
function toSexagesimal($decimal)
{
    $return = array();
    $return['degrees'] = intval($decimal);
    $return['minutes'] = intval(($decimal - $return['degrees'])*60);
    $return['seconds'] = sprintf( '%.4f', ((($decimal - $return['degrees'])*60) - $return['minutes'])*60 );

    // make mins and secs positive
    $return['minutes'] = $return['minutes'] < 0 ? $return['minutes'] * -1 : $return['minutes'];
    $return['seconds'] = $return['seconds'] < 0 ? $return['seconds'] * -1 : $return['seconds'];

    return $return;
}

/**
 * Calculate a destination latitude and longitude.
 *
 * Given:
 *   latStart = starting latitude in decimal notation
 *   lonStart = starting longitude in decimal notation
 *   bearing = cardinal direction (true north) in degrees
 *   distance = units of miles
 *
 * Returns:
 *   array( latDest => destination latitude in decimal notation,
 *          lonDest => destination longitude in decimal notation
 *        );
 *
 *   NULL on failure
 **/
function destPoint($latStart,$lonStart,$bearing,$distance)
{
  //$radius = 6371; // earth's mean radius in km
  $radius = 3958.75587; // earth's mean radius in miles
  $latStart = toRad($latStart);
  $lonStart = toRad($lonStart);
  $bearing = toRad($bearing);

  $latDest = asin( sin($latStart) * cos($distance/$radius) + cos($latStart) * sin($distance/$radius) * cos($bearing) );
  $lonDest = $lonStart + atan2(sin($bearing) * sin($distance/$radius) * cos($latStart),
                              cos($distance/$radius) - sin($latStart) * sin($latDest));
  //$lonDest = ($lonDest+pi())%(2*pi()) - pi();  // normalise to -180...+180

  if (is_nan($latDest) || is_nan($lonDest)) return null;
  return array( 'latDest' => toDeg($latDest), 'lonDest' => toDeg($lonDest) );
}

// first calculate the canopy drift point "opening point"
$canopyDriftDir = '';

// Need to smartly calculate encase we have L&V
if ($lightAndVar['3k'] === true)
{
    // best guess same dir as 6k
    if ($lightAndVar['6k'] === false)
    {
        $canopyDriftDir = $dir['6k'];
    }
    // ok, last resort try 12k
    elseif( $lightAndVar['12k'] === false)
    {
        $canopyDriftDir = $dir['12k'];
    }
    // holy strange winds batman!
    else
    {
        $canopyDriftDir = '0';
    }
}
else
{
    $canopyDriftDir = $dir['3k'];
}

if ($includeCanopyDrift)
{
    $openingPoint = destPoint($CONFIG['lzLat'],$CONFIG['lzLon'],$canopyDriftDir,$canopyDrift);
}
else
{
    // in effect we are setting opening point to dead center LZ
    $openingPoint = array( latDest => $CONFIG['lzLat'], lonDest => $CONFIG['lzLon'] );
}

// then calculate the freefall drift point "exit point" relative to "opening point"
$exitPoint = destPoint($openingPoint['latDest'],$openingPoint['lonDest'],$spotDir,$freefallDrift);

if ($DEBUG) $body .= "openingPoint: ". print_r($openingPoint,true);
if ($DEBUG) $body .= "exitPoint: ". print_r($exitPoint,true);

$latSpot = $openingPoint['latDest'];
$lonSpot = $openingPoint['lonDest'];
$latFarSpot = $exitPoint['latDest'];
$lonFarSpot = $exitPoint['lonDest'];

$pepLatSex = toSexagesimal($CONFIG['lzLat']);
$pepLatSexHtml = $pepLatSex['degrees'] .'&deg; '. $pepLatSex['minutes'] ."&#39; ". $pepLatSex['seconds'] ."&#34;";
$pepLonSex = toSexagesimal($CONFIG['lzLon']);
$pepLonSexHtml = $pepLonSex['degrees'] .'&deg; '. $pepLonSex['minutes'] ."&#39; ". $pepLonSex['seconds'] ."&#34;";

$spotLatSex = toSexagesimal($latSpot);
$spotLatSexHtml = $spotLatSex['degrees'] .'&deg; '. $spotLatSex['minutes'] ."&#39; ". $spotLatSex['seconds'] ."&#34;";
$spotLonSex = toSexagesimal($lonSpot);
$spotLonSexHtml = $spotLonSex['degrees'] .'&deg; '. $spotLonSex['minutes'] ."&#39; ". $spotLonSex['seconds'] ."&#34;";

$farSpotLatSex = toSexagesimal($latFarSpot);
$farSpotLatSexHtml = $farSpotLatSex['degrees'] .'&deg; '. $farSpotLatSex['minutes'] ."&#39; ". $farSpotLatSex['seconds'] ."&#34;";
$farSpotLonSex = toSexagesimal($lonFarSpot);
$farSpotLonSexHtml = $farSpotLonSex['degrees'] .'&deg; '. $farSpotLonSex['minutes'] ."&#39; ". $farSpotLonSex['seconds'] ."&#34;";

$freefallDriftHtml = sprintf('%.2f',$freefallDrift);
$openingDir = $canopyDriftDir;

if ($includeCanopyDrift)
{
    $canopyDriftHtml = sprintf('%.2f',$canopyDrift);
}
else
{
    $canopyDriftHtml = '0.00';
}

if ($DEBUG)
{
    $mapType = "ROADMAP";
}
else
{
    $mapType = "SATELLITE";
}

$script_path = dirname($_SERVER['SCRIPT_NAME']);

// shortcuts for heredocs, uggh
$googleApi = $CONFIG['googleApi'];
$lzLat = $CONFIG['lzLat'];
$lzLon = $CONFIG['lzLon'];
$pageTitle = $CONFIG['pageTitle'];
$controlWidth = $CONFIG['mapWidth'] - 30;

$header = <<<HTML
<html>
<head><title>$pageTitle</title></head>
<script type="text/javascript" src="//maps.googleapis.com/maps/api/js?key=$googleApi"></script>
<script type="text/javascript">
function initialize() {
  var map = new google.maps.Map(document.getElementById("map_canvas"), {
      center: new google.maps.LatLng($lzLat, $lzLon),
      zoom: 13,
      mapTypeId: google.maps.MapTypeId.$mapType
  });

  var lzPoint = new google.maps.LatLng($lzLat, $lzLon);

  var bounds = new google.maps.LatLngBounds();
  function fit(){
    map.panTo(bounds.getCenter());
    map.fitbounds(bounds);
  }

  var lzMarker = new google.maps.Marker({
      icon: '$script_path/images/landing-point.png',
      position: lzPoint,
      map: map
  });

  var theSpotFFdrift = new google.maps.LatLng($latSpot, $lonSpot);
  var theSpotFFCanopyDrift = new google.maps.LatLng($latFarSpot, $lonFarSpot);

  var theSpotFFdriftMarker = new google.maps.Marker({
      icon: '$script_path/images/opening-point.png',
      position: theSpotFFdrift,
      map: map
  });

  var theSpotFFCanopyDriftMarker = new google.maps.Marker({
      icon: '$script_path/images/exit-point.png',
      position: theSpotFFCanopyDrift,
      map: map
  });

  var jumpRun = new google.maps.Polyline({
     path: [
         lzPoint,
         theSpotFFdrift
     ],
     strokeColor: '#FF0000',
     strokeWeight: 7
  });

  var jumpRunFar = new google.maps.Polyline({
     path: [
         theSpotFFdrift,
         theSpotFFCanopyDrift
     ],
     strokeColor: '#FF0000',
     strokeWeight: 7
  });

  jumpRun.setMap(map);
  jumpRunFar.setMap(map);

  var lzHtml = "<span style='font-family:verdana;font-size:small'><b>Landing Zone</b><br />" +
               "$pepLatSexHtml, $pepLonSexHtml</span>";

  var theSpotHtml = "<span style='font-family:verdana;font-size:small'><b>Opening Point</b> (Canopy drift only)<br />" +
                    "<span style='font-family:verdana;font-size:small'>$canopyDriftHtml miles &#64; $openingDir" +
                    "&deg; $compass north</span><br />" +
                    "<span style='font-family:verdana;font-size:x-small'>$spotLatSexHtml, $spotLonSexHtml</span>";

  var theSpotCanopyHtml = "<span style='font-family:verdana;font-size:small'><b>Exit Point</b> (Freefall drift to Opening Point)<br />" +
                          "<span style='font-family:verdana;font-size:small'>$freefallDriftHtml miles &#64; $spotDir" +
                          "&deg; $compass north</span><br />" +
                          "<span style='font-family:verdana;font-size:x-small'>$farSpotLatSexHtml, $farSpotLonSexHtml</span>";

  var lzInfoWindow = new google.maps.InfoWindow({
      content: lzHtml
  });

  var theSpotInfoWindow = new google.maps.InfoWindow({
      content: theSpotHtml
  });

  var theSpotCanopyInfoWindow = new google.maps.InfoWindow({
      content: theSpotCanopyHtml
  });

  lzMarker.addListener('click', function() {
     lzInfoWindow.open(map, lzMarker);
  });

  theSpotFFdriftMarker.addListener('click', function() {
     theSpotInfoWindow.open(map, theSpotFFdriftMarker);
  });

  theSpotFFCanopyDriftMarker.addListener('click', function() {
     theSpotCanopyInfoWindow.open(map, theSpotFFCanopyDriftMarker);
  });

  bounds = new google.maps.LatLngBounds();
  var givenQuality = 40;
HTML;

if ($CONFIG['mapDrawQuarter'] === true)
{
  $header .= <<<HTML
  var givenRad = 0.402336; // 0.25 miles
  drawCircle(lzPoint, givenRad, givenQuality, '#D4FF00','3','0.5','#FFAA00', '0');
HTML;
}

if ($CONFIG['mapDrawHalf'] === true)
{
  $header .= <<<HTML
  var givenRad = 0.804672; // 0.50 miles
  drawCircle(lzPoint, givenRad, givenQuality, '#D4FF00','3','0.5','#FFAA00', '0');
HTML;
}

if ($CONFIG['mapDraw3Quarters'] === true)
{
  $header .= <<<HTML
  var givenRad = 1.207008; // 0.75 miles
  drawCircle(lzPoint, givenRad, givenQuality, '#D4FF00','3','0.5','#FFAA00', '0');
HTML;
}

if ($CONFIG['mapDrawMile'] === true)
{
  $header .= <<<HTML
  var givenRad = 1.609344; // 1.00 miles
  drawCircle(lzPoint, givenRad, givenQuality, '#D4FF00','3','0.5','#FFAA00', '0');
HTML;
}

$header .= <<<HTML
  //fit();

  function drawCircle(center, radius, nodes, liColor, liWidth, liOpa, myFillColor, fillOpa)
  {
    //calculating km/degree
    var latConv = center.distanceFrom(new google.maps.LatLng(center.lat()+0.1, center.lng()))/100;
    var lngConv = center.distanceFrom(new google.maps.LatLng(center.lat(), center.lng()+0.1))/100;

    //Loop
    var points = [];
    var step = parseInt(360/nodes)||10;
    for(var i=0; i<=360; i+=step)
    {
      var pint = new google.maps.LatLng(center.lat() + (radius/latConv * Math.cos(i * Math.PI/180)), center.lng() +
      (radius/lngConv * Math.sin(i * Math.PI/180)));
      points.push(pint);
      bounds.extend(pint); //this is for fit function
    }
    // points.push(points[0]);
    myFillColor = myFillColor||liColor||"#0055ff";
    liWidth = liWidth||2;

    var poly = new google.maps.Polygon({
        paths: points,
        strokeColor: liColor,
        strokeWeight: liWidth,
        stokeOpacity: liOpa,
        fillColor: myFillColor,
        fillOpacity: fillOpa
    });

    poly.setMap(map);

  }
}
google.maps.event.addDomListener(window, 'load', initialize);

/**
* @param {google.maps.LatLng} newLatLng
* @returns {number}
*/
google.maps.LatLng.prototype.distanceFrom = function(newLatLng) {
   // setup our variables
   var lat1 = this.lat();
   var radianLat1 = lat1 * ( Math.PI  / 180 );
   var lng1 = this.lng();
   var radianLng1 = lng1 * ( Math.PI  / 180 );
   var lat2 = newLatLng.lat();
   var radianLat2 = lat2 * ( Math.PI  / 180 );
   var lng2 = newLatLng.lng();
   var radianLng2 = lng2 * ( Math.PI  / 180 );
   // sort out the radius, MILES or KM?
   var earth_radius = 3959; // (km = 6378.1) OR (miles = 3959) - radius of the earth

   // sort our the differences
   var diffLat =  ( radianLat1 - radianLat2 );
   var diffLng =  ( radianLng1 - radianLng2 );
   // put on a wave (hey the earth is round after all)
   var sinLat = Math.sin( diffLat / 2  );
   var sinLng = Math.sin( diffLng / 2  );

   // maths - borrowed from http://www.opensourceconnections.com/wp-content/uploads/2009/02/clientsidehaversinecalculation.html
   var a = Math.pow(sinLat, 2.0) + Math.cos(radianLat1) * Math.cos(radianLat2) * Math.pow(sinLng, 2.0);

   // work out the distance
   var distance = earth_radius * 2 * Math.asin(Math.min(1, Math.sqrt(a)));

   // return the distance
   return distance;
}
</script>
<style type="text/css">
<!--
body, table, tr, td, input {
    font-family: Arial, Verdana, sans-serif;
    font-size: 12px;
    line-height: 1.4em;
}
pre {
    font-size: medium;
}
.data {
    text-align: right;
}
.cat {
    font-weight: bold;
    text-align: center;
}
.infoBox {
    background: #FAFAFA;
    border: 1px solid #E5E5E5;
    margin-bottom: 10px;
    padding: 12px 14px 10px;
    position: relative;
    z-index: 2;
    width: $controlWidth;
}
.layoutTbl {
    border: 0px;
    cellpadding: 2px;
    cellspacing: 0;
}
.aligncenter {
    text-align: center;
}
img.key {
    vertical-align: middle;
}
-->
</style>
</head>
<body>
HTML;

if ($DEBUG) $body .= <<<HTML
spot dir: $spotDir degrees $compass north
spot speed: $spotSpeed mph
freefall drift: $freefallDrift miles
canopy drift: $canopyDrift miles
</pre>
HTML;

$body .= "<div class='infoBox'><form action='$_SERVER[SCRIPT_NAME]' method='get'>";
$body .= <<<HTML
<table class='layoutTbl' width='100%'>
<tr>
  <td width='75%'>
HTML;

$body .= "Forecast data is based on computer forecasts generated on <strong>$basedOnDate</strong><br />";
$body .= "Valid <strong>$validDate</strong> between <strong>$forUseFromDate - $forUseToDate</strong><br /><br />";

$body .= "A <strong>$separationTime</strong> second delay between groups is required to obtain <input type='text' name='separation'
 value='$desiredSeparation' size='4' maxlength='4' /> foot separation assuming an airspeed of <input type='text' name='airspeed'
value='$airSpeedKnots' size='3' maxlength='3' /> knots on jumprun<br />\n";

$body .= <<<HTML
  </td>
  <td width='25%'>
HTML;

if ($CONFIG['allowDebug'])
{
    $body .= "Enable Debug <input type='checkbox' name='debug' value='1' ";
    if ($DEBUG) { $body .= "checked='checked' "; }
    $body .= "/> <br />";
}

$body .= <<<HTML
Forecast Hour <select name='time'>
  <option value='06' $selected6 >6hr</option>
  <option value='12' $selected12 >12hr</option>
  <option value='24' $selected24 >24hr</option>
</select><br />
Compass <select name='compass'>
  <option value='true' $selectedComTru >True North</option>
  <option value='magnetic' $selectedComMag >Magnetic North</option>
</select><br />
Canopy Drift <select name='canopyDrift'>
  <option value='include' $selectedCanDftInc >Included</option>
  <option value='exclude' $selectedCanDftExc >Excluded</option>
</select>

  </td>
</tr>
<tr>
  <td width='100%' colspan='2' align='center'>
<input type='submit' value='Update' />
  </td>
</tr>
</table>
</form>
</div>
HTML;

$body .= "<div id='map_canvas' style='width: ". $CONFIG['mapWidth'] ."px; height: ". $CONFIG['mapHeight'] ."px'></div>";

$body .= <<<HTML
<br />
<div class='infoBox aligncenter key'>
<img class='key' src='$script_path/images/exit-point.png' alt='Exit Point' /> = Exit Point &nbsp;
<img class='key' src='$script_path/images/opening-point.png' alt='Opening Point' /> = Opening Point &nbsp;
<img class='key' src='$script_path/images/landing-point.png' alt='Landing Point' /> = Landing Point
</div>
HTML;

if ($compass == 'magnetic')
{
    $dir_html = "Magnetic North";
}
else
{
    $dir_html = "True North";
}

$body .= <<<HTML
<br />
<table cellspacing='0' cellpadding='5' border='1'>
  <tr>
    <td class='cat'>Elevation<br />(feet MSL)</td>
    <td class='cat'>Direction<br />($dir_html)</td>
    <td class='cat'>Speed<br />(mph)</td>
    <td class='cat'>Temp<br />(F)</td>
  </tr>
HTML;

foreach ( $dir as $elevation => $degrees )
{
    if ( $lightAndVar[$elevation] === true )
    {
        $dir[$elevation] = 'L&amp;V';
        $speed[$elevation] = 'L&amp;V';
    }
    else
    {
        $speed[$elevation] = sprintf('%.2f',$speed[$elevation]);
    }
}

$body .= "<tr><td class='data'>3000</td><td class='data'>". $dir['3k'] ."</td><td class='data'>". $speed['3k'] ."</td><td class='data'>N/A</td></tr>\n";
$body .= "<tr><td class='data'>6000</td><td class='data'>". $dir['6k'] ."</td><td class='data'>". sprintf('%.2f',$speed['6k'])."</td><td class='data'>". $temp['6k'] ."</td></tr>\n";
$body .= "<tr><td class='data'>9000</td><td class='data'>". $dir['9k'] ."</td><td class='data'>". sprintf('%.2f',$speed['9k']) ."</td><td class='data'>". $temp['9k'] ."</td></tr>\n";
$body .= "<tr><td class='data'>12000</td><td class='data'>". $dir['12k'] ."</td><td class='data'>". sprintf('%.2f',$speed['12k']) ."</td><td class='data'>". $temp['12k'] ."</td></tr>\n";
$body .= "</table><br />\n";

$body .= "<pre>".$windsHeader[0]."\n".$winds[0]."</pre>";

if ($DEBUG) { $body .= "\$Id: index.php 132 2013-03-07 05:52:38Z mveno $"; }

$footer = "</body></html>";

echo $header . $body . $footer;

?>
