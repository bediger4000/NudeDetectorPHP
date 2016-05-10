# NudeDetectorPHP
## PHP implementation of Rigan Ap-Apid's "An Algorithm for Nudity Detection"

[An Algorithm for Nudity Detection](http://citeseerx.ist.psu.edu/viewdoc/download?doi=10.1.1.96.9872&rep=rep1&type=pdf)
promises an algorithm to determine whether images contain nudity or not. This is a PHP implementation of it.

### Choices

Ap-Apid's paper doesn't specify the algorithm as tightly as an implementor might wish.

### Example Usage

    #!/usr/bin/env php
    <?php
    include("NudeDetector.php");
    
    $detector = new NudeDetector(null, 'YCbCr');
    foreach ($argv as $idx => $filename) {
        if ($detector->is_nude()) { 
            // Deal with $filename as ceremonially taboo
        }
    }

### Example Programs

The example program illustrate features of `Class NudeDetectorPhp`
that are beyond a simple example.

#### `Checker.php`

> `Checker.php imagefile [imagefile ...]`

Check each image file for nudity, using both HSV and YCbCr skin color models.
Print out reasons for why an image doesn't qualify as "containing nudity".

#### `skin_map.php`

> `skin_map.php imagefile prefix`

Creates two GIF files, one each for HSV and YCbCr skin color models. The GIF
files are strictly black-and-white, every skin-colored pixel in the original
imae file colored black in the GIF files, all other pixels colored white in
the GIF files.  The GIF files have the names `prefixHSV.gif` and `prefixYCbCr.gif`.

Flicking between the two images with, for example, `feh` image viewer, gives
you some idea of differences in how the two skin-color models in
NudeDetectorPHP decide on skin colored pixels.

#### `region_colors.php`

> `region_colors.php HSV|YCbCr imagefile outputfile`

Creates a GIF-format output file with each connected region of skin-colored-pixels
in the original image as a different color in the output image.

### How Does It Perform?
### Other implementations
