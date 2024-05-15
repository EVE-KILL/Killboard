<?php
namespace EK\Console;

use InvalidArgumentException;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Helper\ProgressBar;
use Symfony\Component\Console\Helper\Table;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Question\ChoiceQuestion;
use Symfony\Component\Console\Question\ConfirmationQuestion;
use Symfony\Component\Console\Question\Question;

/**
 * @method void handle() Implemented by the ConsoleCommand that extends this helper
 */
class ConsoleHelper extends Command
{
    /**
     * Name that the command is called with including the parameters
     */
    protected string $signature = '';

    /**
     * Description of the command
     */
    protected string $description = '';

    /**
     * Is the command hidden from the list view?
     */
    protected bool $hidden = false;

    protected InputInterface $input;
    protected OutputInterface $output;

    public function __construct(?string $name = null)
    {
        parent::__construct($name);
        $name = $this->parseArguments();
        $this->setDescription($this->description);
        $this->setName($name);
        $this->setHidden($this->hidden);
    }

    private function parseArguments(): string
    {
        $definition = $this->parse($this->signature);
        foreach ($definition[1] as $argument) {
            $this->getDefinition()->addArgument($argument);
        }
        foreach ($definition[2] as $option) {
            $this->getDefinition()->addOption($option);
        }

        return $definition[0];
    }

    /**
     * Parse the given console command definition into an array.
     *
     * @param string $expression
     *
     * @return array
     * @throws \InvalidArgumentException
     */
    private function parse(string $expression): array
    {
        $name = $this->findName($expression);
        if (preg_match_all('/\{\s*(.*?)\s*\}/', $expression, $matches)) {
            if (count($matches[1])) {
                return array_merge([$name], $this->parameters($matches[1]));
            }
        }

        return [
            $name,
            [],
            []
        ];
    }

    /**
     * Extract the name of the command from the expression.
     *
     * @param string $expression
     *
     * @return string
     * @throws \InvalidArgumentException
     */
    private function findName(string $expression): string
    {
        if (!preg_match('/\S+/', $expression, $matches)) {
            throw new InvalidArgumentException('Unable to determine command name from signature.');
        }

        return $matches[0];
    }

    /**
     * Extract all of the parameters from the tokens.
     *
     * @param array $tokens
     *
     * @return array
     */
    private function parameters(array $tokens): array
    {
        $arguments = [];
        $options = [];
        foreach ($tokens as $token) {
            if (preg_match('/-{2,}(.*)/', $token, $matches)) {
                $options[] = $this->parseOption($matches[1]);
            } else {
                $arguments[] = $this->parseArgument($token);
            }
        }

        return [$arguments, $options];
    }

    /**
     * Parse an option expression.
     *
     * @param string $token
     *
     * @return \Symfony\Component\Console\Input\InputOption
     */
    private function parseOption(string $token): InputOption
    {
        [$token, $description] = $this->extractDescription($token);
        $matches = (array) preg_split('/\s*\|\s*/', $token, 2);
        if (isset($matches[1])) {
            $shortcut = (string) $matches[0];
            $token = $matches[1];
        } else {
            $shortcut = null;
        }

        switch (true) {
            case $this->endsWith($token, '='):
                $inputOption = new InputOption(trim($token, '='), $shortcut, InputOption::VALUE_OPTIONAL, $description);
                break;
            case $this->endsWith($token, '=*'):
                $inputOption = new InputOption(
                    trim($token, '=*'),
                    $shortcut,
                    InputOption::VALUE_OPTIONAL | InputOption::VALUE_IS_ARRAY,
                    $description
                );
                break;
            case preg_match('/(.+)\=\*(.+)/', $token, $matches):
                $inputOption = new InputOption(
                    $matches[1],
                    $shortcut,
                    InputOption::VALUE_OPTIONAL | InputOption::VALUE_IS_ARRAY,
                    $description,
                    preg_split('/,\s?/', $matches[2])
                );
                break;
            case preg_match('/(.+)\=(.+)/', $token, $matches):
                $inputOption = new InputOption(
                    $matches[1],
                    $shortcut,
                    InputOption::VALUE_OPTIONAL,
                    $description,
                    $matches[2]
                );
                break;
            default:
                $inputOption = new InputOption($token, $shortcut, InputOption::VALUE_NONE, $description);
                break;
        }

        return $inputOption;
    }

    /**
     * Parse the token into its token and description segments.
     *
     * @param string $token
     *
     * @return array
     */
    private function extractDescription(string $token): array
    {
        $parts = (array) preg_split('/\s+:\s+/', trim($token), 2);

        return count($parts) === 2 ? $parts : [$token, ''];
    }

    /**
     * @param string $input
     * @param string $element
     *
     * @return bool
     */
    private function endsWith(string $input, string $element): bool
    {
        $length = strlen($element);
        if ($length === 0) {
            return true;
        }

        return (substr($input, -$length) === $element);
    }

    /**
     * Parse an argument expression.
     *
     * @param string $token
     *
     * @return \Symfony\Component\Console\Input\InputArgument
     */
    private function parseArgument(string $token): InputArgument
    {
        [$token, $description] = $this->extractDescription($token);

        switch (true) {
            case $this->endsWith($token, '?*'):
                $inputArgument = new InputArgument(trim($token, '?*'), InputArgument::IS_ARRAY, $description);
                break;
            case $this->endsWith($token, '*'):
                $inputArgument = new InputArgument(
                    trim($token, '*'),
                    InputArgument::IS_ARRAY | InputArgument::REQUIRED,
                    $description
                );
                break;
            case $this->endsWith($token, '?'):
                $inputArgument = new InputArgument(trim($token, '?'), InputArgument::OPTIONAL, $description);
                break;
            case preg_match('/(.+)\=\*(.+)/', $token, $matches):
                /** @var array<string> $pregSplit */
                $pregSplit = (array) preg_split('/,\s?/', $matches[2]);
                $inputArgument = new InputArgument(
                    $matches[1],
                    InputArgument::IS_ARRAY,
                    $description,
                    $pregSplit
                );
                break;
            case preg_match('/(.+)\=(.+)/', $token, $matches):
                $inputArgument = new InputArgument($matches[1], InputArgument::OPTIONAL, $description, $matches[2]);
                break;
            default:
                $inputArgument = new InputArgument($token, InputArgument::REQUIRED, $description);
                break;
        }

        return $inputArgument;
    }

    /**
     * @param string $name
     *
     * @return bool|array|string|null
     */
    public function get(string $name): null|bool|array|string
    {
        if ($this->input->hasArgument($name)) {
            return $this->input->getArgument($name);
        }
        if ($this->input->hasOption($name)) {
            return $this->input->getOption($name);
        }
        return null;
    }

    /**
     * @param string $name
     *
     * @return bool|array|string|null
     */
    public function __get(string $name): null|bool|array|string
    {
        return $this->get($name);
    }

    /**
     * @param string $input
     * @param bool   $newLine
     *
     * @return bool|array|string|null
     */
    public function out(string $input, $newLine = true): null|bool|array|string
    {
        return $this->output->write($input, $newLine);
    }

    /**
     * This one returns what the user inputs
     *
     * @param string $question
     * @param mixed $default
     *
     * @return mixed
     */
    public function ask(string $question, $default = ''): string
    {
        $helper = $this->getHelper('question');
        $q = new Question($question . ' ', $default);

        return $helper->ask($this->input, $this->output, $q);
    }

    /**
     * This one is usually asked for Y/n questions
     *
     * @param string $question
     * @param bool $default
     *
     * @return bool
     */
    public function askWithConfirmation(string $question, bool $default = true): bool
    {
        $helper = $this->getHelper('question');
        $q = new ConfirmationQuestion($question . ' ', $default);

        if (!$helper->ask($this->input, $this->output, $q)) {
            return false;
        }

        return true;
    }

    /**
     * Ask a question consisting of multiple answers
     *
     * @param string $question
     * @param array  $options
     * @param string $default
     * @param bool   $multipleChoice
     *
     * @return string
     */
    public function askWithOptions(string $question, array $options, string $default = '0', bool $multipleChoice = false): string
    {
        $helper = $this->getHelper('question');
        $q = new ChoiceQuestion($question . ' ', $options, $default);

        if ($multipleChoice) {
            $q->setMultiSelect(true);
        }

        return $helper->ask($this->input, $this->output, $q);
    }

    /**
     * Render a table with a single row of information
     *
     * @param array $tableData
     */
    public function tableOneRow(array $tableData): void
    {
        try {
            $table = new Table($this->output);
            $rows = array_map(static function ($a, $b) {
                return ["<info>{$a}</info>", $b];
            }, array_keys($tableData), $tableData);
            $table->addRows($rows);
            $table->render();
        } catch (\Exception $e) {
            throw new \RuntimeException($e->getMessage());
        }
    }

    /**
     * Render a table of information
     *
     * @param array $headers
     * @param array $rows
     */
    public function table(array $headers, array $rows): void
    {
        try {
            $table = new Table($this->output);
            $table->setHeaders($headers);
            if (!empty($rows) && !is_array($rows[0])) {
                $table->addRow($rows);
            } else {
                $table->addRows($rows);
            }
            $table->render();
        } catch (\Exception $e) {
            throw new \RuntimeException($e->getMessage());
        }
    }

    /**
     * @param int $count
     *
     * @return \Symfony\Component\Console\Helper\ProgressBar
     */
    public function progressBar(int $count): ProgressBar
    {
        return new ProgressBar($this->output, $count);
    }

    /**
     * @param \Symfony\Component\Console\Input\InputInterface   $input
     * @param \Symfony\Component\Console\Output\OutputInterface $output
     *
     * @return int
     */
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $this->input = $input;
        $this->output = $output;
        $this->handle();
        exit(0);
    }
}
