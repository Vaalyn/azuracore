<?php
namespace Azura\Console;

use Azura\Event\BuildConsoleCommands;
use Azura\EventDispatcher;
use Psr\Container\ContainerInterface;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class Application extends \Symfony\Component\Console\Application
{
    /** @var ContainerInterface */
    protected $di;

    /**
     * @param ContainerInterface $di
     */
    public function setContainer(ContainerInterface $di)
    {
        $this->di = $di;
    }

    /**
     * @return ContainerInterface
     */
    public function getContainer(): ContainerInterface
    {
        return $this->di;
    }

    /**
     * @param $service_name
     * @return mixed
     * @throws \Azura\Exception
     */
    public function getService($service_name)
    {
        if ($this->di->has($service_name)) {
            return $this->di->get($service_name);
        } else {
            throw new \Azura\Exception(sprintf('Service "%s" not found.', $service_name));
        }
    }

    /**
     * @inheritdoc
     */
    public function run(InputInterface $input = null, OutputInterface $output = null)
    {
        /** @var EventDispatcher $dispatcher */
        $dispatcher = $this->di->get(EventDispatcher::class);

        // Trigger an event for the core app and all plugins to build their CLI commands.
        $event = new BuildConsoleCommands($this);
        $dispatcher->dispatch(BuildConsoleCommands::NAME, $event);

        return parent::run($input, $output);
    }
}
