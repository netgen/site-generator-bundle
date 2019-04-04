<?php

declare(strict_types=1);

namespace Netgen\Bundle\SiteGeneratorBundle\Generator;

use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Yaml\Yaml;

class ConfigurationGenerator extends Generator
{
    /**
     * Generates the main configuration.
     */
    public function generate(InputInterface $input, OutputInterface $output): void
    {
        $settings = [];

        // List of siteaccesses and groups

        $siteAccessList = $input->getOption('site-access-list');
        $siteAccessNames = array_keys($siteAccessList);

        $adminSiteAccessNames = [
            self::NGADMINUI_SITEACCESS_NAME,
            self::EZPLATFORM_ADMIN_SITEACCESS_NAME,
            self::LEGACY_ADMIN_SITEACCESS_NAME,
        ];

        $adminSiteAccessLanguages = array_values(array_unique(array_merge(...array_values($siteAccessList))));

        $settings['ezpublish']['siteaccess']['default_siteaccess'] = $siteAccessNames[0];
        $settings['ezpublish']['siteaccess']['list'] = array_merge(
            $siteAccessNames,
            $adminSiteAccessNames
        );

        $settings['ezpublish']['siteaccess']['groups']['frontend_group'] = $siteAccessNames;
        $settings['ezpublish']['siteaccess']['groups']['ngadmin_group'] = [self::NGADMINUI_SITEACCESS_NAME, self::LEGACY_ADMIN_SITEACCESS_NAME];
        $settings['ezpublish']['siteaccess']['groups']['admin_group'] = [self::EZPLATFORM_ADMIN_SITEACCESS_NAME];

        // List of siteaccess languages

        $settings['ezpublish']['system'] = [];

        if (count($siteAccessNames) > 1) {
            $settings['ezpublish']['system']['frontend_group']['translation_siteaccesses'] = $siteAccessNames;
        }

        $settings['netgen_block_manager']['design_list']['app'] = ['app'];
        $settings['netgen_block_manager']['system']['frontend_group']['design'] = 'app';

        $settings['ezdesign']['design_list']['app'] = ['app', 'common'];
        $settings['ezdesign']['design_list'][self::NGADMINUI_SITEACCESS_NAME] = [
            self::NGADMINUI_SITEACCESS_NAME,
            'common',
        ];

        foreach ($siteAccessList as $siteAccessName => $siteAccessLanguages) {
            $settings['ezpublish']['system'][$siteAccessName]['design'] = 'app';
            $settings['ezpublish']['system'][$siteAccessName]['languages'] = $siteAccessLanguages;
            $settings['ezpublish']['system'][$siteAccessName]['session'] = [
                'name' => 'eZSESSID',
            ];
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
                $settings['ezpublish']['system'][$adminSiteAccessName]['session'] = [
                    'name' => 'eZSESSID',
                ];
            }
        }

        file_put_contents(
            $this->container->getParameter('kernel.project_dir') . '/' . $this->container->getParameter('kernel.name') . '/config/ezplatform_siteaccess.yml',
            Yaml::dump($settings, 7)
        );

        $this->generateServerConfig();

        $output->writeln(
            [
                '',
                'Generated <comment>ezplatform_siteaccess.yml</comment> configuration file!',
            ]
        );
    }

    /**
     * Generates settings that are specific to server.
     */
    protected function generateServerConfig(): void
    {
        $settings = [];

        // Siteaccess match settings

        $settings['ezpublish']['siteaccess']['match']['URIElement'] = '1';

        // Config specific files

        $kernelDir = $this->container->getParameter('kernel.project_dir') . '/' . $this->container->getParameter('kernel.name');
        $serverEnv = $this->container->getParameter('server_environment');

        file_put_contents(
            $kernelDir . '/config/server/' . $serverEnv . '/ezplatform_siteaccess.yml',
            Yaml::dump($settings, 7)
        );

        // Root settings file

        $rootSettings = [
            'imports' => [
                [
                    'resource' => $serverEnv . '/ezplatform_siteaccess.yml',
                ],
            ],
        ];

        file_put_contents(
            $kernelDir . '/config/server/' . $serverEnv . '.yml',
            Yaml::dump($rootSettings, 7)
        );
    }
}
