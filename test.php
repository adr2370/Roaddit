<?php
function getDirections($x0,$y0,$x1,$y1) {
	return json_decode(file_get_contents("http://maps.googleapis.com/maps/api/directions/json?origin=".$x0.",".$y0."&destination=".$x1.",".$y1."&sensor=false"));
}
function getCircle($x,$y,$s,$type) {
	return json_decode(file_get_contents("https://maps.googleapis.com/maps/api/place/nearbysearch/json?location=".$x.",".$y."&radius=".$s."&types=".$type."&sensor=false&key=AIzaSyD3j1urj8LrNyuu5-lViawfLG6nn1N6IJg"));
}
function getLatLong($address) { // getLatLong("")->lat and getLatLong("")->lng
	return json_decode(file_get_contents("http://maps.googleapis.com/maps/api/geocode/json?address=".$address."&sensor=false"))->results[0]->geometry->location;
}
function getAllPlaces($x0,$y0,$x1,$y1) {
	$types="amusement_park|aquarium|art_gallery|bowling_alley|campground|casino|movie_theater|museum|night_club|park|shopping_mall|spa|stadium|zoo|natural_feature|point_of_interest";
	$queryLimit = 20; // max number of total circles
	$dir = getDirections($x0,$y0,$x1,$y1)->routes[0]->legs[0];
	$distance = $dir->distance->value;
	$circleSize = min($distance/10,32000);
	$duration = $dir->duration->value;
	$distSoFar = $circleSize;
	$returnVal = array();
	foreach($dir->steps as $s) {
		$x2=$s->start_location->lat;
		$y2=$s->start_location->lng;
		$x3=$s->end_location->lat;
		$y3=$s->end_location->lng;
		$distSoFar += $s->distance->value;
		if($distSoFar>=$circleSize) {
			$distSoFar=0;
			$stepDist=($s->distance->value)/$circleSize;
			if($stepDist>5) $stepDist=5;
			$dx=($x3-$x2)/($stepDist+1);
			$dy=($y3-$y2)/($stepDist+1);
			for($i=0;$i<=$stepDist;$i++) {
				if($queryLimit > 0) {
					$res = getCircle($x2+$i*$dx, $y2+$i*$dy, $circleSize, $types);
					$queryLimit--;
				}
				foreach($res->results as $r) {
					array_push($returnVal,$r->name." ".$r->geometry->location->lat." ".$r->geometry->location->lng);
					break;
				}
			}
		}
	}
	echo json_encode($returnVal);
}
/*
// Getting variables
$startAddress=$_GET["startAddress"];
$endAddress=$_GET["endAddress"];
$beginDay=$_GET["beginDay"];
$beginTime=$_GET["beginTime"];
$endDay=$_GET["endDay"];
$endTime=$_GET["endTime"];
$budget=$_GET["budget"];
$categories=$_GET["categories"];
*/
// getAllPlaces(34.019308,-118.494466,34.070489,-118.450438); //ROC to UCLA
 getAllPlaces(34.070489,-118.450438,37.774940,-122.419430); //UCLA to San Fransisco
?>