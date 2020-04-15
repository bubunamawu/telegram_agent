<?php

namespace App\Conversations;

use App\Conversations\Traits\HasCustomer;
use App\Conversations\Traits\MakesSales;
use App\Conversations\Traits\ManagesAgent;
use BotMan\BotMan\Messages\Conversations\Conversation;

abstract class SellVoucher extends BaseConversation
{
     use MakesSales, HasCustomer, ManagesAgent;
    /**
     * Start the conversation.
     *
     * @return mixed
     */
    public function run()
    {
        throw_if(empty($this->voucher), new \Exception('No voucher selected - Sell voucher'));
        throw_if(empty($this->customer), new \Exception('No customer set - Sell voucher'));

        if($this->voucher['min_price'] == $this->voucher['max_price']){
            $this->getVoucherQuantity();
            return;
        }
        $this->getVoucherAmount();
    }

    public function setVoucher(array $voucher){
        $this->voucher = $voucher;
    }
}
