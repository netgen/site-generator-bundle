<?php

namespace Netgen\Bundle\GeneratorBundle\Helper;

use Symfony\Component\Process\ProcessBuilder;
use Symfony\Component\Filesystem\Filesystem;
use RuntimeException;

class GitHelper
{
    /**
     * Clones the git repo
     *
     * @param string $repoUrl
     * @param string $location
     */
    public static function cloneRepo( $repoUrl, $location )
    {
        if ( file_exists( $location ) )
        {
            throw new RuntimeException( 'The folder "' . $location . '" already exists. Aborting...' );
        }

        $processBuilder = new ProcessBuilder(
            array(
                'git',
                'clone',
                '--progress',
                $repoUrl,
                $location
            )
        );

        $process = $processBuilder->getProcess();

        $process->setTimeout( 3600 );
        $process->run(
            function ( $type, $buffer )
            {
                echo $buffer;
            }
        );

        if ( !$process->isSuccessful() )
        {
            throw new RuntimeException( $process->getErrorOutput() );
        }
    }
}
