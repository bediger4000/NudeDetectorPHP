#!/usr/bin/env php
<?php
include("NudeDetector.php");

$method = 'HSV';

if ($argc == 1)
	exit;

$input_file = $argv[1];

$detector = new NudeDetector($input_file, $method);
$detector->calculate_everything();

$img = $detector->skin_map;

imagegif($img, $argv[2]);
