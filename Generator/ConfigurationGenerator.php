<?php

namespace Netgen\Bundle\MoreGeneratorBundle\Generator;

use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Yaml\Yaml;
use Stash\Driver\FileSystem;

class ConfigurationGenerator extends Generator
{
    /**
     * Generates the main configuration
     *
     * @param \Symfony\Component\Console\Input\InputInterface $input
     * @param \Symfony\Component\Console\Output\OutputInterface $output
     */
    public function generate( InputInterface $input, OutputInterface $output )
    {
        $settings = array();

        // Resource imports

        $settings['imports'] = array(
            array( 'resource' => 'ezpublish_system.yml' ),
            array( 'resource' => '@' . $input->getOption( 'bundle-name' ) . '/Resources/config/ezpublish.yml' ),
        );

        // List of siteaccesses and groups

        $siteAccessList = $input->getOption( 'site-access-list' );
        $siteAccessNames = array_keys( $siteAccessList );
        $adminSiteAccessName = $input->getOption( 'admin-site-access-name' );

        $adminSiteAccessLanguages = array();
        foreach ( $siteAccessList as $siteAccessLanguages )
        {
            foreach ( $siteAccessLanguages as $siteAccessLanguage )
            {
                if ( !in_array( $siteAccessLanguage, $adminSiteAccessLanguages ) )
                {
                    $adminSiteAccessLanguages[] = $siteAccessLanguage;
                }
            }
        }

        $settings['ezpublish']['siteaccess']['default_siteaccess'] = $siteAccessNames[0];
        $settings['ezpublish']['siteaccess']['list'] = $siteAccessNames;
        $settings['ezpublish']['siteaccess']['list'][] = $adminSiteAccessName;

        $settings['ezpublish']['siteaccess']['groups']['frontend_group'] = $siteAccessNames;
        $settings['ezpublish']['siteaccess']['groups']['administration_group'] = array( $adminSiteAccessName );

        // Siteaccess match settings

        $settings['ezpublish']['siteaccess']['match'] = array(
            'Compound\LogicalAnd' => array(),
            'Map\Host' => array(),
            'URIElement' => '1'
        );

        foreach ( $settings['ezpublish']['siteaccess']['list'] as $siteAccessName )
        {
            if ( $siteAccessName !== $siteAccessNames[0] )
            {
                $settings['ezpublish']['siteaccess']['match']['Compound\LogicalAnd'][$siteAccessName] = array(
                    'matchers' => array(
                        'Map\URI' => array( $siteAccessName => true ),
                        'Map\Host' => array( '%ngmore.default.site_domain%' => true )
                    ),
                    'match' => $siteAccessName
                );
            }
            else
            {
                $settings['ezpublish']['siteaccess']['match']['Map\Host']['%ngmore.default.site_domain%'] = $siteAccessName;
            }
        }

        // List of siteaccess languages

        $settings['ezpublish']['system'] = array();

        if ( count( $siteAccessNames ) > 1 )
        {
            $settings['ezpublish']['system']['frontend_group']['translation_siteaccesses'] = $siteAccessNames;
        }

        foreach ( $siteAccessList as $siteAccessName => $siteAccessLanguages )
        {
            $settings['ezpublish']['system'][$siteAccessName]['languages'] = $siteAccessLanguages;
            $settings['ezpublish']['system'][$siteAccessName]['session'] = array(
                'name' => 'eZSESSID'
            );
        }

        $settings['ezpublish']['system'][$adminSiteAccessName]['languages'] = $adminSiteAccessLanguages;
        $settings['ez_publish_legacy']['system'][$adminSiteAccessName]['legacy_mode'] = true;

        file_put_contents(
            $this->container->getParameter( 'kernel.root_dir' ) . '/config/ezpublish.yml',
            Yaml::dump( $settings, 7 )
        );

        $output->writeln(
            array(
                '',
                'Generated <comment>ezpublish.yml</comment> configuration file!'
            )
        );
    }
}
