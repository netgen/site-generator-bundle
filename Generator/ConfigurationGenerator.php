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
            array( 'resource' => '@NetgenMoreBundle/Resources/config/ezpublish.yml' ),
            array( 'resource' => '@NetgenMoreAdminUIBundle/Resources/config/ezpublish.yml' ),
            array( 'resource' => '@' . $input->getOption( 'bundle-name' ) . '/Resources/config/ezpublish.yml' )
        );

        // Doctrine settings

        $settings['doctrine']['dbal']['connections']['default'] = array(
            'driver' => '%database_driver%',
            'host' => '%database_host%',
            'port' => '%database_port%',
            'user' => '%database_user%',
            'password' => '%database_password%',
            'dbname' => '%database_name%',
            'charset' => 'UTF8'
        );

        // HTTP cache and ImageMagick settings

        $settings['ezpublish']['http_cache']['purge_type'] = '%http_cache.purge_type%';
        $settings['ezpublish']['imagemagick']['enabled'] = true;
        $settings['ezpublish']['imagemagick']['path'] = '%imagemagick_path%';

        // Repository definitions

        $settings['ezpublish']['repositories']['main']['engine'] = 'legacy';
        $settings['ezpublish']['repositories']['main']['connection'] = 'default';

        // List of siteaccesses and groups

        $siteAccessList = $input->getOption( 'site-access-list' );
        $siteAccessNames = array_keys( $siteAccessList );
        $adminSiteAccessNames = array( $input->getOption( 'admin-site-access-name' ) );
        if ( $this->generateNgAdminUi )
        {
            $adminSiteAccessNames[] = self::NGADMINUI_SITEACCESS_NAME;
        }

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
        $settings['ezpublish']['siteaccess']['list'] = array_merge(
            $siteAccessNames, $adminSiteAccessNames
        );

        $settings['ezpublish']['siteaccess']['groups']['frontend_group'] = $siteAccessNames;
        $settings['ezpublish']['siteaccess']['groups']['administration_group'] = $adminSiteAccessNames;

        // Siteaccess match settings

        $settings['ezpublish']['siteaccess']['match'] = array(
            'Compound\LogicalAnd' => array(),
            'Map\Host' => array(),
            'URIElement' => '1'
        );

        $siteDomain = $input->getOption( 'site-domain' );

        foreach ( $settings['ezpublish']['siteaccess']['list'] as $siteAccessName )
        {
            if ( $siteAccessName !== $siteAccessNames[0] )
            {
                $settings['ezpublish']['siteaccess']['match']['Compound\LogicalAnd'][$siteAccessName] = array(
                    'matchers' => array(
                        'Map\URI' => array( $siteAccessName => true ),
                        'Map\Host' => array( $siteDomain => true )
                    ),
                    'match' => $siteAccessName
                );
            }
            else
            {
                $settings['ezpublish']['siteaccess']['match']['Map\Host'][$siteDomain] = $siteAccessName;
            }
        }

        // List of global settings and siteaccess languages

        $settings['ezpublish']['system'] = array();

        $settings['ezpublish']['system']['global']['repository'] = 'main';
        $settings['ezpublish']['system']['global']['var_dir'] = 'var/ezdemo_site';
        $settings['ezpublish']['system']['global']['http_cache'] = array(
            'purge_servers' => '%http_cache.purge_servers%'
        );

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

        foreach ( $adminSiteAccessNames as $adminSiteAccessName )
        {
            if ( $adminSiteAccessName !== self::NGADMINUI_SITEACCESS_NAME )
            {
                $settings['ezpublish']['system'][$adminSiteAccessName]['legacy_mode'] = true;
            }

            $settings['ezpublish']['system'][$adminSiteAccessName]['languages'] = $adminSiteAccessLanguages;
            $settings['ezpublish']['system'][$adminSiteAccessName]['session'] = array(
                'name' => 'eZSESSID'
            );
        }

        // Stash settings

        $settings['stash'] = $this->getStashCacheSettings();

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

    /**
     * Returns cache settings based on which cache functionality is available on the current server
     *
     * Order of preference:
     * - FileSystem
     * - APC
     * - Memcache  [DISABLED, SEE INLINE]
     * - Xcache  [DISABLED, SEE INLINE]
     * - variable instance cache  [DISABLED, SEE INLINE]
     *
     * @return array
     */
    protected function getStashCacheSettings()
    {
        // Should only contain one out of the box
        $handlers = array();
        $handlerSetting = array();
        if ( FileSystem::isAvailable() )
        {
            $handlers[] = 'FileSystem';
            // If running on Windows, use "crc32" keyHashFunction
            if ( stripos( php_uname(), 'win' ) === 0 )
            {
                $handlerSetting['FileSystem'] = array(
                    'keyHashFunction' => 'crc32'
                );
            }
        }
        else
        {
            // '/dev/null' fallback driver, no cache at all
            $handlers[] = 'BlackHole';
        }

        return array(
            'tracking' => false,
            'caches' => array(
                'default' => array(
                    'drivers' => $handlers,
                    // inMemory will enable/disable "Ephemeral", not allowed as separate handler in stash-bundle
                    'inMemory' => true,
                    'registerDoctrineAdapter' => false
                ) + $handlerSetting
            )
        );
    }
}
