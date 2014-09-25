<?php

namespace Netgen\Bundle\GeneratorBundle\Command;

use Symfony\Component\HttpKernel\Bundle\BundleInterface;
use Symfony\Bundle\FrameworkBundle\Command\ContainerAwareCommand;
use Netgen\Bundle\GeneratorBundle\Command\Helper\DialogHelper;
use Netgen\Bundle\GeneratorBundle\Generator\Generator;

abstract class GeneratorCommand extends ContainerAwareCommand
{
    private $generator;

    // only useful for unit tests
    public function setGenerator( Generator $generator )
    {
        $this->generator = $generator;
    }

    protected abstract function createGenerator();

    protected function getGenerator( BundleInterface $bundle = null )
    {
        if ( $this->generator === null )
        {
            $this->generator = $this->createGenerator();
            $this->generator->setSkeletonDirs( $this->getSkeletonDirs( $bundle ) );
        }

        return $this->generator;
    }

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
