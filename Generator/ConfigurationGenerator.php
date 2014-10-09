<?php

namespace Netgen\Bundle\GeneratorBundle\Generator;

use eZ\Publish\Core\MVC\Symfony\ConfigDumperInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Input\InputInterface;
use Netgen\Bundle\GeneratorBundle\Configuration\ConfigurationConverter;
use Netgen\Bundle\GeneratorBundle\Configuration\ConfigurationDumper;

class ConfigurationGenerator extends Generator
{
    /**
     * @var \Symfony\Component\DependencyInjection\ContainerInterface
     */
    private $container;

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
     * Generates Symfony configuration from eZ 5 config
     *
     * @param \Symfony\Component\Console\Input\InputInterface $input
     * @param \Symfony\Component\Console\Output\OutputInterface $output
     */
    public function generate( InputInterface $input, OutputInterface $output )
    {
        $availableEnvironments = array( 'dev', 'prod' );
        $adminSiteAccess = $input->getOption( 'admin-site-access-name' );

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
            $adminSiteAccess,
            $input->getOption( 'bundle-name' ),
            $this->container->get( 'ezpublish_legacy.webconfigurator' )
        );

        $configurationDumper->dump(
            $configurationConverter->fromLegacy(
                strtolower( $input->getOption( 'project' ) ),
                $adminSiteAccess
            ),
            ConfigDumperInterface::OPT_DEFAULT
        );
    }
}
