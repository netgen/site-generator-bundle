<?php

declare(strict_types=1);

namespace Netgen\Bundle\SiteGeneratorBundle\Generator;

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

        $bundleFolder = $this->container->getParameter('kernel.project_dir') . '/src';
        $bundleNamespace = $input->getOption('bundle-namespace');

        $finalBundleLocation = $bundleFolder . '/' . str_replace('\\', '/', $bundleNamespace);

        $extensionFolder = $finalBundleLocation . '/ezpublish_legacy';
        $extensionName = $input->getOption('extension-name');
        $finalExtensionLocation = $extensionFolder . '/' . $extensionName;

        $siteAccessList = $input->getOption('site-access-list');

        // Validate generation of admin siteaccesses

        if (!$fileSystem->exists($finalExtensionLocation . '/settings/_skeleton_legacy_admin')) {
            throw new RuntimeException('Legacy admin siteaccess skeleton directory not found. Aborting...');
        }

        if (!$fileSystem->exists($finalExtensionLocation . '/settings/_skeleton_ngadminui')) {
            throw new RuntimeException('Netgen Admin UI siteaccess skeleton directory not found. Aborting...');
        }

        // Variables valid for all siteaccesses

        $siteName = $input->getOption('site-name');

        $allSiteAccesses = array_keys($siteAccessList);
        $allSiteAccesses[] = self::LEGACY_ADMIN_SITEACCESS_NAME;
        $allSiteAccesses[] = self::NGADMINUI_SITEACCESS_NAME;

        $allLanguages = array_values(array_unique(array_merge(...array_values($siteAccessList))));
        $translationList = implode(';', array_values(array_diff($allLanguages, [$allLanguages[0]])));

        // Generate Netgen Admin UI siteaccess

        $this->setSkeletonDir($finalExtensionLocation . '/settings/_skeleton_ngadminui');

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

        $output->writeln(
            [
                '',
                'Generated <comment>Netgen Admin UI</comment> siteaccess!',
            ]
        );

        $fileSystem->remove($finalExtensionLocation . '/settings/_skeleton_ngadminui');

        // Generating legacy admin siteaccess

        $this->setSkeletonDir($finalExtensionLocation . '/settings/_skeleton_legacy_admin');

        $this->renderFile(
            'site.ini.append.php',
            $finalExtensionLocation . '/settings/siteaccess/' . self::LEGACY_ADMIN_SITEACCESS_NAME . '/site.ini.append.php',
            [
                'siteName' => $siteName,
                'relatedSiteAccessList' => $allSiteAccesses,
                'siteAccessLocale' => $allLanguages[0],
                'siteLanguageList' => $allLanguages,
                'translationList' => $translationList,
            ]
        );

        $output->writeln(
            [
                '',
                'Generated <comment>legacy</comment> admin siteaccess!',
            ]
        );

        $fileSystem->remove($finalExtensionLocation . '/settings/_skeleton_legacy_admin');
    }
}
