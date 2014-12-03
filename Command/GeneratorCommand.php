<?php

namespace Netgen\Bundle\MoreGeneratorBundle\Command;

use Symfony\Bundle\FrameworkBundle\Command\ContainerAwareCommand;
use Symfony\Component\Console\Question\ConfirmationQuestion;
use Symfony\Component\Console\Question\Question;

abstract class GeneratorCommand extends ContainerAwareCommand
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
     * @var \Symfony\Component\Console\Helper\QuestionHelper
     */
    protected $questionHelper;

    /**
     * Asks a question that fills provided option
     *
     * @param string $optionIdentifier
     * @param string $optionName
     * @param string $defaultValue
     * @param string $validator
     *
     * @return string
     */
    protected function askForData( $optionIdentifier, $optionName, $defaultValue, $validator = null )
    {
        $optionValue = $this->input->getOption( $optionIdentifier );
        $optionValue = !empty( $optionValue ) ? $optionValue :
            $defaultValue;

        $question = $this->getQuestion( $optionName, $optionValue, $validator );
        $optionValue = $this->questionHelper->ask(
            $this->input,
            $this->output,
            $question
        );

        $this->input->setOption( $optionIdentifier, $optionValue );

        return $optionValue;
    }

    /**
     * Instantiates and returns a question
     *
     * @param string $questionName
     * @param bool $defaultValue
     * @param string $validator
     *
     * @return \Symfony\Component\Console\Question\Question
     */
    protected function getQuestion( $questionName, $defaultValue = null, $validator = null )
    {
        $questionName = $defaultValue
            ? '<info>' . $questionName . '</info> [<comment>' . $defaultValue . '</comment>]: '
            : '<info>' . $questionName . '</info>: ';

        $question = new Question( $questionName, $defaultValue );
        if ( $validator !== null )
        {
            $question->setValidator( array( 'Netgen\Bundle\MoreGeneratorBundle\Command\Validators', $validator ) );
        }

        return $question;
    }

    /**
     * Instantiates and returns the confirmation question
     *
     * @param string $questionName
     * @param bool $defaultValue
     *
     * @return \Symfony\Component\Console\Question\ConfirmationQuestion
     */
    protected function getConfirmationQuestion( $questionName, $defaultValue = false )
    {
        return new ConfirmationQuestion(
            sprintf(
                '<info>%s</info> [<comment>%s</comment>]? ',
                $questionName,
                $defaultValue ? 'yes' : 'no'
            ),
            $defaultValue
        );
    }

    /**
     * Writes generator summary
     *
     * @param array $errors
     */
    protected function writeGeneratorSummary( $errors )
    {
        if ( !$errors )
        {
            $this->writeSection( 'You can now start using the generated code!' );
        }
        else
        {
            $this->writeSection(
                array(
                    'The command was not able to configure everything automatically.',
                    'You must do the following changes manually.',
                ),
                'error'
            );

            $this->output->writeln( $errors );
        }
    }

    /**
     * Writes a section of text to the output
     *
     * @param string $text
     * @param string $style
     */
    protected function writeSection( $text, $style = 'bg=blue;fg=white' )
    {
        $this->output->writeln(
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
     * @param array $errors
     *
     * @return callable
     */
    protected function getRunner( &$errors )
    {
        $output = $this->output;
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
}
