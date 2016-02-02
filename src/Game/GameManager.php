<?php namespace Slackwolf\Game;

use Exception;
use Slack\Channel;
use Slack\ChannelInterface;
use Slack\DirectMessageChannel;
use Slack\RealTimeClient;
use Slackwolf\Game\Command\Command;
use Slackwolf\Game\Formatter\PlayerListFormatter;
use Slackwolf\Game\Formatter\RoleListFormatter;
use Slackwolf\Game\Formatter\RoleSummaryFormatter;
use Slackwolf\Game\Formatter\VoteSummaryFormatter;
use Slackwolf\Message\Message;
use Slackwolf\Game\OptionsManager;
use Slackwolf\Game\OptionName;

class GameManager
{
    private $games = [];

    private $commandBindings;
    private $client;
    public $optionsManager;
    
    public function __construct(RealTimeClient $client, array $commandBindings)
    {
        $this->commandBindings = $commandBindings;
        $this->client = $client;
        $this->optionsManager = new OptionsManager();
    }
    
    public function input(Message $message)
    {
        $input = $message->getText();

        if ( ! is_string($input)) {
            return false;
        }

        if ( ! isset($input[0])) {
            return false;
        }

        if ($input[0] !== '!') {
            return false;
        }

        $input_array = explode(' ', $input);

        $command = $input_array[0];

        if (strlen($command) < 2) {
            return false;
        }

        $command = substr($command, 1);

        $args = [];

        foreach ($input_array as $i => $arg)
        {
            if ($i == 0) { continue; } // Skip the command

            if (empty($arg)) { continue; }

            $args[] = $arg;
        }

        if ($command == null) {
            return false;
        }

        $command = strtolower($command);

        if ( ! isset($this->commandBindings[$command])) {
            return false;
        }

        try
        {
            /** @var Command $command */
            $command = new $this->commandBindings[$command]($this->client, $this, $message, $args);
            $command->fire();
        } catch (Exception $e)
        {
            return false;
        }

        return true;
    }
    
    public function sendMessageToChannel($game, $msg)
    {
        $client = $this->client;
        $client->getChannelGroupOrDMByID($game->getId())
               ->then(function (ChannelInterface $channel) use ($client,$msg) {
                   $client->send($msg, $channel);
               });
    }

    public function changeGameState($gameId, $newGameState)
    {
        $game = $this->getGame($gameId);

        if ( ! $game) {
            throw new Exception();
        }

        if ($game->isOver()) {
            $this->onGameOver($game);
            return;
        }

        if ($game->getState() == GameState::NIGHT && $newGameState == GameState::DAY) {
            $numSeer = $game->getNumRole(Role::SEER);

            if ($numSeer && ! $game->seerSeen()) {
                return;
            }

            $numWolf = $game->getNumRole(Role::WEREWOLF);

            if ($numWolf && ! $game->getWolvesVoted()) {
                return;
            }

            $numBodyguard = $game->getNumRole(Role::BODYGUARD);

            if ($numBodyguard && ! $game->getGuardedUserId()) {
                return;
            }

            $this->onNightEnd($game);

            if ($game->isOver()) {
                $this->onGameOver($game);
                return;
            }
        }

        $game->changeState($newGameState);

        if ($newGameState == GameState::FIRST_NIGHT) {
            $this->onFirstNight($game);
        }

        if ($newGameState == GameState::DAY) {
            $this->onDay($game);
        }

        if ($newGameState == GameState::NIGHT) {
            $this->onNight($game);
        }
    }

    public function hasGame($id)
    {
        return isset($this->games[$id]);
    }

    /**
     * @param $id
     *
     * @return Game|bool
     */
    public function getGame($id)
    {
        if ($this->hasGame($id)) {
            return $this->games[$id];
        }

        return false;
    }

    public function newGame($id, array $users, $roleStrategy)
    {
        $this->addGame(new Game($id, $users, $roleStrategy));
   }

    public function startGame($id)
    {
        $game = $this->getGame($id);
        if (!$this->hasGame($id)) { return; }
        $users = $game->getLobbyPlayers();
        if(count($users) < 3) {
            $this->sendMessageToChannel($game, "Меньше 3 не собираться!");
            return;
        }
        $game->assignRoles();
        $this->changeGameState($id, GameState::FIRST_NIGHT);
    }
    
    public function endGame($id, $enderUserId = null)
    {
        $game = $this->getGame($id);

        if ( ! $game) {
            return;
        }

        $playerList = RoleSummaryFormatter::format($game->getLivingPlayers(), $game->getOriginalPlayers());

        $client = $this->client;
        $winningTeam = $game->whoWon();

        if($winningTeam !== null) {
            $winMsg = ":clipboard: Подведем итоги\r\n--------------------------------------------------------------\r\n{$playerList}\r\n\r\n:tada: Игра окончена.";
            if ($winningTeam == Role::VILLAGER) {
                $winMsg .= "Победили Крестьяне!";
            }
            elseif ($winningTeam == Role::WEREWOLF) {
                $winMsg .= "Победили Оборотни!";
            }
            elseif ($winningTeam == Role::TANNER) {
                $winMsg .= "Победил Таксидермист!";
            }
            else {
                $winMsg .= "Хз кто победил!";
            }
            $this->sendMessageToChannel($game, $winMsg);
        }

        if ($enderUserId !== null) {
            $client->getUserById($enderUserId)
                   ->then(function (\Slack\User $user) use ($game, $playerList) {
                       $gameMsg = ":triangular_flag_on_post:";
                       $roleSummary = "";
                       if($game->getState() != GameState::LOBBY) {
                           $gameMsg .= "Игра завершена.";
                           $roleSummary .= "\r\n\r\nПодведем итоги:\r\n----------------\r\n{$playerList}";
                       } else {
                           $gameMsg .= "Игра завершена.";
                       }
                       $this->sendMessageToChannel($game, $gameMsg." @{$user->getUsername()}.".$roleSummary);
                   });
        }

        unset($this->games[$id]);
    }

    public function vote(Game $game, $voterId, $voteForId)
    {
        if ( ! $game->isPlayerAlive($voterId)) {
            return;
        }

        if ( ! $game->isPlayerAlive($voteForId)
                && ($voteForId != 'noone' || !$this->optionsManager->getOptionValue(OptionName::no_lynch))
                && $voteForId != 'clear') {
            return;
        }

        if ($game->hasPlayerVoted($voterId)) {
            //If changeVote is not enabled and player has already voted, do not allow another vote
            if (!$this->optionsManager->getOptionValue(OptionName::changevote))
            {
                throw new Exception("Ой, все! Нельзя быть таким непостоянным.");
            }
            $game->clearPlayerVote($voterId);
        }

        if ($voteForId != 'clear') { //if voting for 'clear' just clear vote
            $game->vote($voterId, $voteForId);
        }
        $voteMsg = VoteSummaryFormatter::format($game);

        $this->sendMessageToChannel($game, $voteMsg);
        
        if ( ! $game->votingFinished()) {
            return;
        }

        $votes = $game->getVotes();

        $vote_count = [];
        foreach ($votes as $lynch_player_id => $voters) {
            if ( ! isset($vote_count[$lynch_player_id])) {
                $vote_count[$lynch_player_id] = 0;
            }

            $vote_count[$lynch_player_id] += count($voters);
        }

        $players_to_be_lynched = [];

        $max = 0;
        foreach ($vote_count as $lynch_player_id => $num_votes) {
            if ($num_votes > $max) {
                $max = $num_votes;
            }
        }
        foreach ($vote_count as $lynch_player_id => $num_votes) {
            if ($num_votes == $max && $lynch_player_id != 'noone') {
                $players_to_be_lynched[] = $lynch_player_id;
            }
        }

        $lynchMsg = "\r\n";
        if (count($players_to_be_lynched) == 0){
            $lynchMsg .= ":peace_symbol: На вече решили никого не убивать.";
        }else {
            $lynchMsg .= ":newspaper: Сегодня на вилы посадили: ";

            $lynchedNames = [];
            foreach ($players_to_be_lynched as $player_id) {
                $player = $game->getPlayerById($player_id);
                $lynchedNames[] = "@{$player->getUsername()} ({$player->role})";
                $game->killPlayer($player_id);
            }

            $lynchMsg .= implode(', ', $lynchedNames). "\r\n";
        }
        $this->sendMessageToChannel($game,$lynchMsg);

        $this->changeGameState($game->getId(), GameState::NIGHT);
    }


    private function addGame(Game $game)
    {
        $this->games[$game->getId()] = $game;
    }

    private function onFirstNight(Game $game)
    {
        $client = $this->client;

        foreach ($game->getLivingPlayers() as $player) {
            $client->getDMByUserId($player->getId())
                ->then(function (DirectMessageChannel $dmc) use ($client,$player,$game) {
                    $client->send("Ты у нас будешь {$player->role}", $dmc);

                    if ($player->role == Role::WEREWOLF) {
                        if ($game->getNumRole(Role::WEREWOLF) > 1) {
                            $werewolves = PlayerListFormatter::format($game->getPlayersOfRole(Role::WEREWOLF));
                            $client->send("В этой игре Оборотни: {$werewolves}", $dmc);
                        } else {
                            $client->send("В этой игре ты единственный Оборотень.", $dmc);
                        }
                    }

                    if ($player->role == Role::SEER) {
                        $client->send("Смотритель, выбери игрока !see #channel @username.\r\nВНИМАНИЕ! ДЕРЖИ ЯЗЫК ЗА ЗУБАМИ ДО УТРА, А ЕСЛИ УМЕР ЛУЧШЕ ВООБЩЕ ПОМОЛЧИ!1111", $dmc);
                    }

                    if ($player->role == Role::BEHOLDER) {
                        $seers = $game->getPlayersOfRole(Role::SEER);
                        $seers = PlayerListFormatter::format($seers);

                        $client->send("Смотрители: {$seers}", $dmc);
                    }
                });
        }

        $playerList = PlayerListFormatter::format($game->getLivingPlayers());
        $roleList = RoleListFormatter::format($game->getLivingPlayers());

        $msg = ":wolf: Новая игра в Оборотня начинается! Если не знаешь правил пиши !help.\r\n\r\n";
        $msg .= "ВАЖНО! Главное разобраться какие сообщения писать в общий чат, а какие в личку боту.\r\n\r\n";
        $msg .= "Если кратко: все действия, связанные с ролями идут в личку. Голосование днем и обсуждение - в общий чат. \r\n\r\n";
        $msg .= "Игроки: {$playerList}\r\n";
        $msg .= "Возможные роли: {$game->getRoleStrategy()->getRoleListMsg()}\r\n\r\n";

        if ($this->optionsManager->getOptionValue(OptionName::role_seer)) {
            $msg .= ":crescent_moon: :zzz: Наступает ночь, крестьяне выпили чарку, съели шкварку и идут спать. ";
            $msg .= "Игра начнется как только Смотритель выберет кого-то.";
        }
        $this->sendMessageToChannel($game, $msg);
        
        if (!$this->optionsManager->getOptionValue(OptionName::role_seer)) {
            $this->changeGameState($game->getId(), GameState::NIGHT);        
        }
    }

    private function onDay(Game $game)
    {
        $remainingPlayers = PlayerListFormatter::format($game->getLivingPlayers());

        $dayBreakMsg = ":sunrise: Прокричал петух, пора вставать, у тебя свиньи не кормлены.\r\n";
        $dayBreakMsg .= "Перепись населения: {$remainingPlayers}\r\n\r\n";
        $dayBreakMsg .= "Крестьяне, пора снимать с Оборотней шкуры! Пиши !vote @username чтобы посадить плохиша на вилы.";
        if ($this->optionsManager->getOptionValue(OptionName::changevote))
        {
            $dayBreakMsg .= "\r\nМожно поменять решение пока голосование не завершилось. Пиши !vote clear если у тебя ветренная натура.";
        }
        if ($this->optionsManager->getOptionValue(OptionName::no_lynch))
        {
            $dayBreakMsg .= "\r\nПиши !vote noone если устал от кровопролития и не хочешь никого сегодня убивать.";
        }

        $this->sendMessageToChannel($game, $dayBreakMsg);
    }

    private function onNight(Game $game)
    {
        $client = $this->client;
        $nightMsg = ":crescent_moon: :zzz: На районе выключили электричество. Что делать, надо идти спать.";
        $this->sendMessageToChannel($game, $nightMsg);

        $wolves = $game->getPlayersOfRole(Role::WEREWOLF);

        $wolfMsg = ":crescent_moon: Ночь, время для охоты. Пиши !kill #channel @player чтобы выбрать блюдо дня. ";

        foreach ($wolves as $wolf)
        {
             $this->client->getDMByUserId($wolf->getId())
                  ->then(function (DirectMessageChannel $channel) use ($client,$wolfMsg) {
                      $client->send($wolfMsg, $channel);
                  });
        }

        $seerMsg = ":mag_right: Смотритель, выбери игрока !see #channel @username.";

        $seers = $game->getPlayersOfRole(Role::SEER);

        foreach ($seers as $seer)
        {
            $this->client->getDMByUserId($seer->getId())
                 ->then(function (DirectMessageChannel $channel) use ($client,$seerMsg) {
                     $client->send($seerMsg, $channel);
                 });
        }

        $bodyGuardMsg = ":muscle: Телохранитель, ты можешь спасти кого-то если не лень. Этот игрок не пойдет на колбасу сегодня ночью. Пиши !guard #channel @user";

        $bodyguards = $game->getPlayersOfRole(Role::BODYGUARD);

        foreach ($bodyguards as $bodyguard) {
            $this->client->getDMByUserId($bodyguard->getId())
                 ->then(function (DirectMessageChannel $channel) use ($client,$bodyGuardMsg) {
                     $client->send($bodyGuardMsg, $channel);
                 });
        }
    }

    private function onNightEnd(Game $game)
    {
        $votes = $game->getVotes();

        foreach ($votes as $lynch_id => $voters) {
            $player = $game->getPlayerById($lynch_id);

            if ($lynch_id == $game->getGuardedUserId()) {
                $killMsg = ":muscle: @{$player->getUsername()} спасен благодаря тебе, мужик.";
            } else {
                $killMsg = ":skull_and_crossbones: @{$player->getUsername()} ($player->role) был беспощадно убит.";
                $game->killPlayer($lynch_id);
            }

            $game->setLastGuardedUserId($game->getGuardedUserId());
            $game->setGuardedUserId(null);
            $this->sendMessageToChannel($game, $killMsg);
        }
    }

    private function onGameOver(Game $game)
    {
        $game->changeState(GameState::OVER);
        $this->endGame($game->getId());
    }
}