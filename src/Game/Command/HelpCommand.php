<?php namespace Slackwolf\Game\Command;

use Slack\Channel;
use Slack\ChannelInterface;
use Slack\DirectMessageChannel;

class HelpCommand extends Command
{
    public function fire()
    {
        $client = $this->client;

        $help_msg =  "Как играть в Оборотня (#Werewolf)\r\n------------------------\r\n";
        $help_msg .= "Оборотень это командная игра основанная на интуиции. Игроки тайно получают роль в игре, когда она начинается.\r\n";
        $help_msg .= "ВАЖНО! Главное разобраться какие сообщения писать в общий чат, а какие в личку боту.\r\n";
        $help_msg .= "Если кратко: все действия, связанные с ролями идут в личку. Голосование днем и обсуждение - в общий чат.\r\n";
        $help_msg .= "Если ты Крестьянин, ты должен найти всех оборотней, основываясь на интуиции и поведении игроков. ";
        $help_msg .= "Если ты Оборотень, то ты должен прикинуться невинной овечкой и не раскрывать себя.\r\n";
        $help_msg .= "Действие игры происходит в течении нескольких Дней и Ночей. Каждый день все игоки голосуют за то, кого сегодня посадят на вилы. Если количество голосов совпадает - обоих на костер.";
        $help_msg .= "Каждую ночь Оборотни пускают одного игрока на харчи. Решение принимается анонимно. Если нет консенсуса - волки голосуют до посинения. Бот пришлет в личку результат.\r\n";
        $help_msg .= "Крестьяне выигрывают как только все Оборотни убиты. Оборотни выигрывают если их больше или столько же сколько Крестьян.\r\n\r\n";
        $help_msg .= "Дополнительные роли\r\n------------------------\r\n";
        $help_msg .= " |_ Смотритель - крестьянин, который может ночью посмотреть роль одного из пользователей\r\n";
        $help_msg .= " |_ Таксидермист - странный человек, который выигрывает когда умирает\r\n";
        $help_msg .= " |_ Ликан - крестьянин который кажется смотрителю оборотнем\r\n";
        $help_msg .= " |_ Зритель -крестьянин, который узнает, кто в этой игре смотритель в первую ночь\r\n";
        $help_msg .= " |_ Телохранитель - может защитить одного человека каждую ночь.\r\n\r\n";
        $help_msg .= "Список команд\r\n------------------------\r\n";
        $help_msg .= "|_  !new - создать игру\r\n";
        $help_msg .= "|_  !join - присоединится к следующей игре\r\n";
        $help_msg .= "|_  !leave - выйти из следующей игры\r\n";
        $help_msg .= "|_  !start - начать игру с теми кто присоединился к ней \r\n";
        $help_msg .= "|_  !start all - начать игру со всеми людьми в чате\r\n";
        $help_msg .= "|_  !start @user1 @user2 @user3 - начать игру с перечисленными пользователями\r\n";
        $help_msg .= "|_  !vote @user1|noone|clear - голосование за линчевание пользователя|против всех|очистить выбор\r\n";
        $help_msg .= "|_  !see #channel @user1 -  Только для Смотрителя. Посмотреть роль выбранного пользователя в выбранном чате.\r\n";
        $help_msg .= "|_  !kill #channel @user1 - Только для Оборотня. Выбрать жертву на сегодня. Выбор дожен быть известен только другим оборотням.\r\n";
        $help_msg .= "|_  !guard #channel @user1 - Только для Телохранителя. Защитить выбранного пользователя. Нельзя защищать одного и того же пользователя две ночи подряд.\r\n";
        $help_msg .= "|_  !end - Закончить игру заранее\r\n";
        $help_msg .= "|_  !setoption - Просмотр и редактирование параметров игры.\r\n";
        $help_msg .= "|_  !dead - Показать мертвецов.\r\n";
        $help_msg .= "|_  !alive - Показать выживших.\r\n";

        $this->client->getDMByUserId($this->userId)->then(function(DirectMessageChannel $dm) use ($client, $help_msg) {
            $client->send($help_msg, $dm);
        });
        
        if ($this->channel[0] != 'D') {
            $client->getChannelGroupOrDMByID($this->channel)
               ->then(function (ChannelInterface $channel) use ($client) {
                   $client->send(":book: Проверь личку, там инструкции.", $channel);
               });
        }
    }
}