<?php
use App\Http\Controllers\BotManController;

$botman = resolve('botman');

$botman->fallback(function($bot){
    $conversation = new \App\Conversations\IdentifyCustomer();
    $bot->startConversation($conversation);
});
