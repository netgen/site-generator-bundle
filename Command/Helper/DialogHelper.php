<?php

namespace Netgen\Bundle\MoreGeneratorBundle\Command\Helper;

use Symfony\Component\Console\Helper\DialogHelper as BaseDialogHelper;
use Symfony\Component\Console\Output\OutputInterface;

class DialogHelper extends BaseDialogHelper
{
    /**
     * Writes generator summary
     *
     * @param \Symfony\Component\Console\Output\OutputInterface $output
     * @param array $errors
     */
    public function writeGeneratorSummary( OutputInterface $output, $errors )
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
     * Returns the runner
     *
     * @param \Symfony\Component\Console\Output\OutputInterface $output
     * @param array $errors
     *
     * @return callable
     */
    public function getRunner( OutputInterface $output, &$errors )
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
     * Writes a section of text to the output
     *
     * @param \Symfony\Component\Console\Output\OutputInterface $output
     * @param string $text
     * @param string $style
     */
    public function writeSection( OutputInterface $output, $text, $style = 'bg=blue;fg=white' )
    {
        $output->writeln(
            array(
                '',
                $this->getHelperSet()->get( 'formatter' )->formatBlock( $text, $style, true ),
                '',
            )
        );
    }

    /**
     * Returns a formatted question
     *
     * @param string $question
     * @param boolean $default
     * @param string $sep
     *
     * @return string
     */
    public function getQuestion( $question, $default, $sep = ':' )
    {
        return $default ?
            sprintf( '<info>%s</info> [<comment>%s</comment>]%s ', $question, $default, $sep ) :
            sprintf( '<info>%s</info>%s ', $question, $sep );
    }
}
