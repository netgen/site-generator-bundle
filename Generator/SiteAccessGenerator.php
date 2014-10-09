<?php

namespace Netgen\Bundle\GeneratorBundle\Generator;

use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Input\InputInterface;
use RuntimeException;

class SiteAccessGenerator extends Generator
{
    /**
     * @var \Symfony\Component\DependencyInjection\ContainerInterface
     */
    private $container;

    /**
     * Constructor
     *
     * @param \Symfony\Component\DependencyInjection\ContainerInterface $container
     */
    public function __construct( ContainerInterface $container )
    {
        $this->container = $container;
    }

    /**
     * Generates the siteaccesses
     *
     * @param \Symfony\Component\Console\Input\InputInterface $input
     * @param \Symfony\Component\Console\Output\OutputInterface $output
     */
    public function generate( InputInterface $input, OutputInterface $output )
    {
        $fileSystem = $this->container->get( 'filesystem' );
        $availableEnvironments = array( 'dev', 'prod' );

        $bundleFolder = $this->container->getParameter( 'kernel.root_dir' ) . '/../src';
        $bundleNamespace = $input->getOption( 'bundle-namespace' );

        $finalBundleLocation = $bundleFolder . '/' . strtr( $bundleNamespace, '\\', '/' );

        $extensionFolder = $finalBundleLocation . '/ezpublish_legacy';
        $extensionName = $input->getOption( 'extension-name' );
        $finalExtensionLocation = $extensionFolder . '/' . $extensionName;
        $legacyRootDir = $this->container->getParameter( 'ezpublish_legacy.root_dir' );

        $designName = $input->getOption( 'design-name' );
        $siteDomain = $input->getOption( 'site-domain' );

        // Generating siteaccesses

        $output->writeln(
            array(
                '',
                'Generating siteaccesses...'
            )
        );

        if ( !$fileSystem->exists( $finalExtensionLocation . '/settings/_skeleton_siteaccess' ) )
        {
            throw new RuntimeException( 'Siteaccess skeleton directory not found. Aborting...' );
        }

        foreach ( $availableEnvironments as $environment )
        {
            if ( !$fileSystem->exists( $finalExtensionLocation . '/root_' . $environment . '/settings/_skeleton_siteaccess' ) )
            {
                throw new RuntimeException( 'Siteaccess skeleton directory for "' . $environment . '" environment not found. Aborting...' );
            }
        }

        $siteAccessList = $input->getOption( 'site-access-list' );
        $validSiteAccesses = array();

        // Cleanup the list of siteaccesses, remove those that already exist in ezpublish_legacy/settings/siteaccess folder

        foreach ( $siteAccessList as $siteAccessName => $siteAccessLanguages )
        {
            if ( $fileSystem->exists( $legacyRootDir . '/settings/siteaccess/' . $siteAccessName ) )
            {
                $output->writeln(
                    array(
                        '',
                        'Siteaccess <comment>' . $siteAccessName . '</comment> already exists. Will not generate...'
                    )
                );

                continue;
            }

            $validSiteAccesses[$siteAccessName] = $siteAccessLanguages;
        }

        if ( !empty( $validSiteAccesses ) )
        {
            // Validate generation of admin siteaccess

            $generateAdminSiteAccess = true;
            if ( $fileSystem->exists( $legacyRootDir . '/settings/siteaccess/administration' ) )
            {
                $generateAdminSiteAccess = false;
                $output->writeln(
                    array(
                        '',
                        'Admin siteaccess <comment>administration</comment> already exists. Will not generate...'
                    )
                );
            }

            if ( $generateAdminSiteAccess && !$fileSystem->exists( $finalExtensionLocation . '/settings/_skeleton_admin' ) )
            {
                throw new RuntimeException( 'Admin siteaccess skeleton directory not found. Aborting...' );
            }

            foreach ( $availableEnvironments as $environment )
            {
                if ( $generateAdminSiteAccess && !$fileSystem->exists( $finalExtensionLocation . '/root_' . $environment . '/settings/_skeleton_admin' ) )
                {
                    throw new RuntimeException( 'Admin siteaccess skeleton directory for "' . $environment . '" environment not found. Aborting...' );
                }
            }

            // Cleanup before generation

            $fileSystem->remove( $finalExtensionLocation . '/settings/siteaccess/' );

            foreach ( $availableEnvironments as $environment )
            {
                $fileSystem->remove( $finalExtensionLocation . '/root_' . $environment . '/settings/siteaccess/' );
            }

            $fileSystem->remove( $legacyRootDir . '/settings/siteaccess/base/' );
            $fileSystem->remove( $legacyRootDir . '/settings/siteaccess/mysite/' );
            $fileSystem->remove( $legacyRootDir . '/settings/siteaccess/plain/' );

            // Variables valid for all siteaccesses

            $siteName = $input->getOption( 'site-name' );

            $databaseServer = $input->getOption( 'database-host' );
            $databasePort = $input->getOption( 'database-port' );
            $databaseUser = $input->getOption( 'database-user' );
            $databasePassword = $input->getOption( 'database-password' );
            $databaseName = $input->getOption( 'database-name' );

            $allSiteAccesses = array_keys( $validSiteAccesses );
            $allSiteAccesses[] = 'administration';

            $mainSiteAccess = '';
            if ( $generateAdminSiteAccess )
            {
                $mainSiteAccess = $allSiteAccesses[0];
            }

            $allLanguages = array();
            foreach ( $validSiteAccesses as $validSiteAccessLanguages )
            {
                foreach ( $validSiteAccessLanguages as $language )
                {
                    if ( !in_array( $language, $allLanguages ) )
                    {
                        $allLanguages[] = $language;
                    }
                }
            }

            $translationList = implode( ';', array_values( array_diff( $allLanguages, array( $allLanguages[0] ) ) ) );

            // Generating regular siteaccesses

            foreach ( $validSiteAccesses as $siteAccessName => $siteAccessLanguages )
            {
                $fileSystem->mirror(
                    $finalExtensionLocation . '/settings/_skeleton_siteaccess',
                    $finalExtensionLocation . '/settings/siteaccess/' . $siteAccessName
                );

                $this->setSkeletonDirs( $finalExtensionLocation . '/settings/siteaccess/' . $siteAccessName );

                $this->renderFile(
                    'site.ini.append.php',
                    $finalExtensionLocation . '/settings/siteaccess/' . $siteAccessName . '/site.ini.append.php',
                    array(
                        'siteName' => $siteName,
                        'relatedSiteAccessList' => $allSiteAccesses,
                        'designName' => $designName,
                        'siteAccessLocale' => $siteAccessLanguages[0],
                        'siteLanguageList' => $siteAccessLanguages,
                        'translationList' => $translationList
                    )
                );

                foreach ( $availableEnvironments as $environment )
                {
                    $fileSystem->mirror(
                        $finalExtensionLocation . '/root_' . $environment . '/settings/_skeleton_siteaccess',
                        $finalExtensionLocation . '/root_' . $environment . '/settings/siteaccess/' . $siteAccessName
                    );

                    $this->setSkeletonDirs( $finalExtensionLocation . '/root_' . $environment . '/settings/siteaccess/' . $siteAccessName );

                    $this->renderFile(
                        'site.ini.append.php',
                        $finalExtensionLocation . '/root_' . $environment . '/settings/siteaccess/' . $siteAccessName . '/site.ini.append.php',
                        array(
                            'siteDomain' => $siteDomain,
                            'siteAccessUriPart' => $siteAccessName !== $mainSiteAccess ? $siteAccessName : ''
                        )
                    );
                }

                $output->writeln(
                    array(
                        '',
                        'Generated <comment>' . $siteAccessName . '</comment> siteaccess!'
                    )
                );
            }

            $fileSystem->remove( $finalExtensionLocation . '/settings/_skeleton_siteaccess/' );
            foreach ( $availableEnvironments as $environment )
            {
                $fileSystem->remove( $finalExtensionLocation . '/root_' . $environment . '/settings/_skeleton_siteaccess/' );
            }

            // Generating admin siteaccess

            if ( $generateAdminSiteAccess )
            {
                $fileSystem->mirror(
                    $finalExtensionLocation . '/settings/_skeleton_admin',
                    $finalExtensionLocation . '/settings/siteaccess/administration'
                );

                $this->setSkeletonDirs( $finalExtensionLocation . '/settings/siteaccess/administration' );

                $this->renderFile(
                    'site.ini.append.php',
                    $finalExtensionLocation . '/settings/siteaccess/administration/site.ini.append.php',
                    array(
                        'siteName' => $siteName,
                        'relatedSiteAccessList' => $allSiteAccesses,
                        'siteAccessLocale' => $allLanguages[0],
                        'siteLanguageList' => $allLanguages,
                        'translationList' => $translationList
                    )
                );

                foreach ( $availableEnvironments as $environment )
                {
                    $fileSystem->mirror(
                        $finalExtensionLocation . '/root_' . $environment . '/settings/_skeleton_admin',
                        $finalExtensionLocation . '/root_' . $environment . '/settings/siteaccess/administration'
                    );

                    $this->setSkeletonDirs( $finalExtensionLocation . '/root_' . $environment . '/settings/siteaccess/administration' );

                    $this->renderFile(
                        'site.ini.append.php',
                        $finalExtensionLocation . '/root_' . $environment . '/settings/siteaccess/administration/site.ini.append.php',
                        array(
                            'siteDomain' => $siteDomain
                        )
                    );
                }

                $output->writeln(
                    array(
                        '',
                        'Generated <comment>administration</comment> siteaccess!'
                    )
                );
            }

            $fileSystem->remove( $finalExtensionLocation . '/settings/_skeleton_admin/' );
            foreach ( $availableEnvironments as $environment )
            {
                $fileSystem->remove( $finalExtensionLocation . '/root_' . $environment . '/settings/_skeleton_admin/' );
            }

            // Validate generation of override folder

            $generateOverride = true;
            if ( $fileSystem->exists( $legacyRootDir . '/settings/override' ) )
            {
                $generateOverride = false;
                $output->writeln(
                    array(
                        '',
                        '<comment>settings/override</comment> folder already exists. Will not generate...'
                    )
                );
            }

            foreach ( $availableEnvironments as $environment )
            {
                if ( $generateOverride && !$fileSystem->exists( $finalExtensionLocation . '/root_' . $environment . '/settings/_skeleton_override' ) )
                {
                    throw new RuntimeException( 'settings/override skeleton directory for "' . $environment . '" environment not found. Aborting...' );
                }
            }

            if ( $generateOverride )
            {
                // Cleanup before generation

                foreach ( $availableEnvironments as $environment )
                {
                    $fileSystem->remove( $finalExtensionLocation . '/root_' . $environment . '/settings/override/' );
                }

                // Variables for settings/override

                $hostUriMatchMapItems = array();
                foreach ( $allSiteAccesses as $siteAccessName )
                {
                    if ( $siteAccessName != $mainSiteAccess )
                    {
                        $hostUriMatchMapItems[] = $siteDomain . ';' . $siteAccessName . ';' . $siteAccessName;
                    }
                }

                if ( $mainSiteAccess != '' )
                {
                    $hostUriMatchMapItems[] = $siteDomain . ';' . '' . ';' . $mainSiteAccess;
                }

                // Generating settings/override folder

                foreach ( $availableEnvironments as $environment )
                {
                    $fileSystem->mirror(
                        $finalExtensionLocation . '/root_' . $environment . '/settings/_skeleton_override',
                        $finalExtensionLocation . '/root_' . $environment . '/settings/override'
                    );

                    $this->setSkeletonDirs( $finalExtensionLocation . '/root_' . $environment . '/settings/override' );

                    $this->renderFile(
                        'site.ini.append.php',
                        $finalExtensionLocation . '/root_' . $environment . '/settings/override/site.ini.append.php',
                        array(
                            'databaseServer' => $databaseServer,
                            'databasePort' => $databasePort,
                            'databaseUser' => $databaseUser,
                            'databasePassword' => $databasePassword,
                            'databaseName' => $databaseName,
                            'extensionName' => $extensionName,
                            'defaultAccess' => $mainSiteAccess,
                            'siteList' => $allSiteAccesses,
                            'availableSiteAccessList' => $allSiteAccesses,
                            'hostUriMatchMapItems' => $hostUriMatchMapItems
                        )
                    );
                }

                $output->writeln(
                    array(
                        '',
                        'Generated <comment>settings/override</comment> folder!'
                    )
                );
            }

            foreach ( $availableEnvironments as $environment )
            {
                $fileSystem->remove( $finalExtensionLocation . '/root_' . $environment . '/settings/_skeleton_override/' );
            }
        }
    }
}