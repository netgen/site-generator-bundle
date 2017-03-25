<?php

namespace Netgen\Bundle\MoreGeneratorBundle\Helper;

use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;

class FileHelper
{
    /**
     * Searches and replaces values in the list of files.
     *
     * @param string|array $files
     * @param string $search
     * @param string $replace
     */
    public static function searchAndReplaceInFile($files, $search, $replace)
    {
        if (is_string($files)) {
            $files = array($files);
        }

        foreach ($files as $file) {
            if (!is_file($file) || !is_readable($file) || !is_writable($file) || is_link($file)) {
                continue;
            }

            $fileContents = file_get_contents($file);
            $fileContents = str_replace($search, $replace, $fileContents);
            file_put_contents($file, $fileContents);
        }
    }

    /**
     * Lists all files in a directory.
     *
     * @param string $directory
     *
     * @return array
     */
    public static function findFilesInDirectory($directory)
    {
        if (!is_dir($directory) || is_link($directory)) {
            return array();
        }

        $allFiles = array();

        foreach (new RecursiveIteratorIterator(new RecursiveDirectoryIterator($directory)) as $subItem) {
            /** @var \SplFileInfo $subItem */
            if ($subItem->isFile() && !$subItem->isLink()) {
                $allFiles[] = $subItem->getPathname();
            }
        }

        return $allFiles;
    }
}
