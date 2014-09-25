<?php

namespace Netgen\Bundle\GeneratorBundle\Helper;

class FileHelper
{
    /**
     * Searches and replaces values in the list of files
     *
     * @param string|array $files
     * @param string $search
     * @param string $replace
     */
    public static function searchAndReplaceInFile( $files, $search, $replace )
    {
        if ( is_string( $files ) )
        {
            $files = array( $files );
        }

        foreach ( $files as $file )
        {
            if ( !is_file( $file ) || !is_readable( $file ) || !is_writeable( $file ) )
            {
                continue;
            }

            $fileContents = file_get_contents( $file );
            $fileContents = str_replace( $search, $replace, $fileContents );
            file_put_contents( $file, $fileContents );
        }
    }

    /**
     * Lists all files in a directory
     *
     * @param string $directory
     *
     * @return array
     */
    public static function findFilesInDirectory( $directory )
    {
        if ( !is_dir( $directory ) )
        {
            return array();
        }

        $directoryFiles = scandir( $directory );

        if ( empty( $directoryFiles ) )
        {
            return array();
        }

        $allFiles = array();

        foreach ( $directoryFiles as $file )
        {
            if ( $file == '.' || $file == '..' )
            {
                continue;
            }

            if ( is_dir( $directory . '/' . $file ) )
            {
                $allFiles = array_merge( self::findFilesInDirectory( $directory . '/' . $file ), $allFiles );
                continue;
            }

            if ( !is_file( $directory . '/' . $file ) )
            {
                continue;
            }

            $allFiles[] = $directory . '/' . $file;
        }

        return $allFiles;
    }
}
