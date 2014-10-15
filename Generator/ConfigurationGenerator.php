<?php

namespace Netgen\Bundle\MoreGeneratorBundle\Generator;

use eZ\Publish\Core\MVC\Symfony\ConfigDumperInterface;
use Netgen\Bundle\MoreGeneratorBundle\Configuration\ConfigurationConverter;
use Netgen\Bundle\MoreGeneratorBundle\Configuration\ConfigurationDumper;

class ConfigurationGenerator extends Generator
{
    /**
     * Generates Symfony configuration from eZ 5 config
     *
     * @param string $projectName
     * @param string $adminSiteAccessName
     * @param string $bundleName
     */
    public function generate( $projectName, $adminSiteAccessName, $bundleName )
    {
        $this->container->get( 'ezpublish_legacy.kernel.lazy_loader' )->setBuildEventsEnabled( false );

        $configurationConverter = new ConfigurationConverter(
            $this->container->get( 'ezpublish_legacy.config.resolver' ),
            $this->container->get( 'ezpublish_legacy.kernel' )
        );

        $configurationDumper = new ConfigurationDumper(
            $this->container->get( 'filesystem' ),
            $this->container->getParameter( 'netgen_more.generator.available_environments' ),
            $this->container->getParameter( 'kernel.root_dir' ),
            $this->container->getParameter( 'kernel.cache_dir' ),
            $this->container->get( 'ezpublish_legacy.webconfigurator' )
        );

        $configurationDumper->dump(
            $configurationConverter->fromLegacy(
                strtolower( $projectName ),
                $adminSiteAccessName,
                $bundleName
            ),
            ConfigDumperInterface::OPT_DEFAULT
        );
    }
}
