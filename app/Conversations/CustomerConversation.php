<?php

namespace App\Conversations;

use App\Conversations\Contracts\HasCustomerInterface;
use App\Conversations\Traits\HasCustomer;
use App\Conversations\Traits\HasSales;
use BotMan\BotMan\Messages\Conversations\Conversation;
use BotMan\BotMan\Messages\Incoming\Answer;
use BotMan\BotMan\Messages\Outgoing\Actions\Button;
use BotMan\BotMan\Messages\Outgoing\Question;
use Spatie\Emoji\Emoji;

class CustomerConversation extends BaseConversation implements HasCustomerInterface
{
    use HasCustomer,HasSales;
    /**
     * Start the conversation.
     *
     * @return mixed
     */
    public function run()
    {
        throw_if(empty($this->customer),new \Exception('There was a problem accessing the customer menu - Customer no specified'));

        $this->say(Emoji::receipt().'Customer Menu');
        $question = Question::create('What would you like to do today?')
            ->addButtons([
                Button::create('Buy Voucher')->additionalParameters(['url' => 'https://telegra.ph/MONIcliq-How-to-Buy-04-14']),
                Button::create('Manage Purchases')->value('manage_purchases'),
                //Button::create('Verify OTP')->value('otp'),
                Button::create('Price List')->additionalParameters(['url'=>'https://telegra.ph/MONIcliq-Price-List-04-14']),
                Button::create('Bulk Discounts')->additionalParameters(['url' => 'https://telegra.ph/MONIcliq-Customer-Discounts-04-14']),
                Button::create('Contact Support')->additionalParameters(['url' => 'https://t.me/monicliq']),
            ]);
        $this->ask($question,function(Answer $answer){
            if(!$this->ensureButtonClicked($answer)){
                return;
            }
            switch ($answer->getValue()){
                case 'buy_voucher':
                    $conversation = new SelectVoucher();
                    $conversation->setCustomer($this->customer);
                    $this->bot->startConversation($conversation);
                    break;
                case 'manage_purchases':
                    $this->displaySales();
                    break;
                case 'otp':
                    $conversation = new OTPConversation();
                    $conversation->setCustomer($this->customer);
                    $this->bot->startConversation($conversation);
                    break;
            }
        });
    }
}
