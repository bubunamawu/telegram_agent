<?php

namespace App\Conversations;

use App\Conversations\Traits\HasCustomer;
use App\Rules\ValidGhanaPhoneNumber;
use BotMan\BotMan\Messages\Incoming\Answer;
use BotMan\BotMan\Messages\Outgoing\Actions\Button;
use BotMan\BotMan\Messages\Outgoing\Question;
use Illuminate\Support\Facades\Validator;
use GuzzleHttp\Client;
use GuzzleHttp\RequestOptions;
use Illuminate\Support\Str;
use Spatie\Emoji\Emoji;

class OTPConversation extends BaseConversation
{
    use HasCustomer;

    protected $payoutCustomer;
    protected $payoutNetwork;

    protected $otp;
    /**
     * Start the conversation.
     *
     * @return mixed
     */
    public function run()
    {
        throw_if(empty($this->customer),new \Exception('Customer not set - OTP conversation'));
        $this->say(Emoji::moneyWithWings().'OTP verification');
        $this->getOTP();
    }

    public function getOTP()
    {
        $question = Question::create(Emoji::questionMark()."Please enter the OTP:(DO NOT INCLUDE ANY SPACES)");
        $this->ask($question,function(Answer $answer){
            $validator = Validator::make(['otp'=>$answer->getText()],['otp'=>'alpha_num|size:16']);
            if($validator->passes()){
                $this->otp = strtoupper($answer->getText());
                $this->getPayoutCustomer();
                return;
            }
            $this->say(Emoji::exclamationMark().'OTP entered is Invalid');
            $this->stopsConversation($this->getBot()->getMessage());
        });
    }
    public function getPayoutCustomer()
    {
        $question = Question::create('Which number would you like the money to be paid into?')
            ->addButton(Button::create($this->customer['account_number'])->value('own-number'));
        $this->ask($question,function(Answer $answer){
            if($answer->isInteractiveMessageReply()){
                $this->payoutCustomer = $this->customer['account_number'];
                $this->payoutNetwork = $this->customer['operator'];
                $this->contactAPI();
                return;
            }
            $validator = Validator::make(['payout_customer' => $answer->getText()],['payout_customer' => ['required', new ValidGhanaPhoneNumber()]]);
            if($validator->fails()){
                $this->say(Emoji::warning().'Phone number entered is Invalid');
                $this->stopsConversation($this->getBot()->getMessage());
                return;
            }
            $this->payoutCustomer = $answer->getText();
            $this->getPayoutNetwork();
        });

    }
    public function getPayoutNetwork()
    {
        $question = Question::create('Which network does '.$this->payoutCustomer.' belong to?')
            ->addButtons([
                Button::create($this->customer['MTN MoMo'])->value('MTN'),
                //Button::create($this->customer['Voda Cash'])->value('VODA'),
                //Button::create($this->customer['ATM'])->value('ATM')
            ]);
        $this->ask($question,function(Answer $answer){
            if(!$this->ensureButtonClicked($answer)){
                return;
            }
            $this->payoutNetwork = $answer->getValue();
            $this->contactAPI();
        });

    }


    public function contactAPI()
    {
        $data = [
            "request_id" => Str::orderedUuid(),
            "denomination_id" =>'OTP',
            "customer"=>[
                'name' => $this->customer['name'],
                'operator' => $this->customer['operator'],
                'account_number' => $this->customer['account_number'],
            ],
            "receiver"=>[
                "name" => $this->customer['name'],
                "phone_number" => $this->customer['account_number'],
                "telegram_id" => $this->bot->getMessage()->getSender()
            ],
            "data"=>[
                "otp" => $this->otp,
                "operator" => $this->payoutNetwork,
                "receiver" => $this->payoutCustomer,
            ]
        ];

        $client = new Client();
        try{
            $response = $client->post("https://evds.misornu-backend.com/api/requests?api_token=9Zc8CMcbycF9ywwvEgYx",[
                RequestOptions::HEADERS =>[
                    'Content-Type' => 'application/json',
                    'Accept' => 'application/json'],
                RequestOptions::JSON => $data,
                RequestOptions::TIMEOUT => 10
            ]);
            if (Str::startsWith($response->getStatusCode(), 2)) {
                $this->say(Emoji::moneyWithWings().'OTP verified. Your Account will be loaded soon');
                return;
            }
        }catch(\Exception $e){
            info($e->getMessage());
        }
        $this->say(Emoji::wavingHandDarkSkinTone().'There was an error processing the OTP. Please check try again.');
        $this->stopsConversation($this->getBot()->getMessage());
        return;
    }
}
