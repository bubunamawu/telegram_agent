<?php

return [
    'Agent' => [
        'WAEC' => \App\Conversations\SellWaec::class,
        'ASUPA' => \App\Conversations\SellASupa::class
    ],
    'Ordinary' => ['WAEC']
];
