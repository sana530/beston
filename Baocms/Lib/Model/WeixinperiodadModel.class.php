<?php



class WeixinperiodadModel extends CommonModel{
    protected $pk   = 'ad_id';
    protected $tableName =  'weixin_period_ad';
    protected $token = 'weixin_period_ad';

    public function checkKeyword($keyword){
        $words = $this->fetchAll();
        foreach($words as $val){
            if($val['keyword'] == $keyword)
                return  $val;
        }
        return false;
    }
}