<?php

namespace SmartImage;

/**
 * Class SmartImage
 * @package SmartImage
 */
class SmartImage
{
    /**
     * @var
     */
    private $src;

    /**
     * @var resource
     */
    private $gdID;

    /**
     * @var array|bool
     */
    private $info;

    /**
     * SmartImage constructor.
     * @param $src
     * @param bool $bigImageSize
     */
    public function __construct($src, $bigImageSize = false)
    {
        // In case of very big images (more than 1Mb)
        if ($bigImageSize) {
            $this->setMemoryForBigImage($src);
        }

        // set data
        $this->src  = $src;
        $this->info = getimagesize($src);

        // open file
        if ($this->info[2] == 2) {
            $this->gdID = imagecreatefromjpeg($this->src);
        } elseif ($this->info[2] == 1) {
            $this->gdID = imagecreatefromgif($this->src);
        } elseif ($this->info[2] == 3) {
            $this->gdID = imagecreatefrompng($this->src);
        }
    }

    /**
     * @param $filename
     * @return bool
     */
    private function setMemoryForBigImage($filename)
    {
        $imageInfo    = getimagesize($filename);
        $memoryNeeded = round(($imageInfo[0] * $imageInfo[1] * $imageInfo['bits'] * $imageInfo['channels'] / 8 + Pow(2, 16)) * 1.65);
        $memoryLimit  = (int)ini_get('memory_limit') * 10485760;
        if ((memory_get_usage() + $memoryNeeded) > $memoryLimit) {
            ini_set('memory_limit', ceil((memory_get_usage() + $memoryNeeded + $memoryLimit) / 10485760) . 'M');
            return (true);
        } else {
            return (false);
        }
    }

    /**
     * @param $width
     * @param $height
     * @param bool $cutImage
     * @return bool
     */
    public function resize($width, $height, $cutImage = false)
    {
        if ($cutImage) {
            return $this->resizeWithCut($width, $height);
        } else {
            return $this->resizeNormal($width, $height);
        }
    }

    /**
     * @param $w
     * @param $h
     * @return bool
     */
    private function resizeNormal($w, $h)
    {
        $size      = $this->info;
        $im        = $this->gdID;
        $newwidth  = $size[0];
        $newheight = $size[1];
        if ($newwidth > $w) {
            $newheight = ($w / $newwidth) * $newheight;
            $newwidth  = $w;
        }
        if ($newheight > $h) {
            $newwidth  = ($h / $newheight) * $newwidth;
            $newheight = $h;
        }
        $new    = imagecreatetruecolor($newwidth, $newheight);
        $result = imagecopyresampled($new, $im, 0, 0, 0, 0, $newwidth, $newheight, $size[0], $size[1]);
        @imagedestroy($im);
        $this->gdID = $new;
        $this->updateInfo();
        return $result;
    }

    /**
     * @param $w
     * @param $h
     * @return bool|null
     */
    private function resizeWithCut($w, $h)
    {
        // set data
        $size   = $this->info;
        $im     = $this->gdID;
        $result = null;
        if ($size[0] > $w or $size[1] > $h) {
            $centerX = $size[0] / 2;
            $centerY = $size[1] / 2;

            $propX = $w / $size[0];
            $propY = $h / $size[1];

            if ($propX < $propY) {
                $src_x = $centerX - ($w * (1 / $propY) / 2);
                $src_y = 0;
                $src_w = ceil($w * 1 / $propY);
                $src_h = $size[1];
            } else {
                $src_x = 0;
                $src_y = $centerY - ($h * (1 / $propX) / 2);
                $src_w = $size[0];
                $src_h = ceil($h * 1 / $propX);
            }

            // Resize
            $new    = imagecreatetruecolor($w, $h);
            $result = imagecopyresampled($new, $im, 0, 0, $src_x, $src_y, $w, $h, $src_w, $src_h);

            @imagedestroy($im);
        } else {
            $new = $im;
        }

        $this->gdID = $new;
        $this->updateInfo();

        return $result;
    }

    /**
     * @param $waterMark
     * @param int $opacity
     * @param int $x
     * @param int $y
     */
    public function addWaterMarkImage($waterMark, $opacity = 35, $x = 5, $y = 5)
    {
        // set data
        // $size = $this->info;
        $im = $this->gdID;

        // set WaterMark's data
        $waterMarkSM = new SmartImage($waterMark);
        $imWM        = $waterMarkSM->getGDid();

        // Add it!
        // In png watermark images we ignore the opacity (you have to set it in the watermark image)
        if ($waterMarkSM->info[2] == 3) {
            imageCopy($im, $imWM, $x, $y, 0, 0, imagesx($imWM), imagesy($imWM));
        } else {
            imageCopyMerge($im, $imWM, $x, $y, 0, 0, imagesx($imWM), imagesy($imWM), $opacity);
        }
        $waterMarkSM->close();
        $this->gdID = $im;
    }

    /**
     * @param int $degrees
     */
    public function rotate($degrees = 180)
    {
        $this->gdID = imagerotate($this->gdID, $degrees, 0);
        $this->updateInfo();
    }

    /**
     * @param int $jpegQuality
     */
    public function printImage($jpegQuality = 100)
    {
        $this->outPutImage('', $jpegQuality);
    }

    /**
     * @param $destination
     * @param int $jpegQuality
     */
    public function saveImage($destination, $jpegQuality = 100)
    {
        $this->outPutImage($destination, $jpegQuality);
    }

    /**
     * @param string $dest
     * @param int $jpegQuality
     */
    private function outPutImage($dest = '', $jpegQuality = 100)
    {
        $size = $this->info;
        $im   = $this->gdID;
        // select mime
        if (!empty($dest)) {
            list($size['mime'], $size[2]) = $this->findMime($dest);
        }

        // if output set headers
        if (empty($dest)) {
            header('Content-Type: ' . $size['mime']);
        }

        // output image
        if ($size[2] == 2) {
            imagejpeg($im, $dest, $jpegQuality);
        } elseif ($size[2] == 1) {
            imagegif($im, $dest);
        } elseif ($size[2] == 3) {
            imagepng($im, $dest);
        }
    }

    /**
     * @param $file
     * @return array
     */
    private function findMime($file)
    {
        $file .= ".";
        $bit  = explode(".", $file);
        $ext  = $bit[count($bit) - 2];
        if ($ext == 'jpg') {
            return ['image/jpeg', 2];
        } elseif ($ext == 'jpeg') {
            return ['image/jpeg', 2];
        } elseif ($ext == 'gif') {
            return ['image/gif', 1];
        } elseif ($ext == 'png') {
            return ['image/png', 3];
        } else {
            return ['image/jpeg', 2];
        }
    }

    /**
     * @return resource
     */
    public function getGDid()
    {
        return $this->gdID;
    }

    /**
     * @return array
     */
    public function getSize()
    {
        $size = $this->info;
        return ['x' => $size[0], 'y' => $size[1]];
    }

    /**
     * @param $value
     */
    public function setGDid($value)
    {
        $this->gdID = $value;
    }

    /**
     * Free memory
     */
    public function close()
    {
        @imagedestroy($this->gdID);
    }

    /**
     * Update info class's variable
     */
    private function updateInfo()
    {
        $info = $this->info;
        $im   = $this->gdID;

        $info[0] = imagesx($im);
        $info[1] = imagesy($im);

        $this->info = $info;
    }
}
