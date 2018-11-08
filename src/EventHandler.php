<?php
namespace Azura;

use Symfony\Component\EventDispatcher\EventSubscriberInterface;

class EventHandler implements EventSubscriberInterface
{
    public static function getSubscribedEvents()
    {
        return [
            Event\BuildConsoleCommands::NAME => [
                ['registerConsoleCommands', 0],
            ],
            Event\BuildRoutes::NAME => [
                ['buildRoutes', 0],
            ],
        ];
    }

    public function registerConsoleCommands(Event\BuildConsoleCommands $event)
    {
        $event->getConsole()->addCommands([
            new Console\Command\ClearCache,
        ]);
    }

    public function buildRoutes(Event\BuildRoutes $event)
    {
        $app = $event->getApp();

        $container = $app->getContainer();
        $settings = $container->get('settings');

        if (file_exists($settings['config_dir'].'/routes.php')) {
            call_user_func(include($settings['config_dir'].'/routes.php'), $app);
        }
    }
}

