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
use Symfony\Component\Process\PhpExecutableFinder;
use Symfony\Component\Process\Process;
use function array_key_exists;
use function class_exists;
use function file_get_contents;
use function file_put_contents;
use function in_array;
use function preg_replace;
use function sprintf;
use const PHP_MAJOR_VERSION;
use const PHP_MINOR_VERSION;

class GenerateProjectCommand extends GeneratorCommand
{
    protected function configure(): void
    {
        $this->setDefinition(
            [
                new InputOption('site-access-list', '', InputOption::VALUE_IS_ARRAY | InputOption::VALUE_OPTIONAL, 'Siteaccess list'),
                new InputOption('site-design', '', InputOption::VALUE_OPTIONAL, 'Default site design'),
            ],
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
            ],
        );

        do {
            $siteAccess = $this->questionHelper->ask(
                $this->input,
                $this->output,
                $this->getQuestion(
                    'Siteaccess name (use empty value to finish)',
                    '',
                    'validateSiteAccessName',
                ),
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
                            'validateLanguageCode',
                        ),
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

        $designType = $this->questionHelper->ask(
            $this->input,
            $this->output,
            $this->getQuestion(
                'Which design do you wish to use [remote/local]',
                'local',
                'validateDesignType',
            ),
        );

        $this->input->setOption('site-design', $designType);

        $this->writeSection(['Confirm project generation']);

        if (
            !$this->questionHelper->ask(
                $this->input,
                $this->output,
                $this->getConfirmationQuestion(
                    'Do you confirm project generation (answering <comment>no</comment> will restart the process)',
                    true,
                ),
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

        $this->setPhpVersion();
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
                $fileSystem->exists($projectDir . '/.git')
                && $this->questionHelper->ask(
                    $this->input,
                    $this->output,
                    $this->getConfirmationQuestion(
                        'Do you want to reset the <comment>.git</comment> folder?',
                        true,
                    ),
                )
            ) {
                $fileSystem->remove($projectDir . '/.git');
                $this->runProcess(['git', 'init']);

                return;
            }

            if (
                !$fileSystem->exists($projectDir . '/.git')
                && $this->questionHelper->ask(
                    $this->input,
                    $this->output,
                    $this->getConfirmationQuestion(
                        'Do you want to initialize <comment>.git</comment> folder?',
                        true,
                    ),
                )) {
                $this->runProcess(['git', 'init']);
            }
        } catch (Exception $e) {
            // Do nothing
        }
    }

    private function setPhpVersion(): void
    {
        /** @var \Symfony\Component\Filesystem\Filesystem $fileSystem */
        $fileSystem = $this->getContainer()->get('filesystem');
        $projectDir = $this->getContainer()->getParameter('kernel.project_dir');

        if (!$fileSystem->exists($projectDir . '/composer.json')) {
            return;
        }

        $phpVersion = sprintf('~%d.%d.0', PHP_MAJOR_VERSION, PHP_MINOR_VERSION);

        $composerJsonFile = file_get_contents($projectDir . '/composer.json');
        $composerJsonFile = preg_replace('/"php": "([~^]|>=)(\d\.)*\d(\s*\|\|?\s*(\d\.)*\d)*",/i', '"php": "' . $phpVersion . '",', $composerJsonFile);

        file_put_contents($projectDir . '/composer.json', $composerJsonFile);
    }

    private function activateGitHooks(): void
    {
        if (!class_exists(Config::class)) {
            return;
        }

        /** @var \Symfony\Component\Filesystem\Filesystem $fileSystem */
        $fileSystem = $this->getContainer()->get('filesystem');
        $projectDir = $this->getContainer()->getParameter('kernel.project_dir');

        if (!$fileSystem->exists($projectDir . '/captainhook.template.json')) {
            return;
        }

        if (
            $fileSystem->exists($projectDir . '/.git')
            && $this->questionHelper->ask(
                $this->input,
                $this->output,
                $this->getConfirmationQuestion(
                    'Do you want to use git hooks suitable for project development?',
                    false,
                ),
            )
        ) {
            $fileSystem->symlink(
                'captainhook.template.json',
                $projectDir . '/captainhook.json',
            );

            $this->output->writeln('');

            $phpBinaryFinder = new PhpExecutableFinder();
            $phpBinaryPath = $phpBinaryFinder->find();

            $this->runProcess(
                [
                    $phpBinaryPath,
                    'bin/captainhook',
                    'install',
                    '--force',
                    $this->output->isDecorated() ? '--ansi' : '--no-ansi',
                ],
            );
        }
    }

    private function runProcess(array $arguments): void
    {
        $process = new Process($arguments);

        $process->run(
            function ($type, $line) {
                $this->output->write($line, false);
            },
        );
    }
}
