<?php
$costs = array();
$times = array();
function initArrays() {
	global $costs;
	global $times;
	$costs["amusement_park"]=50;
	$costs["aquarium"]=20;
	$costs["art_gallery"]=15;
	$costs["bar"]=50;
	$costs["bowling_alley"]=15;
	$costs["campground"]=0;
	$costs["casino"]=50;
	$costs["movie_theater"]=20;
	$costs["museum"]=10;
	$costs["night_club"]=20;
	$costs["park"]=0;
	$costs["shopping_mall"]=0;
	$costs["spa"]=50;
	$costs["stadium"]=0;
	$costs["zoo"]=20;
	$times["amusement_park"]=8;
	$times["aquarium"]=4;
	$times["art_gallery"]=4;
	$times["bar"]=4;
	$times["bowling_alley"]=2;
	$times["campground"]=4;
	$times["casino"]=5;
	$times["movie_theater"]=2;
	$times["museum"]=4;
	$times["night_club"]=4;
	$times["park"]=2;
	$times["shopping_mall"]=5;
	$times["spa"]=5;
	$times["stadium"]=1;
	$times["zoo"]=5;
}
function sortTrips($trips) {
	function cmp($x, $y) {
		$a=$x[count($x)-1];
		$b=$y[count($y)-1];
	    if ($a == $b) {
	        return 0;
	    }
	    return ($a > $b) ? -1 : 1;
	}
	uasort($trips, 'cmp');
	return $trips;
}
function getDirections($x0,$y0,$x1,$y1) {
	return json_decode(file_get_contents("http://maps.googleapis.com/maps/api/directions/json?origin=".$x0.",".$y0."&destination=".$x1.",".$y1."&sensor=false"));
}
function getCircle($x,$y,$s,$type) {
	return json_decode(file_get_contents("https://maps.googleapis.com/maps/api/place/nearbysearch/json?location=".$x.",".$y."&radius=".$s."&types=".$type."&sensor=false&key=AIzaSyD3j1urj8LrNyuu5-lViawfLG6nn1N6IJg"));
}
function getLatLong($address) { // getLatLong("")->lat and getLatLong("")->lng
	return json_decode(file_get_contents("http://maps.googleapis.com/maps/api/geocode/json?address=".$address."&sensor=false"))->results[0]->geometry->location;
}
function getPhoto($photo) {
	return file_get_contents("https://maps.googleapis.com/maps/api/place/photo?maxwidth=400&photoreference=".$photo."&sensor=true&key=AIzaSyD3j1urj8LrNyuu5-lViawfLG6nn1N6IJg");
}
function getPlacesAlongRoute($x0,$y0,$x1,$y1,$dir,$types) {
	$queryLimit = 20; // max number of total circles
	$distance = $dir->distance->value;
	$circleSize = min($distance/10,32000);
	$duration = $dir->duration->value;
	$distSoFar = $circleSize;
	$places = array();
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
				$curr = array();
				foreach($res->results as $r) {
					//$r->geometry->location
					//$r->name
					//getPhoto($r->photos[0]->photo_reference);
					//$r->types
					//$r->opening_hours->open_now
					array_push($curr,$r);
					break;
				}
				array_push($places,$curr);
			}
		}
	}
	return $places;
}
function getTripsFromPlaces($p,$dur,$maxDur,$maxCost) {
	global $costs;
	global $times;
	$trips=array();
	for($i=0;$i<pow(2,count($p));$i++) {
		$cost=0;
		$time=$dur;
		$trip=array();
		for($j=0;$j<count($p);$j++) {
			if(($i>>$j)%2) {
				$currcost=0;
				$currtime=0;
				$currplace=$p[$j][0];
				for($k=0;$k<count($currplace->types);$k++) {
					$currcost=max($currcost,$costs[$currplace->types[$k]]);
					$currtime=max($currtime,3600*$times[$currplace->types[$k]]);
				}
				$cost+=$currcost;
				$time+=$currtime;
				array_push($trip,$currplace);
			}
		}
		array_push($trip,$cost,$time);
		//if($cost<=$maxCost&&$time<=$maxDur) {
			array_push($trips,$trip);
		//}
	}
	return sortTrips($trips);
}
function getEverything($x0,$y0,$x1,$y1,$maxDur,$maxCost) {
	$types="amusement_park|aquarium|art_gallery|bar|bowling_alley|campground|casino|movie_theater|museum|night_club|park|shopping_mall|spa|stadium|zoo|natural_feature|point_of_interest";
	$dir = getDirections($x0,$y0,$x1,$y1)->routes[0]->legs[0];
	$p = getPlacesAlongRoute($x0,$y0,$x1,$y1,$dir,$types);
	$trips = getTripsFromPlaces($p,$dir->duration->value,$maxDur,$maxCost);
	
	// print out trips
	$count = 0;
	foreach($trips as $t) {
		echo json_encode($t[count($t)-1])."<br/>";
		$count++;
		if($count>10) break;
	}
}

// Getting variables
$startAddress=$_GET["startAddress"];
$endAddress=$_GET["endAddress"];
$beginDay=$_GET["beginDay"];
$beginTime=$_GET["beginTime"];
$endDay=$_GET["endDay"];
$endTime=$_GET["endTime"];
$budget=$_GET["budget"];
$categories=$_GET["categories"];

// getEverything(34.019308,-118.494466,34.070489,-118.450438); //ROC to UCLA
getEverything(34.070489,-118.450438,37.774940,-122.419430,$maxDur,$maxCost); //UCLA to San Fransisco
?>