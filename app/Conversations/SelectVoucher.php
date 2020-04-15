<?php

namespace App\Conversations;

use App\Conversations\Traits\HasCustomer;
use BotMan\BotMan\Messages\Incoming\Answer;
use BotMan\Drivers\Telegram\Extensions\Keyboard;
use BotMan\Drivers\Telegram\Extensions\KeyboardButton;
use GuzzleHttp\Client;
use GuzzleHttp\RequestOptions;
use Illuminate\Support\Facades\Cache;
use Spatie\Emoji\Emoji;

class SelectVoucher extends BaseConversation
{
    use HasCustomer;
    protected $vouchers;
    /**
     * Start the conversation.
     *
     * @return mixed
     */
    public function run(){
        if(!$this->vouchers){
            $this->getVouchers();
        }
        if(!$this->vouchers){
            $this->say(Emoji::sadButRelievedFace().'No products available');
            $this->stopsConversation($this->getBot()->getMessage());
            return;
        }
        $this->ask('Please select a product',function(Answer $answer){
            if(!$this->ensureButtonClicked($answer)){
                return;
            }

            $conversation_class = config('evds.'.$this->customer['class'].'.'.$answer->getValue());
            $conversation = new $conversation_class();
            $conversation->setCustomer($this->customer);
            $conversation->setVoucher($this->vouchers->firstWhere('id',$answer->getValue()));
            $this->bot->startConversation($conversation);
        },$this->generateVoucherKeyboard()->toArray());

    }
    protected function getVouchers()
    {
        try{
            $this->vouchers = Cache::remember($this->customer['group'].'_vouchers',30,function(){
                $vouchers = implode(',',array_keys(config('evds.'.$this->customer['class'],[])));

                $client = new Client();
                $url = 'http://evds.misornu-backend.com/api/denominations?';
                $url .= 'filter[group_id]='.$this->customer['group'];
                $url .= '&filter[id]='. $vouchers;
                $response = $client->get($url,[
                    RequestOptions::HEADERS => [
                        'Accept' => 'application/json',
                        'Content-Type' => 'application/json',
                    ]
                ]);
                return  collect(json_decode($response->getBody(),true)['data']);
            });

        }catch(\Exception $exception){
            return;
        }
    }
    protected function generateVoucherKeyboard(){
        $keyboard = Keyboard::create(Keyboard::TYPE_INLINE);
        $this->vouchers->each(function($voucher,$key) use ($keyboard){
            $keyboard->addRow(
                KeyboardButton::create($voucher['name'])->callbackData($voucher['id'])
            );
        });
        return $keyboard;
    }
}
