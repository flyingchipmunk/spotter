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

$CONFIG = array(

// Browser title
'pageTitle' => 'Spot Calculation',

// Map width & height in pixels
'mapWidth' => '816',
'mapHeight' => '900',

// Set to true the concentric circles you want displayed around LZ
'mapDrawQuarter' => false,
'mapDrawHalf' => true,
'mapDraw3Quarters' => false,
'mapDrawMile' => true,

// Google Maps API Key
// This api needs to be registered to the domain or the map will not display
//   http://code.google.com/apis/maps/
'googleApi' => '--paste-your-api-here--',

// Path to the data cache directory, should not be directly
//   web accessible.
'dataDir' => '/full/path/to/data/dir',

// The specific 3 digit airport identifier in the forecast.
// Visit this page and click on your region on the map to see a list of
//   available airport identifiers:
//		http://aviationweather.gov/products/nws/winds/
'airportId' => 'BOS',

// Latitude and Longitude of the center of the landing zone
'lzLat' => '42.695566', // north
'lzLon' => '-71.552163', // west

// Declination offset used to calculate magnetic north, this value changes
//   over time, see following URL to retrieve value using the lat/lon
//   of the center of the landing zone.
//     http://www.ngdc.noaa.gov/geomagmodels/Declination.jsp
//
//   >>> Last Updated: March 2010 <<<
'lzDeclination' => 15,

// Set this to true to show the clickable "[D]" in output
// Set this to false to hide the clickable "[D]" links and ignore 'debug' param
'allowDebug' => false,

// Default airspeed used for separation calculation
'defaultAirspeed' => '85',

// Default desired horizontal separation for groups. REF: SIM 5-7 C (1000 ft)
'defaultSeparation' => '1000',

// This is the base URL for retrieving the raw forecast data,
//   don't change unless you know what you are doing!
'baseUrl' => "http://aviationweather.gov/products/nws/all",

);

?>
