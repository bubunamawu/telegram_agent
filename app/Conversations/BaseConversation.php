<?php

namespace App\Conversations;

use App\Conversations\Traits\DeletesMessages;
use BotMan\BotMan\Messages\Conversations\Conversation;
use BotMan\BotMan\Messages\Incoming\Answer;
use Spatie\Emoji\Emoji;

abstract class BaseConversation extends Conversation
{
    use DeletesMessages;
    protected $previous_response;
    public function ensureButtonClicked(Answer $answer){
        if(!$answer->isInteractiveMessageReply()){
            if(empty($this->previous_response)){
                return false;
            }
            $this->bot->sendRequest('editMessageReplyMarkup',
                [
                    'chat_id'=>$this->previous_response->chat->id,
                    'message_id'=>$this->previous_response->message_id,
                    'reply_markup'=>''
                ]
            );
            $this->bot->reply(Emoji::exclamationMark()."You have to select one of the buttons\nPlease enter the command again and start over.",
                ['reply_to_message_id'=>$this->getMessageId()]);
            $this->stopsConversation($this->getBot()->getMessage());
            //$this->repeat();
            return false;
        }
        return true;
    }
    public function ask($question, $next, $additionalParameters = [])
    {
        $response = $this->bot->reply($question, $additionalParameters);
        if($response){
            $response = json_decode($response->getContent());
            if($response->ok){
                $this->previous_response = $response->result;
            }
        }
        $this->bot->storeConversation($this, $next, $question, $additionalParameters);
        return $this;
    }

}
