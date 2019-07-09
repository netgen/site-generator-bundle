<?php

declare(strict_types=1);

namespace Netgen\Bundle\SiteGeneratorBundle\Generator;

use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class LegacySiteAccessGenerator extends Generator
{
    /**
     * Generates the siteaccesses.
     */
    public function generate(InputInterface $input, OutputInterface $output): void
    {
        $projectDir = $this->container->getParameter('kernel.project_dir');
        $legacyExtensionDir = $projectDir . '/src/AppBundle/ezpublish_legacy/app';

        // Variables valid for all siteaccesses

        $siteAccessList = $input->getOption('site-access-list');
        $allSiteAccesses = array_keys($siteAccessList);
        $allSiteAccesses[] = self::LEGACY_ADMIN_SITEACCESS_NAME;
        $allSiteAccesses[] = self::NGADMINUI_SITEACCESS_NAME;

        $allLanguages = array_values(array_unique(array_merge(...array_values($siteAccessList))));
        $translationList = implode(';', array_values(array_diff($allLanguages, [$allLanguages[0]])));

        // Generate Netgen Admin UI siteaccess

        if ($this->fileSystem->exists($legacyExtensionDir . '/settings/_skeleton_ngadminui')) {
            $this->setSkeletonDir($legacyExtensionDir . '/settings/_skeleton_ngadminui');

            $this->renderFile(
                'site.ini.append.php',
                $legacyExtensionDir . '/settings/siteaccess/' . self::NGADMINUI_SITEACCESS_NAME . '/site.ini.append.php',
                [
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

            $this->fileSystem->remove($legacyExtensionDir . '/settings/_skeleton_ngadminui');
        }

        // Generating legacy admin siteaccess

        if ($this->fileSystem->exists($legacyExtensionDir . '/settings/_skeleton_legacy_admin')) {
            $this->setSkeletonDir($legacyExtensionDir . '/settings/_skeleton_legacy_admin');

            $this->renderFile(
                'site.ini.append.php',
                $legacyExtensionDir . '/settings/siteaccess/' . self::LEGACY_ADMIN_SITEACCESS_NAME . '/site.ini.append.php',
                [
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

            $this->fileSystem->remove($legacyExtensionDir . '/settings/_skeleton_legacy_admin');
        }
    }
}
