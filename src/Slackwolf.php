<?php namespace Slackwolf;

use React\EventLoop\Factory;
use Slack\ConnectionException;
use Slack\RealTimeClient;
use Slackwolf\Game\Command\AliveCommand;
use Slackwolf\Game\Command\DeadCommand;
use Slackwolf\Game\Command\EndCommand;
use Slackwolf\Game\Command\GuardCommand;
use Slackwolf\Game\Command\HelpCommand;
use Slackwolf\Game\Command\KillCommand;
use Slackwolf\Game\Command\SeeCommand;
use Slackwolf\Game\Command\NewCommand;
use Slackwolf\Game\Command\JoinCommand;
use Slackwolf\Game\Command\LeaveCommand;
use Slackwolf\Game\Command\StartCommand;
use Slackwolf\Game\Command\VoteCommand;
use Slackwolf\Game\Command\SetOptionCommand;
use Slackwolf\Game\GameManager;
use Slackwolf\Message\Message;

class Slackwolf
{
    public function __construct()
    {
        /*
         * Set the default timezone in case it isn't configured in php.ini
         */
        date_default_timezone_set(getenv('TIMEZONE'));
    }

    public function run()
    {
        /*
         * Create the event loop
         */
        $eventLoop = Factory::create();

        /*
         * Create our Slack client
         */
        $client = new SlackRTMClient($eventLoop);
        $client->setToken(getenv('BOT_TOKEN'));

        /*
         * Setup command bindings
         */
        $commandBindings = [
            'help'  => HelpCommand::class,
            'setoption'  => SetOptionCommand::class,
            'new' => NewCommand::class,
            'join' => JoinCommand::class,
            'leave' => LeaveCommand::class,
            'start' => StartCommand::class,
            'end'   => EndCommand::class,
            'see'   => SeeCommand::class,
            'vote'  => VoteCommand::class,
            'kill'  => KillCommand::class,
            'guard' => GuardCommand::class,
            'alive' => AliveCommand::class,
            'dead'  => DeadCommand::class
        ];
        
        /*
         * Create the game manager
         */
        $gameManager = new GameManager($client, $commandBindings);

        /*
         * Route incoming Slack messages
         */
        $client->on('message', function ($data) use ($client, $gameManager) {
            $message = new Message($data);

            if ($message->getSubType() == 'channel_join') {
                $client->refreshChannel($message->getChannel());
            } else if ($message->getSubType() == 'channel_leave') {
                $client->refreshChannel($message->getChannel());
            } else {
                $gameManager->input($message);
            }
        });

        /*
         * Connect to Slack
         */
        echo "Подключаюсь...\r\n";
        $client->connect()->then(function() {
            echo "Готово.\n";
        }, function(ConnectionException $e) {
            echo $e->getMessage();
            exit();
        });

        /*
         * Start the event loop
         */
        $eventLoop->run();
    }
}