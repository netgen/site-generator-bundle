<?php

namespace Netgen\Bundle\GeneratorBundle\Command;

use Symfony\Component\HttpKernel\Bundle\BundleInterface;
use Symfony\Bundle\FrameworkBundle\Command\ContainerAwareCommand;
use Netgen\Bundle\GeneratorBundle\Command\Helper\DialogHelper;
use Netgen\Bundle\GeneratorBundle\Generator\Generator;

abstract class GeneratorCommand extends ContainerAwareCommand
{
    /**
     * @var \Netgen\Bundle\GeneratorBundle\Generator\Generator
     */
    private $generator;

    /**
     * Sets the generator
     *
     * Only useful for unit tests
     *
     * @param \Netgen\Bundle\GeneratorBundle\Generator\Generator $generator
     */
    public function setGenerator( Generator $generator )
    {
        $this->generator = $generator;
    }

    /**
     * Creates the generator
     *
     * @return \Netgen\Bundle\GeneratorBundle\Generator\Generator
     */
    protected abstract function createGenerator();

    /**
     * Gets the generator
     *
     * @param \Symfony\Component\HttpKernel\Bundle\BundleInterface $bundle
     *
     * @return \Netgen\Bundle\GeneratorBundle\Generator\Generator
     */
    protected function getGenerator( BundleInterface $bundle = null )
    {
        if ( $this->generator === null )
        {
            $this->generator = $this->createGenerator();
            $this->generator->setSkeletonDirs( $this->getSkeletonDirs( $bundle ) );
        }

        return $this->generator;
    }

    /**
     * Returns skeleton directories
     *
     * @param \Symfony\Component\HttpKernel\Bundle\BundleInterface $bundle
     *
     * @return array
     */
    protected function getSkeletonDirs( BundleInterface $bundle = null )
    {
        $skeletonDirs = array();

        if ( isset( $bundle ) && is_dir( $dir = $bundle->getPath() . '/Resources/NetgenGeneratorBundle/skeleton' ) )
        {
            $skeletonDirs[] = $dir;
        }

        if ( is_dir( $dir = $this->getContainer()->get( 'kernel' )->getRootdir() . '/Resources/NetgenGeneratorBundle/skeleton' ) )
        {
            $skeletonDirs[] = $dir;
        }

        $skeletonDirs[] = __DIR__ . '/../Resources/skeleton';
        $skeletonDirs[] = __DIR__ . '/../Resources';

        return $skeletonDirs;
    }

    /**
     * Returns the dialog helper
     *
     * @return \Netgen\Bundle\GeneratorBundle\Command\Helper\DialogHelper
     */
    protected function getDialogHelper()
    {
        $dialog = $this->getHelperSet()->get( 'dialog' );
        if ( !$dialog || get_class( $dialog ) !== 'Netgen\Bundle\GeneratorBundle\Command\Helper\DialogHelper' )
        {
            $this->getHelperSet()->set( $dialog = new DialogHelper() );
        }

        return $dialog;
    }
}
