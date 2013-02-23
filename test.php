<?php
function distance($x0,$y0,$x1,$y1) {
	$R=6371; // in km
	$dLat=deg2rad($y1-$y0);
	$dLon=deg2rad($x1-$x0);
	$lat1=deg2rad($y0);
	$lat2=deg2rad($y1);
	$a=sin($dLat/2) * sin($dLat/2) + sin($dLon/2) * sin($dLon/2) * cos($lat1) * cos($lat2); 
	$c = 2 * atan2(sqrt($a), sqrt(1-$a));
	$d = $R * $c;
	return $d;
}
function getCircle($x,$y,$s,$type) {
	return file_get_contents("https://maps.googleapis.com/maps/api/place/nearbysearch/json?location=".$x.",".$y."&radius=".$s."&types=".$type."&sensor=false&key=AIzaSyD3j1urj8LrNyuu5-lViawfLG6nn1N6IJg");
}
function getAllPlaces($x0,$y0,$x1,$y1) {
	$circleSize = 16000; // in meters
	$dist = distance($x0,$y0,$x1,$y1); // in km
	$res = getCircle(34.06018, -118.41835, $circleSize);
}
$res=json_decode(getCircle(34.06018, -118.41835, 500));
foreach($res->results as $i) {
	$name=$i->name;
	$x=$i->geometry->location->lat;
	$y=$i->geometry->location->lng;
	$open=$i->opening_hours->open_now;
	$rating=$i->rating;
	echo $name." ".$x." ".$y." ".$open." ".$rating."<br/>";
}
?>
<html>
<head>
</head>
<body>
</body>
