<?php

namespace Netgen\Bundle\MoreGeneratorBundle\Generator;

use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Yaml\Yaml;

class ConfigurationGenerator extends Generator
{
    /**
     * Generates the main configuration.
     *
     * @param \Symfony\Component\Console\Input\InputInterface $input
     * @param \Symfony\Component\Console\Output\OutputInterface $output
     */
    public function generate(InputInterface $input, OutputInterface $output)
    {
        $settings = array();

        // Resource imports

        $settings['imports'] = array(
            array('resource' => 'ezplatform_system.yml'),
            array('resource' => '@' . $input->getOption('bundle-name') . '/Resources/config/ezplatform.yml'),
        );

        // List of siteaccesses and groups

        $siteAccessList = $input->getOption('site-access-list');
        $siteAccessNames = array_keys($siteAccessList);

        $adminSiteAccessNames = array('admin', self::NGADMINUI_SITEACCESS_NAME, self::LEGACY_ADMIN_SITEACCESS_NAME);

        $adminSiteAccessLanguages = array();
        foreach ($siteAccessList as $siteAccessLanguages) {
            foreach ($siteAccessLanguages as $siteAccessLanguage) {
                if (!in_array($siteAccessLanguage, $adminSiteAccessLanguages)) {
                    $adminSiteAccessLanguages[] = $siteAccessLanguage;
                }
            }
        }

        $settings['ezpublish']['siteaccess']['default_siteaccess'] = $siteAccessNames[0];
        $settings['ezpublish']['siteaccess']['list'] = array_merge(
            $siteAccessNames, $adminSiteAccessNames
        );

        $settings['ezpublish']['siteaccess']['groups']['frontend_group'] = $siteAccessNames;
        $settings['ezpublish']['siteaccess']['groups']['admin_group'] = $adminSiteAccessNames;

        // List of siteaccess languages

        $settings['ezpublish']['system'] = array();

        if (count($siteAccessNames) > 1) {
            $settings['ezpublish']['system']['frontend_group']['translation_siteaccesses'] = $siteAccessNames;
        }

        $designName = $input->getOption('design-name');

        $settings['netgen_block_manager']['design_list'][$designName] = array($designName);
        $settings['netgen_block_manager']['system']['frontend_group']['design'] = $designName;

        $settings['ezdesign']['design_list'][$designName] = array($designName, 'common');
        $settings['ezdesign']['design_list'][self::NGADMINUI_SITEACCESS_NAME] = array(
            self::NGADMINUI_SITEACCESS_NAME,
            'common',
        );

        foreach ($siteAccessList as $siteAccessName => $siteAccessLanguages) {
            $settings['ezpublish']['system'][$siteAccessName]['design'] = $designName;
            $settings['ezpublish']['system'][$siteAccessName]['languages'] = $siteAccessLanguages;
            $settings['ezpublish']['system'][$siteAccessName]['session'] = array(
                'name' => 'eZSESSID',
            );
        }

        foreach ($adminSiteAccessNames as $adminSiteAccessName) {
            if ($adminSiteAccessName === self::NGADMINUI_SITEACCESS_NAME) {
                $settings['ezpublish']['system'][$adminSiteAccessName]['design'] = $adminSiteAccessName;
            }

            if ($adminSiteAccessName === self::LEGACY_ADMIN_SITEACCESS_NAME) {
                $settings['ez_publish_legacy']['system'][$adminSiteAccessName]['legacy_mode'] = true;
            }

            $settings['ezpublish']['system'][$adminSiteAccessName]['languages'] = $adminSiteAccessLanguages;
            if ($adminSiteAccessName !== self::LEGACY_ADMIN_SITEACCESS_NAME) {
                $settings['ezpublish']['system'][$adminSiteAccessName]['session'] = array(
                    'name' => 'eZSESSID',
                );
            }
        }

        file_put_contents(
            $this->container->getParameter('kernel.root_dir') . '/config/ezplatform.yml',
            Yaml::dump($settings, 7)
        );

        $this->generateServerConfig($settings, $siteAccessNames);

        $output->writeln(
            array(
                '',
                'Generated <comment>ezplatform.yml</comment> configuration file!',
            )
        );
    }

    /**
     * Generates settings that are specific to server.
     *
     * @param array $baseSettings
     * @param array $siteAccessNames
     */
    protected function generateServerConfig(array $baseSettings, array $siteAccessNames)
    {
        $settings = array();

        // Siteaccess match settings

        $settings['ezpublish']['siteaccess']['match'] = array(
            'Compound\LogicalAnd' => array(),
            'Map\Host' => array(),
            'URIElement' => '1',
        );

        foreach ($baseSettings['ezpublish']['siteaccess']['list'] as $siteAccessName) {
            if ($siteAccessName !== $siteAccessNames[0]) {
                $settings['ezpublish']['siteaccess']['match']['Compound\LogicalAnd'][$siteAccessName] = array(
                    'matchers' => array(
                        'Map\URI' => array($siteAccessName => true),
                        'Map\Host' => array('%ngmore.default.site_domain%' => true),
                    ),
                    'match' => $siteAccessName,
                );
            } else {
                $settings['ezpublish']['siteaccess']['match']['Map\Host']['%ngmore.default.site_domain%'] = $siteAccessName;
            }
        }

        // Config specific files

        $kernelDir = $this->container->getParameter('kernel.root_dir');
        $serverEnv = $this->container->getParameter('server_environment');

        file_put_contents(
            $kernelDir . '/config/server/' . $serverEnv . '/ezplatform.yml',
            Yaml::dump($settings, 7)
        );

        // Root settings file

        $rootSettings = array(
            'imports' => array(
                array(
                    'resource' => $serverEnv . '/ezplatform.yml',
                ),
            ),
        );

        file_put_contents(
            $kernelDir . '/config/server/' . $serverEnv . '.yml',
            Yaml::dump($rootSettings, 7)
        );
    }
}
