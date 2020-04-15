<?php
if (! function_exists('normalize_phonenumber')) {
    function normalize_phonenumber($phone_number) {
        if( preg_match(config('phone_number.pattern','/^(0|233)(24|23|50|54|55|59|20|27|57|26|28)[0-9]{7}$/'),$phone_number)){
            if(strlen($phone_number) == 10){
                return '233'.substr($phone_number,1);
            }
            return $phone_number;
        }
        throw new InvalidArgumentException('Invalid phone number');
    }
}
