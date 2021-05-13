<?php

declare(strict_types=1);

namespace Netgen\Bundle\SiteGeneratorBundle\Command;

use Symfony\Bundle\FrameworkBundle\Command\ContainerAwareCommand;
use Symfony\Component\Console\Helper\QuestionHelper;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Question\ConfirmationQuestion;
use Symfony\Component\Console\Question\Question;
use function sprintf;

abstract class GeneratorCommand extends ContainerAwareCommand
{
    protected InputInterface $input;

    protected OutputInterface $output;

    protected QuestionHelper $questionHelper;

    /**
     * Instantiates and returns a question.
     */
    protected function getQuestion(string $questionName, ?string $defaultValue = null, ?string $validator = null): Question
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
                $defaultValue ? 'yes' : 'no',
            ),
            $defaultValue,
        );
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
            ],
        );
    }
}
