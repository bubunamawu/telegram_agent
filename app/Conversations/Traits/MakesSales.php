<?php


namespace App\Conversations\Traits;


use App\Rules\ValidGhanaPhoneNumber;
use BotMan\BotMan\Messages\Incoming\Answer;
use BotMan\BotMan\Messages\Outgoing\Actions\Button;
use BotMan\BotMan\Messages\Outgoing\Question;
use GuzzleHttp\Client;
use GuzzleHttp\RequestOptions;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Spatie\Emoji\Emoji;

trait MakesSales
{
    protected $voucher;
    protected $amount;
    protected $quantity;
    protected $phone_number;
    protected $email;
    protected $data = [];

    public function getVoucherQuantity()
    {
        $this->ask('How many would you like to buy(1 - 100)',function(Answer $answer){
            $validator =Validator::make(['quantity'=>$answer->getText()],['quantity'=>['required','integer','between:1,100']]);
            if($validator->fails()){
                $this->say('You need to enter a quantity between 1 and 100');
                $this->stopsConversation($this->getBot()->getMessage());
                return;
            }
            $this->quantity = $answer->getText();
            $this->amount = $this->quantity * $this->voucher['min_price'];
            $this->getBuyerPhoneNumber();
        });
    }
    public function getBuyerPhoneNumber()
    {
        $this->ask('Please enter the recipients phone number',function(Answer $answer){
            $validator =Validator::make(['phone_number'=>$answer->getText()],['phone_number'=>['required',new ValidGhanaPhoneNumber()]]);
            if($validator->fails()){
                $this->say('The phone number provided is not valid');
                $this->stopsConversation($this->getBot()->getMessage());
                return;
            }
            $this->phone_number = $answer->getText();
            $this->checkForEmail();
        });
    }
    public function getVoucherAmount()
    {
        $question = 'Which amount do you want to buy?'. PHP_EOL;
        $question .= "(GHC".number_format($this->voucher['min_price'],2)." - GHC".number_format($this->voucher['max_price'],2).")";
        $this->ask($question,function(Answer $answer){
            $validator =Validator::make(['amount'=>$answer->getText()],['amount'=>['required','numeric','between:'.$this->voucher['min_price'].",".$this->voucher['max_price']]]);
            if($validator->fails()){
                $this->say('The amount entered is invalid');
                $this->stopsConversation($this->getBot()->getMessage());
                return;
            }
            $this->quantity = 1;
            $this->amount = $answer->getText();
            $this->getBuyerPhoneNumber();
        });
    }
    public function checkForEmail (){
        if($this->quantity > 5){
            $this->getBuyerEmail();
            return;
        }
        $question = Question::create('Would you like to send to customers email also?')
            ->addButtons([
                Button::create('Yes')->value('yes'),
                Button::create('No')->value('no'),
            ]);
        $this->ask($question,function(Answer $answer){
            if(!$this->ensureButtonClicked($answer)){
                return;
            }
            if($answer->getValue() == 'yes'){
                $this->getBuyerEmail();
                return;
            }
            $this->confirm();
        });
    }
    public function getBuyerEmail()
    {
        $this->ask('Please enter the email',function(Answer $answer){
            $validator =Validator::make(['email'=>$answer->getText()],['email'=>['required','email']]);
            if($validator->fails()){
                $this->say('Email provided is not valid');
                $this->stopsConversation($this->getBot()->getMessage());
                return;
            }
            $this->email = $answer->getText();
            $this->confirm();
        });

    }

    public function confirm(){
        $confirmation = $this->makeConfirmationMessage();
        $question = Question::create("Would you like to confirm this sale?\n".$confirmation)
            ->addButtons([
                Button::create('Yes')->value('yes'),
                Button::create('No')->value('no'),
            ]);
        $this->ask($question,function(Answer $answer){
            if(!$this->ensureButtonClicked($answer)){
                return;
            }
            if($answer->getValue() == 'yes'){
                $this->confirmPin('processSale');
                return;
            }
            $this->say('Okay, Another time then!');
            $this->stopsConversation($this->getBot()->getMessage());
        });
    }
    protected function getSaleData()
    {
        $data = [
            'request_id'=> Str::random(20),
            'denomination_id' => $this->voucher['id'],
            'customer' => [
                'name' => $this->customer['name'],
                'operator' => $this->customer['operator'],
                'account_number' => $this->customer['account_number'],
                'pin' => $this->confirmedPin
            ],
            'receiver' => [
                'name' => 'Customer',
                'phone_number' => $this->phone_number,
                'email' => $this->email ?? ''
            ]
        ];
        if(!empty($this->data)){
            $data['data'] = $this->data;
        }
        $data['amount'] = $this->amount;
        if($this->voucher['min_price'] == $this->voucher['max_price']){
            unset($data['amount']);
            $data['quantity'] = $this->quantity;
        }
        return $data;
    }
    public function makeConfirmationMessage(){
        $message = 'Purchase Details'.PHP_EOL;
        $message .= 'Voucher:- '.$this->voucher['id'].PHP_EOL;
        $message .= 'Quantity:- '.$this->quantity.PHP_EOL;
        $message .= 'Price:- '.$this->amount.PHP_EOL;
        $message .= 'Customer Details'.PHP_EOL;
        $message .= '   Phone number:- '.$this->phone_number.PHP_EOL;
        if($this->email){
            $message .= '   Email:- '.$this->email.PHP_EOL;
        }
        return $message;
    }
    public function processSale(){
        try{
            $client = new Client();
            $url = 'https://evds.misornu-backend.com/api/requests?api_token=lyjSrOoHQpko1vPHJB6B';

            $response = $client->post($url,[
                RequestOptions::HEADERS => [
                    'Accept' => 'application/json',
                    'Content-Type' => 'application/json',
                ],
                RequestOptions::JSON => $this->getSaleData()
            ]);
            dump($response->getStatusCode());
            dump((string)$response->getBody());
            $message = 'There was a problem with your request';
            if(Str::startsWith($response->getStatusCode(),2)){
                $message = 'Transaction is being processed';
            }
            $this->say($message);
            $this->stopsConversation($this->getBot()->getMessage());
        }catch(\Exception $exception){
            $this->say($exception->getMessage());
            $this->stopsConversation($this->getBot()->getMessage());
            return;
        }
    }
}