<?php

declare(strict_types=1);

namespace Netgen\Bundle\SiteGeneratorBundle\Command;

use CaptainHook\App\Config;
use Exception;
use Netgen\Bundle\SiteGeneratorBundle\Generator\ConfigurationGenerator;
use Netgen\Bundle\SiteGeneratorBundle\Generator\Generator;
use Netgen\Bundle\SiteGeneratorBundle\Generator\LegacySiteAccessGenerator;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

class GenerateProjectCommand extends GeneratorCommand
{
    protected function configure(): void
    {
        $this->setDefinition(
            [
                new InputOption('site-access-list', '', InputOption::VALUE_IS_ARRAY | InputOption::VALUE_OPTIONAL, 'Siteaccess list'),
            ]
        );
        $this->setDescription('Generates a new Netgen Site client project');
        $this->setName('ngsite:generate:project');
    }

    protected function interact(InputInterface $input, OutputInterface $output): void
    {
        $this->input = $input;
        $this->output = $output;
        $this->questionHelper = $this->getHelper('question');

        $this->writeSection(['Welcome to the Netgen Site client project generator']);

        while (!$this->doInteract()) {
            // We will always ask for siteaccesses
            $this->input->setOption('site-access-list', null);
        }
    }

    protected function doInteract(): bool
    {
        $siteAccessList = [];

        $this->output->writeln(
            [
                'Input the name of every siteaccess you wish to create.',
                'The first siteaccess you specify will become the default siteaccess.',
                'The names must contain <comment>lowercase letters, underscores or numbers</comment>.',
                '',
            ]
        );

        do {
            $siteAccess = $this->questionHelper->ask(
                $this->input,
                $this->output,
                $this->getQuestion(
                    'Siteaccess name (use empty value to finish)',
                    '',
                    'validateSiteAccessName'
                )
            );

            if ($siteAccess === Generator::LEGACY_ADMIN_SITEACCESS_NAME) {
                $this->output->writeln('<error> Siteaccess name cannot be equal to "' . Generator::LEGACY_ADMIN_SITEACCESS_NAME . '". </error>');

                continue;
            }

            if (!empty($siteAccess)) {
                if (array_key_exists($siteAccess, $siteAccessList)) {
                    $this->output->writeln('<error> Siteaccess name already added </error>');

                    continue;
                }

                $siteAccessList[$siteAccess] = [];

                $languageList = [];
                do {
                    $language = $this->questionHelper->ask(
                        $this->input,
                        $this->output,
                        $this->getQuestion(
                            'Language code for <comment>' . $siteAccess . '</comment> siteaccess (use empty value to finish)',
                            '',
                            'validateLanguageCode'
                        )
                    );

                    if ($language === 'eng-EU') {
                        $this->output->writeln('<error> Language name cannot be equal to "eng-EU". "eng-EU" is deprecated and "eng-GB" will be used instead. </error>');
                        $language = 'eng-GB';
                    }

                    if (!empty($language)) {
                        if (in_array($language, $languageList, true)) {
                            $this->output->writeln('<error> Language code already added </error>');

                            continue;
                        }

                        $languageList[] = $language;
                    }
                } while (!empty($language) || empty($languageList));

                $siteAccessList[$siteAccess] = $languageList;
            }
        } while (!empty($siteAccess) || empty($siteAccessList));

        $this->input->setOption('site-access-list', $siteAccessList);

        $this->writeSection(['Confirm project generation']);

        if (
            !$this->questionHelper->ask(
                $this->input,
                $this->output,
                $this->getConfirmationQuestion(
                    'Do you confirm project generation (answering <comment>no</comment> will restart the process)',
                    true
                )
            )
        ) {
            $this->output->writeln('');

            return false;
        }

        return true;
    }

    protected function execute(InputInterface $input, OutputInterface $output): ?int
    {
        if (!$input->isInteractive()) {
            $output->writeln('<error>This command only supports interactive execution</error>');

            return 1;
        }

        $this->writeSection(['Project generation']);

        // Generate legacy siteaccesses
        $legacySiteAccessGenerator = new LegacySiteAccessGenerator($this->getContainer());
        $legacySiteAccessGenerator->generate($this->input, $this->output);

        // Generate configuration
        $configurationGenerator = new ConfigurationGenerator($this->getContainer());
        $configurationGenerator->generate($this->input, $this->output);

        // Various cleanups
        $this->cleanup();

        $this->activateGitHooks();

        $this->writeSection(['You can now start using the site!']);

        return 0;
    }

    /**
     * Cleans up various leftover files.
     */
    protected function cleanup(): void
    {
        $this->output->writeln('');
        $this->output->write('Cleaning up... ');
        $this->output->writeln('');
        $this->output->writeln('');

        $projectDir = $this->getContainer()->getParameter('kernel.project_dir');

        try {
            $fileSystem = $this->getContainer()->get('filesystem');

            if (
                $fileSystem->exists($projectDir . '/.git') &&
                $this->questionHelper->ask(
                    $this->input,
                    $this->output,
                    $this->getConfirmationQuestion(
                        'Do you want to delete the <comment>.git</comment> folder?',
                        true
                    )
                )
            ) {
                $fileSystem->remove($projectDir . '/.git');
            }
        } catch (Exception $e) {
            // Do nothing
        }
    }

    private function activateGitHooks(): void
    {
        if (!class_exists(Config::class)) {
            return;
        }

        /** @var \Symfony\Component\Filesystem\Filesystem $fileSystem */
        $fileSystem = $this->getContainer()->get('filesystem');
        $projectDir = $this->getContainer()->getParameter('kernel.project_dir');
        $captainHookFiles = [
            $projectDir . '/captainhook_disabled.json',
            $projectDir . '/captainhook_enabled.json',
        ];

        if (!$fileSystem->exists($captainHookFiles)) {
            return;
        }

        if ($this->questionHelper->ask(
            $this->input,
            $this->output,
            $this->getConfirmationQuestion(
                    'Do you want to use git hooks suitable for project development?',
                    false
                )
        )
        ) {
            $fileSystem->symlink(
                'captainhook_enabled.json',
                $projectDir . '/captainhook.json'
            );

            return;
        }
    }
}
