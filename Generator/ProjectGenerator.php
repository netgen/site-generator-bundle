<?php

namespace Netgen\Bundle\GeneratorBundle\Generator;

use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Input\InputInterface;
use Netgen\Bundle\GeneratorBundle\Helper\FileHelper;
use Netgen\Bundle\GeneratorBundle\Helper\GitHelper;
use Symfony\Component\DependencyInjection\Container;
use RuntimeException;

class ProjectGenerator extends Generator
{
    private $container;

    public function __construct( ContainerInterface $container )
    {
        $this->container = $container;
    }

    public function generate( InputInterface $input, OutputInterface $output )
    {
        $fileSystem = $this->container->get( 'filesystem' );

        // Cloning the bundle

        $bundleFolder = $this->container->getParameter( 'kernel.root_dir' ) . '/../src';
        $bundleNamespace = $input->getOption( 'bundle-namespace' );
        $bundleName = $input->getOption( 'bundle-name' );

        $finalBundleLocation = $bundleFolder . '/' . strtr( $bundleNamespace, '\\', '/' );

        $output->writeln( 'Cloning the demo bundle into <comment>' . $finalBundleLocation . '</comment>' );

        GitHelper::cloneRepo( $fileSystem, 'git@bitbucket.org:netgen/NetgenMoreDemoBundle.git', $finalBundleLocation );
        $fileSystem->remove( $finalBundleLocation . '/.git/' );

        // Renaming the bundle namespace

        $output->writeln(
            array(
                '',
                'Renaming <comment>Netgen\Bundle\MoreDemoBundle</comment> bundle namespace into <comment>' . $bundleNamespace . '</comment>'
            )
        );

        FileHelper::searchAndReplaceInFile(
            FileHelper::findFilesInDirectory( $finalBundleLocation ),
            'Netgen\Bundle\MoreDemoBundle',
            $bundleNamespace
        );

        // Renaming the bundle name

        $output->writeln(
            array(
                '',
                'Renaming <comment>NetgenMoreDemoBundle</comment> bundle name into <comment>' . $bundleName . '</comment>'
            )
        );

        FileHelper::searchAndReplaceInFile(
            FileHelper::findFilesInDirectory( $finalBundleLocation ),
            'NetgenMoreDemoBundle',
            $bundleName
        );

        $fileSystem->rename(
            $finalBundleLocation . '/NetgenMoreDemoBundle.php',
            $finalBundleLocation . '/' . $bundleName . '.php'
        );

        // Renaming the bundle extension & configuration

        $bundleBaseName = substr( $bundleName, 0, -6 );
        $bundleExtensionName = $bundleBaseName . 'Extension';

        $output->writeln(
            array(
                '',
                'Renaming <comment>NetgenMoreDemoExtension</comment> DI extension into <comment>' . $bundleExtensionName . '</comment>'
            )
        );

        FileHelper::searchAndReplaceInFile(
            $finalBundleLocation . '/DependencyInjection/NetgenMoreDemoExtension.php',
            'NetgenMoreDemoExtension',
            $bundleExtensionName
        );

        $fileSystem->rename(
            $finalBundleLocation . '/DependencyInjection/NetgenMoreDemoExtension.php',
            $finalBundleLocation . '/DependencyInjection/' . $bundleExtensionName . '.php'
        );

        FileHelper::searchAndReplaceInFile(
            $finalBundleLocation . '/DependencyInjection/Configuration.php',
            'netgen_more_demo',
            Container::underscore( $bundleBaseName )
        );

        // Cloning the legacy extension

        $extensionFolder = $finalBundleLocation . '/ezpublish_legacy';
        $extensionName = $input->getOption( 'extension-name' );
        $finalExtensionLocation = $extensionFolder . '/' . $extensionName;

        $output->writeln(
            array(
                '',
                'Cloning the demo extension into <comment>' . $finalExtensionLocation . '</comment>'
            )
        );

        GitHelper::cloneRepo( $fileSystem, 'git@bitbucket.org:netgen/ez_netgen_ngmore_demo.git', $finalExtensionLocation );
        $fileSystem->remove( $finalExtensionLocation . '/.git/' );

        // Symlinking the legacy extension to eZ Publish legacy extension folder

        $legacyExtensionFolder = $this->container->getParameter( 'ezpublish_legacy.root_dir' ) . '/extension';
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
                'Replacing <comment>ez_netgen_ngmore_demo</comment> in files with <comment>' . $extensionName . '</comment>'
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
                'Replacing <comment>ngmore.netgen.biz</comment> in files with <comment>' . $siteDomain . '</comment>'
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
                'Replacing <comment>ngmore_bootstrap3</comment> in files with <comment>' . $designName . '</comment>'
            )
        );

        FileHelper::searchAndReplaceInFile(
            FileHelper::findFilesInDirectory( $finalExtensionLocation ),
            'ngmore_bootstrap3',
            $designName
        );
    }
}
