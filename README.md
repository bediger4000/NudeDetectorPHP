# NudeDetectorPHP
## PHP implementation of Rigan Ap-Apid's "An Algorithm for Nudity Detection"

[An Algorithm for Nudity Detection](http://citeseerx.ist.psu.edu/viewdoc/download?doi=10.1.1.96.9872&rep=rep1&type=pdf)
promises an algorithm to determine whether images contain nudity or not. This is a PHP implementation of it.

### Choices

Ap-Apid's paper doesn't specify the algorithm as tightly as might be wished.

### Example Programs

#### `Checker.php`

> `Checker.php imagefile [imagefile ...]`

Check each image file for nudity, using both HSV and YCbCr skin color models.

#### `skin_map.php`

> `skin_map.php imagefile prefix`

Creates two GIF files, one each for HSV and YCbCr skin color models. The GIF
files are strictly black-and-white, every skin-colored pixel in the original
imae file colored black in the GIF files, all other pixels colored white in
the GIF files.  The GIF files have the names `*prefix*HSV.gif` and `_prefix_YCbCr.gif`.

#### `region_colors.php`

### How Does It Perform?
### Other implementations
