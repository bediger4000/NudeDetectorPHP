#!/usr/bin/env php
<?php
include("NudeDetector.php");

$method = 'HSV';

if ($argc == 1)
	exit;

$method = $argv[1];

$input_file = $argv[2];

$detector = new NudeDetector($input_file, $method);
echo "map sking pixels\n";
$detector->map_skin_pixels();
echo "determine regions\n";
$detector->determine_regions();
echo "count region populations\n";
$detector->count_region_population();
$detector->sort_regions_by_population();

echo "create colored regions\n";
$img = $detector->create_colored_regions();

imagegif($img, $argv[3]);
imagedestroy($img);

