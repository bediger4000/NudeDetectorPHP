#!/usr/bin/env php
<?php
include("NudeDetector.php");

if ($argc == 1)
	exit;

$hsv = new NudeDetector(null, 'HSV');
$hsv->reasons = TRUE;
$ycbcr = new NudeDetector(null, 'YCbCr');
$ycbcr->reasons = TRUE;
foreach ($argv as $idx => $arg) {
	if ($idx == 0) continue;
	$hsv->set_file_name($arg);
	$y = $hsv->is_nude();
    #$frac = (float)$hsv->$background_pixel_count/(float)$skin_pixel_count;
	echo $arg . "\tHSV\t";
	if ($y) echo "NUDITY!\n";
	else echo "Not nude.\n";
	$ycbcr->set_file_name($arg);
	$y = $ycbcr->is_nude();
	echo $arg . "\tYCbCr\t";
	if ($y) echo "NUDITY!\n";
	else echo "Not nude.\n";
}

