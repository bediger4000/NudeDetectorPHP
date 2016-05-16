#!/usr/bin/env php
<?php

$x = $argv[1];
$y = $argv[2];

$suffix = $argv[3];

$total_pixels = $x*$y;

$skin_pixel_count = intval(0.4*$total_pixels);

$percentage = array(0.45, 0.30, 0.20);
shuffle($percentage);

$skin_pixel_count1 = intval($percentage[0]*$skin_pixel_count);
$skin_pixel_count2 = intval($percentage[1]*$skin_pixel_count);
$skin_pixel_count3 = intval($percentage[2]*$skin_pixel_count);

$r1 = intval(sqrt($skin_pixel_count1/3.1415));
$r2 = intval(sqrt($skin_pixel_count2/3.1415));
$r3 = intval(sqrt($skin_pixel_count3/3.1415));

$img = imagecreatetruecolor($x, $y);
$white = imagecolorallocate($img, 255, 255, 255);
imagefilledrectangle($img, 0,0, $x, $y, $white);

$colors = array();
for ($i = 0; $i < 3; ++$i) {
	list($R, $G, $B) = find_a_color();
	$colors[] = imagecolorallocate($img, $R, $G, $B);
}

imagefilledellipse($img, $r1+2, $r1+2, 2*$r1, 2*$r1, $colors[0]);
imagefilledellipse($img, $x-$r2-2, $y/2, 2*$r2, 2*$r2, $colors[1]);
imagefilledellipse($img, $r3-2, $y-$r3-3, 2*$r3, 2*$r3, $colors[2]);

imagejpeg($img, $argv[3]);

exit;

function find_a_color()
{
	$is_skin_colored = FALSE;

	do {
		# Find a random YCbCr "skin color"
		# Based on "Explicit Image Detection using YCbCr
		# Space Color Model as Skin Detection"
		# http://www.wseas.us/e-library/conferences/2011/Mexico/CEMATH/CEMATH-20.pdf
		$Y = rand(0, 255);
		$Cb = rand(80, 120);
		$Cr = rand(133, 173);

		list($R, $G, $B) = RGBFromYCbCr($Y, $Cb, $Cr);

		# Is it "skin colored" according to Rigan Ap-Apid?
		$hsv_skin_colored = HSV_skin_detector($R, $G, $B);
		#$hsv_skin_colored = (($R>95) && ($G>40 && $G <100) && ($B>20) && ((max($R,$G,$B) - min($R,$G,$B)) > 15) && (abs($R-$G)>15) && ($R > $G) && ($R > $B));


		# Is it "skin colored according to 
		# http://www.phpclasses.org/package/3269-PHP-Determine-whether-an-image-may-contain-nudity.html
		# Which seems to be fairly widespread in PHP circles?
		$alsharif_skin_colored = FALSE;
		if ($R >= 0x79 && $R <= 0xFE
			&& $G >= 0x3B && $G <= 0xC5
			&& $B >= 0x24 && $G <= 0xBF)
				$alsharif_skin_colored = TRUE;

		$is_skin_colored = $hsv_skin_colored && $alsharif_skin_colored;

	} while (!$is_skin_colored);

	return array($R, $G, $B);
}

function  RGBFromYCbCr($y, $cb, $cr)
{
	$Y = (double) $y;
	$Cb = (double) $cb;
	$Cr = (double) $cr;

	$r = (int) ($Y + 1.40200 * ($Cr - 0x80));
	$g = (int) ($Y - 0.34414 * ($Cb - 0x80) - 0.71414 * ($Cr - 0x80));
	$b = (int) ($Y + 1.77200 * ($Cb - 0x80));

	$r = max(0, min(255, $r));
	$g = max(0, min(255, $g));
	$b = max(0, min(255, $b));

	return array($r, $g, $b);
}

function HSV_skin_detector($r, $g, $b)
{
	list($H, $S, $V) = calculate_HSV($r, $g, $b);

	$r = FALSE;

	if ( $H > 0. && $H < 0.25
		&& $S > 0.15 && $S < 0.9
		&& $V > 0.20 && $V < 0.95)
			$r = TRUE;

	return $r;
}

# $r, $g, $b between 0 and 255.
# Return HSV, all between 0.0 and 1.0
# XXX - Room here to remove "/255." from the code.
function calculate_HSV($r, $g, $b)
{
	$r = (float)$r/255.;
	$g = (float)$g/255.;
	$b = (float)$b/255.;

	# XXX - not the RGB -> HSV algorithm appearing in
	# Ap-Apid's paper. That one just doesn't work.
	$alpha = ($r + $r - $g - $b)/2.;
	$beta = 0.86602540378 * ($g - $b);
	$H = atan2($beta, $alpha); # $H in radians, -Pi to Pi
	if ($H < 0.0) $H += 2*M_PI;
	$H /= (2*M_PI); // $H in degrees
	$C = sqrt($alpha*$alpha + $beta*$beta);
	$V = max($r, $g, $b);
	if ($V == 0.0)
		$S = 0;
	else
		$S = $C/$V;

	return array($H, $S, $V);
}
