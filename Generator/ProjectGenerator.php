<?php

namespace Netgen\Bundle\MoreGeneratorBundle\Generator;

use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Input\InputInterface;
use Netgen\Bundle\MoreGeneratorBundle\Helper\FileHelper;
use Netgen\Bundle\MoreGeneratorBundle\Helper\GitHelper;
use Symfony\Component\DependencyInjection\Container;

class ProjectGenerator extends Generator
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

        // Cloning the bundle

        $bundleFolder = $this->container->getParameter( 'kernel.root_dir' ) . '/../src';
        $bundleNamespace = $input->getOption( 'bundle-namespace' );
        $bundleName = $input->getOption( 'bundle-name' );

        $finalBundleLocation = $bundleFolder . '/' . strtr( $bundleNamespace, '\\', '/' );

        $output->writeln( 'Cloning the demo bundle into <comment>' . $finalBundleLocation . '</comment>' );

        GitHelper::cloneRepo(
            $this->container->getParameter( 'netgen_more.generator.demo_bundle_url' ),
            $finalBundleLocation
        );
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

        // Renaming the bundle assets path

        $bundleAssetsPathPart = preg_replace( '/bundle$/', '', strtolower( $bundleName ) );

        $output->writeln(
            array(
                '',
                'Renaming <comment>netgenmoredemo</comment> asset path into <comment>' . $bundleAssetsPathPart . '</comment>'
            )
        );

        FileHelper::searchAndReplaceInFile(
            FileHelper::findFilesInDirectory( $finalBundleLocation ),
            '/netgenmoredemo/',
            '/' . $bundleAssetsPathPart . '/'
        );

        // Renaming the siteaccess group names

        $project = strtolower( $input->getOption( 'project' ) );
        $frontendGroupName = $project . '_frontend_group';
        $administrationGroupName = $project . '_administration_group';

        $output->writeln(
            array(
                '',
                'Renaming <comment>ngmore_frontend_group</comment> name into <comment>' . $frontendGroupName . '</comment>'
            )
        );

        FileHelper::searchAndReplaceInFile(
            FileHelper::findFilesInDirectory( $finalBundleLocation ),
            'ngmore_frontend_group',
            $frontendGroupName
        );

        $output->writeln(
            array(
                '',
                'Renaming <comment>ngmore_administration_group</comment> name into <comment>' . $administrationGroupName . '</comment>'
            )
        );

        FileHelper::searchAndReplaceInFile(
            FileHelper::findFilesInDirectory( $finalBundleLocation ),
            'ngmore_administration_group',
            $administrationGroupName
        );
    }
}
