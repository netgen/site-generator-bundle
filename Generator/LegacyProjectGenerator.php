<?php

namespace Netgen\Bundle\MoreGeneratorBundle\Generator;

use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Input\InputInterface;
use Netgen\Bundle\MoreGeneratorBundle\Helper\FileHelper;
use Netgen\Bundle\MoreGeneratorBundle\Helper\GitHelper;
use RuntimeException;

class LegacyProjectGenerator extends Generator
{
    /**
     * Generates the project
     *
     * @param \Symfony\Component\Console\Input\InputInterface $input
     * @param \Symfony\Component\Console\Output\OutputInterface $output
     */
    public function generate( InputInterface $input, OutputInterface $output )
    {
        $fileSystem = $this->container->get( 'filesystem' );

        // Cloning the legacy extension

        $bundleFolder = $this->container->getParameter( 'kernel.root_dir' ) . '/../src';
        $bundleNamespace = $input->getOption( 'bundle-namespace' );

        $finalBundleLocation = $bundleFolder . '/' . strtr( $bundleNamespace, '\\', '/' );

        $extensionFolder = $finalBundleLocation . '/ezpublish_legacy';
        $extensionName = $input->getOption( 'extension-name' );
        $finalExtensionLocation = $extensionFolder . '/' . $extensionName;

        $output->writeln(
            array(
                '',
                'Cloning the demo extension into <comment>' . $finalExtensionLocation . '</comment>'
            )
        );

        GitHelper::cloneRepo(
            $this->container->getParameter( 'netgen_more.generator.demo_extension_url' ),
            $finalExtensionLocation
        );
        $fileSystem->remove( $finalExtensionLocation . '/.git/' );

        // Symlinking the legacy extension to eZ Publish legacy extension folder

        $legacyRootDir = $this->container->getParameter( 'ezpublish_legacy.root_dir' );
        $legacyExtensionFolder = $legacyRootDir . '/extension';
        $finalLegacyExtensionLocation = $legacyExtensionFolder . '/' . $extensionName;

        $output->writeln(
            array(
                '',
                'Symlinking the legacy extension into <comment>' . $finalLegacyExtensionLocation . '</comment>'
            )
        );

        if ( $fileSystem->exists( $finalLegacyExtensionLocation ) )
        {
            throw new RuntimeException( 'The folder "' . $finalLegacyExtensionLocation . '" already exists. Aborting...' );
        }

        $fileSystem->symlink(
            $fileSystem->makePathRelative( $finalExtensionLocation, $legacyExtensionFolder ),
            $finalLegacyExtensionLocation
        );

        // Renaming the design folder (and removing ngmore_bootstrap2 design)

        $designName = $input->getOption( 'design-name' );
        $finalDesignLocation = $finalExtensionLocation . '/design/' . $designName;

        $output->writeln(
            array(
                '',
                'Renaming <comment>ngmore_bootstrap3</comment> design into <comment>' . $designName . '</comment>'
            )
        );

        if ( $fileSystem->exists( $finalDesignLocation ) )
        {
            throw new RuntimeException( 'The folder "' . $finalDesignLocation . '" already exists. Aborting...' );
        }

        $fileSystem->rename( $finalExtensionLocation . '/design/ngmore_bootstrap3', $finalDesignLocation );
        $fileSystem->remove( $finalExtensionLocation . '/design/ngmore_bootstrap2' );

        // Search and replace "ez_netgen_ngmore_demo" with the extension name

        $output->writeln(
            array(
                '',
                'Renaming <comment>ez_netgen_ngmore_demo</comment> extension name into <comment>' . $extensionName . '</comment>'
            )
        );

        FileHelper::searchAndReplaceInFile(
            FileHelper::findFilesInDirectory( $finalExtensionLocation ),
            'ez_netgen_ngmore_demo',
            $extensionName
        );

        // Search and replace "ngmore.netgen.biz" with the site domain

        $siteDomain = $input->getOption( 'site-domain' );

        $output->writeln(
            array(
                '',
                'Renaming <comment>ngmore.netgen.biz</comment> domain into <comment>' . $siteDomain . '</comment>'
            )
        );

        FileHelper::searchAndReplaceInFile(
            FileHelper::findFilesInDirectory( $finalExtensionLocation ),
            'ngmore.netgen.biz',
            $siteDomain
        );

        // Search and replace "ngmore_bootstrap3" with the name of site design

        $output->writeln(
            array(
                '',
                'Renaming <comment>ngmore_bootstrap3</comment> design into <comment>' . $designName . '</comment>'
            )
        );

        FileHelper::searchAndReplaceInFile(
            FileHelper::findFilesInDirectory( $finalExtensionLocation ),
            'ngmore_bootstrap3',
            $designName
        );
    }
}