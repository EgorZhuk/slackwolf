<?php namespace Slackwolf\Game\Command;

use Exception;
use Slack\Channel;
use Slack\ChannelInterface;
use Slackwolf\Game\GameState;
use Slackwolf\Game\Formatter\PlayerListFormatter;

class LeaveCommand extends Command
{
    private $game;

    public function init()
    {
        if ($this->channel[0] == 'D') {
            throw new Exception("Нельзя сойти с поезда в личном сообщении.");
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
        $this->game->removeLobbyPlayer($this->userId);
            
        $playersList = PlayerListFormatter::format($this->game->getLobbyPlayers());
        $this->gameManager->sendMessageToChannel($this->game, "Мальчишки и девчонки, а так же их родители: ".$playersList);    
    }
}