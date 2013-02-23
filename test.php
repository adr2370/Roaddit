<?php
require_once('factual-php-driver/Factual.php');
$factual = new Factual("syEfnIulImFpvm9vRpHyUfjLjcnu5zgd7khd9qgk","CiHOa5CQuWttJ5fdtSnXHsy4BfCbWe6GVYMtbPA7");
$query = new FactualQuery;
$query->search("Sushi Santa Monica");
$res = $factual->fetch("places", $query);
print_r($res->getData());
?>
