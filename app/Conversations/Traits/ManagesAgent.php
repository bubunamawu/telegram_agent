<?php


namespace App\Conversations\Traits;


use BotMan\BotMan\Messages\Incoming\Answer;
use GuzzleHttp\Client;
use GuzzleHttp\RequestOptions;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;
use Spatie\Emoji\Emoji;

trait ManagesAgent
{
    protected $confirmedPin;
    public function confirmPin($nextAction)
    {
        $this->ask(Emoji::pen().'Please enter your current pin(four digits)',function(Answer $answer) use ($nextAction){
            $this->deleteLastMessage();
            $agent = $this->getAgent(true);
            if(!$agent){
                $this->say(Emoji::warning().'Unable to validate pin. Please contact customer care');
                $this->stopsConversation($this->getBot()->getMessage());
                return;
            }
            if($agent['pin'] <> $answer->getText()){
                $this->say(Emoji::warning().'The pin entered is not valid. Please check it and try again');
                $this->stopsConversation($this->getBot()->getMessage());
                return;
            }
            $this->confirmedPin = $agent['pin'];
            $this->{$nextAction}();
        }, [
            'reply_markup' => json_encode(['force_reply' =>true])
        ]);
    }
    protected function editAgent($id,$route,$value)
    {
        $client = new Client();
        $url = 'https://evds.misornu-backend.com/api/agents/';
        $url .= $id.'/'.$route;
        try{
            $response = $client->put($url,[
                RequestOptions::HEADERS => [
                    'Accept' => 'application/json',
                    'Content-Type' => 'application/json',
                ],
                RequestOptions::JSON =>[
                    $route => $value
                ]
            ]);
            if(Str::startsWith($response->getStatusCode(),'2')){
                return json_decode($response->getBody(),true);
            }
            return false;
        }catch(\Exception $exception){
            return false;
        }
    }
    protected function getAgent ($flush = false)
    {
        if($flush){
            Cache::forget('agent_'.$this->customer['id']);
        }

        $customer = $this->customer;
        try{
            return Cache::remember('agent_'.$this->customer['id'],10,function() use ($customer){
                $client = new Client();
                $url = 'https://evds.misornu-backend.com/api/agents/';
                $url .= $customer['id'];
                $response = $client->get($url,[
                    RequestOptions::HEADERS => [
                        'Accept' => 'application/json',
                        'Content-Type' => 'application/json',
                    ]
                ]);
                if(Str::startsWith($response->getStatusCode(),'2')){
                    return json_decode($response->getBody(),true)['data'];
                }
                return false;
            });
        }catch(\Exception $exception){
            return false;
        }
    }

}