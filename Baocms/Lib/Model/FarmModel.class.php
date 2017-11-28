<?php

class FarmModel extends CommonModel{
    protected $pk   = 'farm_id';
    protected $tableName =  'farm';

    public function getFarmGroup() {
        return array(
            '1' => '私导旅游',
            '2' => '周边玩乐',
            '3' => '跟团游玩',
            '4' => '自由行',
            '5' => '境外游学',
            '6' => '主题旅游',
            '7' => '高端旅游',
        );
    }
    
    public function getFarmCate() {
        return array(
            '1' => '户外探险',
            '2' => '空中观光',
            '3' => '文化艺术',
            '4' => '自然观光',
            '5' => '美酒佳肴',
            '6' => '中土世界',
            '7' => '毛利文化',
        );
    }
    
    public function getPeople() {
        return array(
            '1' => '1-2人',
            '2' => '3-5人',
            '3' => '5-8人',
            '4' => '8-12人',
            '5' => '12人以上',
        );
    }
    
    public function getDays() {
        return array(
            '1' => '2天',
            '2' => '3天',
            '3' => '5天',
            '4' => '7天',
        );
    }
    
    public function getid($shop_id,$type) {
        if($type == 1 || !$type){
            $rs = D('Farmgroupattr');  //适合人群
        }else{
            $rs = D('Farmplayattr');   //能玩什么
        }
        if(!$shop_id){
            return false;
        }else if($res = $rs->where(array('shop_id'=>$shop_id))->select()){
            $Arrays = array();
            foreach($res as $k => $v){
                $Arrays[] = $v['attr_id'];
            }
            return $Arrays;
        }else{
            return false;
        }
    }
	
    public function getPics($comment_id){
        $comment_id = (int)$comment_id;
        return $this->where(array('comment_id'=>$comment_id))->select();
    } 
}