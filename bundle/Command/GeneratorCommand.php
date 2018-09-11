<?php

declare(strict_types=1);

namespace Netgen\Bundle\SiteGeneratorBundle\Command;

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
     * Asks a question that fills provided option.
     */
    protected function askForData(string $optionIdentifier, string $optionName, string $defaultValue, string $validator = null): string
    {
        $optionValue = $this->input->getOption($optionIdentifier);
        $optionValue = !empty($optionValue) ? $optionValue :
            $defaultValue;

        $question = $this->getQuestion($optionName, $optionValue, $validator);
        $optionValue = $this->questionHelper->ask(
            $this->input,
            $this->output,
            $question
        );

        $this->input->setOption($optionIdentifier, $optionValue);

        return $optionValue;
    }

    /**
     * Instantiates and returns a question.
     */
    protected function getQuestion(string $questionName, string $defaultValue = null, string $validator = null): Question
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
    protected function getConfirmationQuestion(string $questionName, bool $defaultValue = false): ConfirmationQuestion
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
     * Writes generator summary.
     *
     * @param array $errors
     */
    protected function writeGeneratorSummary(array $errors): void
    {
        if (empty($errors)) {
            $this->writeSection(['You can now start using the generated code!']);

            return;
        }

        $this->writeSection(
            [
                'The command was not able to configure everything automatically.',
                'You must do the following changes manually.',
            ],
            'error'
        );

        $this->output->writeln($errors);
    }

    /**
     * Writes a section of text to the output.
     */
    protected function writeSection(array $messages, string $style = 'bg=blue;fg=white'): void
    {
        $this->output->writeln(
            [
                '',
                $this->getHelper('formatter')->formatBlock($messages, $style, true),
                '',
            ]
        );
    }

    /**
     * Returns the runner.
     *
     * @param array $errors
     *
     * @return callable
     */
    protected function getRunner(array &$errors): callable
    {
        return function (array $err) use (&$errors) {
            if (!empty($err)) {
                $this->output->writeln('<fg=red>FAILED</>');
                $errors = array_merge($errors, $err);
            } else {
                $this->output->writeln('<info>OK</info>');
            }
        };
    }
}
