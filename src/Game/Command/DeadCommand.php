<?php namespace Slackwolf\Game\Command;

use Exception;
use Slack\Channel;
use Slack\ChannelInterface;
use Slackwolf\Game\Formatter\PlayerListFormatter;
use Slackwolf\Game\Game;

class DeadCommand extends Command
{

    /**
     * @var Game
     */
    private $game;

    public function init()
    {
        $this->game = $this->gameManager->getGame($this->channel);
    }

    public function fire()
    {
        $client = $this->client;

        if ( ! $this->gameManager->hasGame($this->channel)) {
            $client->getChannelGroupOrDMByID($this->channel)
               ->then(function (ChannelInterface $channel) use ($client) {
                   $client->send(":warning: Что-то пошло не так. Кажется игра не началась.", $channel);
               });
            return;
        }

        // build list of players
        $playersList = PlayerListFormatter::format($this->game->getDeadPlayers());
        if (empty($playersList))
        {
            $this->gameManager->sendMessageToChannel($this->game, "Все еще 10 негритят, но это ненадолго.");
        }
        else
        {
            $this->gameManager->sendMessageToChannel($this->game, ":angel: Они ушли в лучший мир: ".$playersList);
        }
    }
}