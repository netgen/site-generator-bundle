<?php

declare(strict_types=1);

namespace Netgen\Bundle\SiteGeneratorBundle\Generator;

use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Yaml\Yaml;
use function array_keys;
use function array_merge;
use function array_unique;
use function array_values;
use function count;
use function file_put_contents;

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
            self::EZPLATFORM_ADMIN_SITEACCESS_NAME,
        ];

        $adminSiteAccessLanguages = array_values(array_unique(array_merge(...array_values($siteAccessList))));

        $settings['ezpublish']['siteaccess']['default_siteaccess'] = $siteAccessNames[0];
        $settings['ezpublish']['siteaccess']['list'] = array_merge(
            $siteAccessNames,
            $adminSiteAccessNames,
        );

        $settings['ezpublish']['siteaccess']['groups']['frontend_group'] = $siteAccessNames;
        $settings['ezpublish']['siteaccess']['groups']['admin_group'] = [self::EZPLATFORM_ADMIN_SITEACCESS_NAME];

        // List of siteaccess languages

        $settings['ezpublish']['system'] = [];

        if (count($siteAccessNames) > 1) {
            $settings['ezpublish']['system']['frontend_group']['translation_siteaccesses'] = $siteAccessNames;
        }

        $settings['netgen_layouts']['design_list']['app'] = ['app'];
        $settings['netgen_layouts']['system']['frontend_group']['design'] = 'app';

        $settings['ezdesign']['design_list']['app'] = ['app', 'common'];

        foreach ($siteAccessList as $siteAccessName => $siteAccessLanguages) {
            $settings['ezpublish']['system'][$siteAccessName]['design'] = 'app';
            $settings['ezpublish']['system'][$siteAccessName]['languages'] = $siteAccessLanguages;
            $settings['ezpublish']['system'][$siteAccessName]['session'] = [
                'name' => 'eZSESSID',
            ];
        }

        foreach ($adminSiteAccessNames as $adminSiteAccessName) {
            $settings['ezpublish']['system'][$adminSiteAccessName]['languages'] = $adminSiteAccessLanguages;
            $settings['ezpublish']['system'][$adminSiteAccessName]['session'] = [
                'name' => 'eZSESSID',
            ];
        }

        file_put_contents(
            $this->container->getParameter('kernel.project_dir') . '/config/app/packages/ezplatform_siteaccess.yaml',
            Yaml::dump($settings, 7),
        );

        $this->generateServerConfig();

        $output->writeln(
            [
                '',
                'Generated <comment>ezplatform_siteaccess.yaml</comment> configuration file!',
            ],
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

        $kernelDir = $this->container->getParameter('kernel.project_dir');
        $serverEnv = $this->container->getParameter('server_environment');

        file_put_contents(
            $kernelDir . '/config/app/server/' . $serverEnv . '/ezplatform_siteaccess.yaml',
            Yaml::dump($settings, 7),
        );

        // Root settings file

        $rootSettings = [
            'imports' => [
                [
                    'resource' => $serverEnv . '/ezplatform_siteaccess.yaml',
                ],
            ],
        ];

        file_put_contents(
            $kernelDir . '/config/app/server/' . $serverEnv . '.yaml',
            Yaml::dump($rootSettings, 7),
        );
    }
}
