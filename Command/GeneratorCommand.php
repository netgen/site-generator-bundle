<?php

namespace Netgen\Bundle\MoreGeneratorBundle\Command;

use Symfony\Bundle\FrameworkBundle\Command\ContainerAwareCommand;
use Symfony\Component\Console\Output\OutputInterface;
use Netgen\Bundle\MoreGeneratorBundle\Command\Helper\DialogHelper;

abstract class GeneratorCommand extends ContainerAwareCommand
{
    /**
     * Writes generator summary
     *
     * @param \Symfony\Component\Console\Output\OutputInterface $output
     * @param array $errors
     */
    protected function writeGeneratorSummary( OutputInterface $output, $errors )
    {
        if ( !$errors )
        {
            $this->writeSection( $output, 'You can now start using the generated code!' );
        }
        else
        {
            $this->writeSection(
                $output,
                array(
                    'The command was not able to configure everything automatically.',
                    'You must do the following changes manually.',
                ),
                'error'
            );

            $output->writeln( $errors );
        }
    }

    /**
     * Writes a section of text to the output
     *
     * @param \Symfony\Component\Console\Output\OutputInterface $output
     * @param string $text
     * @param string $style
     */
    protected function writeSection( OutputInterface $output, $text, $style = 'bg=blue;fg=white' )
    {
        $output->writeln(
            array(
                '',
                $this->getHelper( 'formatter' )->formatBlock( $text, $style, true ),
                '',
            )
        );
    }

    /**
     * Returns the runner
     *
     * @param \Symfony\Component\Console\Output\OutputInterface $output
     * @param array $errors
     *
     * @return callable
     */
    protected function getRunner( OutputInterface $output, &$errors )
    {
        $runner = function ( $err ) use ( $output, &$errors )
        {
            if ( !empty( $err ) )
            {
                $output->writeln( '<fg=red>FAILED</>' );
                $errors = array_merge( $errors, $err );
            }
            else
            {
                $output->writeln( '<info>OK</info>' );
            }
        };

        return $runner;
    }

    /**
     * Returns the dialog helper
     *
     * @return \Netgen\Bundle\MoreGeneratorBundle\Command\Helper\DialogHelper
     */
    protected function getDialogHelper()
    {
        $dialog = $this->getHelperSet()->get( 'dialog' );
        if ( !$dialog || get_class( $dialog ) !== 'Netgen\Bundle\MoreGeneratorBundle\Command\Helper\DialogHelper' )
        {
            $this->getHelperSet()->set( $dialog = new DialogHelper() );
        }

        return $dialog;
    }
}
