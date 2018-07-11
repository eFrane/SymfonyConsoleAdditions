<?php
/**
 * @copyright 2018
 * @author Stefan "eFrane" Graupner <efrane@meanderingsoul.com>
 * @license MIT
 */

namespace EFrane\ConsoleAdditions\Command;


use EFrane\ConsoleAdditions\Exception\BatchException;
use Symfony\Component\Console\Application;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\StringInput;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * Batch
 *
 * This class offers batching commands of a Symfony Console Application. This can be
 * useful when writing things like deployment or update scripts as console commands
 * which call many other commands in a set order e.g. cache updating, database
 * migrations, etc.
 *
 * Usage in a `Command::execute`:
 *
 * <code>
 * Batch::create($this->getApplication(), $output)
 *     ->add('my:command --with-option')
 *     ->add('my:other:command for-this-input')
 *     ->run();
 * </code>
 *
 * Exceptions occurring in commands are cascaded upwards. It is also possible to
 * just use this as a command string parser by creating an instance and calling
 * `$command = $batch->createCommandFromString('my:command --with -a --weird signature', $input);`
 *
 * @package EFrane\ConsoleAdditions\Command
 */
class Batch
{
    /**
     * @var OutputInterface
     */
    protected $output = null;

    /**
     * @var array
     */
    protected $commands = [];

    /**
     * @var Application
     */
    protected $application = null;

    public function __construct(Application $application, OutputInterface $output)
    {
        $this->setOutput($output);
        $this->application = $application;
    }

    public static function create(Application $application, OutputInterface $output)
    {
        return new self($application, $output);
    }

    /**
     * @return OutputInterface
     */
    public function getOutput()
    {
        return $this->output;
    }

    /**
     * @param OutputInterface $output
     */
    public function setOutput(OutputInterface $output)
    {
        $this->output = $output;
    }

    /**
     * @return array
     */
    public function getCommands()
    {
        return $this->commands;
    }

    /**
     * @param array $commands
     */
    public function setCommands(array $commands)
    {
        $this->commands = $commands;
    }

    /**
     * @param string $commandWithSignature
     * @return $this;
     */
    public function add($commandWithSignature)
    {
        if (!is_string($commandWithSignature)) {
            throw BatchException::signatureExpected($commandWithSignature);
        }

        array_push($this->commands, $commandWithSignature);

        return $this;
    }

    /**
     * @param Command        $command
     * @param InputInterface $input
     * @return $this
     */
    public function addObject(Command $command, InputInterface $input)
    {
        array_push($this->commands, compact('command', 'input'));

        return $this;
    }

    /**
     * @throws \Exception
     */
    public function run()
    {
        if (OutputInterface::VERBOSITY_VERBOSE <= $this->output->getVerbosity()) {
            $commandCount = count($this->commands);
            $this->output->writeln("Running {$commandCount} commands...");
        }

        $returnValue = 0;

        foreach ($this->commands as $command) {
            $returnValue &= $this->runOne($command);
        }

        return $returnValue;
    }

    /**
     * @param string|array        $command
     * @param InputInterface|null $input
     * @return int
     * @throws \Exception
     */
    public function runOne($command, InputInterface $input = null)
    {
        if (is_array($command)
        ) {
            if (array_keys($command) !== ['command', 'input']) {
                throw BatchException::commandArrayFormatMismatch($command);
            }

            extract($command);
        }

        if (is_string($command)) {
            $command = $this->createCommandFromString($command, $input);
        }

        if (is_null($input)) {
            throw BatchException::inputMustNotBeNull();
        }

        return $command->run($input, $this->output);
    }

    public function createCommandFromString($commandWithSignature, InputInterface &$input = null)
    {
        $commandName = explode(' ', $commandWithSignature, 2)[0];

        $command = $this->application->get($commandName);

        $input = new StringInput($commandWithSignature);

        return $command;
    }
}