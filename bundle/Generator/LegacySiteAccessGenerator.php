<?php

declare(strict_types=1);

namespace Netgen\Bundle\SiteGeneratorBundle\Generator;

use DirectoryIterator;
use RuntimeException;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class LegacySiteAccessGenerator extends Generator
{
    /**
     * Generates the siteaccesses.
     */
    public function generate(InputInterface $input, OutputInterface $output): void
    {
        $fileSystem = $this->container->get('filesystem');
        $availableEnvironments = $this->container->getParameter('netgen_site_generator.available_environments');
        $adminSiteAccessName = self::LEGACY_ADMIN_SITEACCESS_NAME;

        $bundleFolder = $this->container->getParameter('kernel.project_dir') . '/src';
        $bundleNamespace = $input->getOption('bundle-namespace');

        $finalBundleLocation = $bundleFolder . '/' . str_replace('\\', '/', $bundleNamespace);

        $extensionFolder = $finalBundleLocation . '/ezpublish_legacy';
        $extensionName = $input->getOption('extension-name');
        $finalExtensionLocation = $extensionFolder . '/' . $extensionName;
        $legacyRootDir = $this->container->getParameter('ezpublish_legacy.root_dir');

        $siteDomain = $this->container->getParameter('ngsite.default.site_domain');

        // Generating siteaccesses

        if (!$fileSystem->exists($finalExtensionLocation . '/settings/_skeleton_siteaccess')) {
            throw new RuntimeException('Siteaccess skeleton directory not found. Aborting...');
        }

        $siteAccessList = $input->getOption('site-access-list');
        $validSiteAccesses = [];

        // Cleanup the list of siteaccesses, remove those that already exist in ezpublish_legacy/settings/siteaccess folder

        foreach ($siteAccessList as $siteAccessName => $siteAccessLanguages) {
            if ($fileSystem->exists($legacyRootDir . '/settings/siteaccess/' . $siteAccessName)) {
                $output->writeln(
                    [
                        '',
                        'Siteaccess <comment>' . $siteAccessName . '</comment> already exists. Will not generate...',
                    ]
                );

                continue;
            }

            $validSiteAccesses[$siteAccessName] = $siteAccessLanguages;
        }

        if (empty($validSiteAccesses)) {
            return;
        }

        // Validate generation of admin siteaccess

        $generateAdminSiteAccess = true;
        if ($fileSystem->exists($legacyRootDir . '/settings/siteaccess/' . $adminSiteAccessName)) {
            $generateAdminSiteAccess = false;
            $output->writeln(
                [
                    '',
                    'Legacy admin siteaccess <comment>' . $adminSiteAccessName . '</comment> already exists. Will not generate...',
                ]
            );
        }

        if ($generateAdminSiteAccess && !$fileSystem->exists($finalExtensionLocation . '/settings/_skeleton_legacy_admin')) {
            throw new RuntimeException('Legacy admin siteaccess skeleton directory not found. Aborting...');
        }

        // Validate generation of Netgen Admin UI siteaccess

        if (!$fileSystem->exists($finalExtensionLocation . '/settings/_skeleton_ngadminui')) {
            throw new RuntimeException('Netgen Admin UI siteaccess skeleton directory not found. Aborting...');
        }

        // Cleanup before generation

        $fileSystem->remove($finalExtensionLocation . '/settings/siteaccess/');

        foreach ($availableEnvironments as $environment) {
            $fileSystem->remove($finalExtensionLocation . '/root_' . $environment . '/settings/siteaccess/');
        }

        $fileSystem->remove($legacyRootDir . '/settings/siteaccess/base/');
        $fileSystem->remove($legacyRootDir . '/settings/siteaccess/mysite/');
        $fileSystem->remove($legacyRootDir . '/settings/siteaccess/plain/');

        // Variables valid for all siteaccesses

        $siteName = $input->getOption('site-name');

        $allSiteAccesses = array_keys($validSiteAccesses);
        $allSiteAccesses[] = $adminSiteAccessName;
        $allSiteAccesses[] = self::NGADMINUI_SITEACCESS_NAME;

        $mainSiteAccess = '';
        if ($generateAdminSiteAccess) {
            $mainSiteAccess = $allSiteAccesses[0];
        }

        $allLanguages = [];
        foreach ($validSiteAccesses as $validSiteAccessLanguages) {
            foreach ($validSiteAccessLanguages as $language) {
                if (!in_array($language, $allLanguages, true)) {
                    $allLanguages[] = $language;
                }
            }
        }

        $translationList = implode(';', array_values(array_diff($allLanguages, [$allLanguages[0]])));

        // Generating regular siteaccesses

        foreach ($validSiteAccesses as $siteAccessName => $siteAccessLanguages) {
            if ($siteAccessName === $mainSiteAccess) {
                $fileSystem->mirror(
                    $finalExtensionLocation . '/settings/_skeleton_siteaccess',
                    $finalExtensionLocation . '/settings/siteaccess/' . $siteAccessName
                );
            } else {
                $fileSystem->mkdir($finalExtensionLocation . '/settings/siteaccess/' . $siteAccessName);
                $fileSystem->copy(
                    $finalExtensionLocation . '/settings/_skeleton_siteaccess/site.ini.append.php',
                    $finalExtensionLocation . '/settings/siteaccess/' . $siteAccessName . '/site.ini.append.php'
                );

                foreach (new DirectoryIterator($finalExtensionLocation . '/settings/_skeleton_siteaccess') as $item) {
                    if (!$item->isDot() && $item->getBasename() !== 'site.ini.append.php') {
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

            $fileSystem->remove(
                $finalExtensionLocation . '/settings/siteaccess/' . $siteAccessName . '/.keep'
            );

            $this->setSkeletonDir($finalExtensionLocation . '/settings/siteaccess/' . $siteAccessName);

            $this->renderFile(
                'site.ini.append.php',
                $finalExtensionLocation . '/settings/siteaccess/' . $siteAccessName . '/site.ini.append.php',
                [
                    'siteName' => $siteName,
                    'relatedSiteAccessList' => $allSiteAccesses,
                    'siteAccessLocale' => $siteAccessLanguages[0],
                    'siteLanguageList' => $siteAccessLanguages,
                    'translationList' => $translationList,
                ]
            );

            foreach ($availableEnvironments as $environment) {
                if ($fileSystem->exists($finalExtensionLocation . '/root_' . $environment . '/settings/_skeleton_siteaccess/site.ini.append.php')) {
                    if ($siteAccessName === $mainSiteAccess) {
                        $fileSystem->mirror(
                            $finalExtensionLocation . '/root_' . $environment . '/settings/_skeleton_siteaccess',
                            $finalExtensionLocation . '/root_' . $environment . '/settings/siteaccess/' . $siteAccessName
                        );
                    } else {
                        $fileSystem->mkdir($finalExtensionLocation . '/root_' . $environment . '/settings/siteaccess/' . $siteAccessName);
                        $fileSystem->copy(
                            $finalExtensionLocation . '/root_' . $environment . '/settings/_skeleton_siteaccess/site.ini.append.php',
                            $finalExtensionLocation . '/root_' . $environment . '/settings/siteaccess/' . $siteAccessName . '/site.ini.append.php'
                        );

                        foreach (new DirectoryIterator($finalExtensionLocation . '/root_' . $environment . '/settings/_skeleton_siteaccess') as $item) {
                            if (!$item->isDot() && $item->getBasename() !== 'site.ini.append.php') {
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

                    $this->setSkeletonDir($finalExtensionLocation . '/root_' . $environment . '/settings/siteaccess/' . $siteAccessName);

                    $this->renderFile(
                        'site.ini.append.php',
                        $finalExtensionLocation . '/root_' . $environment . '/settings/siteaccess/' . $siteAccessName . '/site.ini.append.php',
                        [
                            'siteDomain' => $siteDomain,
                            'siteAccessUriPart' => $siteAccessName !== $mainSiteAccess ? $siteAccessName : '',
                        ]
                    );
                }
            }

            $output->writeln(
                [
                    '',
                    'Generated <comment>' . $siteAccessName . '</comment> siteaccess!',
                ]
            );
        }

        $fileSystem->remove($finalExtensionLocation . '/settings/_skeleton_siteaccess/');
        foreach ($availableEnvironments as $environment) {
            $fileSystem->remove($finalExtensionLocation . '/root_' . $environment . '/settings/_skeleton_siteaccess/');
        }

        // Generate Netgen Admin UI siteaccess

        $fileSystem->mirror(
            $finalExtensionLocation . '/settings/_skeleton_ngadminui',
            $finalExtensionLocation . '/settings/siteaccess/' . self::NGADMINUI_SITEACCESS_NAME
        );

        $fileSystem->remove(
            $finalExtensionLocation . '/settings/siteaccess/' . self::NGADMINUI_SITEACCESS_NAME . '/.keep'
        );

        $this->setSkeletonDir($finalExtensionLocation . '/settings/siteaccess/' . self::NGADMINUI_SITEACCESS_NAME);

        $this->renderFile(
            'site.ini.append.php',
            $finalExtensionLocation . '/settings/siteaccess/' . self::NGADMINUI_SITEACCESS_NAME . '/site.ini.append.php',
            [
                'siteName' => $siteName,
                'relatedSiteAccessList' => $allSiteAccesses,
                'siteAccessLocale' => $allLanguages[0],
                'siteLanguageList' => $allLanguages,
                'translationList' => $translationList,
            ]
        );

        foreach ($availableEnvironments as $environment) {
            if ($fileSystem->exists($finalExtensionLocation . '/root_' . $environment . '/settings/_skeleton_ngadminui/site.ini.append.php')) {
                $fileSystem->mirror(
                    $finalExtensionLocation . '/root_' . $environment . '/settings/_skeleton_ngadminui',
                    $finalExtensionLocation . '/root_' . $environment . '/settings/siteaccess/' . self::NGADMINUI_SITEACCESS_NAME
                );

                $this->setSkeletonDir($finalExtensionLocation . '/root_' . $environment . '/settings/siteaccess/' . self::NGADMINUI_SITEACCESS_NAME);

                $this->renderFile(
                    'site.ini.append.php',
                    $finalExtensionLocation . '/root_' . $environment . '/settings/siteaccess/' . self::NGADMINUI_SITEACCESS_NAME . '/site.ini.append.php',
                    [
                        'siteDomain' => $siteDomain,
                    ]
                );
            }
        }

        $output->writeln(
            [
                '',
                'Generated <comment>Netgen Admin UI</comment> siteaccess!',
            ]
        );

        $fileSystem->remove($finalExtensionLocation . '/settings/_skeleton_ngadminui/');
        foreach ($availableEnvironments as $environment) {
            $fileSystem->remove($finalExtensionLocation . '/root_' . $environment . '/settings/_skeleton_ngadminui/');
        }

        // Generating legacy admin siteaccess

        if ($generateAdminSiteAccess) {
            $fileSystem->mkdir($finalExtensionLocation . '/settings/siteaccess/' . $adminSiteAccessName);
            $fileSystem->copy(
                $finalExtensionLocation . '/settings/_skeleton_legacy_admin/site.ini.append.php',
                $finalExtensionLocation . '/settings/siteaccess/' . $adminSiteAccessName . '/site.ini.append.php'
            );

            foreach (new DirectoryIterator($finalExtensionLocation . '/settings/siteaccess/' . self::NGADMINUI_SITEACCESS_NAME) as $item) {
                if (!$item->isDot() && $item->getBasename() !== 'site.ini.append.php') {
                    $fileSystem->symlink(
                        $fileSystem->makePathRelative(
                            $finalExtensionLocation . '/settings/siteaccess/' . self::NGADMINUI_SITEACCESS_NAME,
                            $finalExtensionLocation . '/settings/siteaccess/' . $adminSiteAccessName
                        ) . $item->getBasename(),
                        $finalExtensionLocation . '/settings/siteaccess/' . $adminSiteAccessName . '/' . $item->getBasename()
                    );
                }
            }

            $this->setSkeletonDir($finalExtensionLocation . '/settings/siteaccess/' . $adminSiteAccessName);

            $this->renderFile(
                'site.ini.append.php',
                $finalExtensionLocation . '/settings/siteaccess/' . $adminSiteAccessName . '/site.ini.append.php',
                [
                    'siteName' => $siteName,
                    'relatedSiteAccessList' => $allSiteAccesses,
                    'siteAccessLocale' => $allLanguages[0],
                    'siteLanguageList' => $allLanguages,
                    'translationList' => $translationList,
                ]
            );

            foreach ($availableEnvironments as $environment) {
                if ($fileSystem->exists($finalExtensionLocation . '/root_' . $environment . '/settings/_skeleton_legacy_admin/site.ini.append.php')) {
                    $fileSystem->mkdir($finalExtensionLocation . '/root_' . $environment . '/settings/siteaccess/' . $adminSiteAccessName);
                    $fileSystem->copy(
                        $finalExtensionLocation . '/root_' . $environment . '/settings/_skeleton_legacy_admin/site.ini.append.php',
                        $finalExtensionLocation . '/root_' . $environment . '/settings/siteaccess/' . $adminSiteAccessName . '/site.ini.append.php'
                    );

                    foreach (new DirectoryIterator($finalExtensionLocation . '/root_' . $environment . '/settings/siteaccess/' . self::NGADMINUI_SITEACCESS_NAME) as $item) {
                        if (!$item->isDot() && $item->getBasename() !== 'site.ini.append.php') {
                            $fileSystem->symlink(
                                $fileSystem->makePathRelative(
                                    $finalExtensionLocation . '/root_' . $environment . '/settings/siteaccess/' . self::NGADMINUI_SITEACCESS_NAME,
                                    $finalExtensionLocation . '/root_' . $environment . '/settings/siteaccess/' . $adminSiteAccessName
                                ) . $item->getBasename(),
                                $finalExtensionLocation . '/root_' . $environment . '/settings/siteaccess/' . $adminSiteAccessName . '/' . $item->getBasename()
                            );
                        }
                    }

                    $this->setSkeletonDir($finalExtensionLocation . '/root_' . $environment . '/settings/siteaccess/' . $adminSiteAccessName);

                    $this->renderFile(
                        'site.ini.append.php',
                        $finalExtensionLocation . '/root_' . $environment . '/settings/siteaccess/' . $adminSiteAccessName . '/site.ini.append.php',
                        [
                            'siteDomain' => $siteDomain,
                        ]
                    );
                }
            }

            $output->writeln(
                [
                    '',
                    'Generated <comment>' . $adminSiteAccessName . '</comment> admin siteaccess!',
                ]
            );
        }

        $fileSystem->remove($finalExtensionLocation . '/settings/_skeleton_legacy_admin/');
        foreach ($availableEnvironments as $environment) {
            $fileSystem->remove($finalExtensionLocation . '/root_' . $environment . '/settings/_skeleton_legacy_admin/');
        }

        // Validate generation of override folder

        $generateOverride = true;
        if ($fileSystem->exists($legacyRootDir . '/settings/override')) {
            $generateOverride = false;
            $output->writeln(
                [
                    '',
                    '<comment>settings/override</comment> folder already exists. Will not generate...',
                ]
            );
        }

        if ($generateOverride) {
            // Cleanup before generation

            $fileSystem->remove($finalExtensionLocation . '/root/settings/override/');
            foreach ($availableEnvironments as $environment) {
                $fileSystem->remove($finalExtensionLocation . '/root_' . $environment . '/settings/override/');
            }

            // Generating settings/override folder

            if ($fileSystem->exists($finalExtensionLocation . '/root/settings/_skeleton_override/site.ini.append.php')) {
                $fileSystem->mirror(
                    $finalExtensionLocation . '/root/settings/_skeleton_override',
                    $finalExtensionLocation . '/root/settings/override'
                );

                $this->setSkeletonDir($finalExtensionLocation . '/root/settings/override');

                $this->renderFile(
                    'site.ini.append.php',
                    $finalExtensionLocation . '/root/settings/override/site.ini.append.php',
                    [
                        'extensionName' => $extensionName,
                    ]
                );
            } else {
                foreach ($availableEnvironments as $environment) {
                    if ($fileSystem->exists($finalExtensionLocation . '/root_' . $environment . '/settings/_skeleton_override/site.ini.append.php')) {
                        $fileSystem->mirror(
                            $finalExtensionLocation . '/root_' . $environment . '/settings/_skeleton_override',
                            $finalExtensionLocation . '/root_' . $environment . '/settings/override'
                        );

                        $this->setSkeletonDir($finalExtensionLocation . '/root_' . $environment . '/settings/override');

                        $this->renderFile(
                            'site.ini.append.php',
                            $finalExtensionLocation . '/root_' . $environment . '/settings/override/site.ini.append.php',
                            [
                                'extensionName' => $extensionName,
                            ]
                        );
                    }
                }
            }

            $output->writeln(
                [
                    '',
                    'Generated <comment>settings/override</comment> folder!',
                ]
            );
        }

        $fileSystem->remove($finalExtensionLocation . '/root/settings/_skeleton_override/');
        foreach ($availableEnvironments as $environment) {
            $fileSystem->remove($finalExtensionLocation . '/root_' . $environment . '/settings/_skeleton_override/');
        }
    }
}
