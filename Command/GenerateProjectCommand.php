<?php

namespace Netgen\Bundle\MoreGeneratorBundle\Command;

use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\DependencyInjection\Container;
use Symfony\Component\Process\ProcessBuilder;
use Netgen\Bundle\MoreGeneratorBundle\Generator\ProjectGenerator;
use Netgen\Bundle\MoreGeneratorBundle\Generator\LegacyProjectGenerator;
use Netgen\Bundle\MoreGeneratorBundle\Generator\LegacySiteAccessGenerator;
use Netgen\Bundle\MoreGeneratorBundle\Generator\ConfigurationGenerator;
use Netgen\Bundle\MoreGeneratorBundle\Manipulator\RoutingManipulator;
use InvalidArgumentException;
use ReflectionObject;
use RuntimeException;
use Exception;

class GenerateProjectCommand extends GeneratorCommand
{
    /**
     * Configures the command
     */
    protected function configure()
    {
        $this->setDefinition(
            array(
                new InputOption( 'client', '', InputOption::VALUE_REQUIRED, 'Client name' ),
                new InputOption( 'project', '', InputOption::VALUE_REQUIRED, 'Project name' ),
                new InputOption( 'site-name', '', InputOption::VALUE_REQUIRED, 'Site name' ),
                new InputOption( 'admin-site-access-name', '', InputOption::VALUE_REQUIRED, 'Admin siteaccess name' ),
                new InputOption( 'site-access-list-string', '', InputOption::VALUE_OPTIONAL, 'String definition of siteaccess list' ),
                new InputOption( 'site-access-list', '', InputOption::VALUE_IS_ARRAY | InputOption::VALUE_OPTIONAL, 'Siteaccess list' ),
                new InputOption( 'bundle-namespace', '', InputOption::VALUE_REQUIRED, 'Bundle namespace' ),
                new InputOption( 'bundle-name', '', InputOption::VALUE_REQUIRED, 'Bundle name' ),
                new InputOption( 'extension-name', '', InputOption::VALUE_REQUIRED, 'Extension name' ),
                new InputOption( 'design-name', '', InputOption::VALUE_REQUIRED, 'Design name' )
            )
        );
        $this->setDescription( 'Generates Netgen More project' );
        $this->setName( 'ngmore:generate:project' );
    }

    /**
     * Runs the command interactively
     *
     * @param \Symfony\Component\Console\Input\InputInterface $input
     * @param \Symfony\Component\Console\Output\OutputInterface $output
     *
     * @return int
     */
    protected function interact( InputInterface $input, OutputInterface $output )
    {
        $this->input = $input;
        $this->output = $output;
        $this->questionHelper = $this->getHelper( 'question' );

        $this->writeSection( 'Welcome to the Netgen More project generator' );

        while ( !$this->doInteract() )
        {
            // We will always ask for siteaccesses
            $this->input->setOption( 'site-access-list-string', null );
            $this->input->setOption( 'site-access-list', null );
        }
    }

    /**
     * Collects all the project data interactively
     *
     * @return bool
     */
    protected function doInteract()
    {
        $this->output->writeln(
            array(
                'Input the client and project names. These values will be used to generate',
                'bundle name, as well as legacy extension name and legacy design name.',
                '<comment>First letter</comment> of the names must be <comment>uppercased</comment>, and it is recommended',
                'to use <comment>CamelCasing</comment> for the rest of the names.',
                ''
            )
        );

        $client = ucfirst(
            $this->askForData(
                'client',
                'Client name',
                '',
                'validateCamelCaseName'
            )
        );
        $clientNormalized = Container::underscore( $client );

        $project = ucfirst(
            $this->askForData(
                'project',
                'Project name',
                '',
                'validateCamelCaseName'
            )
        );
        $projectNormalized = Container::underscore( $project );

        $this->output->writeln(
            array(
                '',
                'Input the site name, and admin siteaccess name. Site name will be visible',
                'as the title of the pages in eZ Publish, so you are free to input whatever you like here.',
                ''
            )
        );

        $this->askForData(
            'site-name',
            'Site name',
            ucfirst( str_replace( '_', ' ', $projectNormalized ) ),
            'validateNotEmpty'
        );

        $adminSiteAccess = $this->askForData(
            'admin-site-access-name',
            'Admin siteaccess name',
            $this->getContainer()->getParameter( 'ngmore_generator.admin_siteaccess_name' ),
            'validateAdminSiteAccessName'
        );

        $siteAccessList = array();

        // Try to parse the following format
        // eng:eng-EU|cro:cro-HR:eng-EU
        $siteAccessListString = $this->input->getOption( 'site-access-list-string' );
        if ( !empty( $siteAccessListString ) )
        {
            $siteAccessListStringArray = explode( '|', $siteAccessListString );
            $siteAccesses = array();
            foreach ( $siteAccessListStringArray as $siteAccessListStringArrayItem )
            {
                if ( empty( $siteAccessListStringArrayItem ) )
                {
                    throw new RuntimeException( 'Invalid site-access-list-string option provided' );
                }

                $explodedSiteAccessItem = explode( ':', $siteAccessListStringArrayItem );
                if ( count( $explodedSiteAccessItem ) < 2 )
                {
                    throw new RuntimeException( 'Invalid site-access-list-string option provided' );
                }

                foreach ( $explodedSiteAccessItem as $index => $siteAccessOrLanguage )
                {
                    $siteAccessLanguages = array();

                    if ( empty( $siteAccessOrLanguage ) )
                    {
                        throw new RuntimeException( 'Invalid site-access-list-string option provided' );
                    }

                    if ( $index == 0 )
                    {
                        if ( $siteAccessOrLanguage === $adminSiteAccess )
                        {
                            throw new InvalidArgumentException( 'Regular siteaccess name cannot be equal to "' . $adminSiteAccess . '".' );
                        }

                        if ( in_array( $siteAccessOrLanguage, $siteAccesses ) )
                        {
                            throw new InvalidArgumentException( 'Duplicate siteaccess name found: "' . $siteAccessOrLanguage . '".' );
                        }

                        Validators::validateSiteAccessName( $siteAccessOrLanguage );
                        $siteAccesses[] = $siteAccessOrLanguage;
                        continue;
                    }

                    if ( in_array( $siteAccessOrLanguage, $siteAccessLanguages ) )
                    {
                        throw new InvalidArgumentException( 'Duplicate language code found in ' . $explodedSiteAccessItem[0] . ' siteaccess: "' . $siteAccessOrLanguage . '".' );
                    }

                    Validators::validateLanguageCode( $siteAccessOrLanguage );
                    $siteAccessList[$explodedSiteAccessItem[0]][] = $siteAccessOrLanguage;
                    $siteAccessLanguages[] = $siteAccessOrLanguage;
                }
            }
        }

        if ( empty( $siteAccessList ) )
        {
            $this->output->writeln(
                array(
                    '',
                    'Input the name of every siteaccess you wish to create.',
                    'The first siteaccess you specify will become the default siteaccess.',
                    'Admin siteaccess (<comment>' . $adminSiteAccess . '</comment>) will be generated automatically.',
                    'The names must contain <comment>lowercase letters, underscores or numbers</comment>.',
                    ''
                )
            );

            do
            {
                $siteAccess = $this->questionHelper->ask(
                    $this->input,
                    $this->output,
                    $this->getQuestion(
                        'Siteaccess name (use empty value to finish)',
                        '',
                        'validateSiteAccessName'
                    )
                );

                if ( $siteAccess === $adminSiteAccess )
                {
                    $this->output->writeln( '<error> Siteaccess name cannot be equal to "' . $adminSiteAccess . '". </error>' );
                    continue;
                }

                if ( !empty( $siteAccess ) )
                {
                    if ( in_array( $siteAccess, array_keys( $siteAccessList ) ) )
                    {
                        $this->output->writeln( '<error> Siteaccess name already added </error>' );
                        continue;
                    }

                    $siteAccessList[$siteAccess] = array();

                    $languageList = array();
                    do
                    {
                        $language = $this->questionHelper->ask(
                            $this->input,
                            $this->output,
                            $this->getQuestion(
                                'Language code for <comment>' . $siteAccess . '</comment> siteaccess (use empty value to finish)',
                                '',
                                'validateLanguageCode'
                            )
                        );

                        if ( !empty( $language ) )
                        {
                            if ( in_array( $language, $languageList ) )
                            {
                                $this->output->writeln( '<error> Language code already added </error>' );
                                continue;
                            }

                            $languageList[] = $language;
                        }
                    }
                    while ( !empty( $language ) || empty( $languageList ) );

                    $siteAccessList[$siteAccess] = $languageList;
                }
            }
            while ( !empty( $siteAccess ) || empty( $siteAccessList ) );
        }

        $this->input->setOption( 'site-access-list', $siteAccessList );

        $this->output->writeln(
            array(
                '',
                'Input the legacy extension and bundle details.',
                ''
            )
        );

        $extensionName = $this->askForData( 'extension-name', 'Extension name', 'ez_' . $clientNormalized . '_' . $projectNormalized, 'validateLowerCaseName' );
        $designName = $this->askForData( 'design-name', 'Design name', $projectNormalized, 'validateLowerCaseName' );
        $bundleNamespace = $this->askForData( 'bundle-namespace', 'Bundle namespace', $client . "\\Bundle\\" . $project . 'Bundle', 'validateBundleNamespace' );
        $bundleName = $this->askForData( 'bundle-name', 'Bundle name', $client . $project . 'Bundle', 'validateBundleName' );

        $this->writeSection( 'Summary before generation' );

        // Summary
        $this->output->writeln(
            array(
                'You are going to generate a <info>' . $bundleNamespace . '\\' . $bundleName . '</info> bundle',
                'and <info>' . $extensionName . '</info> legacy extension using the <info>' . $designName . '</info> legacy design.',
                ''
            )
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
        )
        {
            $this->output->writeln( '' );
            return false;
        }

        return true;
    }

    /**
     * Runs the command
     *
     * @param \Symfony\Component\Console\Input\InputInterface $input
     * @param \Symfony\Component\Console\Output\OutputInterface $output
     *
     * @return int
     */
    protected function execute( InputInterface $input, OutputInterface $output )
    {
        if ( !$input->isInteractive() )
        {
            $output->writeln( '<error>This command only supports interactive execution</error>' );
            return 1;
        }

        $this->writeSection( 'Project generation' );

        // Generate Netgen More project
        $projectGenerator = new ProjectGenerator( $this->getContainer() );
        $projectGenerator->generate( $this->input, $this->output );

        // Generate Netgen More legacy project
        $legacyProjectGenerator = new LegacyProjectGenerator( $this->getContainer() );
        $legacyProjectGenerator->generate( $this->input, $this->output );

        // Generate legacy siteaccesses
        $legacySiteAccessGenerator = new LegacySiteAccessGenerator( $this->getContainer() );
        $legacySiteAccessGenerator->generate( $this->input, $this->output );

        // Generate configuration
        $configurationGenerator = new ConfigurationGenerator( $this->getContainer() );
        $configurationGenerator->generate( $this->input, $this->output );

        $errors = array();
        $runner = $this->getRunner( $errors );

        // Register the bundle in the EzPublishKernel class
        $runner( $this->updateKernel() );

        // Install Symfony assets as relative symlinks
        $runner( $this->installAssets() );

        // Set up routing
        $runner( $this->updateRouting() );

        // Install Netgen More project symlinks
        $runner( $this->installProjectSymlinks() );

        // Install Netgen More legacy symlinks
        $runner( $this->installLegacySymlinks() );

        // Generate legacy autoloads
        $runner( $this->generateLegacyAutoloads() );

        // Various cleanups
        $runner( $this->cleanup() );

        $errorCount = count( $errors );

        // Import Netgen More database
        $runner( $this->importDatabase() );

        // Move storage folder to proper location
        $runner( $this->moveStorageFolder() );

        if ( count( $errors ) == $errorCount )
        {
            // Deletes the data folder
            $runner( $this->deleteDataFolder() );
        }

        $this->writeGeneratorSummary( $errors );

        return 0;
    }

    /**
     * Installs Netgen More project symlinks
     *
     * @return array
     */
    protected function installProjectSymlinks()
    {
        $this->output->writeln( '' );
        $this->output->write( 'Installing Netgen More project symlinks... ' );

        try
        {
            $processBuilder = new ProcessBuilder(
                array(
                    'php',
                    'ezpublish/console',
                    'ngmore:symlink:project',
                    '--quiet'
                )
            );

            $process = $processBuilder->getProcess();

            $process->setTimeout( 3600 );
            $process->run(
                function ( $type, $buffer )
                {
                    echo $buffer;
                }
            );

            if ( !$process->isSuccessful() )
            {
                return array(
                    '- Run the following command from your installation root to install Netgen More project symlinks:',
                    '',
                    '    <comment>php ezpublish/console ngmore:symlink:project</comment>',
                    '',
                );
            }
        }
        catch ( Exception $e )
        {
            return array(
                'There was an error installing Netgen More project symlinks: ' . $e->getMessage(),
                '',
            );
        }
    }

    /**
     * Installs Netgen More legacy symlinks
     *
     * @return array
     */
    protected function installLegacySymlinks()
    {
        $this->output->writeln( '' );
        $this->output->write( 'Installing Netgen More legacy symlinks... ' );

        try
        {
            $processBuilder = new ProcessBuilder(
                array(
                    'php',
                    'ezpublish/console',
                    'ngmore:symlink:legacy',
                    '--quiet'
                )
            );

            $process = $processBuilder->getProcess();

            $process->setTimeout( 3600 );
            $process->run(
                function ( $type, $buffer )
                {
                    echo $buffer;
                }
            );

            if ( !$process->isSuccessful() )
            {
                return array(
                    '- Run the following command from your installation root to install Netgen More legacy symlinks:',
                    '',
                    '    <comment>php ezpublish/console ngmore:symlink:legacy</comment>',
                    '',
                );
            }
        }
        catch ( Exception $e )
        {
            return array(
                'There was an installing Netgen More legacy symlinks: ' . $e->getMessage(),
                '',
            );
        }
    }

    /**
     * Generates legacy autoloads
     *
     * @return array
     */
    protected function generateLegacyAutoloads()
    {
        $this->output->writeln( '' );
        $this->output->write( 'Generating legacy autoloads... ' );

        $currentWorkingDirectory = getcwd();

        try
        {
            chdir( $this->getContainer()->getParameter( 'ezpublish_legacy.root_dir' ) );

            $processBuilder = new ProcessBuilder(
                array(
                    'php',
                    'bin/php/ezpgenerateautoloads.php',
                    '--quiet'
                )
            );

            $process = $processBuilder->getProcess();

            $process->setTimeout( 3600 );
            $process->run(
                function ( $type, $buffer )
                {
                    echo $buffer;
                }
            );

            chdir( $currentWorkingDirectory );

            if ( !$process->isSuccessful() )
            {
                return array(
                    '- Run the following command from your ezpublish_legacy root to generate legacy autoloads:',
                    '',
                    '    <comment>php bin/php/ezpgenerateautoloads.php</comment>',
                    '',
                );
            }
        }
        catch ( Exception $e )
        {
            chdir( $currentWorkingDirectory );

            return array(
                'There was an error generating legacy autoloads: ' . $e->getMessage(),
                '',
            );
        }
    }

    /**
     * Cleans up various leftover files
     *
     * @return array
     */
    protected function cleanup()
    {
        $this->output->writeln( '' );
        $this->output->write( 'Cleaning up... ' );

        $kernelDir = $this->getContainer()->getParameter( 'kernel.root_dir' );
        $legacyDir = $this->getContainer()->getParameter( 'ezpublish_legacy.root_dir' );

        try
        {
            $fileSystem = $this->getContainer()->get( 'filesystem' );
            $fileSystem->remove( $kernelDir . '/../web/bundles/netgenmoredemo' );
            $fileSystem->remove( $legacyDir . '/extension/ez_netgen_ngmore_demo' );
        }
        catch ( Exception $e )
        {
            return array(
                'There was an error cleaning up: ' . $e->getMessage(),
                '',
            );
        }
    }

    /**
     * Imports MySQL database
     *
     * @return array
     */
    protected function importDatabase()
    {
        $databasePath = $this->getContainer()->getParameter( 'kernel.root_dir' ) . '/data/dump.sql';

        $databaseHost = $this->getContainer()->getParameter( 'database_host' );
        $databasePort = $this->getContainer()->getParameter( 'database_port' );
        $databaseUser = $this->getContainer()->getParameter( 'database_user' );
        $databasePassword = $this->getContainer()->getParameter( 'database_password' );
        $databaseName = $this->getContainer()->getParameter( 'database_name' );

        $errorOutput = array(
            '- Run the following command from your installation root to import Netgen More database:',
            '',
            '    <comment>mysql -u ' . $databaseUser . ' -h ' . $databaseHost . ' -p ' . $databaseName . ' < ' . $databasePath . '</comment>',
            '',
        );

        $this->output->writeln( '' );
        $doImport = $this->questionHelper->ask(
            $this->input,
            $this->output,
            $this->getConfirmationQuestion(
                'Do you want to import Netgen More database (this will destroy all existing data in the selected database)',
                false
            )
        );

        $this->output->writeln( '' );
        $this->output->write( 'Importing Netgen More database... ' );

        try
        {
            if ( !file_exists( $databasePath ) || !$doImport )
            {
                return $errorOutput;
            }

            $processParams = array(
                'mysql',
                '-u',
                $databaseUser,
                '-h',
                $databaseHost
            );

            if ( !empty( $databasePassword ) )
            {
                $processParams[] = '-p' . $databasePassword;
            }

            if ( !empty( $databasePort ) )
            {
                $processParams[] = '-P';
                $processParams[] = $databasePort;
            }

            $processParams[] = $databaseName;

            $processBuilder = new ProcessBuilder( $processParams );
            $process = $processBuilder->getProcess();
            $process->setTimeout( 3600 );

            $process->setEnv( array( "LANG" => "en_US.UTF-8" ) );
            $process->setInput( file_get_contents( $databasePath ) );

            $process->run(
                function ( $type, $buffer )
                {
                    echo $buffer;
                }
            );

            if ( !$process->isSuccessful() )
            {
                return $errorOutput;
            }
        }
        catch ( Exception $e )
        {
            return array(
                'There was an error importing Netgen More database: ' . $e->getMessage(),
                '',
            );
        }
    }

    /**
     * Move storage folder to proper location
     *
     * @return array
     */
    protected function moveStorageFolder()
    {
        $storagePath = $this->getContainer()->getParameter( 'kernel.root_dir' ) . '/data/var/ezdemo_site';

        $finalStoragePath = $this->getContainer()->getParameter( 'ezpublish_legacy.root_dir' ) . '/var/ezdemo_site';

        $errorOutput = array(
            '- Run the following command from your installation root to move the storage folder:',
            '',
            '    <comment>mv ' . $storagePath . ' ' . $finalStoragePath . '</comment>',
            '',
        );

        $this->output->writeln( '' );
        $doMove = $this->questionHelper->ask(
            $this->input,
            $this->output,
            $this->getConfirmationQuestion(
                'Do you want to move the storage folder to proper location',
                false
            )
        );

        $this->output->writeln( '' );
        $this->output->write( 'Moving the storage folder... ' );

        try
        {
            if ( file_exists( $finalStoragePath ) || !$doMove )
            {
                return $errorOutput;
            }

            $fileSystem = $this->getContainer()->get( 'filesystem' );
            $fileSystem->rename( $storagePath, $finalStoragePath );
        }
        catch ( Exception $e )
        {
            return array(
                'There was an error moving the storage folder: ' . $e->getMessage(),
                '',
            );
        }
    }

    /**
     * Deletes the data folder
     *
     * @return array
     */
    protected function deleteDataFolder()
    {
        $dataPath = $this->getContainer()->getParameter( 'kernel.root_dir' ) . '/data';

        $errorOutput = array(
            '- Run the following command from your installation root to delete the data folder:',
            '',
            '    <comment>rm -r ' . $dataPath . '</comment>',
            '',
        );

        $this->output->writeln( '' );
        $doDelete = $this->questionHelper->ask(
            $this->input,
            $this->output,
            $this->getConfirmationQuestion(
                'Do you want to delete the data folder',
                false
            )
        );

        $this->output->writeln( '' );
        $this->output->write( 'Deleting the folder... ' );

        try
        {
            if ( !$doDelete )
            {
                return $errorOutput;
            }

            $fileSystem = $this->getContainer()->get( 'filesystem' );
            $fileSystem->remove( $dataPath );
        }
        catch ( Exception $e )
        {
            return array(
                'There was an error deleting the data folder: ' . $e->getMessage(),
                '',
            );
        }
    }

    /**
     * Adds the bundle to the kernel file
     *
     * @return array
     */
    protected function updateKernel()
    {
        $this->output->writeln( '' );
        $this->output->write( 'Enabling the bundle inside the kernel... ' );

        try
        {
            $bundleFQN = $this->input->getOption( 'bundle-namespace' ) . '\\' . $this->input->getOption( 'bundle-name' );

            $reflected = new ReflectionObject( $this->getContainer()->get( 'kernel' ) );

            $fileContent = file_get_contents( $reflected->getFileName() );
            $fileContent = str_replace(
                '$bundles[] = new \Netgen\Bundle\MoreDemoBundle\NetgenMoreDemoBundle();',
                '$bundles[] = new \\' . $bundleFQN . '();',
                $fileContent
            );

            $updated = file_put_contents( $reflected->getFileName(), $fileContent );

            if ( !$updated )
            {
                return array(
                    '- Edit <comment>' . $reflected->getFilename() . '</comment>',
                    '  and add the following bundle at the end of <comment>' . $reflected->getName() . '::registerBundles()</comment>',
                    '  method, replacing the existing NetgenMoreDemoBundle:',
                    '',
                    '    <comment>$bundles[] = new \\' . $bundleFQN . '();</comment>',
                    '',
                );
            }
        }
        catch ( Exception $e )
        {
            return array(
                'There was an error enabling bundle inside the kernel: ' . $e->getMessage(),
                '',
            );
        }
    }

    /**
     * Updates the routing file
     *
     * @return array
     */
    protected function updateRouting()
    {
        $this->output->writeln( '' );
        $this->output->write( 'Importing the bundle routing resource... ' );

        $routing = new RoutingManipulator( $this->getContainer()->getParameter( 'kernel.root_dir' ) . '/config/routing.yml' );
        try
        {
            $bundleName = $this->input->getOption( 'bundle-name' );
            $updated = $routing->addResource( $bundleName );
            if ( !$updated )
            {
                return array(
                    '- Import the bundle\'s routing resource in the main routing file:',
                    '',
                    '    <comment>' . Container::underscore( substr( $bundleName, 0, -6 ) ) . ':</comment>',
                    '        <comment>resource: \"@' . $bundleName . '/Resources/config/routing.yml\"</comment>\n',
                    ''
                );
            }
        }
        catch ( Exception $e )
        {
            return array(
                'There was an error importing bundle routing resource: ' . $e->getMessage(),
                '',
            );
        }
    }

    /**
     * Installs Symfony assets as relative symlinks
     *
     * @return array
     */
    protected function installAssets()
    {
        $this->output->writeln( '' );
        $this->output->write( 'Installing assets using the <comment>symlink</comment> option... ' );

        try
        {
            $processBuilder = new ProcessBuilder(
                array(
                    'php',
                    'ezpublish/console',
                    'assets:install',
                    '--symlink',
                    '--relative',
                    '--quiet'
                )
            );

            $process = $processBuilder->getProcess();

            $process->setTimeout( 3600 );
            $process->run(
                function ( $type, $buffer )
                {
                    echo $buffer;
                }
            );

            if ( !$process->isSuccessful() )
            {
                return array(
                    '- Run the following command from your installation root to install assets:',
                    '',
                    '    <comment>php ezpublish/console assets:install --symlink --relative</comment>',
                    '',
                );
            }
        }
        catch ( Exception $e )
        {
            return array(
                'There was an error installing assets: ' . $e->getMessage(),
                '',
            );
        }
    }
}
