#!/usr/bin/env php
<?php
include("NudeDetector.php");

if ($argc == 1)
	exit;

$input_file = $argv[1];
$prefix = $argv[2];

$hsv = new NudeDetector($input_file, 'HSV');
$ycbcr = new NudeDetector($input_file, 'YCbCr');

$hsv->map_skin_pixels();
$ycbcr->map_skin_pixels();

$img = $hsv->skin_map;
imagegif($img, $prefix .'HSV.gif');
$img = $ycbcr->skin_map;
imagegif($img, $prefix .'YCbCr.gif');
