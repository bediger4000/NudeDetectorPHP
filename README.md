# NudeDetectorPHP
## PHP implementation of Rigan Ap-Apid's "An Algorithm for Nudity Detection"

[An Algorithm for Nudity
Detection](http://citeseerx.ist.psu.edu/viewdoc/download?doi=10.1.1.96.9872&rep=rep1&type=pdf)
promises an algorithm to determine whether images contain nudity or not. This
is a PHP implementation of it.

### Choices

Ap-Apid's paper doesn't specify the algorithm as tightly as an implementor might wish.

An implementor could decide to use 4-connectivity or 8-connectivity to
determine the "connected regions" of skin-colored pixels. I used the [Two
Pass](https://en.wikipedia.org/wiki/Connected-component_labeling#Two-pass)
algorithm from Wikipedia, and 4-connectivity..

Ap-Apid's algorithm depends on the "three largest regions" of connected
skin-colored pixels.  Does "largest" just mean greatest pixel count, or do you
account for blocks of non-skin-colored pixels inside a region? How do you
decide on 3 largest if you have equal sizes (by whatever measure) of more than
one region?

The algorithm has you finding "the leftmost, the uppermost, the rightmost, and
the  lowermost  skin  pixels  of  the  three  largest  skin regions.  Use these
points as the corner points of a bounding polygon."

Which "leftmost"? If the leftmost pixels of a skin-colored region are along the
left hand side of an image, many pixels are "leftmost". Do you pick top, bottom
or middle leftmost?  The same question applies to the other 3 "x most" pixels
of each region.

The paper doesn't define "bounding polygon", I took it to be the convex hull
that surrounds the twelve points (topmost, leftmost, lowermost and rightmost)
of the 3 biggest skin-colored regions. But maybe Ap-Apid means something else,
an irregular, possibly concave polygon perhaps.

### Example Use

    #!/usr/bin/env php
    <?php
    include("NudeDetector.php");
    
    $detector = new NudeDetector(null, 'YCbCr');  # 'HSV' for alternate skin-color-detection
    foreach ($argv as $idx => $filename) {
		if ($idx == 0) continue;
		$detector->set_file_name($filename);
        if ($detector->is_nude()) { 
            // Deal with $filename as ritually unclean.
        } else {
			// $filename probably doesn't contain nudity
		}
    }

Notice that you can use the methods `set_file_name()` followed by `is_nude()`
without creating a new instance of `NudeDetector`.

### Example Programs

The example program illustrate features of `Class NudeDetectorPhp`
that are beyond a simple example. They also allow you to check on
intermediate steps in the nudity determination.

> `Checker.php imagefile [imagefile ...]`

Check each image file for nudity, using both HSV and YCbCr skin color models.
Print out reasons for why an image doesn't qualify as "containing nudity".

> `skin_map.php imagefile prefix`

Creates two GIF files, one each for HSV and YCbCr skin color models. The GIF
files are strictly black-and-white, every skin-colored pixel in the original
imae file colored black in the GIF files, all other pixels colored white in
the GIF files.  The GIF files have the names `prefixHSV.gif` and `prefixYCbCr.gif`.

Flicking between the two images with, for example, `feh` image viewer, gives
you some idea of differences in how the two skin-color models in
NudeDetectorPHP decide on skin colored pixels.

> `region_colors.php imagefile outputfile`

Creates a GIF-format output file with each connected region of skin-colored-pixels
in the original image as a different color in the output image.

> `bounding_polygon.php imagefile outputfile`

Creates a GIF-format output file that has the "bounding polygon" of Ap-Apid's
algorithm clipping the skin-colored pixels.

### Generate fake nudity
> `fake_nude.php x y imagefile`

Generates a JPEG-format imagefile `x` pixels wide and `y` pixels tall. This image
does not contain any nudity. In tact the "skin colors" it generates are usually not
even in the realm of biological, but `Checker.php` will consistently label the
image as containing nudity. Ha ha!

### How Does It Perform?

In my personal evaluation, not very well. It almost always identifies portraits
and head shots as "nudity".  It's confused by natural colors, sand, wood, rock
or soil or even leaves. This is a flaw of skin color detection, but Ap-Apid
does specify an HSV skin-color test.

Overall, I can't even characterize it as "too prudish" (lots of false positives),
or "too lecherous" (lots of false negatives).

### Other implementation

The most widely known implementation of Rigan Ap-Apid's algorithm is
[nude.js](https://github.com/pa7/nude.js). I didn't transliterate `nude.js` to
PHP, I implemented the algorithm in Ap-Apid's paper from scratch.
