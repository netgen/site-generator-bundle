<?php

namespace Netgen\Bundle\MoreGeneratorBundle\Generator;

use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Input\InputInterface;
use DirectoryIterator;
use RuntimeException;

class LegacySiteAccessGenerator extends Generator
{
    /**
     * Generates the siteaccesses
     *
     * @param \Symfony\Component\Console\Input\InputInterface $input
     * @param \Symfony\Component\Console\Output\OutputInterface $output
     */
    public function generate( InputInterface $input, OutputInterface $output )
    {
        $fileSystem = $this->container->get( 'filesystem' );
        $availableEnvironments = $this->container->getParameter( 'ngmore_generator.available_environments' );
        $adminSiteAccessName = $input->getOption( 'admin-site-access-name' );

        $bundleFolder = $this->container->getParameter( 'kernel.root_dir' ) . '/../src';
        $bundleNamespace = $input->getOption( 'bundle-namespace' );

        $finalBundleLocation = $bundleFolder . '/' . strtr( $bundleNamespace, '\\', '/' );

        $extensionFolder = $finalBundleLocation . '/ezpublish_legacy';
        $extensionName = $input->getOption( 'extension-name' );
        $finalExtensionLocation = $extensionFolder . '/' . $extensionName;
        $legacyRootDir = $this->container->getParameter( 'ezpublish_legacy.root_dir' );

        $designName = $input->getOption( 'design-name' );
        $siteDomain = $this->container->getParameter( 'ngmore.default.site_domain' );

        // Generating siteaccesses

        if ( !$fileSystem->exists( $finalExtensionLocation . '/settings/_skeleton_siteaccess' ) )
        {
            throw new RuntimeException( 'Siteaccess skeleton directory not found. Aborting...' );
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

        if ( empty( $validSiteAccesses ) )
        {
            return;
        }

        // Validate generation of admin siteaccess

        $generateAdminSiteAccess = true;
        if ( $fileSystem->exists( $legacyRootDir . '/settings/siteaccess/' . $adminSiteAccessName ) )
        {
            $generateAdminSiteAccess = false;
            $output->writeln(
                array(
                    '',
                    'Admin siteaccess <comment>' . $adminSiteAccessName . '</comment> already exists. Will not generate...'
                )
            );
        }

        if ( $generateAdminSiteAccess && !$fileSystem->exists( $finalExtensionLocation . '/settings/_skeleton_admin' ) )
        {
            throw new RuntimeException( 'Admin siteaccess skeleton directory not found. Aborting...' );
        }

        // Validate generation of Netgen Admin UI siteaccess

        if ( $fileSystem->exists( $legacyRootDir . '/settings/siteaccess/' . self::NGADMINUI_SITEACCESS_NAME ) )
        {
            $this->generateNgAdminUi = false;
            $output->writeln(
                array(
                    '',
                    'Netgen Admin UI siteaccess already exists. Will not generate...'
                )
            );
        }

        if ( $this->generateNgAdminUi && !$fileSystem->exists( $finalExtensionLocation . '/settings/_skeleton_ngadminui' ) )
        {
            throw new RuntimeException( 'Netgen Admin UI siteaccess skeleton directory not found. Aborting...' );
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

        $allSiteAccesses = array_keys( $validSiteAccesses );
        $allSiteAccesses[] = $adminSiteAccessName;
        if ( $this->generateNgAdminUi )
        {
            $allSiteAccesses[] = self::NGADMINUI_SITEACCESS_NAME;
        }

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
            if ( $siteAccessName === $mainSiteAccess )
            {
                $fileSystem->mirror(
                    $finalExtensionLocation . '/settings/_skeleton_siteaccess',
                    $finalExtensionLocation . '/settings/siteaccess/' . $siteAccessName
                );

                $fileSystem->remove(
                    $finalExtensionLocation . '/settings/siteaccess/' . $siteAccessName . '/.keep'
                );
            }
            else
            {
                $fileSystem->mkdir( $finalExtensionLocation . '/settings/siteaccess/' . $siteAccessName );
                $fileSystem->copy(
                    $finalExtensionLocation . '/settings/_skeleton_siteaccess/site.ini.append.php',
                    $finalExtensionLocation . '/settings/siteaccess/' . $siteAccessName . '/site.ini.append.php'
                );

                foreach ( new DirectoryIterator( $finalExtensionLocation . '/settings/_skeleton_siteaccess' ) as $item )
                {
                    if ( !$item->isDot() && $item->getBasename() !== 'site.ini.append.php' )
                    {
                        $fileSystem->symlink(
                            $fileSystem->makePathRelative(
                                $finalExtensionLocation . '/settings/siteaccess/' . $mainSiteAccess,
                                $finalExtensionLocation . '/settings/siteaccess/' . $siteAccessName
                            ) . $item->getBasename(),
                            $finalExtensionLocation . '/settings/siteaccess/' . $siteAccessName . '/' . $item->getBasename()
                        );
                    }
                }
            }

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
                if ( $fileSystem->exists( $finalExtensionLocation . '/root_' . $environment . '/settings/_skeleton_siteaccess/site.ini.append.php' ) )
                {
                    if ( $siteAccessName === $mainSiteAccess )
                    {
                        $fileSystem->mirror(
                            $finalExtensionLocation . '/root_' . $environment . '/settings/_skeleton_siteaccess',
                            $finalExtensionLocation . '/root_' . $environment . '/settings/siteaccess/' . $siteAccessName
                        );
                    }
                    else
                    {
                        $fileSystem->mkdir( $finalExtensionLocation . '/root_' . $environment . '/settings/siteaccess/' . $siteAccessName );
                        $fileSystem->copy(
                            $finalExtensionLocation . '/root_' . $environment . '/settings/_skeleton_siteaccess/site.ini.append.php',
                            $finalExtensionLocation . '/root_' . $environment . '/settings/siteaccess/' . $siteAccessName . '/site.ini.append.php'
                        );

                        foreach ( new DirectoryIterator( $finalExtensionLocation . '/root_' . $environment . '/settings/_skeleton_siteaccess' ) as $item )
                        {
                            if ( !$item->isDot() && $item->getBasename() !== 'site.ini.append.php' )
                            {
                                $fileSystem->symlink(
                                    $fileSystem->makePathRelative(
                                        $finalExtensionLocation . '/root_' . $environment . '/settings/siteaccess/' . $mainSiteAccess,
                                        $finalExtensionLocation . '/root_' . $environment . '/settings/siteaccess/' . $siteAccessName
                                    ) . $item->getBasename(),
                                    $finalExtensionLocation . '/root_' . $environment . '/settings/siteaccess/' . $siteAccessName . '/' . $item->getBasename()
                                );
                            }
                        }
                    }

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
                $finalExtensionLocation . '/settings/siteaccess/' . $adminSiteAccessName
            );

            $fileSystem->remove(
                $finalExtensionLocation . '/settings/siteaccess/' . $adminSiteAccessName . '/.keep'
            );

            $this->setSkeletonDirs( $finalExtensionLocation . '/settings/siteaccess/' . $adminSiteAccessName );

            $this->renderFile(
                'site.ini.append.php',
                $finalExtensionLocation . '/settings/siteaccess/' . $adminSiteAccessName . '/site.ini.append.php',
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
                if ( $fileSystem->exists( $finalExtensionLocation . '/root_' . $environment . '/settings/_skeleton_admin/site.ini.append.php' ) )
                {
                    $fileSystem->mirror(
                        $finalExtensionLocation . '/root_' . $environment . '/settings/_skeleton_admin',
                        $finalExtensionLocation . '/root_' . $environment . '/settings/siteaccess/' . $adminSiteAccessName
                    );

                    $this->setSkeletonDirs( $finalExtensionLocation . '/root_' . $environment . '/settings/siteaccess/' . $adminSiteAccessName );

                    $this->renderFile(
                        'site.ini.append.php',
                        $finalExtensionLocation . '/root_' . $environment . '/settings/siteaccess/' . $adminSiteAccessName . '/site.ini.append.php',
                        array(
                            'siteDomain' => $siteDomain
                        )
                    );
                }
            }

            $output->writeln(
                array(
                    '',
                    'Generated <comment>' . $adminSiteAccessName . '</comment> admin siteaccess!'
                )
            );
        }

        $fileSystem->remove( $finalExtensionLocation . '/settings/_skeleton_admin/' );
        foreach ( $availableEnvironments as $environment )
        {
            $fileSystem->remove( $finalExtensionLocation . '/root_' . $environment . '/settings/_skeleton_admin/' );
        }

        // Generate Netgen Admin UI siteaccess

        if ( $generateAdminSiteAccess && $this->generateNgAdminUi )
        {
            $fileSystem->mkdir( $finalExtensionLocation . '/settings/siteaccess/' . self::NGADMINUI_SITEACCESS_NAME );
            $fileSystem->copy(
                $finalExtensionLocation . '/settings/_skeleton_ngadminui/site.ini.append.php',
                $finalExtensionLocation . '/settings/siteaccess/' . self::NGADMINUI_SITEACCESS_NAME . '/site.ini.append.php'
            );

            foreach ( new DirectoryIterator( $finalExtensionLocation . '/settings/siteaccess/' . $adminSiteAccessName ) as $item )
            {
                if ( !$item->isDot() && $item->getBasename() !== 'site.ini.append.php' )
                {
                    $fileSystem->symlink(
                        $fileSystem->makePathRelative(
                            $finalExtensionLocation . '/settings/siteaccess/' . $adminSiteAccessName,
                            $finalExtensionLocation . '/settings/siteaccess/' . self::NGADMINUI_SITEACCESS_NAME
                        ) . $item->getBasename(),
                        $finalExtensionLocation . '/settings/siteaccess/' . self::NGADMINUI_SITEACCESS_NAME . '/' . $item->getBasename()
                    );
                }
            }

            $this->setSkeletonDirs( $finalExtensionLocation . '/settings/siteaccess/' . self::NGADMINUI_SITEACCESS_NAME );

            $this->renderFile(
                'site.ini.append.php',
                $finalExtensionLocation . '/settings/siteaccess/' . self::NGADMINUI_SITEACCESS_NAME . '/site.ini.append.php',
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
                if ( $fileSystem->exists( $finalExtensionLocation . '/root_' . $environment . '/settings/_skeleton_ngadminui/site.ini.append.php' ) )
                {
                    $fileSystem->mkdir( $finalExtensionLocation . '/root_' . $environment . '/settings/siteaccess/' . self::NGADMINUI_SITEACCESS_NAME );
                    $fileSystem->copy(
                        $finalExtensionLocation . '/root_' . $environment . '/settings/_skeleton_ngadminui/site.ini.append.php',
                        $finalExtensionLocation . '/root_' . $environment . '/settings/siteaccess/' . self::NGADMINUI_SITEACCESS_NAME . '/site.ini.append.php'
                    );

                    foreach ( new DirectoryIterator( $finalExtensionLocation . '/root_' . $environment . '/settings/siteaccess/' . $adminSiteAccessName ) as $item )
                    {
                        if ( !$item->isDot() && $item->getBasename() !== 'site.ini.append.php' )
                        {
                            $fileSystem->symlink(
                                $fileSystem->makePathRelative(
                                    $finalExtensionLocation . '/root_' . $environment . '/settings/siteaccess/' . $adminSiteAccessName,
                                    $finalExtensionLocation . '/root_' . $environment . '/settings/siteaccess/' . self::NGADMINUI_SITEACCESS_NAME
                                ) . $item->getBasename(),
                                $finalExtensionLocation . '/root_' . $environment . '/settings/siteaccess/' . self::NGADMINUI_SITEACCESS_NAME . '/' . $item->getBasename()
                            );
                        }
                    }

                    $this->setSkeletonDirs( $finalExtensionLocation . '/root_' . $environment . '/settings/siteaccess/' . self::NGADMINUI_SITEACCESS_NAME );

                    $this->renderFile(
                        'site.ini.append.php',
                        $finalExtensionLocation . '/root_' . $environment . '/settings/siteaccess/' . self::NGADMINUI_SITEACCESS_NAME . '/site.ini.append.php',
                        array(
                            'siteDomain' => $siteDomain
                        )
                    );
                }
            }

            $output->writeln(
                array(
                    '',
                    'Generated <comment>Netgen Admin UI</comment> siteaccess!'
                )
            );
        }

        $fileSystem->remove( $finalExtensionLocation . '/settings/_skeleton_ngadminui/' );
        foreach ( $availableEnvironments as $environment )
        {
            $fileSystem->remove( $finalExtensionLocation . '/root_' . $environment . '/settings/_skeleton_ngadminui/' );
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

        if ( $generateOverride )
        {
            // Cleanup before generation

            $fileSystem->remove( $finalExtensionLocation . '/root/settings/override/' );
            foreach ( $availableEnvironments as $environment )
            {
                $fileSystem->remove( $finalExtensionLocation . '/root_' . $environment . '/settings/override/' );
            }

            // Generating settings/override folder

            if ( $fileSystem->exists( $finalExtensionLocation . '/root/settings/_skeleton_override/site.ini.append.php' ) )
            {
                $fileSystem->mirror(
                    $finalExtensionLocation . '/root/settings/_skeleton_override',
                    $finalExtensionLocation . '/root/settings/override'
                );

                $this->setSkeletonDirs( $finalExtensionLocation . '/root/settings/override' );

                $this->renderFile(
                    'site.ini.append.php',
                    $finalExtensionLocation . '/root/settings/override/site.ini.append.php',
                    array(
                        'extensionName' => $extensionName
                    )
                );
            }
            else
            {
                foreach ( $availableEnvironments as $environment )
                {
                    if ( $fileSystem->exists( $finalExtensionLocation . '/root_' . $environment . '/settings/_skeleton_override/site.ini.append.php' ) )
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
                                'extensionName' => $extensionName
                            )
                        );
                    }
                }
            }

            $output->writeln(
                array(
                    '',
                    'Generated <comment>settings/override</comment> folder!'
                )
            );
        }

        $fileSystem->remove( $finalExtensionLocation . '/root/settings/_skeleton_override/' );
        foreach ( $availableEnvironments as $environment )
        {
            $fileSystem->remove( $finalExtensionLocation . '/root_' . $environment . '/settings/_skeleton_override/' );
        }
    }
}
