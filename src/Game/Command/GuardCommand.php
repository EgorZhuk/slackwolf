<?php namespace Slackwolf\Game\Command;

use Exception;
use InvalidArgumentException;
use Slack\Channel;
use Slack\ChannelInterface;
use Slack\DirectMessageChannel;
use Slackwolf\Game\Formatter\ChannelIdFormatter;
use Slackwolf\Game\Formatter\UserIdFormatter;
use Slackwolf\Game\Game;
use Slackwolf\Game\GameState;
use Slackwolf\Game\Role;

class GuardCommand extends Command
{
    /**
     * @var Game
     */
    private $game;

    public function init()
    {
        $client = $this->client;

        if ($this->channel[0] != 'D') {
            throw new Exception("Псс, пацан, если хочешь защищать (!guard) то пиши в личку.");
        }

        if (count($this->args) < 2) {
            $client->getChannelGroupOrDMByID($this->channel)
                   ->then(function (ChannelInterface $channel) use ($client) {
                       $client->send(":warning: Ты что-то попутал. Смотри как надо: !guard #channel @user", $channel);
                   });
            throw new InvalidArgumentException("Ты что-то попутал.");
        }

        $client = $this->client;

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
                                 $this->client->send(":warning: Не в тот чат, милок. Смотри как надо: !guard #channel @user", $dmc);
                             }
                         );
            throw new InvalidArgumentException();
        }

        $this->game = $this->gameManager->getGame($channelId);

        if ( ! $this->game) {
            $client->getChannelGroupOrDMByID($this->channel)
                   ->then(function (ChannelInterface $channel) use ($client) {
                       $client->send(":warning: Что-то пошло не так. Кажется игра не началась.", $channel);
                   });
            throw new Exception("Что-то пошло не так. Кажется игра не началась.");
        }
        
        $this->args[1] = UserIdFormatter::format($this->args[1], $this->game->getOriginalPlayers());
    }

    public function fire()
    {
        $client = $this->client;

        if ($this->game->getState() != GameState::NIGHT) {
            $client->getChannelGroupOrDMByID($this->channel)
                   ->then(function (ChannelInterface $channel) use ($client) {
                       $client->send(":warning: Ночь утра мудренее. Дождись заката.", $channel);
                   });
            throw new Exception("Ночь утра мудренее. Дождись заката.");
        }

        // Voter should be alive
        if ( ! $this->game->isPlayerAlive($this->userId)) {
            $client->getChannelGroupOrDMByID($this->channel)
                   ->then(function (ChannelInterface $channel) use ($client) {
                       $client->send(":warning: Кажется тебя уже выпилили, наберись терпения и жди следующей игры.", $channel);
                   });
            throw new Exception("Кажется тебя уже выпилили, наберись терпения и жди следующей игры.");
        }

        // Person player is voting for should also be alive
        if ( ! $this->game->isPlayerAlive($this->args[1])) {
            $client->getChannelGroupOrDMByID($this->channel)
                   ->then(function (ChannelInterface $channel) use ($client) {
                       $client->send(":warning: Не вижу такого чела, а он с какого района?", $channel);
                   });
            throw new Exception("Не вижу такого чела, а он с какого района?");
        }

        // Person should be werewolf
        $player = $this->game->getPlayerById($this->userId);

        if ($player->role != Role::BODYGUARD) {
            $client->getChannelGroupOrDMByID($this->channel)
                   ->then(function (ChannelInterface $channel) use ($client) {
                       $client->send(":warning: А справка, что ты телохранитель у тебя есть?", $channel);
                   });
            throw new Exception("А справка, что ты телохранитель у тебя есть?");
        }

        if ($this->game->getGuardedUserId() !== null) {
            $client->getChannelGroupOrDMByID($this->channel)
                   ->then(function (ChannelInterface $channel) use ($client) {
                       $client->send(":warning: Палехче, не больше одного за раз, громила.", $channel);
                   });
            throw new Exception("Палехче, не больше одного за раз, громила.");
        }

        if ($this->game->getLastGuardedUserId() == $this->args[1]) {
            $client->getChannelGroupOrDMByID($this->channel)
                   ->then(function (ChannelInterface $channel) use ($client) {
                       $client->send(":warning: Я понимаю, что он твой любимчик, но два раза подряд не прокатит.", $channel);
                   });
            throw new Exception("Я понимаю, что он твой любимчик, но два раза подряд не прокатит.");
        }

        $this->game->setGuardedUserId($this->args[1]);

        $client->getChannelGroupOrDMByID($this->channel)
               ->then(function (ChannelInterface $channel) use ($client) {
                   $client->send("Все путем.", $channel);
               });

        $this->gameManager->changeGameState($this->game->getId(), GameState::DAY);
    }
}