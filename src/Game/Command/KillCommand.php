<?php namespace Slackwolf\Game\Command;

use Exception;
use InvalidArgumentException;
use Slack\Channel;
use Slack\ChannelInterface;
use Slack\DirectMessageChannel;
use Slackwolf\Game\Formatter\ChannelIdFormatter;
use Slackwolf\Game\Formatter\KillFormatter;
use Slackwolf\Game\Formatter\UserIdFormatter;
use Slackwolf\Game\Game;
use Slackwolf\Game\GameState;
use Slackwolf\Game\Role;
use Slackwolf\Game\OptionManager;
use Slackwolf\Game\OptionName;

class KillCommand extends Command
{
    /**
     * @var Game
     */
    private $game;

    public function init()
    {
        $client = $this->client;

        if ($this->channel[0] != 'D') {
            throw new Exception("Псс, пацан, если хочешь убивать (!kill) то пиши в личку.");
        }

        if (count($this->args) < 2) {
            $client->getChannelGroupOrDMByID($this->channel)
                   ->then(function (ChannelInterface $channel) use ($client) {
                       $client->send(":warning: Ты что-то попутал. Смотри как надо: !kill #channel @user", $channel);
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
                                 $this->client->send(":warning: Не в тот чат, милок. Смотри как надо: !kill #channel @user", $dmc);
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
        if ($this->game->getWolvesVoted()){
            $client->getChannelGroupOrDMByID($this->channel)
                   ->then(function (ChannelInterface $channel) use ($client) {
                       $client->send(":warning: Волки уже свое отвыли.", $channel);
                   });
            throw new Exception("Волки уже свое отвыли.");
        }

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

        if ($player->role != Role::WEREWOLF) {
            $client->getChannelGroupOrDMByID($this->channel)
                   ->then(function (ChannelInterface $channel) use ($client) {
                       $client->send(":warning: А справка, что ты оборотень у тебя есть?", $channel);
                   });
            throw new Exception("А справка, что ты оборотень у тебя есть?");
        }

        if ($this->game->hasPlayerVoted($this->userId)) {               
            //If changeVote is not enabled and player has already voted, do not allow another vote
            if (!$this->gameManager->optionsManager->getOptionValue(OptionName::changevote))
            {
                throw new Exception("Ой, все! Нельзя быть таким непостоянным.");
            }
        
            $this->game->clearPlayerVote($this->userId);
        }

        $this->game->vote($this->userId, $this->args[1]);

        $msg = KillFormatter::format($this->game);

        foreach($this->game->getPlayersOfRole(Role::WEREWOLF) as $player) {
            $client->getDMByUserID($player->getId())
                ->then(function(DirectMessageChannel $channel) use ($client,$msg) {
                    $client->send($msg,$channel);
                });
        }

        foreach ($this->game->getPlayersOfRole(Role::WEREWOLF) as $player)
        {
            if ( ! $this->game->hasPlayerVoted($player->getId())) {
                return;
            }
        }

        $votes = $this->game->getVotes();

        if (count($votes) > 1) {
            $this->game->clearVotes();
            foreach($this->game->getPlayersOfRole(Role::WEREWOLF) as $player) {
                $client->getDMByUserID($player->getId())
                       ->then(function(DirectMessageChannel $channel) use ($client) {
                           $client->send(":warning: Никого не убили. Вы уж там между собой разберитесь и проголосуйте снова.",$channel);
                       });
            }
            return;
        }

        $this->game->setWolvesVoted(true);

        $this->gameManager->changeGameState($this->game->getId(), GameState::DAY);
    }
}
