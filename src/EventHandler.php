<?php
namespace Azura;

use Doctrine\DBAL\Migrations\Tools\Console\Command as MigrationsCommand;
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
        $console = $event->getConsole();

        $console->addCommands([
            new Console\Command\ClearCache,
        ]);

        // Doctrine ORM/DBAL
        \Doctrine\ORM\Tools\Console\ConsoleRunner::addCommands($console);

        // Doctrine Migrations
        $settings = $console->getService('settings');

        $defaults = [
            'table_name'    => 'app_migrations',
            'directory'     => $settings[Settings::BASE_DIR].'/src/Entity/Migration',
            'namespace'     => 'App\Entity\Migration',
        ];

        $user_options = $settings[Settings::DOCTRINE_OPTIONS]['migrations'] ?? [];
        $options = array_merge($defaults, $user_options);

        /** @var \Doctrine\ORM\EntityManager $em */
        $em = $console->getService(\Doctrine\ORM\EntityManager::class);

        $migrate_config = new \Doctrine\DBAL\Migrations\Configuration\Configuration($em->getConnection());
        $migrate_config->setMigrationsTableName($options['table_name']);
        $migrate_config->setMigrationsDirectory($options['directory']);
        $migrate_config->setMigrationsNamespace($options['namespace']);

        $output = new \Symfony\Component\Console\Output\ConsoleOutput;
        $migrate_config->setOutputWriter(new \Doctrine\DBAL\Migrations\OutputWriter(function($message) use ($output) {
            $output->writeln($message);
        }));

        $migration_commands = [
            new MigrationsCommand\DiffCommand,
            new MigrationsCommand\ExecuteCommand,
            new MigrationsCommand\GenerateCommand,
            new MigrationsCommand\MigrateCommand,
            new MigrationsCommand\StatusCommand,
            new MigrationsCommand\VersionCommand
        ];

        foreach($migration_commands as $cmd) {
            $cmd->setMigrationConfiguration($migrate_config);
            $console->add($cmd);
        }

        $helper_set = $console->getHelperSet();
        $doctrine_helpers = \Doctrine\ORM\Tools\Console\ConsoleRunner::createHelperSet($em);

        $helper_set->set($doctrine_helpers->get('db'), 'db');
        $helper_set->set($doctrine_helpers->get('em'), 'em');
    }

    public function buildRoutes(Event\BuildRoutes $event)
    {
        $app = $event->getApp();

        // Add default middleware.
        $app->add(Middleware\EnableRouter::class);
        $app->add(Middleware\EnableSession::class);
        $app->add(Middleware\RemoveSlashes::class);

        // Load app-specific route configuration.
        $container = $app->getContainer();
        $settings = $container->get('settings');

        if (file_exists($settings[Settings::CONFIG_DIR].'/routes.php')) {
            call_user_func(include($settings[Settings::CONFIG_DIR].'/routes.php'), $app);
        }
    }
}

