<?php

declare(strict_types=1);

namespace Netgen\Bundle\SiteGeneratorBundle\Generator;

use Netgen\Bundle\SiteGeneratorBundle\Helper\FileHelper;
use RuntimeException;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class LegacyProjectGenerator extends Generator
{
    /**
     * Generates the project.
     */
    public function generate(InputInterface $input, OutputInterface $output): void
    {
        $fileSystem = $this->container->get('filesystem');

        // Renaming the legacy extension

        $bundleFolder = $this->container->getParameter('kernel.project_dir') . '/src';
        $bundleNamespace = $input->getOption('bundle-namespace');

        $finalBundleLocation = $bundleFolder . '/' . str_replace('\\', '/', $bundleNamespace);

        $extensionFolder = $finalBundleLocation . '/ezpublish_legacy';
        $extensionName = $input->getOption('extension-name');
        $originalExtensionLocation = $extensionFolder . '/ngsite_demo';
        $finalExtensionLocation = $extensionFolder . '/' . $extensionName;

        $output->writeln(
            [
                '',
                'Renaming the demo extension into <comment>' . $finalExtensionLocation . '</comment>',
            ]
        );

        $fileSystem->rename($originalExtensionLocation, $finalExtensionLocation);

        // Symlinking the legacy extension to eZ Publish legacy extension folder

        $legacyRootDir = $this->container->getParameter('ezpublish_legacy.root_dir');
        $legacyExtensionFolder = $legacyRootDir . '/extension';
        $finalLegacyExtensionLocation = $legacyExtensionFolder . '/' . $extensionName;

        $output->writeln(
            [
                '',
                'Symlinking the legacy extension into <comment>' . $finalLegacyExtensionLocation . '</comment>',
            ]
        );

        if ($fileSystem->exists($finalLegacyExtensionLocation)) {
            throw new RuntimeException('The folder "' . $finalLegacyExtensionLocation . '" already exists. Aborting...');
        }

        $fileSystem->symlink(
            $fileSystem->makePathRelative($finalExtensionLocation, $legacyExtensionFolder),
            $finalLegacyExtensionLocation
        );

        // Search and replace "ngsite_demo" with the extension name

        $output->writeln(
            [
                '',
                'Renaming <comment>ngsite_demo</comment> extension name into <comment>' . $extensionName . '</comment>',
            ]
        );

        FileHelper::searchAndReplaceInFile(
            FileHelper::findFilesInDirectory($finalBundleLocation),
            'ngsite_demo',
            $extensionName
        );
    }
}
