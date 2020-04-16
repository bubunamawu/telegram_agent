<?php

return [
    'Agent' => [
        'WAEC' => \App\Conversations\SellWaec::class,
        'PBECE' => \App\Conversations\SellVoucher::class,
        'NMC' => \App\Conversations\SellVoucher::class,
        'TEU' => \App\Conversations\SellVoucher::class,
        'ASUPA' => \App\Conversations\SellASupa::class
    ],
    'Ordinary' => ['WAEC']
];
