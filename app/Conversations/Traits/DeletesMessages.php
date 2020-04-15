<?php
/**
 * Created by PhpStorm.
 * User: user
 * Date: 7/21/2019
 * Time: 9:43 AM
 */

namespace App\Conversations\Traits;


trait DeletesMessages
{
    protected function getMessageId(){
        $payload = $this->getBot()->getMessage()->getPayload();
        return $payload['message_id'];
    }
    protected function deleteLastMessage(){
        $this->bot->sendRequest('deleteMessage',[
            'chat_id'=>$this->getBot()->getMessage()->getSender(),
            'message_id'=>$this->getMessageId(),
        ]);
    }
}