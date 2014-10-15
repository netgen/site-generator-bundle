<?php

namespace Netgen\Bundle\MoreGeneratorBundle\Command;

use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\HttpKernel\KernelInterface;
use Symfony\Component\DependencyInjection\Container;
use Symfony\Component\Process\ProcessBuilder;
use Netgen\Bundle\MoreGeneratorBundle\Generator\ProjectGenerator;
use Netgen\Bundle\MoreGeneratorBundle\Generator\SiteAccessGenerator;
use Netgen\Bundle\MoreGeneratorBundle\Manipulator\KernelManipulator;
use Netgen\Bundle\MoreGeneratorBundle\Manipulator\RoutingManipulator;
use InvalidArgumentException;
use ReflectionObject;
use RuntimeException;

class GenerateProjectCommand extends GeneratorCommand
{

    /**
     * @var \Symfony\Component\Console\Input\InputInterface
     */
    protected $input;

    /**
     * @var \Symfony\Component\Console\Output\OutputInterface
     */
    protected $output;

    /**
     * @var \Netgen\Bundle\MoreGeneratorBundle\Command\Helper\DialogHelper
     */
    protected $dialog;

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
                new InputOption( 'site-domain', '', InputOption::VALUE_REQUIRED, 'Site domain' ),
                new InputOption( 'admin-site-access-name', '', InputOption::VALUE_REQUIRED, 'Admin siteaccess name' ),
                new InputOption( 'site-access-list-string', '', InputOption::VALUE_OPTIONAL, 'String definition of siteaccess list' ),
                new InputOption( 'site-access-list', '', InputOption::VALUE_IS_ARRAY | InputOption::VALUE_OPTIONAL, 'Siteaccess list' ),
                new InputOption( 'database-host', '', InputOption::VALUE_REQUIRED, 'Database host' ),
                new InputOption( 'database-port', '', InputOption::VALUE_OPTIONAL, 'Database port' ),
                new InputOption( 'database-user', '', InputOption::VALUE_REQUIRED, 'Database user' ),
                new InputOption( 'database-password', '', InputOption::VALUE_OPTIONAL, 'Database password' ),
                new InputOption( 'database-name', '', InputOption::VALUE_REQUIRED, 'Database name' ),
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
        $this->dialog = $this->getDialogHelper();

        $this->dialog->writeSection( $this->output, 'Welcome to the Netgen More project generator' );

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
                'Input the site name, site domain and admin siteaccess name. Site name will be visible',
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

        $this->askForData(
            'site-domain',
            'Site domain',
            str_replace( '_', '-', $projectNormalized ) . '.' .
                trim( $this->getContainer()->getParameter( 'netgen_more.generator.defaults.domain_suffix' ), '.' ),
            'validateNotEmpty'
        );

        $adminSiteAccess = $this->askForData(
            'admin-site-access-name',
            'Admin siteaccess name',
            $this->getContainer()->getParameter( 'netgen_more.generator.defaults.admin_siteaccess_name' ),
            'validateAdminSiteAccessName'
        );

        $siteAccessList = array();

        // Try to parse the following format
        // eng:eng-EU|cro:cro-HR:eng-EU
        $siteAccessListString = $this->input->getOption( 'site-access-list-string' );
        if ( !empty( $siteAccessListString ) )
        {
            $siteAccessListStringArray = explode( '|', $siteAccessListString );
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

                        Validators::validateSiteAccessName( $siteAccessOrLanguage );
                        continue;
                    }

                    Validators::validateLanguageCode( $siteAccessOrLanguage );
                    $siteAccessList[$explodedSiteAccessItem[0]][] = $siteAccessOrLanguage;
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
                $siteAccess = $this->dialog->askAndValidate(
                    $this->output,
                    $this->dialog->getQuestion( 'Siteaccess name (use empty value to finish)', '' ),
                    array( 'Netgen\Bundle\MoreGeneratorBundle\Command\Validators', 'validateSiteAccessName' ),
                    false
                );

                if ( $siteAccess === $adminSiteAccess )
                {
                    $this->output->writeln( '<error> Siteaccess name cannot be equal to "' . $adminSiteAccess . '". </error>' );
                    continue;
                }

                if ( !empty( $siteAccess ) )
                {
                    $siteAccessList[$siteAccess] = array();

                    $languageList = array();
                    do
                    {
                        $language = $this->dialog->askAndValidate(
                            $this->output,
                            $this->dialog->getQuestion( 'Language code for <comment>' . $siteAccess . '</comment> siteaccess (use empty value to finish)', '' ),
                            array( 'Netgen\Bundle\MoreGeneratorBundle\Command\Validators', 'validateLanguageCode' ),
                            false
                        );

                        if ( !empty( $language ) )
                        {
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
                'Input the database connection details.',
                ''
            )
        );

        $this->askForData( 'database-host', 'Database host', $this->getContainer()->getParameter( 'netgen_more.generator.defaults.database_host' ), 'validateNotEmpty' );
        $this->askForData( 'database-port', 'Database port', '' );
        $this->askForData( 'database-user', 'Database user', $this->getContainer()->getParameter( 'netgen_more.generator.defaults.database_user' ), 'validateNotEmpty' );
        $this->askForData( 'database-password', 'Database password', '' );
        $this->askForData( 'database-name', 'Database name', '', 'validateNotEmpty' );

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

        $this->dialog->writeSection( $this->output, 'Summary before generation' );

        // Summary
        $this->output->writeln(
            array(
                'You are going to generate a <info>' . $bundleNamespace . '\\' . $bundleName . '</info> bundle',
                'and <info>' . $extensionName . '</info> legacy extension using the <info>' . $designName . '</info> legacy design.',
                ''
            )
        );

        if ( !$this->dialog->askConfirmation( $this->output, $this->dialog->getQuestion( 'Do you confirm project generation (answering <comment>no</comment> will restart the process)', 'yes', '?' ), true ) )
        {
            $this->output->writeln( '' );
            return false;
        }

        return true;
    }

    protected function askForData( $optionIdentifier, $optionName, $defaultValue, $validator = null )
    {
        $optionValue = $this->input->getOption( $optionIdentifier );
        $optionValue = !empty( $optionValue ) ? $optionValue :
            $defaultValue;

        if ( $validator !== null )
        {
            $optionValue = $this->dialog->askAndValidate(
                $this->output,
                $this->dialog->getQuestion( $optionName, $optionValue ),
                array( 'Netgen\Bundle\MoreGeneratorBundle\Command\Validators', $validator ),
                false,
                $optionValue
            );
        }
        else
        {
            $optionValue = $this->dialog->ask(
                $this->output,
                $this->dialog->getQuestion( $optionName, $optionValue ),
                $optionValue
            );
        }

        $this->input->setOption( $optionIdentifier, $optionValue );

        return $optionValue;
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

        $this->dialog->writeSection( $this->output, 'Project generation' );

        // Generate a project
        $projectGenerator = new ProjectGenerator( $this->getContainer() );
        $projectGenerator->generate( $this->input, $this->output );

        // Generate siteaccesses
        $siteAccessGenerator = new SiteAccessGenerator( $this->getContainer() );
        $siteAccessGenerator->generate( $this->input, $this->output );

        $errors = array();
        $runner = $this->dialog->getRunner( $this->output, $errors );

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

        // Generate eZ 5 configuration
        $runner( $this->generateYamlConfiguration() );

        // Import MySQL database
        $runner( $this->importDatabase() );

        $this->dialog->writeGeneratorSummary( $this->output, $errors );

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
        $this->output->write( 'Installing ngmore project symlinks... ' );

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
                    '- Run the following command from your installation root to install ngmore project symlinks:',
                    '',
                    '    <comment>php ezpublish/console ngmore:symlink:project</comment>',
                    '',
                );
            }
        }
        catch ( RuntimeException $e )
        {
            return array(
                'There was an error running the command: ' . $e->getMessage(),
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
        $this->output->write( 'Installing ngmore legacy symlinks... ' );

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
                    '- Run the following command from your installation root to install ngmore legacy symlinks:',
                    '',
                    '    <comment>php ezpublish/console ngmore:symlink:legacy</comment>',
                    '',
                );
            }
        }
        catch ( RuntimeException $e )
        {
            return array(
                'There was an error running the command: ' . $e->getMessage(),
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
        catch ( RuntimeException $e )
        {
            chdir( $currentWorkingDirectory );

            return array(
                'There was an error running the command: ' . $e->getMessage(),
                '',
            );
        }
    }

    /**
     * Generates eZ 5 configuration
     *
     *
     * @return array
     */
    protected function generateYamlConfiguration()
    {
        $this->output->writeln( '' );
        $this->output->write( 'Generating Yaml configuration from legacy... ' );

        try
        {
            $project = $this->input->getOption( 'project' );
            $adminSiteAccess = $this->input->getOption( 'admin-site-access-name' );
            $bundleName = $this->input->getOption( 'bundle-name' );

            $processBuilder = new ProcessBuilder(
                array(
                    'php',
                    'ezpublish/console',
                    'ngmore:generate:configuration',
                    '--project=' . $project,
                    '--admin-site-access-name=' . $adminSiteAccess,
                    '--bundle-name=' . $bundleName,
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
                    '- Run the following command from your installation root to generate Yaml configuration from legacy:',
                    '',
                    '    <comment>php ezpublish/console ngmore:generate:configuration --project=' . $project . ' --admin-site-access-name=' . $adminSiteAccess . ' --bundle-name=' . $bundleName . '</comment>',
                    '',
                );
            }
        }
        catch ( RuntimeException $e )
        {
            return array(
                'There was an error running the command: ' . $e->getMessage(),
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
        $databasePath = $this->getContainer()->getParameter( 'kernel.root_dir' ) . '/../ezpublish_legacy/extension/' .
                        $this->input->getOption( 'extension-name' ) . '/data/dump.sql';

        $databaseHost = $this->input->getOption( 'database-host' );
        $databasePort = $this->input->getOption( 'database-port' );
        $databaseUser = $this->input->getOption( 'database-user' );
        $databasePassword = $this->input->getOption( 'database-password' );
        $databaseName = $this->input->getOption( 'database-name' );

        $errorOutput = array(
            '- Run the following command from your installation root to import the database:',
            '',
            '    <comment>mysql -u ' . $databaseUser . ' -h ' . $databaseHost . ' -p ' . $databaseName . ' < ' . $databasePath . '</comment>',
            '',
        );

        $this->output->writeln( '' );
        $doImport = $this->dialog->askConfirmation( $this->output, $this->dialog->getQuestion( 'Do you want to import Netgen More database (this will destroy all existing data in the selected database)', 'no', '?' ), false );

        $this->output->writeln( '' );
        $this->output->write( 'Importing MySQL database... ' );

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
            $process->setStdin( file_get_contents( $databasePath ) );

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
        catch ( RuntimeException $e )
        {
            return array(
                'There was an error running the command: ' . $e->getMessage(),
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
        $this->output->write( 'Enabling the bundle inside the kernel: ' );

        $kernel = $this->getContainer()->get( 'kernel' );

        $manipulator = new KernelManipulator( $kernel );
        try
        {
            $bundleFQN = $this->input->getOption( 'bundle-namespace' ) . '\\' . $this->input->getOption( 'bundle-name' );
            $updated = $manipulator->addBundle( $bundleFQN );

            if ( !$updated )
            {
                $reflected = new ReflectionObject( $kernel );

                return array(
                    '- Edit <comment>' . $reflected->getFilename() . '</comment>',
                    '  and add the following bundle at the end of <comment>' . $reflected->getName() . '::registerBundles()</comment>',
                    '  method, just before <comment>return $bundles;</comment> line:',
                    '',
                    '    <comment>$bundles[] = new \\' . $bundleFQN . '();</comment>',
                    '',
                );
            }
        }
        catch ( RuntimeException $e )
        {
            return array(
                'There was an error activating bundle inside the kernel: ' . $e->getMessage(),
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
        $this->output->write( 'Importing the bundle routing resource: ' );

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
        catch ( RuntimeException $e )
        {
            return array(
                'There was an error importing bundle routes: ' . $e->getMessage(),
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
        catch ( RuntimeException $e )
        {
            return array(
                'There was an error running the command: ' . $e->getMessage(),
                '',
            );
        }
    }
}
