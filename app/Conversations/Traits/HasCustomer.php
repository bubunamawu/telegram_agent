<?php


namespace App\Conversations\Traits;


use App\Conversations\AgentConversation;
use App\Conversations\CustomerConversation;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\ClientException;
use GuzzleHttp\RequestOptions;
use Illuminate\Support\Str;
use Spatie\Emoji\Emoji;

trait HasCustomer
{
    protected $customer;
    protected $network;
    public function setCustomer(array $customer){
        $this->customer = $customer;
    }
    protected function createCustomer()
    {
        $client = new Client();

        try{
            $response = $client->post('https://evds.misornu-backend.com/api/customers',[
                RequestOptions::HEADERS => [
                    'Accept' => 'application/json',
                    'Content-Type' => 'application/json',
                ],
                RequestOptions::JSON =>[
                    'name'  => 'Customer',
                    'operator' => $this->network,
                    'account_number' => $this->contact->phone_number,
                    'data' =>[
                        'telegram_id' => $this->bot->getMessage()->getSender()
                    ]]
            ]);

            if(Str::startsWith($response->getStatusCode(),2)){
                $this->customer = json_decode($response->getBody(),true);
                $this->say(Emoji::handshake().'Welcome to the MONIcliq family');
                $this->routeCustomer();
                return;
            }
            $this->say(Emoji::warning().'There was a problem adding you to the system');
        }catch(ClientException $exception){
            $this->say(Emoji::warning().'There was a problem adding you to the system');
            return;
        }
    }

    protected function updateCustomer($telegramId){
        $client = new Client();
        try{
            $response = $client->put('https://evds.misornu-backend.com/api/customers/'.$this->customer['id'],[
                RequestOptions::HEADERS => [
                    'Accept' => 'application/json',
                    'Content-Type' => 'application/json',
                ],
                RequestOptions::JSON =>['data'=>['telegram_id' => $telegramId]]
            ]);
            if(Str::startsWith($response->getStatusCode(),2)){
                $this->customer = json_decode($response->getBody(),true);
            }
        }catch(ClientException $exception){
            return;
        }
    }
    protected function getCustomerByFilter ($filter,$value)
    {
        $client = new Client();
        try{
            $response = $client->get('https://evds.misornu-backend.com/api/customers?filter['.$filter.']='.$value,[
                RequestOptions::HEADERS => [
                    'Accept' => 'application/json',
                    'Content-Type' => 'application/json',
                ],
            ]);
            if(Str::startsWith($response->getStatusCode(),2)){
                $this->customer = json_decode($response->getBody(),true);
            }
        }catch(ClientException $exception){
            return ;
        }
    }
    protected function getCustomerByTelegramId($telegramId){
        $this->getCustomerByFilter('telegram_id',$telegramId);
    }
    protected function getCustomerByPhoneNumber($phoneNumber){
        $this->getCustomerByFilter('account_number',$phoneNumber);
    }
    protected function routeCustomer(){
        $conversation = new CustomerConversation();
        if($this->customer['class'] == 'Agent'){
            $conversation = new AgentConversation();
        }
        $conversation->setCustomer($this->customer);
        $this->bot->startConversation($conversation);
    }
}