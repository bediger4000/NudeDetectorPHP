<?php
class NudeDetector {

	// Image and direct information about it.
	var $file_name;
	var $image = NULL;    // "truecolor" image
	var $height;
	var $width;

	// Map of skin-colored pixels
	var $detection_function = 'YCbCr_skin_detector';
	var $skin_map = NULL; // "palette" image, black = skin, white = background.
	var $background_pixel_count;
	var $skin_pixel_count;

	// Connected regions of skin-colored pixels
	var $region_count = 0;
	var $region_numbers;  // 2-D, one entry per pixel, region number of that pixel
	var $region_population = NULL; // Associate, key region number, value population
	var $sorted_region_populations;

	// "Bounding Polygon" stuff.
	var $hull = NULL;
	var $hull_area = 0;
	var $skin_pixels_hull_count = 0;

	// Configuration.
	var $reasons = FALSE;

	public function __construct($file_name = null, $skin_detection = 'YCbCr') {
		$this->detection_function = $skin_detection . '_skin_detector';
		if ($file_name)
			$this->set_file_name($file_name);
	}

	public function __destruct() {
		if ($this->image) imagedestroy($this->image);
		if ($this->skin_map) imagedestroy($this->skin_map);
		if ($this->region_numbers) $this->region_numbers = NULL;
	}

	public function set_file_name($file_name) {
		$this->file_name = $file_name;
		if ($this->file_name) {
			if ($this->image) {
				imagedestroy($this->image);
				$this->image = NULL;
			}
			if ($this->skin_map) {
				imagedestroy($this->skin_map);
				$this->skin_map = NULL;
			}
			$this->create_image();
		} else
			$this->image = NULL;

		if ($this->image) {
			$this->width = imagesx($this->image);
			$this->height = imagesy($this->image);
		} else {
			$this->height = -1;
			$this->width = -1;
		}
	}

	function create_image() {

		$info = getimagesize($this->file_name);

		switch ($info[2]) {
		case IMAGETYPE_GIF:
			if (($this->image = imagecreatefromgif($this->file_name)) !== false) {
				imagepalettetotruecolor($this->image);
			}
			break;
		case IMAGETYPE_JPEG:
			if (function_exists('imagecreatefromjpeg')) {
				$this->image = imagecreatefromjpeg($this->file_name);
				return;
			}
			break;
		case IMAGETYPE_PNG:
			$this->image = imagecreatefrompng($this->file_name);
			break;
		}
		return;
	}

	function map_skin_pixels() {
		if ($this->image == NULL || $this->height < 0 || $this->width < 0)
			return;

		if ($this->skin_map) {
			imagedestroy($this->skin_map);
			$this->region_numbers = NULL;
		}

		$this->skin_map = imagecreate($this->width, $this->height);
		$black = imagecolorallocate($this->skin_map, 0,0,0);
		$white = imagecolorallocate($this->skin_map, 255,255,255);
		$this->background_pixel_count = 0;
		$this->skin_pixel_count = 0;


		foreach (range(0, $this->width -1) as $x) {
			foreach (range(0, $this->height - 1) as $y) {
				$rgb = imagecolorat($this->image, $x, $y);
				$r = ($rgb >> 16) & 0xFF;
				$g = ($rgb >> 8) & 0xFF;
				$b = $rgb & 0xFF;

				# XXX - Need to have ability to use YCbCr, too.

				$color = $white;
				if ($this->{$this->detection_function}($r, $g, $b)) {
					++$this->skin_pixel_count;
					$color = $black;
				} else
					++$this->background_pixel_count;

				imagesetpixel($this->skin_map, $x, $y, $color);
			}
		}
	}

	function islamic_skin_detector($R, $G, $B) {
		# skin detection criteria from "Image Filter v 1.0".
		$islamic_skin_colored = FALSE;
		if ($R >= 0x79 && $R <= 0xFE
			&& $G >= 0x3B && $G <= 0xC5
			&& $B >= 0x24 && $G <= 0xBF)
				$islamic_skin_colored = TRUE;
		return $islamic_skin_colored;
	}

	function YCbCr_skin_detector($r, $g, $b) {

		list($Y, $Cb, $Cr) = $this->calculate_YCbCr($r, $g, $b);

		$r = FALSE;
		# "Explicit Image Dectition using YCbCr Space Color Model
		# as Skin Detection", Basilio, Torees, Perez, Medina and Meana
		if (/* $YCbCr[0] > 80.0 && */ $Cb >= 80. && $Cb <= 120.
			&& $Cr >= 133. && $Cr <= 173.)
				$r = TRUE;

		return $r;
	}

	function HSV_skin_detector($r, $g, $b) {

		list($H, $S, $V) = $this->calculate_HSV($r, $g, $b);

		$r = FALSE;

		if ( $H > 0. && $H < 0.25
			&& $S > 0.15 && $S < 0.9
			&& $V > 0.20 && $V < 0.95)
				$r = TRUE;

		return $r;
	}

	function calculate_everything() {
		if ($this->skin_map == NULL)
			$this->map_skin_pixels();

		if ($this->region_numbers == NULL)
			$this->determine_regions();

		if ($this->region_population == NULL)
			$this->count_region_population();

		if ($this->hull == NULL)
			$this->find_bounding_polygon();

		if ($this->hull_area == 0)
			$this->calculate_hull_area();

		if ($this->skin_pixels_hull_count == 0)
			$this->count_bounding_polygon();
	}

	function decision() {
		$total_pixel_count
			= (float)($this->skin_pixel_count
				+ $this->background_pixel_count);

		if ($total_pixel_count == 0)
			return FALSE;

		$total_skin_portion = (float)$this->skin_pixel_count/$total_pixel_count;

		# Criteria (a)
		if ($total_skin_portion < 0.15) {
			if ($this->reasons)
				printf("Total skin pixes are %.2f%% of total pixel count, < 15%%\n", $total_skin_portion * 100.);
			return FALSE;
		}

		# Criteria (b)
		$largest_region_portion = (float)$this->sorted_region_populations[0][1]/(float)$this->skin_pixel_count;
		$next_region_portion =  (float)$this->sorted_region_populations[1][1]/(float)$this->skin_pixel_count;
		$third_region_portion =  (float)$this->sorted_region_populations[2][1]/(float)$this->skin_pixel_count;
		if ($largest_region_portion < 0.35 && $next_region_portion < 0.30 && $third_region_portion < 0.30) {
			if ($this->reasons)
				printf("3 largest skin regions are %.0f%%, %.0f%% and %.0f%%, less than 35%%, 30%% and 30%%, respectively.\n",
					$largest_region_portion*100.,
					$next_region_portion*100.,
					$third_region_portion*100.
				);
			return FALSE;
		}

		# Criteria (c)
		if ($largest_region_portion < 0.45) {
			if ($this->reasons)
				printf("Largest skin-colored region is %.2f%% of total image pixels, < 45%%\n");
			return FALSE;
		}

		# Criteria (d)
		if ($total_skin_portion < 0.30) {
			$in_polygon_portion = (float)$this->skin_pixels_hull_count/(float)$this->hull_area;
			if ($in_polygon_portion < 0.55) {
				if ($this->reasons)
					printf("Skin pixels %.0f%% of total, < 30%%, in-bounding polygon skin pixels %.0f%%, < 55%% of bounding polygon area\n",
						$total_skin_portion*100, $in_polygon_portion*100);
				return FALSE;
			}
		}

		# Criteria (e)
		# "If the number of skin regions is more than 60 and the average
		#  intensity within the polygon is less than 0.25, the image is not nude."
		# WTF does "intensity" mean?

		# Criteria (f)
		# "Otherwise, the image is nude."
		return TRUE;
	}

	function is_nude() {

		if ($this->skin_map == NULL)
			$this->map_skin_pixels();

		$total_pixel_count = (float)($this->skin_pixel_count +$this->background_pixel_count);

		if ($total_pixel_count == 0)
			return FALSE;

		$total_skin_portion = (float)$this->skin_pixel_count/$total_pixel_count;

		# Criteria (a)
		if ($total_skin_portion < 0.15) {
			if ($this->reasons)
				printf("Total skin pixes are %.2f%% of total pixel count, < 15%%\n", $total_skin_portion * 100.);
			return FALSE;
		}

		if ($this->region_numbers == NULL)
			$this->determine_regions();

		if ($this->region_population == NULL)
			$this->count_region_population();

		if ($this->sorted_region_populations == NULL)
			$this->sort_regions_by_population();

		# Criteria (b)
		$largest_region_portion = (float)$this->sorted_region_populations[0][1]/(float)$this->skin_pixel_count;
		$next_region_portion =  (float)$this->sorted_region_populations[1][1]/(float)$this->skin_pixel_count;
		$third_region_portion =  (float)$this->sorted_region_populations[2][1]/(float)$this->skin_pixel_count;
		if ($largest_region_portion < 0.35 && $next_region_portion < 0.30 && $third_region_portion < 0.30) {
			if ($this->reasons)
				printf("3 largest skin regions are %.0f%%, %.0f%% and %.0f%%, less than 35%%, 30%% and 30%%, respectively.\n",
					$largest_region_portion*100.,
					$next_region_portion*100.,
					$third_region_portion*100.
				);
			return FALSE;
		}

		# Criteria (c)
		if ($largest_region_portion < 0.45) {
			if ($this->reasons)
				printf("Largest skin-colored region is %.2f%% of total image pixels, < 45%%\n", $largest_region_portion*100);
			return FALSE;
		}

		# Criteria (d)
		if ($total_skin_portion < 0.30) {
			if ($this->hull == NULL)
				$this->find_bounding_polygon();

			if ($this->hull_area == 0)
				$this->calculate_hull_area();

			if ($this->skin_pixels_hull_count == 0)
				$this->count_bounding_polygon();

			$in_polygon_portion = (float)$this->skin_pixels_hull_count/(float)$this->hull_area;
			if ($in_polygon_portion < 0.55) {
				if ($this->reasons)
					printf("Skin pixels %.0f%% of total, < 30%%, in-bounding polygon skin pixels %.0f%%, < 55%% of bounding polygon area\n",
						$total_skin_portion*100, $in_polygon_portion*100);
				return FALSE;
			}
		}

		# Criteria (e)
		# "If the number of skin regions is more than 60 and the average
		#  intensity within the polygon is less than 0.25, the image is not nude."
		# WTF does "intensity" mean?

		# Criteria (f)
		# "Otherwise, the image is nude."
		return TRUE;
	}

	# Count all skin pixels that lie within the
	# "bounding polygon". Done by coloring pixels on $this->skin_map
	# (which is black & white) in grey, and then scanning across the image.
	# If you hit a grey pixel, and you're not "in" the polygon, you're "in" on
	# the next pixel.  If you're "in" and hit a grey pixel, now you're "out".
	# Have to account for "jaggies" in pixelated lines that leave more than
	# a single pixel per horizontal scan colored grey.
	function count_bounding_polygon() {
		if ($this->skin_map == NULL)
			return;

		# Draw grey lines on $this->skin_map to represent
		# the bounding polygon.
		$grey = imagecolorallocate($this->skin_map, 255, 0, 0);
		$black = imagecolorclosest($this->skin_map, 0, 0, 0);
		$n = count($this->hull);
		foreach (range(0, count($this->hull) - 2) as $i)
			imageline($this->skin_map, $this->hull[$i][0], $this->hull[$i][1], $this->hull[$i+1][0], $this->hull[$i+1][1], $grey);

		$white = imagecolorclosest($this->skin_map, 255, 255, 255);
		for ($y = 0; $y < $this->height; ++$y) {
			$in_polygon = FALSE;
			$left_pixel_color = $white;
			for ($x = 0; $x < $this->width; ++$x) {
				$pixel_color = imagecolorat($this->skin_map, $x, $y);
				if ($pixel_color == $grey) {
					# Because we scan from left to right, the following accounts
					# for "lines" that leave multiple y-coord pixels colored.
					if ($left_pixel_color != $grey)
						$in_polygon = $in_polygon? FALSE: TRUE;
					# Else, leave $in_polygon flag alone.
				}
				$left_pixel_color = $pixel_color;

				$skin_pixel = ($pixel_color == $black);

				if ($in_polygon && $skin_pixel)
					++$this->skin_pixels_hull_count;
				else if (!$in_polygon && $skin_pixel)
					imagesetpixel($this->skin_map, $x, $y, $white);
			}
		}
	}

	# $this->hull[] contains vertices of a convex hull.
	# Calculate hull's area.
	function calculate_hull_area() {

		if ($this->hull == NULL || count($this->hull) < 1)
			return;

		// first and last entries identical.
		$n = count($this->hull) - 1;

		$a = 0;

		for ($i = 0; $i < $n  - 1; ++$i)
			$a += $this->hull[$i][0]*$this->hull[$i + 1][1];
		$a += $this->hull[$n-1][0]*$this->hull[0][1];
		for ($i = 0; $i < $n  - 1; ++$i)
			$a -= $this->hull[$i+1][0]*$this->hull[$i][1];
		$a -= $this->hull[0][0]*$this->hull[$n-1][1];

		$this->hull_area = abs((float)$a/2);
	}

	# Create a list of regions/populations sorted by
	# population, largest to smallest. This is a little
	# weird, as you can easily end up with more than one
	# region with a given population. This could cause
	# problems with Ap-Apid's algorithm - you could interpret
	# "3 largest regions" many different ways.
	function sort_regions_by_population() {
		if ($this->region_population == NULL)
			return;

		$populations = array();
		foreach ($this->region_population as $regno => $population) {
			if ($regno != 0)
				$populations[] = $population;
		}
		rsort($populations);
		$prev_sorted_regions = array();

		# This is an instance var because some data in it gets
		# used here, and in deciding "nude/not nude" later.
		$this->sorted_region_populations = array();

		$max = count($populations);

		# This is going to be an O(n^2) sort of $this->region_population,
		# but there's usually only a few regions. Hopefully, it's never
		# prohibitive. Another reason to make 1- or 2-pixel regions into
		# "background", I suppose.
		for ($i = 0; $i < $max; ++$i) {
			$pop = $populations[$i];
			foreach ($this->region_population as $regno => $population) {
				if (!in_array($regno, $prev_sorted_regions)) {
					if ($pop == $population) {
						$this->sorted_region_populations[] = array($regno, $population);
						$prev_sorted_regions[] = $regno;
					}
				}
			}
		}

		# $this->sorted_region_population[] is a largest-to-smallest
		# list of [region number, population of that region] pairs.
	}

	# Ap-Apid doesn't define what a "bounding polygon" is,
	# so I'm assuming that a convex hull of the 4 points for
	# each of the 3 largest regions constitutes a "bounding polygon".
	# If Ap-Apid means some kind of concave hull around the
	# top/left/right/bottom points of the 3 largest regions,
	# the convex hull will over-count the number of skin pixels
	# in the "bounding polygon".
	function find_bounding_polygon() {

		if ($this->sorted_region_populations == NULL)
			$this->sort_regions_by_population();

		if ($this->region_population == NULL)
			return;

		if ($this->sorted_region_populations == NULL)
			return;

		# Arrays to store coords of top, left, right and bottom
		# coords of pixels of each of 3 largest regions.
		# $top[0] will be the largest region's uppermost (closest
		# to X-axis (x,y) coords, $top[1] will be 2nd largest
		# region's coords, and so on. 12 coordinate pairs in all.
		$top =    array_fill(0, 3, array(0, $this->height + 1));
		$left =   array_fill(0, 3, array($this->width + 1, 0));
		$right =  array_fill(0, 3, array(0, 0));
		$bot =    array_fill(0, 3, array(0, 0));

		$this->find_extreme_coords($top, $left, $right, $bot);

		# Points that are either on or in a convex hull.
		$points = array();

		for ($n = 0; $n < 3; ++$n) {
			$points[] = $top[$n];
			$points[] = $left[$n];
			$points[] = $right[$n];
			$points[] = $bot[$n];
		}


		# "Gift Wrapping" method - only 12 possible points so this
		# doesn't take much time.
		$lower_left_point = $this->find_lower_left($points);
		$this->hull = array($lower_left_point);

		$vector = array(0, 1);
		$last_magnitude = 1.0;
		$current_point = array($lower_left_point[0], $lower_left_point[1]);

		$point_count = 0;
		$number_of_points = count($points);

		do {
			$min_angle = 600.0;
			$next_point = FALSE;

			foreach ($points as $candidate_point) {

				// Artifact of foreach(): $current_point can turn up.
				if ($candidate_point[0] == $current_point[0]
					&& $candidate_point[1] == $current_point[1]) continue;

				$delta_x = $candidate_point[0] - $current_point[0];
				$delta_y = $candidate_point[1] - $current_point[1];
				$magnitude = sqrt($delta_x*$delta_x + $delta_y*$delta_y);

				$algebraic_dot_product = $delta_x * $vector[0]
					+ $delta_y * $vector[1];

				$cosine_angle = $algebraic_dot_product/$magnitude;
				$angle = acos($cosine_angle);

				if ($angle < $min_angle) {
					$next_point = array($candidate_point[0], $candidate_point[1]);
					$min_angle = $angle;
					$last_magnitude = $magnitude;
					$last_delta_x = $delta_x;
					$last_delta_y = $delta_y;
				}
			}

			$vector = array($last_delta_x/$last_magnitude, $last_delta_y/$last_magnitude);
			$current_point = $next_point;

			$this->hull[] = $current_point;
			++$point_count;

		} while ($point_count <= $number_of_points
			&& !($current_point[0] == $lower_left_point[0]
				&& $current_point[1] == $lower_left_point[1])
		);

		// $this->hull has convex hull vertices in order
		// from lower left (upper left as we view image)
		// around and back to lower left.  First and last
		// entries identical.
	}

	function find_lower_left($points) {
		$min_x = $this->width + 2;
		$min_y = $this->height + 2;
		$lower_left_point = FALSE;

		foreach ($points as $point) {
			if ($point[0] < $min_x) {
				$lower_left_point = array($point[0],$point[1]);
				$min_x = $point[0];
				$min_y = $point[1]; 
			} else if ($point[0] == $min_x) {
				if ($point[1] < $min_y) {
					$lower_left_point = array($point[0],$point[1]);
					$min_x = $point[0];
					$min_y = $point[1];
				}
			}
		}
		return $lower_left_point;
	}

	# Another possible deviation from Ap-Apid's algorithm:
	# if 4 or more largest regions have the same population,
	# you could pick *different* "3 largest regions".
	function find_extreme_coords(&$top, &$left, &$right, &$bot) {

		$r1 = $this->sorted_region_populations[0][0];
		$r2 = $this->sorted_region_populations[1][0];
		$r3 = $this->sorted_region_populations[2][0];

		for ($x = 0; $x < $this->width; ++$x) {
			for ($y = 0; $y < $this->height; ++$y) {
				$n = -1;
				$regno = $this->region_numbers[$x][$y];
				switch ($regno) {
				case $r1:
					$n = 0;
					break;
				case $r2:
					$n = 1;
					break;
				case $r3:
					$n = 2;
					break;
				default:
					break;
				}

				# Ap-Apid isn't too careful about what "topmost"
				# or "leftmost" or *most means. I effectively choose the
				# leftmost element with the minimum Y-coord, you could
				# choose rightmost or the middle minimum Y-coord. Same
				# with top/bottom/right coords. The first pixel with a
				# *most coordinate wins. Changing ">" to ">=", "<" to "<="
				# could choose different extreme points.
				if ($n >= 0) {
					# Topmost
					if ($y < $top[$n][1]) {
						$top[$n][0] = $x;
						$top[$n][1] = $y;
					}

					# Leftmost
					if ($x < $left[$n][0]) {
						$left[$n][0] = $x;
						$left[$n][1] = $y;
					}

					# Rightmost
					if ($x > $right[$n][0]) {
						$right[$n][0] = $x;
						$right[$n][1] = $y;
					}

					# Bottommost
					if ($y > $bot[$n][1]) {
						$bot[$n][0] = $x;
						$bot[$n][1] = $y;
					}
				}
			}
		}

	}

	// Determine regions of skin colored pixels, where "region" is
	// based on whether a pixel touches another pixle left/right/above/below
	// it. See http://en.wikipedia.org/wiki/Connected-component_labeling
	// This finds 4-connected (top, left, bottom, right) regions. 4-connected
	// and 8-connected regions aren't different in real images very often.
	//
	// This is unfortunately large, as the algorithm is unfortunately
	// complicated.
	//
	// Also, Ap-Apid doesn't give details of what algorithm he used
	// to choose "regions". This one just considers a 4-pixel surrounding
	// region, rather than an 8-pixel region. No corner-to-corner touching
	// considered.
	//
	// Computationally expensive, too.
	function determine_regions() {
		if ($this->skin_map == NULL)
			return;

		$this->region_population = NULL;

		$equiv_reqions = array(0);  // Index of 0 is invalid
		$this->region_numbers = array_fill(0, $this->width+1, array_fill(0, $this->height+1, 0));

		// For now, $this->region_numbers[$x][$y], if nonzero, is an index into
		// $equiv_regions[], and that value is the region number. That changes
		// later in this function.

		$region_sequence = 0; // Used to number regions as they turn up.

		$black = imagecolorclosest($this->skin_map, 0, 0, 0);
		$black_pixel_count = 0;

		# Outer loop over Y-values, inner loop over X-values,
		# so that North and West pixels (relative to pixel at [$x,$y]) have
		# a region number assigned to them already.
		for ($y = 0; $y < $this->height; ++$y) {
			for ($x = 0; $x < $this->width; ++$x) {
				if (imagecolorat($this->skin_map, $x, $y) == $black) {
					++$black_pixel_count;

					$west_region_no = 0;  // A region number, not index into $equiv_regions[]
					$north_region_no = 0; // Also a region number.

					# West pixel
					if ($x - 1 >= 0 && imagecolorat($this->skin_map, $x - 1, $y) == $black) {
						// Pixel at ($x,$y) is in same region as pixel at ($x-1,$y)
						if (($west_region_idx = $this->region_numbers[$x-1][$y]) > 0) {
							$this->region_numbers[$x][$y] = $west_region_idx;
							$west_region_no = $equiv_regions[$west_region_idx];
						}
					}
		
					# North pixel
					if ($y - 1 >= 0 && imagecolorat($this->skin_map, $x, $y-1) == $black) {
						// Pixel at ($x,$y) is in same region as pixel at ($x,$y-1)
						if (($north_region_idx = $this->region_numbers[$x][$y-1]) > 0) {
							$this->region_numbers[$x][$y] = $north_region_idx;
							$north_region_no = $equiv_regions[$north_region_idx];
						}
					}
		
					# Both pixels - current pixel connects previonsly
					# unconnected regions.
					if ($west_region_no != 0 && $north_region_no != 0 && $west_region_no != $north_region_no) {
						$connected_region_no = $north_region_no;
						$other_region_no = $west_region_no;
						if ($north_region_no > $west_region_no) {
							$connected_region_no = $west_region_no;
							$other_region_no = $north_region_no;
						}
						$equiv_regions[$west_region_idx]  = $connected_region_no;
						$equiv_regions[$north_region_idx] = $connected_region_no;
		
						// The apparent useless indirection of $equiv_regions comes
						// into play here: we only need iterate over $equiv_regions
						// to connect regions, not over the entire contents of $this->reqion_indexes
						// each time we find a connection between previously separate regions.
						for ($i = 1; $i < count($equiv_regions); ++$i)
							if ($equiv_regions[$i] == $other_region_no)
								$equiv_regions[$i] = $connected_region_no;
		
					} else if (($west_region_no == 0) && ($north_region_no == 0)) {
						# New region
						$this->region_numbers[$x][$y] = $region_sequence;
						$equiv_regions[] = $region_sequence++;
					} // else - Nothing more to do.
				}
			}
		}

		# XXX - should check if $black_pixel_count and $this->skin_pixel_count equate.

		# Make a map, called $renumber, so as to get consecutive
		# numbers, starting at 1, for the skin-colored regions.
		$renumber = array(0 => 0);
		$idx = 1;
		$max = count($equiv_regions);
		for ($i = 0; $i < $max; ++$i) {
			$regno = $equiv_regions[$i];
			if (!array_key_exists($regno, $renumber))
				$renumber[$regno] = $idx++;
		}

		# Fix up $this->region_numbers to hold actual region numbers, not
		# indexes into $equiv_regions[]. Note the use of $renumber.
		for ($x = 0; $x < $this->width; ++$x) {
			$a = $this->region_numbers[$x];
			for ($y = 0; $y < $this->height; ++$y) {
				$this->region_numbers[$x][$y]
					= $renumber[$equiv_regions[$a[$y]]];
			}
		}

	}

	// Fill in $this->region_population, a 1-D associative array,
	// keys are region numbers, values are pixel populations.
	function count_region_population() {
		if ($this->region_numbers == NULL)
			return;
		$this->hull = NULL;
		$this->hull_area = 0;
		$this->skin_pixels_hull_count = 0;

		$this->region_population = array();
		for ($x = 0; $x < $this->width; ++$x) {
			$a = $this->region_numbers[$x];
			for ($y = 0; $y < $this->height; ++$y) {
				$regno = $a[$y];
				if (isset($this->region_population[$regno]))
					++$this->region_population[$regno];
				else
					$this->region_population[$regno] = 1;
			}
		}

		# Clean up very tiny regions.
		$tiny_region_numbers = array();
		foreach ($this->region_population as $regno => $pop) {
			if ($pop < 10) {
				unset($this->region_population[$regno]);
				$tiny_region_numbers[] = $regno;
			}
		}

		for ($x = 0; $x < $this->width; ++$x) {
			$a = $this->region_numbers[$x];
			for ($y = 0; $y < $this->height; ++$y) {
				$regno = $a[$y];
				if (in_array($regno, $tiny_region_numbers)) {
					$this->region_numbers[$x][$y] = 0;
				}
			}
		}

		$this->region_count = count($this->region_population);
	}

	// 0 <= $r <= 255, and for $g and $b, all integers.
	function calculate_YCbCr($r, $g, $b)
	{
		return array(
			(int)( 16.0 + 0.256788*$r + 0.504129*$g +  0.097905*$b), # Y
			(int)(128.0 - 0.148223*$r - 0.290992*$g +  0.439215*$b), # Cb
			(int)(128.0 + 0.439215*$r - 0.367788*$g -  0.071427*$b), # Cr
		);
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

	function create_colored_regions() {
		if ($this->skin_map == NULL) return NULL;
		if ($this->region_numbers == NULL) return NULL;
		if ($this->region_count == 0) return NULL;

		$region_map = imagecreate($this->width, $this->height);

		$colrat = array();

		# Non-skin pixels in region 0, color them white.
		$colorat[0] = imagecolorallocate($region_map, 255,255,255);

		$colorat[$this->sorted_region_populations[0][0]] = imagecolorallocate($region_map, 255,0,0);
		$colorat[$this->sorted_region_populations[1][0]] = imagecolorallocate($region_map, 0,255,0);
		$colorat[$this->sorted_region_populations[2][0]] = imagecolorallocate($region_map, 0,0,255);

		foreach (range(3, min(255, $this->region_count - 2)) as $i) {
			$colorat[$this->sorted_region_populations[$i][0]]
				= imagecolorallocate(
					$region_map,
					rand(0,250),
					rand(0,250),
					rand(0,250)
				);
		}

		foreach (range(0, $this->height - 1) as $y) {
			foreach (range(0, $this->width - 1) as $x) {
				$region_no = $this->region_numbers[$x][$y];
				imagesetpixel($region_map, $x, $y, isset($colorat[$region_no])?  $colorat[$region_no]: $colorat[0]);
			}
		}

		return $region_map;
	}
}
?>
