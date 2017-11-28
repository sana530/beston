<?php
class CrypterModel extends CommonModel{
    private $key = '';
    private $iv = '';
    private $model = '';
    function __construct($key='pay1stpay',$iv='48765123'){
        $this->key = $key;
        $this->iv  = $iv;
    }
    protected function getCipher(){
        $cipher = mcrypt_module_open(MCRYPT_BLOWFISH,'',$this->model,'');
        mcrypt_generic_init($cipher, $this->key, $this->iv);
        return $cipher;
    }
    function encrypt($string, $model='cbc'){
        $this->model = $model;  //加密成长的数字，model可以用cbc, 短的可以用cfb等。详见mcrypt_module_open用法
        $binary = mcrypt_generic($this->getCipher(),$string);
        $string = '';
        for($i = 0; $i < strlen($binary); $i++){
            $string .=  str_pad(ord($binary[$i]),3,'0',STR_PAD_LEFT);
        }
        return $string;
    }
    function decrypt($encrypted, $model='cbc'){
        $this->model = $model;
        //check for missing leading 0's
        $encrypted = str_pad($encrypted, ceil(strlen($encrypted) / 3) * 3,'0', STR_PAD_LEFT);
        $binary = '';
        $values = str_split($encrypted,3);
        foreach($values as $chr){
            $chr = ltrim($chr,'0');
            $binary .= chr($chr);
        }
        return mdecrypt_generic($this->getCipher(),$binary);
    }
}