<?php

namespace Netgen\Bundle\GeneratorBundle\Generator;

use Symfony\Component\DependencyInjection\ContainerInterface;
use Twig_Loader_Filesystem;
use Twig_Environment;

abstract class Generator
{
    /**
     * @var array
     */
    protected $skeletonDirs;

    /**
     * @var \Symfony\Component\DependencyInjection\ContainerInterface
     */
    protected $container;

    /**
     * Constructor
     *
     * @param \Symfony\Component\DependencyInjection\ContainerInterface $container
     */
    public function __construct( ContainerInterface $container )
    {
        $this->container = $container;
    }

    /**
     * Sets an array of directories to look for templates
     *
     * The directories must be sorted from the most specific to the least specific directory
     *
     * @param array $skeletonDirs An array of skeleton dirs
     */
    public function setSkeletonDirs( $skeletonDirs )
    {
        $this->skeletonDirs = is_array( $skeletonDirs ) ? $skeletonDirs : array( $skeletonDirs );
    }

    /**
     * Renders the template
     *
     * @param string $template
     * @param array $parameters
     *
     * @return string
     */
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

    /**
     * Renders the template to a file
     *
     * @param string $template
     * @param string $target
     * @param array $parameters
     *
     * @return int
     */
    protected function renderFile( $template, $target, $parameters )
    {
        if ( !is_dir( dirname( $target ) ) )
        {
            mkdir( dirname( $target ), 0777, true );
        }

        return file_put_contents( $target, $this->render( $template, $parameters ) );
    }
}
