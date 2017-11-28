<?php

/* 
 * 软件为合肥生活宝网络公司出品，未经授权许可不得使用！
 * 作者：baocms团队
 * 官网：www.baocms.com
 * 邮件: youge@baocms.com  QQ 800026911
 */

class HotelModel extends CommonModel{
    protected $pk   = 'hotel_id';
    protected $tableName =  'hotel';
    
    
    public function getHotelCate() {
        return array(
            '1' => '民宿',
            '2' => '汽车旅馆',
            '3' => '营地',
            '4' => '酒店',
            '5' => '农场住宿',
            '6' => '度假屋',
            '7' => '短租公寓',
            '8' => '青年旅社',
        );
    }

    
    public function getHotelStar() {
        return array(
            '1' => '一星酒店',
            '2' => '二星酒店',
            '3' => '三星酒店',
            '4' => '四星酒店',
            '5' => '五星酒店',
        );
    }
     
}