<?php namespace Slackwolf\Game\Command;

use Exception;
use Slack\Channel;
use Slack\ChannelInterface;
use Slackwolf\Game\GameState;
use Slackwolf\Game\Formatter\PlayerListFormatter;
use Slackwolf\Game\Formatter\UserIdFormatter;

class JoinCommand extends Command
{
    public function init()
    {
        if ($this->channel[0] == 'D') {
            throw new Exception("Нелья просто так взять и присоединится к игре в личном сообщении.");
        }
        
        $this->game = $this->gameManager->getGame($this->channel);

        if ( ! $this->game) {
            throw new Exception("Что-то пошло не так. Кажется игра не началась.");
        }
        
        if ($this->game->getState() != GameState::LOBBY) { 
            throw new Exception("Запись на рейс окончена.");
        }
    }

    public function fire()
    {
        $userId = $this->userId;
        $game = $this->game;
    
        $this->client->getChannelGroupOrDMByID($this->channel)
            ->then(function (Channel $channel) {
                return $channel->getMembers();
            })
            ->then(function (array $users) use ($userId, $game) {
                foreach($users as $key => $user) {
                    if ($user->getId() == $userId) {
                        $game->addLobbyPlayer($user);
                    }
                }
            });
            
        $playersList = PlayerListFormatter::format($this->game->getLobbyPlayers());
        $this->gameManager->sendMessageToChannel($this->game, "Мальчишки и девчонки, а так же их родители: ".$playersList);
    }
}