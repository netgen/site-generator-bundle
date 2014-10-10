<?php

namespace Netgen\Bundle\GeneratorBundle\Generator;

use eZ\Publish\Core\MVC\Symfony\ConfigDumperInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Input\InputInterface;
use Netgen\Bundle\GeneratorBundle\Configuration\ConfigurationConverter;
use Netgen\Bundle\GeneratorBundle\Configuration\ConfigurationDumper;

class ConfigurationGenerator extends Generator
{
    /**
     * Generates Symfony configuration from eZ 5 config
     *
     * @param \Symfony\Component\Console\Input\InputInterface $input
     * @param \Symfony\Component\Console\Output\OutputInterface $output
     */
    public function generate( InputInterface $input, OutputInterface $output )
    {
        $availableEnvironments = array( 'dev', 'prod' );

        $this->container->get( 'ezpublish_legacy.kernel.lazy_loader' )->setBuildEventsEnabled( false );

        $configurationConverter = new ConfigurationConverter(
            $this->container->get( 'ezpublish_legacy.config.resolver' ),
            $this->container->get( 'ezpublish_legacy.kernel' )
        );

        $configurationDumper = new ConfigurationDumper(
            $this->container->get( 'filesystem' ),
            $availableEnvironments,
            $this->container->getParameter( 'kernel.root_dir' ),
            $this->container->getParameter( 'kernel.cache_dir' ),
            strtolower( $input->getOption( 'project' ) ),
            $input->getOption( 'bundle-name' ),
            $this->container->get( 'ezpublish_legacy.webconfigurator' )
        );

        $configurationDumper->dump(
            $configurationConverter->fromLegacy(
                strtolower( $input->getOption( 'project' ) ),
                $input->getOption( 'admin-site-access-name' )
            ),
            ConfigDumperInterface::OPT_DEFAULT
        );
    }
}
