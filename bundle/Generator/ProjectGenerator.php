<?php

declare(strict_types=1);

namespace Netgen\Bundle\SiteGeneratorBundle\Generator;

use Netgen\Bundle\SiteGeneratorBundle\Helper\FileHelper;
use RuntimeException;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\DependencyInjection\Container;

class ProjectGenerator extends Generator
{
    /**
     * Generates the project.
     */
    public function generate(InputInterface $input, OutputInterface $output): void
    {
        $fileSystem = $this->container->get('filesystem');
        $srcFolder = $this->container->getParameter('kernel.project_dir') . '/src';
        $bundleNamespace = $input->getOption('bundle-namespace');
        $bundleFolder = str_replace('\\', '/', $bundleNamespace);
        $bundleName = $input->getOption('bundle-name');
        $finalBundleLocation = $srcFolder . '/' . $bundleFolder;

        // Renaming the bundle namespace

        $output->writeln(
            [
                '',
                'Renaming <comment>Netgen\Bundle\SiteDemoBundle</comment> bundle namespace into <comment>' . $bundleNamespace . '</comment>',
            ]
        );

        $namespaceClientPart = explode('/', str_replace('\\', '/', $bundleNamespace));
        $namespaceClientPart = $namespaceClientPart[0];

        if (strtolower($namespaceClientPart) !== 'netgen') {
            $fileSystem->rename(
                $srcFolder . '/Netgen',
                $srcFolder . '/' . $namespaceClientPart
            );
        }

        $fileSystem->rename(
            $srcFolder . '/' . $namespaceClientPart . '/Bundle/SiteDemoBundle',
            $finalBundleLocation
        );

        FileHelper::searchAndReplaceInFile(
            FileHelper::findFilesInDirectory($finalBundleLocation),
            'Netgen\Bundle\SiteDemoBundle',
            $bundleNamespace
        );

        // Renaming the bundle folder

        $output->writeln(
            [
                '',
                'Renaming <comment>src/Netgen/Bundle/SiteDemoBundle</comment> bundle folder into <comment>src/' . $bundleFolder . '</comment>',
            ]
        );

        if (file_exists($this->container->getParameter('kernel.project_dir') . '/webpack.config.default.js')) {
            FileHelper::searchAndReplaceInFile(
                [$this->container->getParameter('kernel.project_dir') . '/webpack.config.default.js'],
                'Netgen/Bundle/SiteDemoBundle',
                $bundleFolder
            );
        }

        // Renaming the bundle name

        $bundleBaseName = substr($bundleName, 0, -6);

        $output->writeln(
            [
                '',
                'Renaming <comment>NetgenSiteDemo</comment> bundle name into <comment>' . $bundleBaseName . '</comment>',
            ]
        );

        FileHelper::searchAndReplaceInFile(
            FileHelper::findFilesInDirectory($finalBundleLocation),
            'NetgenSiteDemo',
            $bundleBaseName
        );

        $fileSystem->rename(
            $finalBundleLocation . '/NetgenSiteDemoBundle.php',
            $finalBundleLocation . '/' . $bundleName . '.php'
        );

        // Renaming the bundle extension & configuration

        $bundleExtensionName = $bundleBaseName . 'Extension';

        $output->writeln(
            [
                '',
                'Renaming <comment>NetgenSiteDemoExtension</comment> DI extension into <comment>' . $bundleExtensionName . '</comment>',
            ]
        );

        $fileSystem->rename(
            $finalBundleLocation . '/DependencyInjection/NetgenSiteDemoExtension.php',
            $finalBundleLocation . '/DependencyInjection/' . $bundleExtensionName . '.php'
        );

        FileHelper::searchAndReplaceInFile(
            [$finalBundleLocation . '/DependencyInjection/Configuration.php'],
            'netgen_site_demo',
            Container::underscore($bundleBaseName)
        );

        // Renaming the bundle assets path

        $bundleAssetsPathPart = preg_replace('/bundle$/', '', strtolower($bundleName));

        $output->writeln(
            [
                '',
                'Renaming <comment>netgensitedemo</comment> asset path into <comment>' . $bundleAssetsPathPart . '</comment>',
            ]
        );

        FileHelper::searchAndReplaceInFile(
            FileHelper::findFilesInDirectory($finalBundleLocation),
            '/netgensitedemo/',
            '/' . $bundleAssetsPathPart . '/'
        );

        // Renaming the site name

        $siteName = $input->getOption('site-name');

        $output->writeln(
            [
                '',
                'Renaming <comment>Netgen Site</comment> site name into <comment>' . $siteName . '</comment>',
            ]
        );

        FileHelper::searchAndReplaceInFile(
            FileHelper::findFilesInDirectory($finalBundleLocation),
            'Netgen Site',
            $siteName
        );

        // Renaming the theme folder

        $themeName = $input->getOption('theme-name');

        $output->writeln(
            [
                '',
                'Renaming <comment>demo</comment> theme into <comment>' . $themeName . '</comment>',
            ]
        );

        $themeFolders = [
            $finalBundleLocation . '/Resources/views/themes/demo' => $finalBundleLocation . '/Resources/views/themes/' . $themeName,
            $finalBundleLocation . '/Resources/views/ngbm/themes/demo' => $finalBundleLocation . '/Resources/views/ngbm/themes/' . $themeName,
        ];

        foreach ($themeFolders as $sourceThemeFolder => $destThemeFolder) {
            if ($fileSystem->exists($destThemeFolder)) {
                throw new RuntimeException('The folder "' . $destThemeFolder . '" already exists. Aborting...');
            }

            $fileSystem->rename($sourceThemeFolder, $destThemeFolder);
        }
    }
}
