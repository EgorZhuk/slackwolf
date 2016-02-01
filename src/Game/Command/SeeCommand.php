<?php namespace Slackwolf\Game\Command;

use Exception;
use Slack\Channel;
use Slack\ChannelInterface;
use Slack\DirectMessageChannel;
use Slackwolf\Game\Formatter\ChannelIdFormatter;
use Slackwolf\Game\Formatter\UserIdFormatter;
use Slackwolf\Game\Game;
use Slackwolf\Game\GameState;
use Slackwolf\Game\Role;
use Zend\Loader\Exception\InvalidArgumentException;

class SeeCommand extends Command
{
    /**
     * @var Game
     */
    private $game;

    /**
     * @var string
     */
    private $gameId;

    /**
     * @var string
     */
    private $chosenUserId;

    public function init()
    {
        $client = $this->client;

        if ($this->channel[0] != 'D') {
            throw new Exception("Псс, пацан, если хочешь видеть (!see) то пиши в личку.");
        }

        if (count($this->args) < 2) {
            $this->client->getDMById($this->channel)
                         ->then(
                             function (DirectMessageChannel $dmc) use ($client) {
                                 $this->client->send(":warning: Ты что-то попутал. Смотри как надо: !see #channel @user", $dmc);
                             }
                         );

            throw new InvalidArgumentException();
        }

        $channelId   = null;
        $channelName = "";

        if (strpos($this->args[0], '#C') !== false) {
            $channelId = ChannelIdFormatter::format($this->args[0]);
        } else {
            if (strpos($this->args[0], '#') !== false) {
                $channelName = substr($this->args[0], 1);
            } else {
                $channelName = $this->args[0];
            }
        }

        if ($channelId != null) {
            $this->client->getChannelById($channelId)
                         ->then(
                             function (ChannelInterface $channel) use (&$channelId) {
                                 $channelId = $channel->getId();
                             },
                             function (Exception $e) {
                                 // Do nothing
                             }
                         );
        }

        if ($channelId == null) {
            $this->client->getGroupByName($channelName)
                         ->then(
                             function (ChannelInterface $channel) use (&$channelId) {
                                 $channelId = $channel->getId();
                             },
                             function (Exception $e) {
                                 // Do nothing
                             }
                         );
        }

        if ($channelId == null) {
            $this->client->getDMById($this->channel)
                         ->then(
                             function (DirectMessageChannel $dmc) use ($client) {
                                 $this->client->send(":warning: Не в тот чат, милок. Смотри как надо: !see #channel @user", $dmc);
                             }
                         );
            throw new InvalidArgumentException();
        }

        $this->game   = $this->gameManager->getGame($channelId);
        $this->gameId = $channelId;

        if (!$this->game) {
            $this->client->getDMById($this->channel)
                         ->then(
                             function (DirectMessageChannel $dmc) use ($client) {
                                 $this->client->send(":warning: Что-то пошло не так. Кажется игра не началась.", $dmc);
                             }
                         );

            throw new InvalidArgumentException();
        }

        $this->args[1] = UserIdFormatter::format($this->args[1], $this->game->getOriginalPlayers());
        $this->chosenUserId = $this->args[1];

        $player = $this->game->getPlayerById($this->userId);

        if ( ! $player) {
            $this->client->getDMById($this->channel)
                 ->then(
                     function (DirectMessageChannel $dmc) use ($client) {
                         $this->client->send(":warning: Не вижу тебя в списке игроков.", $dmc);
                     }
                 );

            throw new InvalidArgumentException();
        }

        // Player should be alive
        if ( ! $this->game->isPlayerAlive($this->userId)) {
            $client->getChannelGroupOrDMByID($this->channel)
                ->then(function (ChannelInterface $channel) use ($client) {
                    $client->send(":warning: Кажется тебя уже выпилили, наберись терпения и жди следующей игры.", $channel);
                });
            throw new Exception("Кажется тебя уже выпилили, наберись терпения и жди следующей игры.");
        }

        if ($player->role != Role::SEER) {
            $this->client->getDMById($this->channel)
                 ->then(
                     function (DirectMessageChannel $dmc) use ($client) {
                         $this->client->send(":warning: А справка от окулиста у тебя есть?", $dmc);
                     }
                 );
            throw new Exception("А справка от окулиста у тебя есть?");
        }

        if (! in_array($this->game->getState(), [GameState::FIRST_NIGHT, GameState::NIGHT])) {
            throw new Exception("Ночь утра мудренее. Дождись заката.");
        }

        if ($this->game->seerSeen()) {
            $this->client->getDMById($this->channel)
                 ->then(
                     function (DirectMessageChannel $dmc) use ($client) {
                         $this->client->send(":warning: Глазки устали, иди поспи.", $dmc);
                     }
                 );
            throw new Exception("Глазки устали, иди поспи.");
        }
    }

    public function fire()
    {
        $client = $this->client;

        foreach ($this->game->getLivingPlayers() as $player) {
            if (! strstr($this->chosenUserId, $player->getId())) {
                continue;
            }

            if ($player->role == Role::WEREWOLF || $player->role == Role::LYCAN) {
                $msg = "@{$player->getUsername()} на стороне Оборотней.";
            } else {
                $msg = "@{$player->getUsername()} на стороне Крестьян.";
            }

            $this->client->getDMById($this->channel)
                 ->then(
                     function (DirectMessageChannel $dmc) use ($client, $msg) {
                         $this->client->send($msg, $dmc);
                     }
                 );

            $this->game->setSeerSeen(true);

            $this->gameManager->changeGameState($this->game->getId(), GameState::DAY);

            return;
        }

        $this->client->getDMById($this->channel)
             ->then(
                 function (DirectMessageChannel $dmc) use ($client) {
                     $this->client->send("Не вижу такого чела, а он с какого района?", $dmc);
                 }
             );
    }
}