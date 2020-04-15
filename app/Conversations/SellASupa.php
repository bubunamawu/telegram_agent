<?php

namespace App\Conversations;

use App\Rules\ValidGhanaPhoneNumber;
use BotMan\BotMan\Messages\Conversations\Conversation;
use BotMan\BotMan\Messages\Incoming\Answer;
use BotMan\BotMan\Messages\Outgoing\Actions\Button;
use BotMan\BotMan\Messages\Outgoing\Question;
use Illuminate\Support\Facades\Validator;

class SellASupa extends SellVoucher
{
    public function confirm()
    {
        $question = Question::create("Would you like to load the customers account.?")
            ->addButtons([
                Button::create('Yes')->value('yes'),
                Button::create('No')->value('no'),
            ]);
        $this->ask($question,function(Answer $answer){
            if(!$this->ensureButtonClicked($answer)){
                return;
            }
            if($answer->getValue() == 'no'){
                parent::confirm();
                return;
            }
            $this->getBuyerSupaID();
        });
    }
    public function getBuyerSupaID()
    {
        $this->ask('Please enter the customers SupaID',function(Answer $answer){
            $validator =Validator::make(['supa_id'=>$answer->getText()],['supa_id'=>['required','numeric','digits_between:5,10']]);
            if($validator->fails()){
                $this->say('The supa ID provided is not valid');
                $this->stopsConversation($this->getBot()->getMessage());
                return;
            }
            $this->data['supa_id'] = $answer->getText();
            parent::confirm();
        });
    }
    public function makeConfirmationMessage(){
        $message = parent::makeConfirmationMessage();
        if(!empty($this->data['supa_id'])){
            $message .= '   SUPA ID:- '.$this->data['supa_id'].PHP_EOL;
        }
        return $message;
    }
    protected function getSaleData()
    {
        $saleData = parent::getSaleData();
        if(!empty($this->data['supa_id'])){
            $saleData +=$this->data;
        }
        return $saleData;
    }
}
