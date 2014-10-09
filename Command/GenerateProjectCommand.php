<?php

namespace Netgen\Bundle\GeneratorBundle\Command;

use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\HttpKernel\KernelInterface;
use Symfony\Component\DependencyInjection\Container;
use Symfony\Component\Process\ProcessBuilder;
use Netgen\Bundle\GeneratorBundle\Generator\ProjectGenerator;
use Netgen\Bundle\GeneratorBundle\Manipulator\KernelManipulator;
use Netgen\Bundle\GeneratorBundle\Manipulator\RoutingManipulator;
use Netgen\Bundle\GeneratorBundle\Command\Helper\DialogHelper;
use ReflectionObject;
use RuntimeException;

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
                new InputOption( 'site-domain', '', InputOption::VALUE_REQUIRED, 'Site domain' ),
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
        $dialog = $this->getDialogHelper();
        $dialog->writeSection( $output, 'Welcome to the Netgen More project generator' );

        $output->writeln(
            array(
                'Input the client and project names. These values will be used to generate',
                'bundle name, as well as legacy extension name and legacy design name.',
                'First letter of the names must be uppercased, and it is recommended',
                'to use <comment>CamelCasing</comment> for the rest of the names.',
                ''
            )
        );

        $client = ucfirst(
            $dialog->askAndValidate(
                $output,
                $dialog->getQuestion( 'Client name', $input->getOption( 'client' ) ),
                array( 'Netgen\Bundle\GeneratorBundle\Command\Validators', 'validateCamelCaseName' ),
                false,
                $input->getOption( 'client' )
            )
        );

        $input->setOption( 'client', $client );
        $clientNormalized = Container::underscore( $client );

        $project = ucfirst(
            $dialog->askAndValidate(
                $output,
                $dialog->getQuestion( 'Project name', $input->getOption( 'project' ) ),
                array( 'Netgen\Bundle\GeneratorBundle\Command\Validators', 'validateCamelCaseName' ),
                false,
                $input->getOption( 'project' )
            )
        );

        $input->setOption( 'project', $project );
        $projectNormalized = Container::underscore( $project );

        $output->writeln(
            array(
                '',
                'Input the site name and site domain. Site name will be visible as the title',
                'of the pages in eZ Publish, so you are free to input what ever you like here.',
                ''
            )
        );

        $siteName = $dialog->askAndValidate(
            $output,
            $dialog->getQuestion( 'Site name', $input->getOption( 'site-name' ) ),
            array( 'Netgen\Bundle\GeneratorBundle\Command\Validators', 'validateNotEmpty' ),
            false,
            $input->getOption( 'site-name' )
        );

        $input->setOption( 'site-name', $siteName );

        $siteDomain = $dialog->askAndValidate(
            $output,
            $dialog->getQuestion( 'Site domain', $input->getOption( 'site-domain' ) ),
            array( 'Netgen\Bundle\GeneratorBundle\Command\Validators', 'validateNotEmpty' ),
            false,
            $input->getOption( 'site-domain' )
        );

        $input->setOption( 'site-domain', $siteDomain );

        $output->writeln(
            array(
                '',
                'Input the name of every siteaccess you wish to create.',
                'The first siteaccess you specify will become the default siteaccess.',
                '<comment>administration</comment> siteaccess will be generated automatically.',
                'The names must contain lowercase letters, underscores or numbers.',
                ''
            )
        );

        $siteAccessList = array();

        // Try to parse the following format
        // eng:eng-EU|cro:cro-HR:eng-EU
        $siteAccessListString = $input->getOption( 'site-access-list-string' );
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
            do
            {
                $siteAccess = $dialog->askAndValidate(
                    $output,
                    $dialog->getQuestion( 'Siteaccess name (use empty value to finish)', '' ),
                    array( 'Netgen\Bundle\GeneratorBundle\Command\Validators', 'validateSiteAccessName' ),
                    false
                );

                if ( !empty( $siteAccess ) )
                {
                    $siteAccessList[$siteAccess] = array();

                    $languageList = array();
                    do
                    {
                        $language = $dialog->askAndValidate(
                            $output,
                            $dialog->getQuestion( 'Language code for <comment>' . $siteAccess . '</comment> siteaccess (use empty value to finish)', '' ),
                            array( 'Netgen\Bundle\GeneratorBundle\Command\Validators', 'validateLanguageCode' ),
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

        $input->setOption( 'site-access-list', $siteAccessList );

        $output->writeln(
            array(
                '',
                'Input the database connection details.',
                ''
            )
        );

        $databaseHost = $dialog->askAndValidate(
            $output,
            $dialog->getQuestion( 'Database host', $input->getOption( 'database-host' ) ),
            array( 'Netgen\Bundle\GeneratorBundle\Command\Validators', 'validateNotEmpty' ),
            false,
            $input->getOption( 'database-host' )
        );

        $input->setOption( 'database-host', $databaseHost );

        $databasePort = $dialog->ask(
            $output,
            $dialog->getQuestion( 'Database port', $input->getOption( 'database-port' ) ),
            $input->getOption( 'database-port' )
        );

        $input->setOption( 'database-port', $databasePort );

        $databaseUser = $dialog->askAndValidate(
            $output,
            $dialog->getQuestion( 'Database user', $input->getOption( 'database-user' ) ),
            array( 'Netgen\Bundle\GeneratorBundle\Command\Validators', 'validateNotEmpty' ),
            false,
            $input->getOption( 'database-user' )
        );

        $input->setOption( 'database-user', $databaseUser );

        $databasePassword = $dialog->askHiddenResponse(
            $output,
            $dialog->getQuestion( 'Database password', $input->getOption( 'database-password' ) ),
            $input->getOption( 'database-password' )
        );

        $input->setOption( 'database-password', $databasePassword );

        $databaseName = $dialog->askAndValidate(
            $output,
            $dialog->getQuestion( 'Database name', $input->getOption( 'database-name' ) ),
            array( 'Netgen\Bundle\GeneratorBundle\Command\Validators', 'validateNotEmpty' ),
            false,
            $input->getOption( 'database-name' )
        );

        $input->setOption( 'database-name', $databaseName );

        $extensionName = "ez_" . $clientNormalized . "_" . $projectNormalized;
        $extensionName = $dialog->askAndValidate(
            $output,
            $dialog->getQuestion( 'Extension name', $extensionName ),
            array( 'Netgen\Bundle\GeneratorBundle\Command\Validators', 'validateLowerCaseName' ),
            false,
            $extensionName
        );

        $input->setOption( 'extension-name', $extensionName );

        $designName = $projectNormalized;
        $designName = $dialog->askAndValidate(
            $output,
            $dialog->getQuestion( 'Design name', $designName ),
            array( 'Netgen\Bundle\GeneratorBundle\Command\Validators', 'validateLowerCaseName' ),
            false,
            $designName
        );

        $input->setOption( 'design-name', $designName );

        $bundleNamespace = $client . "\\Bundle\\" . $project . "Bundle";
        $bundleNamespace = $dialog->askAndValidate(
            $output,
            $dialog->getQuestion( 'Bundle namespace', $bundleNamespace ),
            array( 'Netgen\Bundle\GeneratorBundle\Command\Validators', 'validateBundleNamespace' ),
            false,
            $bundleNamespace
        );

        $input->setOption( 'bundle-namespace', $bundleNamespace );

        $bundleName = $client . $project . "Bundle";
        $bundleName = $dialog->askAndValidate(
            $output,
            $dialog->getQuestion( 'Bundle name', $bundleName ),
            array( 'Netgen\Bundle\GeneratorBundle\Command\Validators', 'validateBundleName' ),
            false,
            $bundleName
        );

        $input->setOption( 'bundle-name', $bundleName );

        $dialog->writeSection( $output, 'Summary before generation' );

        // Summary
        $output->writeln(
            array(
                sprintf( "You are going to generate a \"<info>%s\\%s</info>\" bundle\nand \"<info>%s</info>\" legacy extension using the \"<info>%s</info>\" legacy design.", $bundleNamespace, $bundleName, $extensionName, $designName ),
                ''
            )
        );
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

        $dialog = $this->getDialogHelper();
        if ( !$dialog->askConfirmation( $output, $dialog->getQuestion( 'Do you confirm project generation', 'yes', '?' ), true ) )
        {
            $output->writeln( '<error>Command aborted</error>' );
            return 1;
        }

        $dialog->writeSection( $output, 'Project generation' );

        /** @var \Netgen\Bundle\GeneratorBundle\Generator\ProjectGenerator $generator */
        $generator = $this->getGenerator();
        $generator->generate( $input, $output );

        $errors = array();
        $runner = $dialog->getRunner( $output, $errors );

        // Install Netgen More legacy symlinks
        $runner(
            $this->installLegacySymlinks(
                $dialog,
                $input,
                $output
            )
        );

        // Generate legacy autoloads
        $runner(
            $this->generateLegacyAutoloads(
                $dialog,
                $input,
                $output
            )
        );

        // Register the bundle in the EzPublishKernel class
        $runner(
            $this->updateKernel(
                $dialog,
                $input,
                $output,
                $this->getContainer()->get( 'kernel' ),
                $input->getOption( 'bundle-namespace' ),
                $input->getOption( 'bundle-name' )
            )
        );

        // Install Symfony assets as relative symlinks
        $runner(
            $this->installAssets(
                $dialog,
                $input,
                $output,
                empty( $errors )
            )
        );

        // Set up routing
        $runner(
            $this->updateRouting(
                $dialog,
                $input,
                $output,
                $input->getOption( 'bundle-name' ),
                'yml'
            )
        );

        $dialog->writeGeneratorSummary( $output, $errors );

        return 0;
    }

    /**
     * Adds the bundle to the kernel file
     *
     * @param \Netgen\Bundle\GeneratorBundle\Command\Helper\DialogHelper $dialog
     * @param \Symfony\Component\Console\Input\InputInterface $input
     * @param \Symfony\Component\Console\Output\OutputInterface $output
     * @param \Symfony\Component\HttpKernel\KernelInterface $kernel
     * @param string $namespace
     * @param string $bundle
     *
     * @return array
     */
    protected function updateKernel( DialogHelper $dialog, InputInterface $input, OutputInterface $output, KernelInterface $kernel, $namespace, $bundle )
    {
        $output->writeln( '' );
        $autoUpdate = $dialog->askConfirmation( $output, $dialog->getQuestion( 'Confirm automatic update of your Kernel', 'no', '?' ), false );

        $output->write( 'Enabling the bundle inside the kernel: ' );
        $manipulator = new KernelManipulator( $kernel );
        try
        {
            $updated = $autoUpdate ? $manipulator->addBundle( $namespace.'\\' . $bundle ) : false;

            if ( !$updated )
            {
                $reflected = new ReflectionObject( $kernel );

                return array(
                    sprintf( '- Edit <comment>%s</comment>', $reflected->getFilename() ),
                    '  and add the following bundle at the end of <comment>' . $reflected->getName() . '::registerBundles()</comment>',
                    '  method, just before <comment>return $bundles;</comment> line:',
                    '',
                    sprintf( '    <comment>$bundles[] = new \\%s();</comment>', $namespace . '\\' . $bundle ),
                    '',
                );
            }
        }
        catch ( RuntimeException $e )
        {
            return array(
                sprintf( 'Bundle <comment>%s</comment> is already defined in <comment>EzPublishKernel::registerBundles()</comment>.', $namespace . '\\' . $bundle ),
                '',
            );
        }
    }

    /**
     * Installs Symfony assets as relative symlinks
     *
     * @param \Netgen\Bundle\GeneratorBundle\Command\Helper\DialogHelper $dialog
     * @param \Symfony\Component\Console\Input\InputInterface $input
     * @param \Symfony\Component\Console\Output\OutputInterface $output
     * @param bool $kernelUpdated
     *
     * @return array
     */
    protected function installAssets( DialogHelper $dialog, InputInterface $input, OutputInterface $output, $kernelUpdated )
    {
        $output->writeln( '' );
        $output->write( 'Installing assets using the <comment>symlink</comment> option... ' );

        try
        {
            $returnMessage = array(
                '- Run the following command from your installation root to install assets:',
                '',
                '    <comment>php ezpublish/console assets:install --symlink --relative</comment>',
                '',
            );

            if ( !$kernelUpdated )
            {
                return $returnMessage;
            }

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
                return $returnMessage;
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
     * @param \Netgen\Bundle\GeneratorBundle\Command\Helper\DialogHelper $dialog
     * @param \Symfony\Component\Console\Input\InputInterface $input
     * @param \Symfony\Component\Console\Output\OutputInterface $output
     *
     * @return array
     */
    protected function installLegacySymlinks( DialogHelper $dialog, InputInterface $input, OutputInterface $output )
    {
        $output->writeln( '' );
        $output->write( 'Installing ngmore legacy symlinks... ' );

        try
        {
            $processBuilder = new ProcessBuilder(
                array(
                    'php',
                    'ezpublish/console',
                    'ngmore:legacy:symlink',
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
                    '    <comment>php ezpublish/console ngmore:legacy:symlink</comment>',
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
     * Updates the routing file
     *
     * @param \Netgen\Bundle\GeneratorBundle\Command\Helper\DialogHelper $dialog
     * @param \Symfony\Component\Console\Input\InputInterface $input
     * @param \Symfony\Component\Console\Output\OutputInterface $output
     * @param string $bundle
     * @param string $format
     *
     * @return array
     */
    protected function updateRouting( DialogHelper $dialog, InputInterface $input, OutputInterface $output, $bundle, $format )
    {
        $output->writeln( '' );
        $autoUpdate = $dialog->askConfirmation( $output, $dialog->getQuestion( 'Confirm automatic update of the Routing', 'no', '?' ), false );

        $output->write( 'Importing the bundle routing resource: ' );
        $routing = new RoutingManipulator( $this->getContainer()->getParameter( 'kernel.root_dir' ) . '/config/routing.yml' );
        try
        {
            $updated = $autoUpdate ? $routing->addResource( $bundle, $format ) : false;
            if ( !$updated )
            {
                if ( $format === 'annotation' )
                {
                    $help = sprintf( "        <comment>resource: \"@%s/Controller/\"</comment>\n        <comment>type: annotation</comment>\n", $bundle );
                }
                else
                {
                    $help = sprintf( "        <comment>resource: \"@%s/Resources/config/routing.%s\"</comment>\n", $bundle, $format );
                }

                return array(
                    '- Import the bundle\'s routing resource in the main routing file:',
                    '',
                    sprintf( '    <comment>%s:</comment>', Container::underscore( substr( $bundle, 0, -6 ) ) ),
                    $help,
                    ''
                );
            }
        }
        catch ( RuntimeException $e )
        {
            return array(
                sprintf( 'Bundle <comment>%s</comment> is already imported.', $bundle ),
                '',
            );
        }
    }

    /**
     * Generates legacy autoloads
     *
     * @param \Netgen\Bundle\GeneratorBundle\Command\Helper\DialogHelper $dialog
     * @param \Symfony\Component\Console\Input\InputInterface $input
     * @param \Symfony\Component\Console\Output\OutputInterface $output
     *
     * @return array
     */
    protected function generateLegacyAutoloads( DialogHelper $dialog, InputInterface $input, OutputInterface $output )
    {
        $output->writeln( '' );
        $output->write( 'Generating legacy autoloads... ' );

        try
        {
            $processBuilder = new ProcessBuilder(
                array(
                    'php',
                    'ezpublish/console',
                    'ezpublish:legacy:script',
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

            if ( !$process->isSuccessful() )
            {
                return array(
                    '- Run the following command from your installation root to generate legacy autoloads:',
                    '',
                    '    <comment>php ezpublish/console ezpublish:legacy:script bin/php/ezpgenerateautoloads.php</comment>',
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
     * Creates the generator
     *
     * @return \Netgen\Bundle\GeneratorBundle\Generator\ProjectGenerator
     */
    protected function createGenerator()
    {
        return new ProjectGenerator( $this->getContainer() );
    }
}
