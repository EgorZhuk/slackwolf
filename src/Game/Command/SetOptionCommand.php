<?php namespace Slackwolf\Game\Command;

use Slack\DirectMessageChannel;
use Slackwolf\Game;
use Slackwolf\Game\Formatter\OptionFormatter;

class SetOptionCommand extends Command
{
    public function init()
    {
        if (count($this->args) > 1)
        {
            //Attempt to change an option detected
            $this->gameManager->optionsManager->setOptionValue($this->args, true);
        }
    }
    
    public function fire()
    {
        $client = $this->client;

        $help_msg =  "Параметры\r\n------------------------\r\n";
        $help_msg .= "Формат нашего с тобой общения такой: !setOption Название Значение. Названия и допустимые значение представлены в списке ниже. Текущее значение указано в скобочках.\r\n";
        $help_msg .= "Какие у нас есть варианты\r\n------------------------\r\n";
        foreach($this->gameManager->optionsManager->options as $curOption)
        {
            /** @var Slackwolf\Game\Option $curOption */
            $help_msg .= OptionFormatter::format($curOption);
        }
        
        $this->client->getDMByUserId($this->userId)->then(function(DirectMessageChannel $dm) use ($client, $help_msg) {
            $client->send($help_msg, $dm);
        });
    }
}