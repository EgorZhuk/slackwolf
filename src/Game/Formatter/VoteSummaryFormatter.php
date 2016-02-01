<?php namespace Slackwolf\Game\Formatter;

use Slackwolf\Game\Game;

class VoteSummaryFormatter
{
    public static function format(Game $game)
    {
        $msg = ":memo: Вече\r\n--------------------------------------------------------------\r\n";

        foreach ($game->getVotes() as $voteForId => $voters)
        {
            $voteForPlayer = $game->getPlayerById($voteForId);
            $numVoters = count($voters);

            if ($voteForId == 'noone'){
                $msg .= ":peace_symbol: Пожалуй сегодня обойдемся без расчлененки и сжигания на костре\t\t | ({$numVoters}) | ";
            } else {
                $msg .= ":knife: Сжечь ведьму @{$voteForPlayer->getUsername()}\t\t | ({$numVoters}) | ";
            }
            
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

        foreach ($game->getLivingPlayers() as $player)
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