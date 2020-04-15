<?php


namespace App\Conversations\Traits;


use BotMan\BotMan\Messages\Incoming\Answer;
use BotMan\Drivers\Telegram\Extensions\Keyboard;
use BotMan\Drivers\Telegram\Extensions\KeyboardButton;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\ClientException;
use GuzzleHttp\RequestOptions;
use Illuminate\Support\Str;
use Spatie\Emoji\Emoji;

trait HasSales
{
    protected $currentPage = 1;
    protected $pageSize = 5;
    protected $sales = ['data'=>[]];

    public function nextPage()
    {
         $this->currentPage += 1;
        $this->updateNavigationKeyboard();
    }
    public function prevPage()
    {
        $this->currentPage -= 1;
        $this->updateNavigationKeyboard();
    }
    protected function updateNavigationKeyboard()
    {
        $this->deleteLastMessage();
        $this->displaySales();
    }
    public function displaySales(){
        $this->getSales();
        if(empty($this->sales['data'])){
            $this->say(Emoji::sadButRelievedFace().'No sales currently');
            $this->stopsConversation($this->getBot()->getMessage());
            return;
        }
        $this->ask('Select Sale',function(Answer $answer){
            if(!$this->ensureButtonClicked($answer)){
                return;
            }
            switch ($answer->getValue()){
                case 'prev':
                    $this->prevPage();
                    break;
                case 'next':
                    $this->nextPage();
                    break;
                case 'quit':
                    $this->say(Emoji::sadButRelievedFace().'End of Sales listing');
                    $this->stopsConversation($this->getBot()->getMessage());
                    break;
                default:
                    $this->displaySaleDetails($answer->getValue());
                    break;
            }
        },$this->generateNavigationKeyboard()->toArray());

    }
    public function displaySaleDetails($id)
    {
        $sale = $this->getSaleDescription($id);
        $this->ask($sale,function(Answer $answer) use($id){
            if($this->ensureButtonClicked($answer)){
                return;
            }
            if($answer->getValue() == 'telegram'){
                $this->displaySales();
                return;
            }
            switch ($answer->getValue()){
                case 'sms':
                    $this->smsSale($id);
                    break;
                case 'email':
                    $this->emailSale($id);
                    break;
                case 'telegram':
                    $this->telegramSale($id);
                    break;
            }
            $this->say(Emoji::postalHorn(). 'Request notification has been sent');
            $this->stopsConversation($answer->getMessage());
        },$this->generateSaleKeyboard()->toArray());
    }
    public function getSaleDescription($id)
    {
        $sale = $this->getSale($id);
        $description = 'Sale Details'.PHP_EOL;
        $description .= 'Date: '. $sale['created_at'].PHP_EOL;
        $description .= 'Product: '. $sale['denomination']['name'].PHP_EOL;
        $description .= 'Price: '. $sale['selling_price'].PHP_EOL;
        $description .= 'status: '. $sale['code_status'].PHP_EOL;
        if($sale['codes']){
            $description .= 'Codes: '. count($sale['codes']);
        }
        return $description;

    }
    public function generateSaleKeyboard(){
        $keyboard = Keyboard::create(Keyboard::TYPE_INLINE);
        $keyboard->addRow(
            KeyboardButton::create('Resend SMS')->callbackData('sms'),
            KeyboardButton::create('Resend Email')->callbackData('email')
        );
        if($this->customer['class'] <> 'Agent'){
            $keyboard->addRow(
                KeyboardButton::create('Telegram me!')->callbackData('telegram')
            );
        }
        $keyboard->addRow(
            KeyboardButton::create('<< Back')->callbackData('back')
        );
        return $keyboard;
    }
    protected function generateNavigationKeyboard()
    {
        $keyboard = Keyboard::create(Keyboard::TYPE_INLINE);
        collect($this->sales['data'])->each(function($sale,$key) use ($keyboard){
            $keyboard->addRow(
                KeyboardButton::create($sale['created_at'].'|'.$sale['denomination_id'])->callbackData($sale['id'])
            );
        });
        if($this->currentPage > 1){
            $keyboard->addRow(
                KeyboardButton::create('<< Prev. Page')->callbackData('prev')
            );
        }
        if($this->currentPage < $this->sales['meta']['last_page']){
            $keyboard->addRow(
                KeyboardButton::create('Next Page >>')->callbackData('next')
            );
        }
        $keyboard->addRow(
            KeyboardButton::create('Quit')->callbackData('quit')
        );
        return $keyboard;
    }
    protected function resendSale($id,$route)
    {
        $client = new Client();
        $url = 'https://evds.misornu-backend.com/api/sales/';
        $url .= $id.'/'.$route;
        try{
            $response = $client->post($url,[
                RequestOptions::HEADERS => [
                    'Accept' => 'application/json',
                    'Content-Type' => 'application/json',
                ]
            ]);
            $this->say(Emoji::constructionWorkerDarkSkinTone().'Your request is being proccessed.');
            return;
        }catch(\Exception $exception){
            $this->say(Emoji::doubleExclamationMark().'There was a problem with your request');
            return;
        }
    }
    public function smsSale($id){
        $this->resendSale($id,'sms');
    }
    public function emailSale($id){
        $this->resendSale($id,'email');
    }
    public function telegramSale($id){
        $this->resendSale($id,'telegram');
    }
    public function getSale($id)
    {
        return collect($this->sales['data'])->firstWhere('id', $id);
    }
    protected function getSales ()
    {
        $client = new Client();
        $url = 'https://evds.misornu-backend.com/api/customers/';
        $url .= $this->customer['id'].'/sales?';
        $url .= 'page[number]='.$this->currentPage;
        $url .= '&page[size]='.$this->pageSize;
        try{
            $response = $client->get($url,[
                RequestOptions::HEADERS => [
                    'Accept' => 'application/json',
                    'Content-Type' => 'application/json',
                ]
            ]);

            if(Str::startsWith($response->getStatusCode(),'2')){
                $this->sales = json_decode($response->getBody(),true);
                return;
            }
            $this->sales = null;
            return;
        }catch(\Exception $exception){
            return;
        }
    }
}