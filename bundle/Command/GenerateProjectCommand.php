<?php

declare(strict_types=1);

namespace Netgen\Bundle\SiteGeneratorBundle\Command;

use Exception;
use InvalidArgumentException;
use Netgen\Bundle\SiteGeneratorBundle\Generator\ConfigurationGenerator;
use Netgen\Bundle\SiteGeneratorBundle\Generator\Generator;
use Netgen\Bundle\SiteGeneratorBundle\Generator\LegacyProjectGenerator;
use Netgen\Bundle\SiteGeneratorBundle\Generator\LegacySiteAccessGenerator;
use Netgen\Bundle\SiteGeneratorBundle\Generator\ProjectGenerator;
use Netgen\Bundle\SiteGeneratorBundle\Manipulator\RoutingManipulator;
use ReflectionObject;
use RuntimeException;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\DependencyInjection\Container;
use Symfony\Component\Process\Process;

class GenerateProjectCommand extends GeneratorCommand
{
    protected function configure(): void
    {
        $this->setDefinition(
            [
                new InputOption('client', '', InputOption::VALUE_REQUIRED, 'Client name'),
                new InputOption('project', '', InputOption::VALUE_REQUIRED, 'Project name'),
                new InputOption('site-name', '', InputOption::VALUE_REQUIRED, 'Site name'),
                new InputOption('site-access-list-string', '', InputOption::VALUE_OPTIONAL, 'String definition of siteaccess list'),
                new InputOption('site-access-list', '', InputOption::VALUE_IS_ARRAY | InputOption::VALUE_OPTIONAL, 'Siteaccess list'),
                new InputOption('bundle-namespace', '', InputOption::VALUE_REQUIRED, 'Bundle namespace'),
                new InputOption('bundle-name', '', InputOption::VALUE_REQUIRED, 'Bundle name'),
                new InputOption('theme-name', '', InputOption::VALUE_REQUIRED, 'Theme name'),
                new InputOption('extension-name', '', InputOption::VALUE_REQUIRED, 'Extension name'),
            ]
        );
        $this->setDescription('Generates Netgen Site project');
        $this->setName('ngsite:generate:project');
    }

    protected function interact(InputInterface $input, OutputInterface $output): void
    {
        $this->input = $input;
        $this->output = $output;
        $this->questionHelper = $this->getHelper('question');

        $this->writeSection(['Welcome to the Netgen Site project generator']);

        while (!$this->doInteract()) {
            // We will always ask for siteaccesses
            $this->input->setOption('site-access-list-string', null);
            $this->input->setOption('site-access-list', null);
        }
    }

    protected function doInteract(): bool
    {
        $this->output->writeln(
            [
                'Input the client and project names. These values will be used to generate',
                'bundle name, theme name and legacy extension name.',
                '<comment>First letter</comment> of the names must be <comment>uppercased</comment>, and it is recommended',
                'to use <comment>CamelCasing</comment> for the rest of the names.',
                '',
            ]
        );

        $client = ucfirst(
            $this->askForData(
                'client',
                'Client name',
                '',
                'validateCamelCaseName'
            )
        );
        $clientNormalized = Container::underscore($client);

        $project = ucfirst(
            $this->askForData(
                'project',
                'Project name',
                '',
                'validateCamelCaseName'
            )
        );
        $projectNormalized = Container::underscore($project);

        $this->output->writeln(
            [
                '',
                'Input the site name, and admin siteaccess name. Site name will be visible',
                'as the title of the pages in eZ Publish, so you are free to input whatever you like here.',
                '',
            ]
        );

        $this->askForData(
            'site-name',
            'Site name',
            ucfirst(str_replace('_', ' ', $projectNormalized)),
            'validateNotEmpty'
        );

        $siteAccessList = [];

        // Try to parse the following format
        // eng:eng-GB|cro:cro-HR:eng-GB
        $siteAccessListString = $this->input->getOption('site-access-list-string');
        if (!empty($siteAccessListString)) {
            $siteAccessListStringArray = explode('|', $siteAccessListString);
            $siteAccesses = [];
            foreach ($siteAccessListStringArray as $siteAccessListStringArrayItem) {
                if (empty($siteAccessListStringArrayItem)) {
                    throw new RuntimeException('Invalid site-access-list-string option provided');
                }

                $explodedSiteAccessItem = explode(':', $siteAccessListStringArrayItem);
                if (count($explodedSiteAccessItem) < 2) {
                    throw new RuntimeException('Invalid site-access-list-string option provided');
                }

                foreach ($explodedSiteAccessItem as $index => $siteAccessOrLanguage) {
                    $siteAccessLanguages = [];

                    if (empty($siteAccessOrLanguage)) {
                        throw new RuntimeException('Invalid site-access-list-string option provided');
                    }

                    if ($index === 0) {
                        if ($siteAccessOrLanguage === Generator::LEGACY_ADMIN_SITEACCESS_NAME) {
                            throw new InvalidArgumentException('Regular siteaccess name cannot be equal to "' . Generator::LEGACY_ADMIN_SITEACCESS_NAME . '".');
                        }

                        if (in_array($siteAccessOrLanguage, $siteAccesses, true)) {
                            throw new InvalidArgumentException('Duplicate siteaccess name found: "' . $siteAccessOrLanguage . '".');
                        }

                        Validators::validateSiteAccessName($siteAccessOrLanguage);
                        $siteAccesses[] = $siteAccessOrLanguage;
                        continue;
                    }

                    if (in_array($siteAccessOrLanguage, $siteAccessLanguages, true)) {
                        throw new InvalidArgumentException('Duplicate language code found in ' . $explodedSiteAccessItem[0] . ' siteaccess: "' . $siteAccessOrLanguage . '".');
                    }

                    Validators::validateLanguageCode($siteAccessOrLanguage);
                    $siteAccessList[$explodedSiteAccessItem[0]][] = $siteAccessOrLanguage;
                    $siteAccessLanguages[] = $siteAccessOrLanguage;
                }
            }
        }

        if (empty($siteAccessList)) {
            $this->output->writeln(
                [
                    '',
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
        }

        $this->input->setOption('site-access-list', $siteAccessList);

        $this->output->writeln(
            [
                '',
                'Input the bundle and theme details.',
                '',
            ]
        );

        $bundleNamespace = $this->askForData('bundle-namespace', 'Bundle namespace', $client . '\\Bundle\\' . $project . 'Bundle', 'validateBundleNamespace');
        $bundleName = $this->askForData('bundle-name', 'Bundle name', $client . $project . 'Bundle', 'validateBundleName');
        $themeName = $this->askForData('theme-name', 'Theme name', $projectNormalized, 'validateLowerCaseName');

        $this->output->writeln(
            [
                '',
                'Input the legacy extension details.',
                '',
            ]
        );

        $extensionName = $this->askForData('extension-name', 'Extension name', $clientNormalized . '_' . $projectNormalized, 'validateLowerCaseName');

        $this->writeSection(['Summary before generation']);

        // Summary
        $this->output->writeln(
            [
                'You are going to generate a <info>' . $bundleNamespace . '\\' . $bundleName . '</info> bundle using the <info>' . $themeName . '</info> theme',
                'and <info>' . $extensionName . '</info> legacy extension',
                '',
            ]
        );

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

        // Generate Netgen Site project
        $projectGenerator = new ProjectGenerator($this->getContainer());
        $projectGenerator->generate($this->input, $this->output);

        // Generate Netgen Site legacy project
        $legacyProjectGenerator = new LegacyProjectGenerator($this->getContainer());
        $legacyProjectGenerator->generate($this->input, $this->output);

        // Generate legacy siteaccesses
        $legacySiteAccessGenerator = new LegacySiteAccessGenerator($this->getContainer());
        $legacySiteAccessGenerator->generate($this->input, $this->output);

        // Generate configuration
        $configurationGenerator = new ConfigurationGenerator($this->getContainer());
        $configurationGenerator->generate($this->input, $this->output);

        $errors = [];
        $runner = $this->getRunner($errors);

        // Register the bundle in the AppKernel class
        $runner($this->updateKernel());

        // Install Symfony assets as relative symlinks
        $runner($this->installAssets());

        // Set up routing
        $runner($this->updateRouting());

        // Install Netgen Site project symlinks
        $runner($this->installProjectSymlinks());

        // Install Netgen Site legacy symlinks
        $runner($this->installLegacySymlinks());

        // Generate legacy autoloads
        $runner($this->generateLegacyAutoloads());

        // Various cleanups
        $runner($this->cleanup());

        $this->writeGeneratorSummary($errors);

        return 0;
    }

    /**
     * Installs Netgen Site project symlinks.
     */
    protected function installProjectSymlinks(): array
    {
        $this->output->writeln('');
        $this->output->write('Installing Netgen Site project symlinks... ');

        try {
            $process = new Process(
                [
                    'php',
                    'bin/console',
                    'ngsite:symlink:project',
                    '--quiet',
                ],
                null,
                null,
                null,
                3600
            );

            $process->run(
                function ($type, $buffer) {
                    echo $buffer;
                }
            );

            if (!$process->isSuccessful()) {
                return [
                    '- Run the following command from your installation root to install Netgen Site project symlinks:',
                    '',
                    '    <comment>php bin/console ngsite:symlink:project</comment>',
                    '',
                ];
            }
        } catch (Exception $e) {
            return [
                'There was an error installing Netgen Site project symlinks: ' . $e->getMessage(),
                '',
            ];
        }

        return [];
    }

    /**
     * Installs Netgen Site legacy symlinks.
     */
    protected function installLegacySymlinks(): array
    {
        $this->output->writeln('');
        $this->output->write('Installing Netgen Site legacy symlinks... ');

        try {
            $process = new Process(
                [
                    'php',
                    'bin/console',
                    'ngsite:symlink:legacy',
                    '--quiet',
                ],
                null,
                null,
                null,
                3600
            );

            $process->run(
                function ($type, $buffer) {
                    echo $buffer;
                }
            );

            if (!$process->isSuccessful()) {
                return [
                    '- Run the following command from your installation root to install Netgen Site legacy symlinks:',
                    '',
                    '    <comment>php bin/console ngsite:symlink:legacy</comment>',
                    '',
                ];
            }
        } catch (Exception $e) {
            return [
                'There was an installing Netgen Site legacy symlinks: ' . $e->getMessage(),
                '',
            ];
        }

        return [];
    }

    /**
     * Generates legacy autoloads.
     */
    protected function generateLegacyAutoloads(): array
    {
        $this->output->writeln('');
        $this->output->write('Generating legacy autoloads... ');

        $currentWorkingDirectory = getcwd();

        try {
            chdir($this->getContainer()->getParameter('ezpublish_legacy.root_dir'));

            $process = new Process(
                [
                    'php',
                    'bin/php/ezpgenerateautoloads.php',
                    '--quiet',
                ],
                null,
                null,
                null,
                3600
            );

            $process->run(
                function ($type, $buffer) {
                    echo $buffer;
                }
            );

            chdir($currentWorkingDirectory);

            if (!$process->isSuccessful()) {
                return [
                    '- Run the following command from your ezpublish_legacy root to generate legacy autoloads:',
                    '',
                    '    <comment>php bin/php/ezpgenerateautoloads.php</comment>',
                    '',
                ];
            }
        } catch (Exception $e) {
            chdir($currentWorkingDirectory);

            return [
                'There was an error generating legacy autoloads: ' . $e->getMessage(),
                '',
            ];
        }

        return [];
    }

    /**
     * Cleans up various leftover files.
     */
    protected function cleanup(): array
    {
        $this->output->writeln('');
        $this->output->write('Cleaning up... ');
        $this->output->writeln('');
        $this->output->writeln('');

        $projectDir = $this->getContainer()->getParameter('kernel.project_dir');
        $legacyDir = $this->getContainer()->getParameter('ezpublish_legacy.root_dir');

        try {
            $fileSystem = $this->getContainer()->get('filesystem');
            $fileSystem->remove($projectDir . '/web/bundles/netgensitedemo');
            $fileSystem->remove($legacyDir . '/extension/ngsite_demo');

            if (
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
            return [
                'There was an error cleaning up: ' . $e->getMessage(),
                '',
            ];
        }

        return [];
    }

    /**
     * Adds the bundle to the kernel file.
     */
    protected function updateKernel(): array
    {
        $this->output->writeln('');
        $this->output->write('Enabling the bundle inside the kernel... ');

        try {
            $bundleFQN = $this->input->getOption('bundle-namespace') . '\\' . $this->input->getOption('bundle-name');

            $reflected = new ReflectionObject($this->getContainer()->get('kernel'));

            $fileContent = file_get_contents($reflected->getFileName());
            $fileContent = str_replace(
                '$bundles[] = new Netgen\Bundle\SiteDemoBundle\NetgenSiteDemoBundle();',
                '$bundles[] = new ' . $bundleFQN . '();',
                $fileContent
            );

            $updated = file_put_contents($reflected->getFileName(), $fileContent);

            if (!$updated) {
                return [
                    '- Edit <comment>' . $reflected->getFileName() . '</comment>',
                    '  and add the following bundle at the end of <comment>' . $reflected->getName() . '::registerBundles()</comment>',
                    '  method, replacing the existing NetgenSiteDemoBundle:',
                    '',
                    '    <comment>$bundles[] = new ' . $bundleFQN . '();</comment>',
                    '',
                ];
            }
        } catch (Exception $e) {
            return [
                'There was an error enabling bundle inside the kernel: ' . $e->getMessage(),
                '',
            ];
        }

        return [];
    }

    /**
     * Updates the routing file.
     */
    protected function updateRouting(): array
    {
        $this->output->writeln('');
        $this->output->write('Importing the bundle routing resource... ');

        $projectDir = $this->getContainer()->getParameter('kernel.project_dir');
        $kernelName = $this->getContainer()->getParameter('kernel.name');

        $routing = new RoutingManipulator($projectDir . '/' . $kernelName . '/config/routing.yml');
        try {
            $bundleName = $this->input->getOption('bundle-name');
            $updated = $routing->addResource($bundleName);
            if (!$updated) {
                return [
                    '- Import the bundle\'s routing resource in the main routing file:',
                    '',
                    '    <comment>' . Container::underscore(substr($bundleName, 0, -6)) . ':</comment>',
                    '        <comment>resource: \"@' . $bundleName . '/Resources/config/routing.yml\"</comment>\n',
                    '',
                ];
            }
        } catch (Exception $e) {
            return [
                'There was an error importing bundle routing resource: ' . $e->getMessage(),
                '',
            ];
        }

        return [];
    }

    /**
     * Installs Symfony assets as relative symlinks.
     */
    protected function installAssets(): array
    {
        $this->output->writeln('');
        $this->output->write('Installing assets using the <comment>symlink</comment> option... ');

        try {
            $process = new Process(
                [
                    'php',
                    'bin/console',
                    'assets:install',
                    '--symlink',
                    '--relative',
                    '--quiet',
                ],
                null,
                null,
                null,
                3600
            );

            $process->run(
                function ($type, $buffer) {
                    echo $buffer;
                }
            );

            if (!$process->isSuccessful()) {
                return [
                    '- Run the following command from your installation root to install assets:',
                    '',
                    '    <comment>php bin/console assets:install --symlink --relative</comment>',
                    '',
                ];
            }
        } catch (Exception $e) {
            return [
                'There was an error installing assets: ' . $e->getMessage(),
                '',
            ];
        }

        return [];
    }
}
