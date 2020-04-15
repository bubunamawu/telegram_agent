<?php

namespace App\Conversations;

use BotMan\BotMan\Messages\Incoming\Answer;
use BotMan\Drivers\Telegram\Extensions\Keyboard;
use BotMan\Drivers\Telegram\Extensions\KeyboardButton;
use GuzzleHttp\Client;
use GuzzleHttp\RequestOptions;
use Illuminate\Support\Str;
use Spatie\Emoji\Emoji;

class AgentTransactions extends BaseConversation
{
    protected $agent;
    protected $currentPage = 1;
    protected $pageSize = 5;
    protected $transactions = ['data'=>[]];
    /**
     * Start the conversation.
     *
     * @return mixed
     */
    public function run()
    {
        if(empty($this->agent)){
            $this->say('Unable to display transactions at this moment.');
            $this->stopsConversation($this->bot->getMessage());
            return;
        }
        $this->getTransactions();
        $this->displayTransactionList();
    }
    public function setAgent($agent)
    {
        $this->agent = $agent;
    }
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
    public function displayTransactionList(){
        $this->getTransactions();
        if(empty($this->transactions['data'])){
            $this->say(Emoji::sadButRelievedFace().'No transactions currently');
            $this->stopsConversation($this->getBot()->getMessage());
            return;
        }
        $this->ask('Select Transaction',function(Answer $answer){
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
                    $this->say(Emoji::sadButRelievedFace().'End of Transaction listing');
                    $this->stopsConversation($this->getBot()->getMessage());
                    break;
                default:
                    $this->displayTransactionDetails($answer->getValue());
                    break;
            }
        },$this->generateNavigationKeyboard()->toArray());

    }
    public function displayTransactionDetails($id)
    {
        $transaction = $this->getTransactionDescription($id);
        $this->ask($transaction,function(Answer $answer) use($id){
            if($this->ensureButtonClicked($answer)){
                return;
            }
            if($answer->getValue() == 'back'){
                $this->displayTransactionList();
                return;
            }
            $this->say('End of Transaction List');
            $this->stopsConversation($answer->getMessage());

        },$this->generateTransactionKeyboard()->toArray());
    }
    public function generateTransactionKeyboard()
    {
        $keyboard = Keyboard::create(Keyboard::TYPE_INLINE);
        $keyboard->addRow(
            KeyboardButton::create('<< Back')->callbackData('back')
        );
        $keyboard->addRow(
            KeyboardButton::create('Quit')->callbackData('quit')
        );
        return $keyboard;
    }
    public function getTransactionDescription($id)
    {
        $transaction = $this->getTransaction($id);

        $description = 'Transaction Details'.PHP_EOL;
        $description .= 'Date: '. $transaction['created_at'].PHP_EOL;
        $description .= 'Source: '. $transaction['parent_type'].PHP_EOL;
        $description .= 'Amount: '. $transaction['amount'].PHP_EOL;

        return $description;

    }

    protected function generateNavigationKeyboard()
    {
        $keyboard = Keyboard::create(Keyboard::TYPE_INLINE);
        collect($this->transactions['data'])->each(function($transaction,$key) use ($keyboard){
            $keyboard->addRow(
                KeyboardButton::create($transaction['created_at'].'|'.$transaction['amount'])->callbackData($transaction['id'])
            );
        });
        if($this->currentPage > 1){
            $keyboard->addRow(
                KeyboardButton::create('<< Prev. Page')->callbackData('prev')
            );
        }
        if($this->currentPage < $this->transactions['meta']['last_page']){
            $keyboard->addRow(
                KeyboardButton::create('Next Page >>')->callbackData('next')
            );
        }
        $keyboard->addRow(
            KeyboardButton::create('Quit')->callbackData('quit')
        );
        return $keyboard;
    }

    public function getTransaction($id)
    {
        return collect($this->transactions['data'])->firstWhere('id', $id);
    }
    protected function getTransactions ()
    {
        $client = new Client();
        $url = 'https://evds.misornu-backend.com/api/agents/';
        $url .= $this->agent['id'].'/transactions?';
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
                $this->transactions = json_decode($response->getBody(),true);
                return;
            }
            $this->transactions = null;
        }catch(\Exception $exception){
            return;
        }
    }
}
