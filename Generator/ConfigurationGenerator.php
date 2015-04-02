<?php

namespace Netgen\Bundle\MoreGeneratorBundle\Generator;

use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\DependencyInjection\Container;
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
            array( 'resource' => '@' . $input->getOption( 'bundle-name' ) . '/Resources/config/ezpublish.yml' )
        );

        // HTTP cache and ImageMagick settings

        $settings['ezpublish']['http_cache']['purge_type'] = '%http_cache.purge_type%';
        $settings['ezpublish']['imagemagick']['enabled'] = true;
        $settings['ezpublish']['imagemagick']['path'] = '%imagemagick_path%';

        // Repository definitions

        $projectNormalized = Container::underscore( $input->getOption( 'project' ) );
        $doctrineRepositoryName = $projectNormalized . '_repository';
        $settings['ezpublish']['repositories'][$doctrineRepositoryName]['engine'] = 'legacy';
        $settings['ezpublish']['repositories'][$doctrineRepositoryName]['connection'] = 'default';

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

        $groupName = $projectNormalized . '_group';
        $settings['ezpublish']['siteaccess']['groups'][$groupName] = $settings['ezpublish']['siteaccess']['list'];

        $frontendGroupName = $projectNormalized . '_frontend_group';
        $settings['ezpublish']['siteaccess']['groups'][$frontendGroupName] = $siteAccessNames;

        $administrationGroupName = $projectNormalized . '_administration_group';
        $settings['ezpublish']['siteaccess']['groups'][$administrationGroupName] = array( $adminSiteAccessName );

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

        $settings['ezpublish']['system'][$groupName]['repository'] = $doctrineRepositoryName;
        $settings['ezpublish']['system'][$groupName]['var_dir'] = 'var/ezdemo_site';
        $settings['ezpublish']['system'][$groupName]['http_cache'] = array(
            'purge_servers' => '%http_cache.purge_servers%'
        );

        $settings['ezpublish']['system'][$frontendGroupName]['translation_siteaccesses'] = $siteAccessNames;

        foreach ( $siteAccessList as $siteAccessName => $siteAccessLanguages )
        {
            $settings['ezpublish']['system'][$siteAccessName]['languages'] = $siteAccessLanguages;
            $settings['ezpublish']['system'][$siteAccessName]['session'] = array(
                'name' => 'eZSESSID'
            );
        }

        $settings['ezpublish']['system'][$adminSiteAccessName]['languages'] = $adminSiteAccessLanguages;

        $settings['ezpublish']['system']['default']['content']['view_cache'] = true;
        $settings['ezpublish']['system']['default']['content']['ttl_cache'] = true;
        $settings['ezpublish']['system']['default']['content']['default_ttl'] = 300;

        $settings['ez_publish_legacy']['system'][$adminSiteAccessName]['legacy_mode'] = true;

        // Stash settings

        $settings['stash'] = $this->getStashCacheSettings();

        file_put_contents(
            $this->container->getParameter( 'kernel.root_dir' ) . '/config/ezpublish.yml',
            Yaml::dump( $settings, 7 )
        );

        // Handling various parameters
        $webConfigurator = $this->container->get( 'ezpublish_legacy.webconfigurator' );
        $webConfigurator->mergeParameters(
            array(
                // Step #1 is SecretStep
                'secret' => $webConfigurator->getStep( 1 )->secret
            )
        );
        $webConfigurator->write();

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
