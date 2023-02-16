<?php

declare(strict_types=1);

namespace Netgen\Bundle\SiteGeneratorBundle\Command;

use CaptainHook\App\Config;
use Exception;
use Netgen\Bundle\SiteGeneratorBundle\Generator\ConfigurationGenerator;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Helper\QuestionHelper;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Question\ConfirmationQuestion;
use Symfony\Component\Console\Question\Question;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\Filesystem\Filesystem;
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

final class GenerateProjectCommand extends Command
{
    private InputInterface $input;

    private OutputInterface $output;

    private QuestionHelper $questionHelper;

    public function __construct(private ContainerInterface $container, private Filesystem $fileSystem)
    {
        // Parent constructor call is mandatory for commands registered as services
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->setDefinition(
            [
                new InputOption('site-access-list', '', InputOption::VALUE_IS_ARRAY | InputOption::VALUE_OPTIONAL, 'Siteaccess list'),
            ],
        );

        $this->setDescription('Generates a new Netgen Site client project');
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

    protected function execute(InputInterface $input, OutputInterface $output): ?int
    {
        if (!$input->isInteractive()) {
            $output->writeln('<error>This command only supports interactive execution</error>');

            return 1;
        }

        $this->writeSection(['Project generation']);

        // Generate configuration
        $configurationGenerator = new ConfigurationGenerator($this->container);
        $configurationGenerator->generate($this->input, $this->output);

        // Various cleanups
        $this->cleanup();

        $this->setPhpVersion();
        $this->activateGitHooks();

        $this->writeSection(['You can now start using the site!']);

        return 0;
    }

    private function doInteract(): bool
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

    /**
     * Instantiates and returns a question.
     */
    private function getQuestion(string $questionName, ?string $defaultValue = null, ?string $validator = null): Question
    {
        $questionName = $defaultValue
            ? '<info>' . $questionName . '</info> [<comment>' . $defaultValue . '</comment>]: '
            : '<info>' . $questionName . '</info>: ';

        $question = new Question($questionName, $defaultValue);
        if ($validator !== null) {
            $question->setValidator([Validators::class, $validator]);
        }

        return $question;
    }

    /**
     * Instantiates and returns the confirmation question.
     */
    private function getConfirmationQuestion(string $questionName, bool $defaultValue = false): ConfirmationQuestion
    {
        return new ConfirmationQuestion(
            sprintf(
                '<info>%s</info> [<comment>%s</comment>]? ',
                $questionName,
                $defaultValue ? 'yes' : 'no',
            ),
            $defaultValue,
        );
    }

    /**
     * Writes a section of text to the output.
     */
    private function writeSection(array $messages, string $style = 'bg=blue;fg=white'): void
    {
        $this->output->writeln(
            [
                '',
                $this->getHelper('formatter')->formatBlock($messages, $style, true),
                '',
            ],
        );
    }

    /**
     * Cleans up various leftover files.
     */
    private function cleanup(): void
    {
        $this->output->writeln('');
        $this->output->write('Cleaning up... ');
        $this->output->writeln('');
        $this->output->writeln('');

        $projectDir = $this->container->getParameter('kernel.project_dir');

        try {
            if (
                $this->fileSystem->exists($projectDir . '/.git')
                && $this->questionHelper->ask(
                    $this->input,
                    $this->output,
                    $this->getConfirmationQuestion(
                        'Do you want to reset the <comment>.git</comment> folder?',
                        true,
                    ),
                )
            ) {
                $this->fileSystem->remove($projectDir . '/.git');
                $this->runProcess(['git', 'init']);

                return;
            }

            if (
                !$this->fileSystem->exists($projectDir . '/.git')
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
        } catch (Exception) {
            // Do nothing
        }
    }

    private function setPhpVersion(): void
    {
        $projectDir = $this->container->getParameter('kernel.project_dir');

        if (!$this->fileSystem->exists($projectDir . '/composer.json')) {
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

        $projectDir = $this->container->getParameter('kernel.project_dir');

        if (!$this->fileSystem->exists($projectDir . '/captainhook.template.json')) {
            return;
        }

        if (
            $this->fileSystem->exists($projectDir . '/.git')
            && $this->questionHelper->ask(
                $this->input,
                $this->output,
                $this->getConfirmationQuestion(
                    'Do you want to use git hooks suitable for project development?',
                ),
            )
        ) {
            $this->fileSystem->symlink(
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
                $this->output->write($line);
            },
        );
    }
}
