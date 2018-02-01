<?php

namespace Netgen\Bundle\MoreGeneratorBundle\Generator;

use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Input\InputInterface;
use Netgen\Bundle\MoreGeneratorBundle\Helper\FileHelper;
use RuntimeException;

class LegacyProjectGenerator extends Generator
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

        // Renaming the legacy extension

        $bundleFolder = $this->container->getParameter('kernel.project_dir') . '/src';
        $bundleNamespace = $input->getOption('bundle-namespace');

        $finalBundleLocation = $bundleFolder . '/' . strtr($bundleNamespace, '\\', '/');

        $extensionFolder = $finalBundleLocation . '/ezpublish_legacy';
        $extensionName = $input->getOption('extension-name');
        $originalExtensionLocation = $extensionFolder . '/ez_netgen_ngmore_demo';
        $finalExtensionLocation = $extensionFolder . '/' . $extensionName;

        $output->writeln(
            array(
                '',
                'Renaming the demo extension into <comment>' . $finalExtensionLocation . '</comment>',
            )
        );

        $fileSystem->rename($originalExtensionLocation, $finalExtensionLocation);

        // Symlinking the legacy extension to eZ Publish legacy extension folder

        $legacyRootDir = $this->container->getParameter('ezpublish_legacy.root_dir');
        $legacyExtensionFolder = $legacyRootDir . '/extension';
        $finalLegacyExtensionLocation = $legacyExtensionFolder . '/' . $extensionName;

        $output->writeln(
            array(
                '',
                'Symlinking the legacy extension into <comment>' . $finalLegacyExtensionLocation . '</comment>',
            )
        );

        if ($fileSystem->exists($finalLegacyExtensionLocation)) {
            throw new RuntimeException('The folder "' . $finalLegacyExtensionLocation . '" already exists. Aborting...');
        }

        $fileSystem->symlink(
            $fileSystem->makePathRelative($finalExtensionLocation, $legacyExtensionFolder),
            $finalLegacyExtensionLocation
        );

        // Renaming the design folder

        $designName = $input->getOption('design-name');
        $finalDesignLocation = $finalExtensionLocation . '/design/' . $designName;

        $output->writeln(
            array(
                '',
                'Renaming <comment>ngmore_demo</comment> design into <comment>' . $designName . '</comment>',
            )
        );

        if ($fileSystem->exists($finalDesignLocation)) {
            throw new RuntimeException('The folder "' . $finalDesignLocation . '" already exists. Aborting...');
        }

        $fileSystem->rename($finalExtensionLocation . '/design/ngmore_demo', $finalDesignLocation);

        // Search and replace "ez_netgen_ngmore_demo" with the extension name

        $output->writeln(
            array(
                '',
                'Renaming <comment>ez_netgen_ngmore_demo</comment> extension name into <comment>' . $extensionName . '</comment>',
            )
        );

        FileHelper::searchAndReplaceInFile(
            FileHelper::findFilesInDirectory($finalBundleLocation),
            'ez_netgen_ngmore_demo',
            $extensionName
        );

        // Search and replace "ngmore_demo" with the name of site design

        $output->writeln(
            array(
                '',
                'Renaming <comment>ngmore_demo</comment> design into <comment>' . $designName . '</comment>',
            )
        );

        FileHelper::searchAndReplaceInFile(
            FileHelper::findFilesInDirectory($finalExtensionLocation),
            'ngmore_demo',
            $designName
        );
    }
}
