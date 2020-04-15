<?php

namespace App\Conversations;

use App\Conversations\AgentConversation;
use App\Conversations\CustomerConversation;
use App\Conversations\Traits\HasCustomer;
use BotMan\BotMan\Messages\Conversations\Conversation;
use BotMan\BotMan\Messages\Incoming\Answer;
use BotMan\BotMan\Messages\Outgoing\Actions\Button;
use BotMan\BotMan\Messages\Outgoing\Question;
use BotMan\Drivers\Telegram\Extensions\Keyboard;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\ClientException;
use GuzzleHttp\RequestOptions;
use Spatie\Emoji\Emoji;

class IdentifyCustomer extends BaseConversation
{
    use  HasCustomer;
    protected $contact;

    /**
     * Start the conversation.
     *
     * @return mixed
     */
    public function run()
    {
        try{
            $this->getCustomerByTelegramId($this->bot->getMessage()->getSender());
            if(!$this->customer){
                $this->getContact();
                return;
            }
            $this->routeCustomer();
        } catch(\Exception $exception){
            $this->bot->reply('something is wrong'.$exception->getMessage());
        }
    }

    protected function getNetwork(){
        $question = Question::create('Which network are you using')
            ->addButton(Button::create('MTN')->value('MTN'))
            ->addButton(Button::create('AIRTELTIGO')->value('ATM'))
            ->addButton(Button::create('VODAFONE')->value('VODA'));
        $this->ask($question, function (Answer $answer) {
            if(!$this->ensureButtonClicked($answer)){
                return;
            }
            $this->network = $answer->getValue();
            $this->createCustomer();
        });
    }

    public function getContact(){
        $question = "It seems this is the fist time your are using this servcie\nLet us get to know you a bit more\nPlease click the button to share your contact details";
        $this->ask($question, function (Answer $answer) {
            $payload = json_decode($answer->getMessage()->getPayload());
            if(!property_exists($payload,'contact')){
                $this->bot->reply(Emoji::exclamationMark()."Your Contact details are needed to continue\nPlease start over and make sure to click the button to send your contact information");
                $this->stopsConversation($answer->getMessage());
                return;
            }
            $this->getCustomerByPhoneNumber($payload->contact->phone_number);
            if(!$this->customer){
                $this->contact = $payload->contact;
                $this->getNetwork();
                return;
            }
            $this->say(Emoji::wavingHand().'Hello! '.$this->customer['name']);
            $this->updateCustomer($answer->getMessage()->getSender());
            $this->routeCustomer();
        }, ['reply_markup' => json_encode(['resize_keyboard'=>true,'one_time_keyboard'=>true,
            'keyboard' => [[['text' => 'Share Phone Number', 'request_contact' => true]]]
        ])]);

    }
}
