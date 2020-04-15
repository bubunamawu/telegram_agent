<?php

namespace App\Conversations;

use App\Conversations\Contracts\HasCustomerInterface;
use App\Conversations\Traits\ManagesAgent;
use App\Conversations\Traits\HasCustomer;
use App\Conversations\Traits\HasSales;
use BotMan\BotMan\Messages\Incoming\Answer;
use BotMan\BotMan\Messages\Outgoing\Actions\Button;
use BotMan\BotMan\Messages\Outgoing\Question;
use BotMan\Drivers\Telegram\Extensions\Keyboard;
use BotMan\Drivers\Telegram\Extensions\KeyboardButton;
use GuzzleHttp\Client;
use GuzzleHttp\RequestOptions;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Spatie\Emoji\Emoji;

class AgentConversation extends BaseConversation implements HasCustomerInterface
{
    use HasCustomer, HasSales, ManagesAgent;
    protected $pin;
    protected $email;
    /**
     * Start the conversation.
     *
     * @return mixed
     */
    public function run()
    {
        if(empty($this->customer)){
            $this->say(Emoji::warning().'There was a problem accessing the agent menu. Please contact support.');
            $this->stopsConversation($this->bot->getMessage());
            return;
        }
        $this->say(Emoji::receipt().'Agent Menu');
        $question = Question::create('What would you like to do today?')
            ->addButtons([
                Button::create('Sell Voucher')->value('sell_voucher'),
                Button::create('Manage Sales')->value('manage_sales'),
                Button::create('Manage Account')->value('manage_account'),
                Button::create('Price List')->additionalParameters(['url'=>'https://telegra.ph/MONIcliq-Price-List-04-14']),
                Button::create('Current Discounts')->additionalParameters(['url' => 'https://telegra.ph/MONIcliq-Agent-Discounts-04-14']),
                Button::create('Contact Support')->additionalParameters(['url' => 'https://t.me/monicliq']),
            ]);

        $this->ask($question,function(Answer $answer){
            if(!$this->ensureButtonClicked($answer)){
                return;
            }
            switch ($answer->getValue()){
                case 'sell_voucher':
                    $conversation = new SelectVoucher();
                    $conversation->setCustomer($this->customer);
                    $this->bot->startConversation($conversation);
                    break;
                case 'manage_sales':
                    $this->displaySales();
                    break;
                case 'manage_account':
                    $this->manageAccount();
                    break;
            }
        });
    }
    public function manageAccount(){
        $this->say(Emoji::bank().'Manage Account');
        $keyboard = Keyboard::create(Keyboard::TYPE_INLINE)
            ->addRow(KeyboardButton::create('Check Balance')->callbackData('check_balance'))
            ->addRow(KeyboardButton::create('View Transactions')->callbackData('transactions'))
            ->addRow(KeyboardButton::create('Load Account')->callbackData('load_account'))
            ->addRow(KeyboardButton::create('update Email')->callbackData('email'))
            ->addRow(KeyboardButton::create('Reset Pin')->callbackData('pin'));
        $this->ask('What would you like to do today?',function(Answer $answer){
            if(!$this->ensureButtonClicked($answer)){
                return;
            }
            switch ($answer->getValue()){
                case 'check_balance':
                    $message = 'Unable to check balance';
                    if($agent = $this->getAgent(true)){
                        $message = 'Your current balance is GHC'. $agent['balance'];
                    }
                    $this->say($message);
                    $this->stopsConversation($this->getBot()->getMessage());
                    break;
                case 'load_account':
                    $this->say('Kindly follow the steps here to load your account');
                    $this->stopsConversation($this->getBot()->getMessage());
                    return;
                    break;
                case 'transactions':
                    $conversation = new AgentTransactions();
                    $conversation->setAgent($this->getAgent(true));
                    $this->bot->startConversation($conversation);
                    return;
                    break;
                case 'pin':
                    return $this->getPin();
                    break;
                case 'email':
                    return $this->getEmail();
                    break;
            }
        },$keyboard->toArray());
    }
    public function getPin()
    {
        $this->ask(Emoji::pen().'Please enter your new pin(four digits)',function(Answer $answer){
            $validator = Validator::make(['pin'=> $answer->getText()],['pin'=>'required|numeric|digits:4']);
            $this->deleteLastMessage();
            if($validator->passes()){
                if(is_null($this->pin)){
                    $this->pin = $answer->getText();
                    $this->repeat(Emoji::pen().'Please confirm your new pin(four digits)');
                    return;
                }
                if($this->pin <> $answer->getText()){
                    $this->say(Emoji::warning().'The pins entered to not match. Please check and try again.');
                    $this->stopsConversation($this->getBot()->getMessage());
                    return;
                }
                $this->confirmPin('resetPin');
                return;
            }
            $this->say(Emoji::warning().'The pin entered is not valid. Please check it and try again');
            $this->stopsConversation($this->getBot()->getMessage());
        }, [
            'reply_markup' => json_encode(['force_reply' =>true])
        ]);
    }
    public function getEmail()
    {
        $agent = $this->getAgent();
        if(!$agent){
            $this->say("Unable to allow email change at the moment!");
            $this->stopsConversation($this->getBot()->getMessage());
            return;
        }
        $this->say (Emoji::eMail()."\nYour current email is ".$agent['email']);
        $this->ask('What would you like to change it to?',
            function(Answer $answer){
                $email = trim($answer->getText());
                $validator = Validator::make(['email' => $email],['email'=>'required|email']);
                if($validator->fails()){
                    $this->say('Input was not a valid email');
                    $this->stopsConversation($this->getBot()->getMessage());
                    return;
                }
                $this->email = $email;
                $this->confirmPin('resetEmail');
            },
            ['reply_markup' => json_encode([
                'force_reply' =>true])
            ]);
    }

    public function resetPin()
    {
        $message = 'There was a problem with your request to reset pin. Please contact customer care';
        if($this->editAgent($this->customer['id'],'pin',$this->pin)){
            $message = 'Pin has been reset';
        }
        $this->say($message);
        $this->stopsConversation($this->getBot()->getMessage());
    }
    public function resetEmail()
    {
        $message = 'There was a problem with your request. Please contact customer care';
        if($this->editAgent($this->customer['id'],'email',$this->email)){
            $message = 'Email has been updated!';
        }
        $this->say($message);
        $this->stopsConversation($this->getBot()->getMessage());
    }

}
