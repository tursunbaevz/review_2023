<?php

namespace App\Converters;

use App\Services\ImageOptimizer;
use Illuminate\Support\Str;

class WebpConverter
{
    /**
     * @param $file
     * @param $path
     * @return string|string[]
     */
    public function handle($file, string $path)
    {
        $extension = $file->getClientOriginalExtension();
        $name = strtolower(Str::random(15)) . '.' . strtolower($extension);

        // ignore webp format
        if ($extension == 'webp') {
            $defaultImage = $file->move($path, $name);
            return $this->getRightPath($defaultImage->getPathname());
        }

        $image = \Image::make($file);
        $image->orientate();

        // check if path exists
        if (!\File::exists($path)) {
            \File::makeDirectory($path, 493, true);
        }

        // prepare data for converting
        $savedImage = $image->save($path . '/'. $name);
        $savedImagePath = $savedImage->dirname . '/' . $savedImage->basename;

        // convert to webp
        $webp = $this->imageConvert($savedImagePath, config()->get('constants.MIMES.DEFAULT'), $extension);

        $imageOptimizer = app(ImageOptimizer::class);
        $imageOptimizer->optimize($webp, 'gallery');

        // delete default moved image
        deleteFile($savedImagePath, $this->getRightPath($savedImagePath));

        return $webp;
    }

    /**
     * @param string $destination
     * @param string $mimes
     * @param string $extension
     * @return string|string[]
     */
    private function imageConvert(string $destination, string $mimes, string $extension)
    {
        // image creation
        $img = imagecreatefromstring(file_get_contents($destination));

        switch ($extension) {
            case 'png':
                $img = imagecreatefrompng($destination);
                imagepalettetotruecolor($img);
                imagealphablending($img, true);
                imagesavealpha($img, true);
                break;
            case 'jpg':
                $img = imagecreatefromjpeg($destination);
                break;
        }

        // replace extension
        $newWebp = preg_replace(
            '"\.(' . str_replace(',', '|', $mimes) . ')$"',
            '.webp',
            $destination
        );

        imagewebp($img, $newWebp, 50);

        return $this->getRightPath($newWebp);
    }

    /**
     * @param string $fullPath
     * @return string|string[]
     */
    private function getRightPath(string $fullPath)
    {
        return str_replace(public_path('/storage'), '', $fullPath);
    }
}
