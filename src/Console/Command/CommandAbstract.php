<?php
namespace Azura\Console\Command;

use Azura\Console\Application;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Output\OutputInterface;

abstract class CommandAbstract extends Command
{
    /**
     * Return a Dependency Injection service.
     *
     * @param $service_name
     * @return mixed
     * @throws \Azura\Exception
     * @throws \Interop\Container\Exception\ContainerException
     */
    public function get($service_name)
    {
        /** @var Application $application */
        $application = self::getApplication();

        return $application->getService($service_name);
    }

    /**
     * @param OutputInterface $output
     * @param $command_name
     * @param array $command_args
     * @throws \Exception
     */
    protected function runCommand(OutputInterface $output, $command_name, $command_args = [])
    {
        $command = $this->getApplication()->find($command_name);

        $input = new ArrayInput(['command' => $command_name] + $command_args);
        $input->setInteractive(false);

        $command->run($input, $output);
    }
}
