<?php

namespace Netgen\Bundle\GeneratorBundle\Generator;

use Twig_Loader_Filesystem;
use Twig_Environment;

class Generator
{
    private $skeletonDirs;

    /**
     * Sets an array of directories to look for templates.
     *
     * The directories must be sorted from the most specific to the most
     * directory.
     *
     * @param array $skeletonDirs An array of skeleton dirs
     */
    public function setSkeletonDirs( $skeletonDirs )
    {
        $this->skeletonDirs = is_array( $skeletonDirs ) ? $skeletonDirs : array( $skeletonDirs );
    }

    protected function render( $template, $parameters )
    {
        $twig = new Twig_Environment(
            new Twig_Loader_Filesystem( $this->skeletonDirs ),
            array(
                'debug' => true,
                'cache' => false,
                'strict_variables' => true,
                'autoescape' => false,
            )
        );

        return $twig->render( $template, $parameters );
    }

    protected function renderFile( $template, $target, $parameters )
    {
        if ( !is_dir( dirname( $target ) ) )
        {
            mkdir( dirname( $target ), 0777, true );
        }

        return file_put_contents( $target, $this->render( $template, $parameters ) );
    }
}
