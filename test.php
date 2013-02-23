<?php
require_once('factual-php-driver/Factual.php');

function getCircle($x,$y,$s) {
	$factual = new Factual("syEfnIulImFpvm9vRpHyUfjLjcnu5zgd7khd9qgk","CiHOa5CQuWttJ5fdtSnXHsy4BfCbWe6GVYMtbPA7");
	$query = new FactualQuery;
	$query->within(new FactualCircle($x, $y, $s));
	$query->limit(10);
	$query->includeRowCount();
	$query->field("category")->search("Arts, Entertainment & Nightlife");
	return $factual->fetch("places", $query);
}
function getNumSitesForPlace($id) {
	$factual = new Factual("syEfnIulImFpvm9vRpHyUfjLjcnu5zgd7khd9qgk","CiHOa5CQuWttJ5fdtSnXHsy4BfCbWe6GVYMtbPA7");
	$query = new FactualQuery;
	$query->field("factual_id")->equal($id);
	try {
		$a = $factual->fetch("crosswalk", $query);
		$count = 0;
		foreach($a as $j) {
			$count++;
		}
		return $count;
	} catch (Exception $e) {
	    return 0;
	}
}
$res = getCircle(34.06018, -118.41835, 5000);
foreach($res as $i) {
	echo $i["name"]." - ".getNumSitesForPlace($i["factual_id"])."<br/>";
}
?>
<html>
<head>
</head>
<body>
<script type='text/javascript'>
	var x=eval(<?php echo json_encode($res); ?>);
	for(var i in x) {
		console.log(x[i].name);
	}
</script>
</body>
