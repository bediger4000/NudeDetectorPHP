#!/usr/bin/env php
<?php
include("NudeDetector.php");

if ($argc == 1)
	exit;

$input_file = $argv[1];
$prefix = $argv[2];

$islamic = new NudeDetector($input_file, 'islamic');
$hsv = new NudeDetector($input_file, 'HSV');
$ycbcr = new NudeDetector($input_file, 'YCbCr');

$islamic->map_skin_pixels();
$hsv->map_skin_pixels();
$ycbcr->map_skin_pixels();

$img = $islamic->skin_map;
imagegif($img, $prefix .'islamic.gif');
$img = $hsv->skin_map;
imagegif($img, $prefix .'HSV.gif');
$img = $ycbcr->skin_map;
imagegif($img, $prefix .'YCbCr.gif');
