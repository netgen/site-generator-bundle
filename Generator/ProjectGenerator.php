<?php

namespace Netgen\Bundle\MoreGeneratorBundle\Generator;

use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Input\InputInterface;
use Netgen\Bundle\MoreGeneratorBundle\Helper\FileHelper;
use Symfony\Component\DependencyInjection\Container;
use RuntimeException;

class ProjectGenerator extends Generator
{
    /**
     * Generates the project.
     *
     * @param \Symfony\Component\Console\Input\InputInterface $input
     * @param \Symfony\Component\Console\Output\OutputInterface $output
     */
    public function generate(InputInterface $input, OutputInterface $output)
    {
        $fileSystem = $this->container->get('filesystem');
        $bundleFolder = $this->container->getParameter('kernel.project_dir') . '/src';
        $bundleNamespace = $input->getOption('bundle-namespace');
        $bundleName = $input->getOption('bundle-name');
        $finalBundleLocation = $bundleFolder . '/' . strtr($bundleNamespace, '\\', '/');

        // Renaming the bundle namespace

        $output->writeln(
            array(
                '',
                'Renaming <comment>Netgen\Bundle\MoreDemoBundle</comment> bundle namespace into <comment>' . $bundleNamespace . '</comment>',
            )
        );

        $namespaceClientPart = explode('/', strtr($bundleNamespace, '\\', '/'));
        $namespaceClientPart = $namespaceClientPart[0];

        if (strtolower($namespaceClientPart) !== 'netgen') {
            $fileSystem->rename(
                $bundleFolder . '/Netgen',
                $bundleFolder . '/' . $namespaceClientPart
            );
        }

        $fileSystem->rename(
            $bundleFolder . '/' . $namespaceClientPart . '/Bundle/MoreDemoBundle',
            $finalBundleLocation
        );

        FileHelper::searchAndReplaceInFile(
            FileHelper::findFilesInDirectory($finalBundleLocation),
            'Netgen\Bundle\MoreDemoBundle',
            $bundleNamespace
        );

        // Renaming the bundle name

        $bundleBaseName = substr($bundleName, 0, -6);

        $output->writeln(
            array(
                '',
                'Renaming <comment>NetgenMoreDemo</comment> bundle name into <comment>' . $bundleBaseName . '</comment>',
            )
        );

        FileHelper::searchAndReplaceInFile(
            FileHelper::findFilesInDirectory($finalBundleLocation),
            'NetgenMoreDemo',
            $bundleBaseName
        );

        $fileSystem->rename(
            $finalBundleLocation . '/NetgenMoreDemoBundle.php',
            $finalBundleLocation . '/' . $bundleName . '.php'
        );

        // Renaming the bundle extension & configuration

        $bundleExtensionName = $bundleBaseName . 'Extension';

        $output->writeln(
            array(
                '',
                'Renaming <comment>NetgenMoreDemoExtension</comment> DI extension into <comment>' . $bundleExtensionName . '</comment>',
            )
        );

        $fileSystem->rename(
            $finalBundleLocation . '/DependencyInjection/NetgenMoreDemoExtension.php',
            $finalBundleLocation . '/DependencyInjection/' . $bundleExtensionName . '.php'
        );

        FileHelper::searchAndReplaceInFile(
            $finalBundleLocation . '/DependencyInjection/Configuration.php',
            'netgen_more_demo',
            Container::underscore($bundleBaseName)
        );

        // Renaming the bundle assets path

        $bundleAssetsPathPart = preg_replace('/bundle$/', '', strtolower($bundleName));

        $output->writeln(
            array(
                '',
                'Renaming <comment>netgenmoredemo</comment> asset path into <comment>' . $bundleAssetsPathPart . '</comment>',
            )
        );

        FileHelper::searchAndReplaceInFile(
            FileHelper::findFilesInDirectory($finalBundleLocation),
            '/netgenmoredemo/',
            '/' . $bundleAssetsPathPart . '/'
        );

        // Renaming the site name

        $siteName = $input->getOption('site-name');

        $output->writeln(
            array(
                '',
                'Renaming <comment>NG More</comment> site name into <comment>' . $siteName . '</comment>',
            )
        );

        FileHelper::searchAndReplaceInFile(
            FileHelper::findFilesInDirectory($finalBundleLocation),
            'NG More',
            $siteName
        );

        // Renaming the design folder

        $designName = $input->getOption('design-name');

        $output->writeln(
            array(
                '',
                'Renaming <comment>demo</comment> theme into <comment>' . $designName . '</comment>',
            )
        );

        $themeFolders = array(
            $finalBundleLocation . '/Resources/views/themes/demo' => $finalBundleLocation . '/Resources/views/themes/' . $designName,
            $finalBundleLocation . '/Resources/views/ngbm/themes/demo' => $finalBundleLocation . '/Resources/views/ngbm/themes/' . $designName,
        );

        foreach ($themeFolders as $sourceThemeFolder => $destThemeFolder) {
            if ($fileSystem->exists($destThemeFolder)) {
                throw new RuntimeException('The folder "' . $destThemeFolder . '" already exists. Aborting...');
            }

            $fileSystem->rename($sourceThemeFolder, $destThemeFolder);
        }
    }
}
