#!/usr/bin/env php
<?php
include("NudeDetector.php");

if ($argc == 1)
	exit;

$input_file = $argv[1];

$detector = new NudeDetector($input_file, 'HSV');
echo "map sking pixels\n";
$detector->map_skin_pixels();
echo "determine regions\n";
$detector->determine_regions();
echo "count region populations\n";
$detector->count_region_population();
$detector->sort_regions_by_population();

echo "create colored regions\n";
$img = $detector->create_colored_regions();

imagegif($img, $argv[2]);
imagedestroy($img);

