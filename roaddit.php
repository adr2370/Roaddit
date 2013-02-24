<?php
$costs = array();
$times = array();
$circles = array();
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
	$costs["food"]=0;
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
	$times["food"]=3;
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
function alreadyThere($p,$t) {
	foreach($t as $a) {
		if($a->name==$p->name) return true;
	}
	return false;
}
function toUnixTimestamp($time) {
	$extraHour=0;
	if(substr($time,-2,2)=="PM") $extraHour=12;
	return mktime(substr($time,-8,2)+$extraHour,substr($time,-5,2),0,substr($time,0,2),substr($time,3,2),substr($time,6,4));
}
function getDirections($x0,$y0,$x1,$y1) {
	return json_decode(file_get_contents("http://maps.googleapis.com/maps/api/directions/json?origin=".$x0.",".$y0."&destination=".$x1.",".$y1."&sensor=false"));
}
function getCircle($x,$y,$s,$type) {
	global $circles;
	array_push($circles,"https://maps.googleapis.com/maps/api/place/nearbysearch/json?location=".$x.",".$y."&radius=".$s."&types=".$type."&sensor=false&key=AIzaSyD3j1urj8LrNyuu5-lViawfLG6nn1N6IJg");
}
function getLatLong($address) { // getLatLong("")->lat and getLatLong("")->lng
	return json_decode(file_get_contents("http://maps.googleapis.com/maps/api/geocode/json?address=".urlencode($address)."&sensor=false"))->results[0]->geometry->location;
}
function getPhoto($photo) {
	return file_get_contents("https://maps.googleapis.com/maps/api/place/photo?maxwidth=400&photoreference=".$photo."&sensor=true&key=AIzaSyD3j1urj8LrNyuu5-lViawfLG6nn1N6IJg");
}
function getPlacesAlongRoute($x0,$y0,$x1,$y1,$dir,$types) {
	global $circles;
	$queryLimit = 10; // max number of total circles
	$distance = $dir->distance->value;
	$circleSize = max(min($distance/10,32000),3200);
	$duration = $dir->duration->value;
	$distLimit = $circleSize*3;
	$distSoFar = $distLimit;
	$timeSoFar = 0;
	$places = array();
	foreach($dir->steps as $s) {
		$x2=$s->start_location->lat;
		$y2=$s->start_location->lng;
		$x3=$s->end_location->lat;
		$y3=$s->end_location->lng;
		$distSoFar += $s->distance->value;
		if($distSoFar>=$distLimit) {
			$distSoFar=0;
			$stepDist=($s->distance->value)/$circleSize;
			if($stepDist>5) $stepDist=5;
			$dx=($x3-$x2)/($stepDist+1);
			$dy=($y3-$y2)/($stepDist+1);
			$dt=$s->duration->value/($stepDist+1);
			for($i=0;$i<=$stepDist;$i++) {
				$res = getCircle($x2+$i*$dx, $y2+$i*$dy, $circleSize, $types);
				$food = getCircle($x2+$i*$dx, $y2+$i*$dy, $circleSize, "food");
			}
		}
	}
	$urls = $circles;
	$url_count = count($urls);
	$curl_array = array();
	$ch = curl_multi_init();
	foreach($urls as $count => $url) {
		$curl_array[$count] = curl_init($url);
		curl_setopt($curl_array[$count], CURLOPT_RETURNTRANSFER, 1);
		curl_multi_add_handle($ch, $curl_array[$count]);
	}
	do {
		curl_multi_exec($ch, $exec);
	} while($exec > 0);
	$numCircles=0;
	foreach($urls as $count => $url) {
		if($count%2==1) continue;
		$res = json_decode(curl_multi_getcontent($curl_array[$count]));
		$food = json_decode(curl_multi_getcontent($curl_array[$count+1]));
		$curr = array();
		foreach($res->results as $r) {
			array_push($curr,$r);
			foreach($food->results as $f) {
				array_push($curr,$f);
				array_push($curr,$timeSoFar+$i*$dt);
				break;
			}
			break;
		}
		array_push($places,$curr);
		$timeSoFar += $s->duration->value;
		$numCircles++;
		if($numCircles>100) break;
	}
	foreach($urls as $count => $url) {
		curl_multi_remove_handle($ch, $curl_array[$count]);
	}
	curl_multi_close($ch); 
	foreach($urls as $count => $url) {
		curl_close($curl_array[$count]);
	}
	return $places;
}
function getTripsFromPlaces($p,$dur,$maxDur,$maxCost,$startTime) {
	global $costs;
	global $times;
	$trips=array();
	$keys=array_keys($p);
	for($i=0;$i<1000;$i++) {
		shuffle($keys);
		for($j=0;$j<5;$j++) {
			$currPlaces=array();
			$cost=0;
			$time=$dur;
			$trip=array();
			$count=0;
			$lunchDone=0;
			$dinnerDone=0;
			$currcost=0;
			$currtime=0;
			for($k=0;$k<=$j;$k++) {
				array_push($currPlaces,$keys[$k]);
			}
			sort($currPlaces);
			for($k=0;$k<=$j;$k++) {
				$which=mt_rand()%2;
				$currplace=$p[$currPlaces[$k]][$which];
				if(!alreadyThere($currplace,$trip)) {
					if($which==1) {
						//check to make sure food is coming at right time
						$currTime=$startTime+$currtime+$p[$j][2];
						$hour=date("G",$currTime);
						if($hour>=12&&$hour<=14) {
							if($lunchDone) break;
							$lunchDone++;
						} else if($hour>=17&&$hour<=20) {
							if($dinnerDone) break;
							$dinnerDone++;
						}
					}
					$currcost=0;
					$currtime=0;
					for($k=0;$k<count($currplace->types);$k++) {
						$currcost=max($currcost,$costs[$currplace->types[$k]]);
						$currtime=max($currtime,3600*$times[$currplace->types[$k]]);
					}
					$cost+=$currcost;
					$time+=$currtime;
					array_push($trip,$currplace);
				}
			}
			if($cost<$maxCost&&$time<$maxDur) {
				$weight=$time+$lunchDone*5+$dinnerDone*5;
				array_push($trip,"",$cost,$time,$weight);
				array_push($trips,$trip);
			}
		}
	}
	return sortTrips($trips);
}
function getEverything($x0,$y0,$x1,$y1,$maxDur,$maxCost,$types,$startTime) {
	initArrays();
	$types="amusement_park|aquarium|art_gallery|bar|bowling_alley|campground|casino|movie_theater|museum|night_club|park|shopping_mall|spa|stadium|zoo|natural_feature|point_of_interest";
	$dir = getDirections($x0,$y0,$x1,$y1)->routes[0];
	echo $dir->bounds->northeast->lat."|";
	echo $dir->bounds->northeast->lng."|";
	echo $dir->bounds->southwest->lat."|";
	echo $dir->bounds->southwest->lng."|";
	$dir = $dir->legs[0];
	$p = getPlacesAlongRoute($x0,$y0,$x1,$y1,$dir,$types);
	$trips = getTripsFromPlaces($p,$dir->duration->value,$maxDur,$maxCost,$startTime);
	// print out trips
	$count = 1;
	echo $count;
	$tripsGiven=array();
	foreach($trips as $t) {
		$places=0;
		$tripOutput="|Start|".$x0."|".$y0;
		foreach($t as $place) {
			if($place=="") break;
			$places++;
			$tripOutput.="|".$place->name;
			$tripOutput.="|".$place->geometry->location->lat;
			$tripOutput.="|".$place->geometry->location->lng;
		}
		$tripOutput.="|End|".$x1."|".$y1;
		if(!in_array($tripOutput,$tripsGiven)) {
			array_push($tripsGiven,$tripOutput);
			echo "|".$places.$tripOutput;
			$count--;
		}	
		if($count<=0) break;
	}
}
// Getting variables
$startAddress=$_GET["starta"];
$endAddress=$_GET["enda"];
$startDateTime=$_GET["startdt"];
$endDateTime=$_GET["enddt"];
$budget=$_GET["budget"];
$categories=$_GET["categories"];
$startCoor=getLatLong($startAddress);
$endCoor=getLatLong($endAddress);
$duration=toUnixTimestamp($endDateTime)-toUnixTimestamp($startDateTime);
getEverything($startCoor->lat,$startCoor->lng,$endCoor->lat,$endCoor->lng,$duration,$budget,$categories,toUnixTimestamp($startDateTime));

	//$r->geometry->location
	//$r->name
	//getPhoto($r->photos[0]->photo_reference);
	//$r->types
	//$r->opening_hours->open_now
?>