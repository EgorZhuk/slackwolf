<?php namespace Slackwolf\Game\Command;

use Exception;
use Slackwolf\Game\Formatter\UserIdFormatter;
use Slackwolf\Game\GameState;
use Zend\Loader\Exception\InvalidArgumentException;

class VoteCommand extends Command
{
    private $game;

    public function init()
    {
        if ($this->channel[0] == 'D') {
            throw new Exception("Больше двух говорят вслух. Нельзя головать (!vote) втихую.");
        }

        if (count($this->args) < 1) {
            throw new InvalidArgumentException("Выбери жертву.");
        }

        $this->game = $this->gameManager->getGame($this->channel);

        if ( ! $this->game) {
            throw new Exception("Что-то пошло не так. Кажется игра не началась.");
        }

        if ($this->game->getState() != GameState::DAY) {
            throw new Exception("Слишком темно для голования, дождись утра.");
        }

        // Voter should be alive
        if ( ! $this->game->isPlayerAlive($this->userId)) {
            throw new Exception("Мертвые лежат и не возникают.");
        }

        $this->args[0] = UserIdFormatter::format($this->args[0], $this->game->getOriginalPlayers());
echo $this->args[0];
        // Person player is voting for should also be alive
        if ( ! $this->game->isPlayerAlive($this->args[0])
                && $this->args[0] != 'noone'
                && $this->args[0] != 'clear') {
            echo 'not found';
            throw new Exception("Не вижу такого чела, а он с какого района?");
        }
    }

    public function fire()
    {
        $this->gameManager->vote($this->game, $this->userId, $this->args[0]);
    }
}