<?php

declare(strict_types=1);

namespace Netgen\Bundle\SiteGeneratorBundle\Helper;

use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;

class FileHelper
{
    /**
     * Searches and replaces values in the list of files.
     */
    public static function searchAndReplaceInFile(array $files, string $search, string $replace): void
    {
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
     */
    public static function findFilesInDirectory(string $directory): array
    {
        if (!is_dir($directory) || is_link($directory)) {
            return [];
        }

        $allFiles = [];

        foreach (new RecursiveIteratorIterator(new RecursiveDirectoryIterator($directory)) as $subItem) {
            if ($subItem->isFile() && !$subItem->isLink()) {
                $allFiles[] = $subItem->getPathname();
            }
        }

        return $allFiles;
    }
}
