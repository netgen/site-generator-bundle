<?php

namespace Netgen\Bundle\MoreGeneratorBundle\Command;

use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Netgen\Bundle\MoreGeneratorBundle\Generator\ConfigurationGenerator;
use InvalidArgumentException;

class GenerateConfigurationCommand extends GeneratorCommand
{
    /**
     * Configures the command
     */
    protected function configure()
    {
        $this->setDefinition(
            array(
                new InputOption( 'project', '', InputOption::VALUE_REQUIRED, 'Project name' ),
                new InputOption( 'admin-site-access-name', '', InputOption::VALUE_REQUIRED, 'Admin siteaccess name' ),
                new InputOption( 'bundle-name', '', InputOption::VALUE_REQUIRED, 'Bundle name' )
            )
        );
        $this->setDescription( 'Generates Netgen More project configuration' );
        $this->setName( 'ngmore:generate:configuration' );
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
        $project = $input->getOption( 'project' );
        if ( empty( $project ) )
        {
            throw new InvalidArgumentException( 'The "--project" option must be defined' );
        }

        $adminSiteAccessName = $input->getOption( 'admin-site-access-name' );
        if ( empty( $adminSiteAccessName ) )
        {
            throw new InvalidArgumentException( 'The "--admin-site-access-name" option must be defined' );
        }

        $bundleName = $input->getOption( 'bundle-name' );
        if ( empty( $bundleName ) )
        {
            throw new InvalidArgumentException( 'The "--bundle-name" option must be defined' );
        }

        $configurationGenerator = new ConfigurationGenerator( $this->getContainer() );
        $configurationGenerator->generate(
            $input->getOption( 'project' ),
            $input->getOption( 'admin-site-access-name' ),
            $input->getOption( 'bundle-name' )
        );

        $output->writeln( 'Generated <comment>ezpublish.yml</comment> config file!' );
    }
}
