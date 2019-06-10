<?php /** @noinspection ALL */

/**
 * Image_Moo library
 *
 * @package Image_Moo
 * @file    image_moo.php
 * Written due to image_lib not being so nice
 * when you have to do multiple things to a single image!
 *
 * @author  Matthew Augier <matthew@dps.uk.com>
 * @author  Denys Holda <deniska4x@gmail.com>
 * @license MIT License
 * @version Release: 1.1.7
 * @link    http://www.dps.uk.com http://www.matmoo.com
 * @docu    http://todo :)
 * @date    2019 Jun 10
 *
 * Copyright (c) 2011-2014 Matthew (Mat-Moo.com) Augier
 *
 * Requires PHP 5 and GD2!
 *
 * Example usage
 *    $this->image_moo->load("file")->resize(64,40)->save("thumb")->resize(640,480)->save("medium");
 *    if ($this->image_moo->errors) print $this->image_moo->displayErrors();
 *
 * COLOURS!
 * Any function that can take a colour as a parameter
 * can take "#RGB", "#RRGGBB" or an array(R,G,B)
 *
 * KNOWN BUGS
 * make_watermark_text does not deal with rotation angle correctly, box is cropped
 *
 * THANKS
 * Matjaž for poiting out the save_pa bug (should of tested it!)
 * Cahva for posting yet another bug in the save_pa (Man I can be silly sometimes!)
 * Cole spotting the resize flaw and providing a fix
 * Nuno Mira for suggesting the new width/new size on teh ci forums
 * HugoSolar for transparent rotate
 **/

class Image_moo
{
    /**
     * Default image
     *
     * @var string string
     */
    private $_main_image = "";
    /**
     * Default watermark image
     *
     * @var string
     */
    private $_watermark_image;
    /**
     * Default temp image
     *
     * @var string
     */
    private $_temp_image;
    /**
     * Quality of output jpeg
     *
     * @var int
     */
    private $_jpeg_quality = 75;
    /**
     * Default background color
     *
     * @var string
     */
    private $_background_colour = "#ffffff";
    /**
     * Unknown
     *
     * @var
     */
    private $_watermark_method;
    /**
     * Set to true or call ignoreJpegWarnings()
     *
     * @var bool
     */
    private $_jpeg_ignore_warnings = false;
    /**
     * When a resizing an image too small, allow it to be stretched larger
     *
     * @var bool
     */
    private $_can_stretch = false;
    /**
     * Default filename
     *
     * @var string
     */
    private $_filename = "";
    /**
     * Watermark stuff, opacity
     *
     * @var int
     */
    private $_watermark_transparency = 50;

    // reported errors
    /**
     * Errors array
     *
     * @var bool
     */
    public $errors = false;
    /**
     * Error messages array
     *
     * @var array
     */
    private $_error_msg = array();

    /**
     * Width of the image
     *
     * @var int
     */
    public $width = 0;
    /**
     * Height of the image
     *
     * @var int
     */
    public $height = 0;
    /**
     * New width of the image
     *
     * @var int
     */
    public $new_width = 0;
    /**
     * New height of the image
     *
     * @var int
     */
    public $new_height = 0;

    /**
     * Image_moo constructor.
     */
    public function __construct()
    {
        if ($this->_jpeg_ignore_warnings) {
            $this->ignoreJpegWarnings();
        }
    }

    /**
     * Sets the gd.jpeg_ignore_warning to help load
     * having loaded lots of jpegs I quite often get corrupt ones,
     * this setting relaxs GD a bit
     * requires 5.1.3 php
     *
     * @param bool $onoff Ignore value
     *
     * @return $this
     */
    public function ignoreJpegWarnings($onoff = true)
    {
        ini_set('gd.jpeg_ignore_warning', $onoff == true);
        return $this;
    }

    /**
     * When using resize, setting this to
     * If you want to stretch or crop images that are smaller than the target size,
     * call this with TRUE to scale up
     *
     * @param bool $onoff Allow
     *
     * @return $this
     */
    public function allowScaleUp($onoff = false)
    {
        $this->_can_stretch = $onoff;
        return $this;
    }

    /**
     * Load a resource
     *
     * @return bool
     */
    private function _clearErrors()
    {
        $this->_error_msg = array();
        return true;
    }

    /**
     * Set an error message
     *
     * @param string $msg message
     *
     * @return bool
     */
    private function _setError($msg)
    {
        $this->errors = true;
        $this->_error_msg[] = $msg;
        return true;
    }

    /**
     * Returns the errors formatted as needed, same as CI doed
     *
     * @param string $open  open tag
     * @param string $close close tag
     *
     * @return string
     */
    public function displayErrors($open = '<p>', $close = '</p>')
    {
        $str = '';
        foreach ($this->_error_msg as $val) {
            $str .= $open . $val . $close;
        }
        return $str;
    }

    /**
     * Run to see if you server can use this library
     * Verification util to see if you can use image_moo
     *
     * @return bool
     * @throws Exception
     */
    public function checkGd()
    {
        try {
            // is gd loaded?
            if (!extension_loaded('gd')) {
                throw new Exception('GD library does not appear to be loaded');
            }

            // check version
            if (function_exists('gd_info')) {
                $gdarray = @gd_info();
                $versiontxt = preg_replace('/[A-Z,\ ()\[\]]/i', '', $gdarray['GD Version']);
                $versionparts = explode('.', $versiontxt);
                // looking for a version 2
                if ($versionparts[0] == "2") {
                    return true;
                } else {
                    throw new Exception('Requires GD2, this reported as ' . $versiontxt);
                }
            } else {
                // should this be a warning?
                throw new Exception('Could not verify GD version');
            }
        } catch (Exception $e) {
            throw new Exception($e);
        }
    }

    /**
     * Checks that we have an image loaded
     *
     * @return bool
     */
    private function _checkImage()
    {
        // generic check
        if (!is_resource($this->_main_image)) {
            $this->_setError("No main image loaded!");
            return false;
        } else {
            return true;
        }
    }

    /**
     * Back compatibility
     * Saves image as a datastream for inline inclustion writes
     * as a temp image you can not chain this one!
     *
     * @deprecated use getDataStreamInstead
     *
     * @param string $filename path to file
     *
     * @return $this|bool|false|string
     */
    public function get_data_stream($filename = "")
    {
        return $this->getDataStream($filename);
    }

    /**
     * Return the image as a stream so that it can be sent as source data to the output
     * Saves image as a datastream for inline inclustion writes
     * as a temp image you can not chain this one!
     *
     * @param string $filename path to file
     *
     * @return $this|bool|false|string
     */
    public function getDataStream($filename = "")
    {
        // validate we loaded a main image
        if (!$this->_checkImage()) {
            return $this;
        }

        // if no operations, copy it for temp save
        $this->_copyToTempIfNeeded();

        // ok, lets go!
        if ($filename == "") {
            $filename = rand(1000, 999999) . ".jpg";
        }                    // send as jpeg
        $ext = strtoupper(pathinfo($filename, PATHINFO_EXTENSION));

        // start new buffer
        ob_start();

        switch ($ext) {
        case "GIF":
            imagegif($this->_temp_image);
            break;
        case "JPG":
        case "JPEG":
            imagejpeg($this->_temp_image);
            break;
        case "PNG":
            imagepng($this->_temp_image);
            break;
        default:
            $this->_setError('Extension not recognised! Must be jpg/png/gif');
            return false;
                break;
        }

        // get the buffer
        $contents = ob_get_contents();

        // remove buffer
        ob_end_clean();

        // return teh buffer (allows user to encode it)
        return $contents;
    }

    /**
     * Saves as a stream output, use _filename to return png/jpg/gif etc., default is jpeg
     * Saves the temp image as a dynamic image e.g. direct output to the browser
     *
     * @param string $filename name of the file
     *
     * @return $this
     */
    public function save_dynamic($filename = "")
    {
        // validate we loaded a main image
        if (!$this->_checkImage()) {
            return $this;
        }

        // if no operations, copy it for temp save
        $this->_copyToTempIfNeeded();

        // ok, lets go!
        if ($filename == "") {
            $filename = rand(1000, 999999) . ".jpg";
        }                    // send as jpeg
        $ext = strtoupper(pathinfo($filename, PATHINFO_EXTENSION));
        header("Content-disposition: _filename=$filename;");
        header('Content-transfer-Encoding: binary');
        header('Last-modified: ' . gmdate('D, d M Y H:i:s'));
        switch ($ext) {
        case "GIF":
            header("Content-type: image/gif");
            imagegif($this->_temp_image);
            return $this;
                break;
        case "JPG":
        case "JPEG":
            header("Content-type: image/jpeg");
            imagejpeg($this->_temp_image, null, $this->_jpeg_quality);
            return $this;
                break;
        case "PNG":
            header("Content-type: image/png");
            imagepng($this->_temp_image);
            return $this;
                break;
        }
        $this->_setError('Unable to save, extension not GIF/JPEG/JPG/PNG');
        return $this;
    }

    /**
     * Saves using the original image name but with prepend and append text, e.g.
     * load('moo.jpg')->save_pa('pre_','_app') would save as _filename pre_moo_app.jpg
     * Saves the temp image as the _filename specified, overwrite = true of false
     *
     * @param string $prepend
     * @param string $append
     * @param bool   $overwrite
     *
     * @return $this
     */
    public function save_pa($prepend = "", $append = "", $overwrite = false)
    {
        // validate we loaded a main image
        if (!$this->_checkImage()) {
            return $this;
        }

        // get current file parts
        $parts = pathinfo($this->_filename);

        // save
        $this->save($parts["dirname"] . '/' . $prepend . $parts['_filename'] . $append . '.' . $parts["extension"], $overwrite);

        return $this;
    }

    /**
     * Saved the manipulated image (if applicable) to file $x - JPG, PNG, GIF supported
     * Saves the temp image as the _filename specified overwrite = true of false
     *
     * @param $filename
     * @param bool     $overwrite
     *
     * @return $this
     */
    public function save($filename, $overwrite = false)
    {
        // validate we loaded a main image
        if (!$this->_checkImage()) {
            return $this;
        }

        // if no operations, copy it for temp save
        $this->_copyToTempIfNeeded();

        // check if it already exists
        if (!$overwrite) {
            // don't overwrite, so check for file
            if (file_exists($filename)) {
                $this->_setError('File exists, overwrite is FALSE, could not save over file ' . $filename);
                return $this;
            }
        }

        // find out the type of file to save
        $ext = strtoupper(pathinfo($filename, PATHINFO_EXTENSION));
        switch ($ext) {
        case "GIF":
            imagegif($this->_temp_image, $filename);
            return $this;
                break;
        case "JPG":
        case "JPEG":
            imagejpeg($this->_temp_image, $filename, $this->_jpeg_quality);
            return $this;
                break;
        case "PNG":
            imagepng($this->_temp_image, $filename);
            return $this;
                break;
        }

        // invalid filetype?!
        $this->_setError(
            'Do no know what ' . $ext . ' filetype is in _filename ' . $filename
        );
        return $this;
    }

    /**
     * Private function to load a resource
     *
     * @param $filename
     *
     * @return bool|false|resource
     */
    private function _loadImage($filename)
    {
        // check the request file can be located
        if (!file_exists($filename)) {
            $this->_setError('Could not locate file ' . $filename);
            return false;
        }

        // get image info about this file
        $image_info = getimagesize($filename);

        // load file depending on mimetype
        try {
            switch ($image_info["mime"]) {
            case "image/gif":
                return @imagecreatefromgif($filename);
                    break;
            case "image/jpeg":
                return @imagecreatefromjpeg($filename);
                    break;
            case "image/png":
                return @imagecreatefrompng($filename);
                    break;
            }
        } catch (Exception $e) {
            $this->_setError('Exception loading ' . $filename . ' - ' . $e->getMessage());
        }

        // invalid filetype?!
        $this->_setError('Unable to load ' . $filename . ' filetype ' . $image_info["mime"] . 'not recognised');
        return false;
    }

    /**
     * Takes a cropped/altered image and makes it the main image to work with.
     *
     * @return $this|bool
     */
    public function load_temp()
    {
        // validate we loaded a main image
        if (!$this->_checkImage()) {
            return $this;
        }

        if (!is_resource($this->_temp_image)) {
            $this->_setError("No temp image created!");
            return false;
        }

        // make main the temp
        $this->_main_image = $this->_temp_image;

        // clear temp
        $this->clear_temp();

        // reset sizes
        $this->_setNewSize();

        // return the object
        return $this;
    }

    /**
     * Loads an image file specified by $x - JPG, PNG, GIF supported
     *
     * @param $filename
     *
     * @return $this
     */
    public function load($filename)
    {
        // new image, reset error messages
        $this->_clearErrors();

        // remove temporary image stored
        $this->clear_temp();

        // save _filename
        $this->_filename = $filename;

        // reset width and height
        $this->width = 0;
        $this->height = 0;

        // load it
        $this->_main_image = $this->_loadImage($filename);

        // no error, then get the dminesions set
        if ($this->_main_image <> false) {
            $this->new_width = $this->width = imageSX($this->_main_image);
            $this->new_height = $this->height = imageSY($this->_main_image);
            $this->_setNewSize();
        }

        // return the object
        return $this;
    }

    /**
     * Loads the specified file as the watermark file, if using PNG32/24
     * use x,y to specify direct positions of colour to use as index
     *
     * @param $filename
     * @param null     $transparent_x
     * @param null     $transparent_y
     *
     * @return $this
     */
    public function load_watermark($filename, $transparent_x = null, $transparent_y = null)
    {
        if (is_resource($this->_watermark_image)) {
            imagedestroy($this->_watermark_image);
        }
        $this->_watermark_image = $this->_loadImage($filename);

        if (is_resource($this->_watermark_image)) {
            $this->_watermark_method = 1;
            if (($transparent_x <> null) and ($transparent_y <> null)) {
                // get the top left corner colour allocation
                $tpcolour = imagecolorat(
                    $this->_watermark_image,
                    $transparent_x,
                    $transparent_y
                );

                // set this as the transparent colour
                imagecolortransparent($this->_watermark_image, $tpcolour);

                // $set diff method
                $this->_watermark_method = 2;
            }
        }

        // return this object
        return $this;
    }

    /**
     * Returns the actual file size of the original image
     *
     * @return string
     */
    public function real_filesize()
    {
        // _filename?
        if ($this->_filename == "") {
            $this->_setError('Unable to get filesize, no _filename!');
            return "-";
        }
        if (!file_exists($this->_filename)) {
            $this->_setError('Unable to get filesize, file does not exist!');
            return "-";
        }

        // set the units (found on filesize.php)
        $size = filesize($this->_filename);

        // set the units
        $units = array(' B', ' KB', ' MB', ' GB', ' TB');
        for ($i = 0; $size >= 1024 && $i < 4; $i++) {
            $size /= 1024;
        }

        // return the closest
        return round($size, 2) . $units[$i];
    }

    /**
     * Sets the quality that jpeg will be saved at
     *
     * @param int $transparency
     *
     * @return $this
     */
    public function set__watermark_transparency($transparency = 50)
    {
        $this->_watermark_transparency = $transparency;
        return $this;
    }

    /**
     * Sets teh background colour to use on rotation and padding for resize
     *
     * @param string $colour hex color code
     *
     * @return $this
     */
    public function set__background_colour($colour = "#ffffff")
    {
        $this->_background_colour = $this->_html2rgb($colour);
        return $this;
    }

    /**
     * Sets the quality that jpeg will be saved at
     *
     * @param int $quality jpeg quality 1-100
     *
     * @return $this
     */
    public function set__jpeg_quality($quality = 75)
    {
        $this->_jpeg_quality = $quality;
        return $this;
    }

    /**
     * If temp image is empty, e.g. not resized or done
     * anything then just copy main image
     *
     * @return bool
     */
    private function _copyToTempIfNeeded()
    {
        $success = false;
        if (!is_resource($this->_temp_image)) {
            // create a temp based on new dimensions
            $this->_temp_image = imagecreatetruecolor($this->width, $this->height);

            // check it
            if (!is_resource($this->_temp_image)) {
                $this->_setError(
                    'Unable to create temp image sized ' . $this->width .
                    ' x ' . $this->height
                );
                return false;
            }

            // copy image to temp workspace
            $success = imagecopy(
                $this->_temp_image, $this->_main_image,
                0, 0, 0, 0,
                $this->width, $this->height
            );
            if ($success) {
                $this->_setNewSize();
            }
        }
        return $success;
    }

    /**
     * Clear everything!
     *
     * @return $this
     */
    public function clear()
    {
        if (is_resource($this->_main_image)) {
            imagedestroy($this->_main_image);
        }
        if (is_resource($this->_watermark_image)) {
            imagedestroy($this->_watermark_image);
        }
        if (is_resource($this->_temp_image)) {
            imagedestroy($this->_temp_image);
        }
        return $this;
    }

    /**
     * You may want to revert back to teh original image to work on, e.g. watermark, this clears temp
     *
     * @return $this
     */
    public function clear_temp()
    {
        if (is_resource($this->_temp_image)) {
            imagedestroy($this->_temp_image);
        }
        return $this;
    }

    /**
     * Proportioanlly resize original image using the bounds
     * $x and $y but cropped to fill dimensions
     * take main image and resize to tempimage using EXACT boundaries mw,mh (max width and max height)
     * this is proportional and crops the image centrally to fit
     *
     * @param $mw
     * @param $mh
     *
     * @return $this
     */
    public function resize_crop($mw, $mh)
    {
        if (!$this->_checkImage()) {
            return $this;
        }

        // clear temp image
        $this->clear_temp();

        // create a temp based on new dimensions
        $this->_temp_image = imagecreatetruecolor($mw, $mh);

        // check it
        if (!is_resource($this->_temp_image)) {
            $this->_setError('Unable to create temp image sized ' . $mw . ' x ' . $mh);
            return $this;
        }

        // work out best positions for copy
        $wx = $this->width / $mw;
        $wy = $this->height / $mh;

        if ($wx >= $wy) {
            // use full height
            $sy = 0;
            $sy2 = $this->height;

            // calcs
            $calc_width = $mw * $wy;
            $sx = ($this->width - $calc_width) / 2;
            $sx2 = $calc_width;
        } else {
            // use full width
            $sx = 0;
            $sx2 = $this->width;

            // calcs
            $calc_height = $mh * $wx;
            $sy = ($this->height - $calc_height) / 2;
            $sy2 = $calc_height;
        }

        //image transparency preserved
        imagealphablending($this->_temp_image, false);
        imagesavealpha($this->_temp_image, true);

        // copy section
        imagecopyresampled($this->_temp_image, $this->_main_image, 0, 0, $sx, $sy, $mw, $mh, $sx2, $sy2);

        // set sizes
        $this->_setNewSize();

        // return self
        return $this;
    }

    /**
     * Proportioanlly resize original image using
     * the bounds $x and $y (if y is false x size is used), if padding is set return
     * image is as defined centralised using BG colour
     * take main image and resize to tempimage using boundaries mw,mh (max width or max height)
     * this is proportional, pad to true will set it in the middle of area size
     *
     * @param $mw
     * @param bool $mh
     * @param bool $pad
     *
     * @return $this
     */
    public function resize($mw, $mh = false, $pad = false)
    {
        // no image - fail!
        if (!$this->_checkImage()) {
            return $this;
        }

        // set mh if not set
        if ($mh == false) {
            $mh = $mw;
        }

        // calc new dimensions
        if ($this->width > $mw || $this->height > $mh || $this->_can_stretch) {
            if (($this->width / $this->height) > ($mw / $mh)) {
                $tnw = $mw;
                $tnh = $tnw * $this->height / $this->width;
            } else {
                $tnh = $mh;
                $tnw = $tnh * $this->width / $this->height;
            }
        } else {
            $tnw = $this->width;
            $tnh = $this->height;
        }
        // clear temp image
        $this->clear_temp();

        // create a temp based on new dimensions
        if ($pad) {
            $tx = $mw;
            $ty = $mh;
            $px = ($mw - $tnw) / 2;
            $py = ($mh - $tnh) / 2;
        } else {
            $tx = $tnw;
            $ty = $tnh;
            $px = 0;
            $py = 0;
        }

        $this->_temp_image = imagecreatetruecolor($tx, $ty);

        // check it
        if (!is_resource($this->_temp_image)) {
            $this->_setError('Unable to create temp image sized ' . $tx . ' x ' . $ty);
            return $this;
        }


        /* hmm what was I doing here?!
        imagealphablending($this->_main_image, true);
        $a = imagecolortransparent($this->_temp_image, imagecolorallocatealpha($this->_temp_image, 0, 0, 0, 127));
        imagefilledrectangle($this->_temp_image, 0, 0, $tx, $ty, $a);
        imagesavealpha($this->_temp_image, true);
        */

        $col = $this->_html2rgb($this->_background_colour);
        $bg = imagecolorallocate($this->_temp_image, $col[0], $col[1], $col[2]);
        imagefilledrectangle($this->_temp_image, 0, 0, $tx, $ty, $bg);

        // if padding, fill background
        if ($pad) {
            $col = $this->_html2rgb($this->_background_colour);
            $bg = imagecolorallocate($this->_temp_image, $col[0], $col[1], $col[2]);
            imagefilledrectangle($this->_temp_image, 0, 0, $tx, $ty, $bg);
            /* TO DO
            imagealphablending($this->_temp_image, false);
            imagesavealpha($this->_temp_image, true);
            $color = imagecolorallocatealpha($this->_temp_image, 0, 0, 0, 127);
            imagefilledrectangle($this->_temp_image, 0, 0, $this->width, $this->height, $color);
            */
        }

        // copy resized
        imagecopyresampled($this->_temp_image, $this->_main_image, $px, $py, 0, 0, $tnw, $tnh, $this->width, $this->height);

        // set sizes
        $this->_setNewSize();

        // return self
        return $this;
    }

    /**
     * Take the original image and stretch it to fill new dimensions $x $y
     * take main image and resize to tempimage using boundaries mw,mh (max width or max height)
     * does not retain proportions
     *
     * @param $mw
     * @param $mh
     *
     * @return $this
     */
    public function stretch($mw, $mh)
    {
        if (!$this->_checkImage()) {
            return $this;
        }

        // clear temp image
        $this->clear_temp();

        // create a temp based on new dimensions
        $this->_temp_image = imagecreatetruecolor($mw, $mh);

        // check it
        if (!is_resource($this->_temp_image)) {
            $this->_setError('Unable to create temp image sized ' . $mh . ' x ' . $mw);
            return $this;
        }

        // copy resized (stethced, proportions not kept);
        imagecopyresampled($this->_temp_image, $this->_main_image, 0, 0, 0, 0, $mw, $mh, $this->width, $this->height);

        // set sizes
        $this->_setNewSize();

        // return self
        return $this;
    }

    /**
     * Crop the original image using Top left,
     * $x1,$y1 to bottom right $x2,y2. New image size =$x2-x1 x $y2-y1
     *
     * @param $x1
     * @param $y1
     * @param $x2
     * @param $y2
     *
     * @return $this
     */
    public function crop($x1, $y1, $x2, $y2)
    {
        if (!$this->_checkImage()) {
            return $this;
        }

        // clear temp image
        $this->clear_temp();

        // check dimensions
        if ($x1 < 0 || $y1 < 0 || $x2 - $x1 > $this->width || $y2 - $y1 > $this->height) {
            $this->_setError('Invalid crop dimensions, either - passed or width/heigh too large ' . $x1 . '/' . $y1 . ' x ' . $x2 . '/' . $y2);
            return $this;
        }

        // create a temp based on new dimensions
        $this->_temp_image = imagecreatetruecolor($x2 - $x1, $y2 - $y1);

        // check it
        if (!is_resource($this->_temp_image)) {
            $this->_setError('Unable to create temp image sized ' . $x2 - $x1 . ' x ' . $y2 - $y1);
            return $this;
        }

        // copy cropped portion
        imagecopy($this->_temp_image, $this->_main_image, 0, 0, $x1, $y1, $x2 - $x1, $y2 - $y1);

        // set sizes
        $this->_setNewSize();

        // return self
        return $this;
    }

    /**
     * convert #aa0011 to a php colour array
     *
     * @param $colour
     *
     * @return array|bool|string
     */
    private function _html2rgb($colour)
    {
        if (is_array($colour)) {
            if (count($colour) == 3) {
                return $colour;
            }                                // rgb sent as an array so use it
            $this->_setError('Colour error, array sent not 3 elements, expected array(r,g,b)');
            return false;
        }
        if ($colour[0] == '#') {
            $colour = substr($colour, 1);
        }

        if (strlen($colour) == 6) {
            list($r, $g, $b) = array($colour[0] . $colour[1],
                $colour[2] . $colour[3],
                $colour[4] . $colour[5]);
        } elseif (strlen($colour) == 3) {
            list($r, $g, $b) = array($colour[0] . $colour[0], $colour[1] . $colour[1], $colour[2] . $colour[2]);
        } else {
            $this->_setError('Colour error, value sent not #RRGGBB or RRGGBB, and not array(r,g,b)');
            return false;
        }

        $r = hexdec($r);
        $g = hexdec($g);
        $b = hexdec($b);

        return array($r, $g, $b);
    }

    /**
     * Rotates the work image by X degrees, normally
     * 90,180,270 can be any angle.Excess filled with background colour
     *
     * @param $angle
     *
     * @return $this
     */
    public function rotate($angle)
    {
        // validate we loaded a main image
        if (!$this->_checkImage()) {
            return $this;
        }

        // if no operations, copy it for temp save
        $this->_copyToTempIfNeeded();

        // set the colour
        //        $col = $this->_html2rgb($this->_background_colour); TODO Why?
        $bg = imagecolorallocatealpha($this->_temp_image, 0, 0, 0, 127);

        // rotate as needed
        $this->_temp_image = imagerotate($this->_temp_image, $angle, $bg);
        imagealphablending($this->_temp_image, false);
        imagesavealpha($this->_temp_image, true);

        // set sizes
        $this->_setNewSize();

        // return self
        return $this;
    }

    /**
     * Creates a text watermark
     * create an image from text that can be applied as a watermark
     * text is the text to write, $fontile is a ttf file that will be
     * used $size=font size, $colour is the colour of text
     * Does not deal with rotation angle correctly, box is cropped!
     *
     * @param $text
     * @param $fontfile
     * @param int      $size
     * @param string   $colour
     * @param int      $angle
     *
     * @return $this
     */
    public function make_watermark_text($text, $fontfile, $size = 16, $colour = "#ffffff", $angle = 0)
    {
        // check font file can be found
        if (!file_exists($fontfile)) {
            $this->_setError('Could not locate font file "' . $fontfile . '"');
            return $this;
        }

        // validate we loaded a main image
        if (!$this->_checkImage()) {
            $remove = true;
            // no image loaded so make temp image to use
            $this->_main_image = imagecreatetruecolor(1000, 1000);
        } else {
            $remove = false;
        }

        // work out text dimensions
        $bbox = imageftbbox($size, $angle, $fontfile, $text);
        $bw = abs($bbox[4] - $bbox[0]) + 1;
        $bh = abs($bbox[1] - $bbox[5]) + 1;
        $bl = $bbox[1];

        // use this to create watermark image
        if (is_resource($this->_watermark_image)) {
            imagedestroy($this->_watermark_image);
        }
        $this->_watermark_image = imagecreatetruecolor($bw, $bh);

        // set colours
        $col = $this->_html2rgb($colour);
        $font_col = imagecolorallocate($this->_watermark_image, $col[0], $col[1], $col[2]);
        $bg_col = imagecolorallocate($this->_watermark_image, 127, 128, 126);

        // set method to use
        $this->_watermark_method = 2;

        // create bg
        imagecolortransparent($this->_watermark_image, $bg_col);
        imagefilledrectangle($this->_watermark_image, 0, 0, $bw, $bh, $bg_col);

        // write text to watermark
        imagefttext($this->_watermark_image, $size, $angle, 0, $bh - $bl, $font_col, $fontfile, $text);

        if ($remove) {
            imagedestroy($this->_main_image);
        }
        return $this;
    }

    /**
     * Use the loaded watermark, or created
     * text to place a watermark. $position works like NUM PAD key layout, e.g.
     * 7=Top left, 3=Bottom right $offset is the padding/indentation,
     * if $abs is true then use $positiona and $offset as direct values
     * to watermark placement
     * add a watermark to the image
     * position works like a keypad e.g.
     * 7 8 9
     * 4 5 6
     * 1 2 3
     * offset moves image inwards by x pixels
     * if abs is set then $position, $offset = direct placement coords
     *
     * @param $position
     * @param int      $offset
     * @param bool     $abs
     *
     * @return $this
     */
    public function watermark($position, $offset = 8, $abs = false)
    {
        // validate we loaded a main image
        if (!$this->_checkImage()) {
            return $this;
        }

        // validate we have a watermark
        if (!is_resource($this->_watermark_image)) {
            $this->_setError("Can't watermark image, no watermark loaded/created");
            return $this;
        }

        // if no operations, copy it for temp save
        $this->_copyToTempIfNeeded();

        // get watermark width
        $wm_w = imageSX($this->_watermark_image);
        $wm_h = imageSY($this->_watermark_image);

        // get temp widths
        $temp_w = imageSX($this->_temp_image);
        $temp_h = imageSY($this->_temp_image);

        // check watermark will fit!
        if ($wm_w > $temp_w || $wm_h > $temp_h) {
            $this->_setError("Watermark is larger than image. WM: $wm_w x $wm_h Temp image: $temp_w x $temp_h");
            return $this;
        }

        if ($abs) {
            // direct placement
            $dest_x = $position;
            $dest_y = $offset;
        } else {
            // do X position
            switch ($position) {
                // x left
            case "7":
            case "4":
            case "1":
                $dest_x = $offset;
                break;
                // x middle
            case "8":
            case "5":
            case "2":
                $dest_x = ($temp_w - $wm_w) / 2;
                break;
                // x right
            case "9":
            case "6":
            case "3":
                $dest_x = $temp_w - $offset - $wm_w;
                break;
            default:
                $dest_x = $offset;
                $this->_setError("Watermark position $position not in valid range 7,8,9 - 4,5,6 - 1,2,3");
            }
            // do y position
            switch ($position) {
                // y top
            case "7":
            case "8":
            case "9":
                $dest_y = $offset;
                break;
                // y middle
            case "4":
            case "5":
            case "6":
                $dest_y = ($temp_h - $wm_h) / 2;
                break;
                // y bottom
            case "1":
            case "2":
            case "3":
                $dest_y = $temp_h - $offset - $wm_h;
                break;
            default:
                $dest_y = $offset;
                $this->_setError("Watermark position $position not in valid range 7,8,9 - 4,5,6 - 1,2,3");
            }
        }

        // copy over temp image to desired location
        if ($this->_watermark_method == 1) {
            // use back methods to do this, taken from php help files
            //$this->imagecopymerge_alpha($this->_temp_image, $this->_watermark_image, $dest_x, $dest_y, 0, 0, $wm_w, $wm_h, $this->_watermark_transparency);

            $opacity = $this->_watermark_transparency;

            // creating a cut resource
            $cut = imagecreatetruecolor($wm_w, $wm_h);

            // copying that section of the background to the cut
            imagecopy($cut, $this->_temp_image, 0, 0, $dest_x, $dest_y, $wm_w, $wm_h);

            // inverting the opacity
            $opacity = 100 - $opacity;

            // placing the watermark now
            imagecopy($cut, $this->_watermark_image, 0, 0, 0, 0, $wm_w, $wm_h);
            imagecopymerge($this->_temp_image, $cut, $dest_x, $dest_y, 0, 0, $wm_w, $wm_h, $opacity);
        } else {
            // use normal with selected transparency colour
            imagecopymerge($this->_temp_image, $this->_watermark_image, $dest_x, $dest_y, 0, 0, $wm_w, $wm_h, $this->_watermark_transparency);
        }

        return $this;
    }

    /**
     * Draw a border around the output image X pixels wide in colour specified
     *
     * @param int    $width
     * @param string $colour
     *
     * @return $this
     */
    public function border($width = 5, $colour = "#000")
    {
        // validate we loaded a main image
        if (!$this->_checkImage()) {
            return $this;
        }

        // if no operations, copy it for temp save
        $this->_copyToTempIfNeeded();

        // get colour set for temp image
        $col = $this->_html2rgb($colour);
        $border_col = imagecolorallocate($this->_temp_image, $col[0], $col[1], $col[2]);

        // get temp widths
        $temp_w = imageSX($this->_temp_image);
        $temp_h = imageSY($this->_temp_image);

        // do border
        for ($x = 0; $x < $width; $x++) {
            imagerectangle($this->_temp_image, $x, $x, $temp_w - $x - 1, $temp_h - $x - 1, $border_col);
        }

        // return object
        return $this;
    }

    /**
     * Draw a 3d border (opaque) around
     * the current image $width wise in 0-3 rot positions, $opacity allows you
     * to change how much it effects the picture
     * Overlay a black white border to make it look 3d
     *
     * @param int $width
     * @param int $rot
     * @param int $opacity
     *
     * @return $this
     */
    public function border_3d($width = 5, $rot = 0, $opacity = 30)
    {
        // validate we loaded a main image
        if (!$this->_checkImage()) {
            return $this;
        }

        // if no operations, copy it for temp save
        $this->_copyToTempIfNeeded();

        // create temp canvas to merge
        $border_image = imagecreatetruecolor($this->new_width, $this->new_height);

        // create colours
        $black = imagecolorallocate($border_image, 0, 0, 0);
        $white = imagecolorallocate($border_image, 255, 255, 255);
        switch ($rot) {
        case 1:
            $cols = array($white, $black, $white, $black);
            break;
        case 2:
            $cols = array($black, $black, $white, $white);
            break;
        case 3:
            $cols = array($black, $white, $black, $white);
            break;
        default:
            $cols = array($white, $white, $black, $black);
        }
        $bg_col = imagecolorallocate($border_image, 127, 128, 126);

        // create bg
        imagecolortransparent($border_image, $bg_col);
        imagefilledrectangle($border_image, 0, 0, $this->new_width, $this->new_height, $bg_col);

        // do border
        for ($x = 0; $x < $width; $x++) {
            // top
            imageline($border_image, $x, $x, $this->new_width - $x - 1, $x, $cols[0]);
            // left
            imageline($border_image, $x, $x, $x, $this->new_width - $x - 1, $cols[1]);
            // bottom
            imageline($border_image, $x, $this->new_height - $x - 1, $this->new_width - 1 - $x, $this->new_height - $x - 1, $cols[3]);
            // right
            imageline($border_image, $this->new_width - $x - 1, $x, $this->new_width - $x - 1, $this->new_height - $x - 1, $cols[2]);
        }

        // merg with temp image
        imagecopymerge($this->_temp_image, $border_image, 0, 0, 0, 0, $this->new_width, $this->new_height, $opacity);

        // clean up
        imagedestroy($border_image);

        // return object
        return $this;
    }

    /**
     * Size in pixels, note that the image will increase by this size, so
     * resize(400,400)->shadoe(4) will give an image 404 pixels in size,
     * Direction works on teh keypad basis like the watermark, so 3 is bottom right,
     * $color if the colour of the shadow.
     *
     * @param int    $size
     * @param int    $direction
     * @param string $colour
     *
     * @return $this|bool
     */
    public function shadow($size = 4, $direction = 3, $colour = "#444")
    {
        // validate we loaded a main image
        if (!$this->_checkImage()) {
            return $this;
        }

        // if no operations, copy it for temp save
        $this->_copyToTempIfNeeded();

        // get the current size
        $sx = imagesx($this->_temp_image);
        $sy = imagesy($this->_temp_image);

        // new image
        $bu_image = imagecreatetruecolor($sx, $sy);

        // check it
        if (!is_resource($bu_image)) {
            $this->_setError('Unable to create shadow temp image sized ' . $this->width . ' x ' . $this->height);
            return false;
        }

        // copy the current image to memory
        imagecopy($bu_image, $this->_temp_image, 0, 0, 0, 0, $sx, $sy);

        imagedestroy($this->_temp_image);
        $this->_temp_image = imagecreatetruecolor($sx + $size, $sy + $size);

        // fill background colour
        $col = $this->_html2rgb($this->_background_colour);
        $bg = imagecolorallocate($this->_temp_image, $col[0], $col[1], $col[2]);
        imagefilledrectangle($this->_temp_image, 0, 0, $sx + $size, $sy + $size, $bg);

        // work out position
        // do X position
        switch ($direction) {
            // x left
        case "7":
        case "4":
        case "1":
            $sh_x = 0;
            $pic_x = $size;
            break;
            // x middle
        case "8":
        case "5":
        case "2":
            $sh_x = $size / 2;
            $pic_x = $size / 2;
            break;
            // x right
        case "9":
        case "6":
        case "3":
            $sh_x = $size;
            $pic_x = 0;
            break;
        default:
            $sh_x = $size;
            $pic_x = 0;
            $this->_setError("Shadow position $direction not in valid range 7,8,9 - 4,5,6 - 1,2,3");
        }
        // do y position
        switch ($direction) {
            // y top
        case "7":
        case "8":
        case "9":
            $sh_y = 0;
            $pic_y = $size;
            break;
            // y middle
        case "4":
        case "5":
        case "6":
            $sh_y = $size / 2;
            $pic_y = $size / 2;
            break;
            // y bottom
        case "1":
        case "2":
        case "3":
            $sh_y = $size;
            $pic_y = 0;
            break;
        default:
            $sh_y = $size;
            $pic_y = 0;
            $this->_setError("Shadow position $direction not in valid range 7,8,9 - 4,5,6 - 1,2,3");
        }

        // create the shadow
        $shadowcolour = $this->_html2rgb($colour);
        $shadow = imagecolorallocate($this->_temp_image, $shadowcolour[0], $shadowcolour[1], $shadowcolour[2]);
        imagefilledrectangle($this->_temp_image, $sh_x, $sh_y, $sh_x + $sx - 1, $sh_y + $sy - 1, $shadow);

        // copy current image to correct location
        imagecopy($this->_temp_image, $bu_image, $pic_x, $pic_y, 0, 0, $sx, $sy);

        // clean up and desstroy temp image
        imagedestroy($bu_image);

        // set sizes
        $this->_setNewSize();

        // return self
        return $this;
    }

    /**
     * Runs the standard imagefilter GD2 command, see
     * http://www.php.net/manual/en/function.imagefilter.php for details
     *
     * @param $function
     * @param null     $arg1
     * @param null     $arg2
     * @param null     $arg3
     * @param null     $arg4
     *
     * @return $this
     */
    public function filter($function, $arg1 = null, $arg2 = null, $arg3 = null, $arg4 = null)
    {
        // validate we loaded a main image
        if (!$this->_checkImage()) {
            return $this;
        }

        // if no operations, copy it for temp save
        $this->_copyToTempIfNeeded();

        if (!imagefilter($this->_temp_image, $function, $arg1, $arg2, $arg3, $arg4)) {
            $this->_setError("Filter $function failed");
        }

        // set sizes
        $this->_setNewSize();

        // return self
        return $this;
    }

    /**
     * Adds rounded corners to the output
     * using a quarter and rotating as you can end up with odd roudning if you draw a whole and use parts
     *
     * @param int    $radius
     * @param bool   $invert
     * @param string $corners
     *
     * @return $this
     */
    public function round($radius = 5, $invert = false, $corners = "")
    {
        // validate we loaded a main image
        if (!$this->_checkImage()) {
            return $this;
        }

        // if no operations, copy it for temp save
        $this->_copyToTempIfNeeded();

        // check input
        if ($corners == "") {
            $corners = array(true, true, true, true);
        }
        if (!is_array($corners) || count($corners) <> 4) {
            $this->_setError("Round failed, expected an array of 4 items round(radius,tl,tr,br,bl)");
            return $this;
        }

        // create corner
        $corner = imagecreatetruecolor($radius, $radius);

        // turn on aa make it nicer
        imageantialias($corner, true);
        $col = $this->_html2rgb($this->_background_colour);

        // use bg col for corners
        $bg = imagecolorallocate($corner, $col[0], $col[1], $col[2]);

        // create our transparent colour
        $xparent = imagecolorallocate($corner, 127, 128, 126);
        imagecolortransparent($corner, $xparent);
        if ($invert) {
            // fill and clear bits
            imagefilledrectangle($corner, 0, 0, $radius, $radius, $xparent);
            imagefilledellipse($corner, 0, 0, ($radius * 2) - 1, ($radius * 2) - 1, $bg);
        } else {
            // fill and clear bits
            imagefilledrectangle($corner, 0, 0, $radius, $radius, $bg);
            imagefilledellipse($corner, $radius, $radius, ($radius * 2), ($radius * 2), $xparent);
        }

        // get temp widths
        $temp_w = imageSX($this->_temp_image);
        $temp_h = imageSY($this->_temp_image);

        // do corners
        if ($corners[0]) {
            imagecopymerge($this->_temp_image, $corner, 0, 0, 0, 0, $radius, $radius, 100);
        }
        $corner = imagerotate($corner, 270, 0);
        if ($corners[1]) {
            imagecopymerge($this->_temp_image, $corner, $temp_w - $radius, 0, 0, 0, $radius, $radius, 100);
        }
        $corner = imagerotate($corner, 270, 0);
        if ($corners[2]) {
            imagecopymerge($this->_temp_image, $corner, $temp_w - $radius, $temp_h - $radius, 0, 0, $radius, $radius, 100);
        }
        $corner = imagerotate($corner, 270, 0);
        if ($corners[3]) {
            imagecopymerge($this->_temp_image, $corner, 0, $temp_h - $radius, 0, 0, $radius, $radius, 100);
        }

        // set sizes
        $this->_setNewSize();

        // return self
        return $this;
    }

    /**
     * Updates the new_widht and height sizes
     */
    private function _setNewSize()
    {
        // just in case
        if (!$this->_checkImage()) {
            $this->new_height = 0;
            $this->new_width = 0;
            return;
        }

        // is there a temp image?
        if (!is_resource($this->_temp_image)) {
            $this->new_height = $this->height;
            $this->new_width = $this->width;
            return;
        }

        // set new sizes
        $this->new_height = imagesy($this->_temp_image);
        $this->new_width = imagesx($this->_temp_image);
    }
}
/* End of file image_moo.php */

