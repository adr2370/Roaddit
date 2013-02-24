<?php
$costs = array();
$times = array();
$circles = array();
$route = array();
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
	$costs["lodging"]=50;
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
	$times["lodging"]=10;
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
function getLatLong($address) {
	return json_decode(file_get_contents("http://maps.googleapis.com/maps/api/geocode/json?address=".urlencode($address)."&sensor=false"))->results[0]->geometry->location;
}
function getFormattedAddress($lat,$lng) {
	return json_decode(file_get_contents("http://maps.googleapis.com/maps/api/geocode/json?address=".$lat.",".$lng."&sensor=false"))->results[0]->formatted_address;
}
function getPhoto($photo) {
	$url="https://maps.googleapis.com/maps/api/place/photo?maxwidth=400&photoreference=".$photo."&sensor=true&key=AIzaSyD3j1urj8LrNyuu5-lViawfLG6nn1N6IJg";
	$ch = curl_init();
	curl_setopt($ch, CURLOPT_URL, $url);
	curl_setopt($ch, CURLOPT_HEADER, TRUE);
	curl_setopt($ch, CURLOPT_FOLLOWLOCATION, FALSE);
	curl_setopt($ch, CURLOPT_RETURNTRANSFER, TRUE);
	$a = curl_exec($ch);
	if(preg_match('#Location: (.*)#', $a, $r))
		return trim($r[1]);
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
	$cir=0;
	foreach($dir->steps as $s) {
		$x2=$s->start_location->lat;
		$y2=$s->start_location->lng;
		$x3=$s->end_location->lat;
		$y3=$s->end_location->lng;
		$distSoFar += $s->distance->value;
		if($cir<100&&$distSoFar>=$distLimit) {
			$distSoFar=0;
			$stepDist=($s->distance->value)/$circleSize;
			$stepDist=min($stepDist,ceil((100-$cir)/20));
			$dx=($x3-$x2)/($stepDist+1);
			$dy=($y3-$y2)/($stepDist+1);
			$dt=$s->duration->value/($stepDist+1);
			for($i=0;$cir<100&&$i<=$stepDist;$i++) {
				$res = getCircle($x2+$i*$dx, $y2+$i*$dy, $circleSize, $types);
				$food = getCircle($x2+$i*$dx, $y2+$i*$dy, $circleSize, "food");
				$hotel = getCircle($x2+$i*$dx, $y2+$i*$dy, $circleSize, "lodging");
				$cir++;
				$curr = array();
				array_push($curr,$timeSoFar+$i*$dt);
				array_push($places,$curr);
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
	if($cir==1) {
		foreach($urls as $count => $url) {
			if($count%3) continue;
			$res = json_decode(curl_multi_getcontent($curl_array[$count]));
			$food = json_decode(curl_multi_getcontent($curl_array[$count+1]));
			$hotel = json_decode(curl_multi_getcontent($curl_array[$count+2]));
			for($i=0;$i<20;$i++) {
				$places[$i][0]=0;
				$places[$i][1]=$res->results[$i];
				$places[$i][2]=$food->results[$i];
				$places[$i][3]=$hotel->results[$i];
			}
		}
	} else {
		foreach($urls as $count => $url) {
			if($count%3) continue;
			$res = json_decode(curl_multi_getcontent($curl_array[$count]));
			$food = json_decode(curl_multi_getcontent($curl_array[$count+1]));
			$hotel = json_decode(curl_multi_getcontent($curl_array[$count+2]));
			$curr = $places[$count/3];
			array_push($curr,$res->results[rand()%3]);
			array_push($curr,$food->results[rand()%3]);
			array_push($curr,$hotel->results[rand()%3]);
			$places[$count/3]=$curr;
			$timeSoFar += $s->duration->value;
			$numCircles++;
			if($numCircles>100) break;
		}
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
	for($i=0;$i<100;$i++) {
		shuffle($keys);
		for($j=0;$j<10;$j++) {
			$currPlaces=array();
			$cost=0;
			$time=$dur;
			$trip=array();
			$count=0;
			$lunchDone=0;
			$dinnerDone=0;
			$sleepDone=0;
			$currcost=0;
			$currtime=0;
			for($k=0;$k<=$j;$k++) {
				array_push($currPlaces,$keys[$k]);
			}
			sort($currPlaces);
			for($k=0;$k<=$j;$k++) {
				$currTime2=$startTime+$currtime+$p[$j][0];
				$hour=date("G",$currTime2);
				$which=1;
				if($lunchDone<=$sleepDone&&$hour>=12) {
					$which=2;
					$lunchDone++;
				} else if($dinnerDone<=$sleepDone&&$hour>=18) {
					$which=2;
					$dinnerDone++;
				} else if($hour>=22||$hour<=4) {
					$which=3;
					$sleepDone++;
				}
				$currplace=$p[$currPlaces[$k]][$which];
				if(!alreadyThere($currplace,$trip)) {
					$currcost=0;
					$currtime=0;
					for($l=0;$l<count($currplace->types);$l++) {
						$currcost=max($currcost,$costs[$currplace->types[$l]]);
						$currtime=max($currtime,3600*$times[$currplace->types[$l]]);
					}
					$cost+=$currcost;
					$time+=$currtime;
					array_push($trip,$currplace);
				}
			}
			if($cost<$maxCost&&$time<$maxDur) {
				$weight=$time+$lunchDone*5+$dinnerDone*5+$sleepDone*10;
				array_push($trip,"",$cost,$time,$weight);
				array_push($trips,$trip);
			}
		}
	}
	return sortTrips($trips);
}
function getEverything($x0,$y0,$x1,$y1,$maxDur,$maxCost,$types,$startTime) {
	global $costs;
	global $times;
	global $route;
	initArrays();
	$dir = getDirections($x0,$y0,$x1,$y1)->routes[0];
	//echo $dir->bounds->northeast->lat."|";
	//echo $dir->bounds->northeast->lng."|";
	//echo $dir->bounds->southwest->lat."|";
	//echo $dir->bounds->southwest->lng."|";
	$dir = $dir->legs[0];
	$p = getPlacesAlongRoute($x0,$y0,$x1,$y1,$dir,$types);
	$trips = getTripsFromPlaces($p,$dir->duration->value,$maxDur,$maxCost,$startTime);
	// print out trips
	$count = 1;
	//echo $count;
	$tripsGiven=array();
	if(count($trips)==0) {
		$places=0;
		$tripOutput="|Start|".$x0."|".$y0."|0|0|http://www.superclass.us/sitebuilder/images/yellow_box-618x547.jpg";
		$tripOutput.="|End|".$x1."|".$y1."|0|0|http://www.superclass.us/sitebuilder/images/yellow_box-618x547.jpg|";
		echo "|".$places.$tripOutput;
	} else {
		foreach($trips as $t) {
			$places=0;
			$tripOutput="|Start|".$x0."|".$y0."|0|0|http://www.superclass.us/sitebuilder/images/yellow_box-618x547.jpg";
			array_push($route,$x0.",".$y0);
			foreach($t as $place) {
				if($place=="") break;
				$places++;
				$tripOutput.="|".$place->name;
				array_push($route,$place->geometry->location->lat.",".$place->geometry->location->lng);
				$tripOutput.="|".$place->geometry->location->lat;
				$tripOutput.="|".$place->geometry->location->lng;
				$currcost=0;
				$currtime=0;
				for($k=0;$k<count($place->types);$k++) {
					$currcost=max($currcost,$costs[$place->types[$k]]);
					$currtime=max($currtime,$times[$place->types[$k]]);
				}
				$tripOutput.="|".$currcost; // cost in dollars
				$tripOutput.="|".$currtime; // time in hours
				$tripOutput.="|".getPhoto($place->photos[0]->photo_reference);
				//$tripOutput.="|".getFormattedAddress($place->geometry->location->lat,$place->geometry->location->lng);
			}
			$tripOutput.="|End|".$x1."|".$y1."|0|0|http://www.superclass.us/sitebuilder/images/yellow_box-618x547.jpg|";
			array_push($route,$x1.",".$y1);
			if(!in_array($tripOutput,$tripsGiven)) {
				array_push($tripsGiven,$tripOutput);
				//echo "|".$places.$tripOutput;
				$count--;
			}	
			if($count<=0) break;
		}
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

echo '<iframe width="850" height="700" frameborder="0" scrolling="no" marginheight="0" marginwidth="0" src="https://maps.google.com/maps?f=d&amp;source=s_d&amp;saddr=';
echo $route[0];
echo '&amp;daddr=';
echo $route[1];
for($i=2;$i<count($route);$i++) {
	echo "+to:".$route[$i];
}
echo '&amp;hl=en&amp;output=embed"></iframe>';
echo '<a href="index.php" style="font-size: 100px;">Go back</a>'
?>