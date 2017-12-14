<?php



class WeixintipsModel extends CommonModel{
    protected $pk   = 'tips_id';
    protected $tableName =  'weixin_tips';
    protected $token = 'weixin_tips';
  
    public function checkKeyword($keyword){
        $words = $this->fetchAll();
        foreach($words as $val){
            if($val['keyword'] == $keyword)
                return  $val;
        }
        return false;
    }
}