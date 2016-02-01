<?php namespace Slackwolf\Game\Formatter;

use Slackwolf\Game\Game;
use Slackwolf\Game\Role;

class KillFormatter
{
    public static function format(Game $game)
    {
        $msg = ":memo: Самые честные в мире итоги голосования Оборотней\r\n-----------------------------------------\r\n";

        foreach ($game->getVotes() as $voteForId => $voters)
        {
            $voteForPlayer = $game->getPlayerById($voteForId);

            $numVoters = count($voters);

            $msg .= ":knife: Выпилили @{$voteForPlayer->getUsername()}\t\t | ({$numVoters}) | ";

            $voterNames = [];

            foreach ($voters as $voter)
            {
                $voter = $game->getPlayerById($voter);
                $voterNames[] = '@'.$voter->getUsername();
            }

            $msg .= implode(', ', $voterNames) . "\r\n";
        }

        $msg .= "\r\n--------------------------------------------------------------\r\n:hourglass: Еще не опускали бюллетень в урну: ";

        $playerNames = [];

        foreach ($game->getPlayersOfRole(Role::WEREWOLF) as $player)
        {
            if ( ! $game->hasPlayerVoted($player->getId())) {
                $playerNames[] = '@'.$player->getUsername();
            }
        }

        if (count($playerNames) > 0) {
            $msg .= implode(', ', $playerNames);
        } else {
            $msg .= "Нет никого";
        }

        $msg .= "\r\n--------------------------------------------------------------\r\n";

        return $msg;
    }
}