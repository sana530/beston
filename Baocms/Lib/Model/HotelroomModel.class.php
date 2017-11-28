<?php

/* 
 * 软件为合肥生活宝网络公司出品，未经授权许可不得使用！
 * 作者：baocms团队
 * 官网：www.baocms.com
 * 邮件: youge@baocms.com  QQ 800026911
 */

class HotelroomModel extends CommonModel{
    protected $pk   = 'room_id';
    protected $tableName =  'hotel_room';
    
    
    public function getRoomType(){
        return array(
1 => '单人间',	
2 => '双人间',	
3 => '双床间',	
4 => '双床双人间',	
5 => '三人间',	
6 => '四人间',	
7 => '家庭房',	
8 => '套房',	
9 => '一室公寓',	
10 => '公寓',	
11 => '宿舍间',	
12 => '宿舍间床位',	
13 => '简易别墅',	
14 => '木屋',	
15 => '别墅',	
16 => '度假屋',	
17 => '移动房屋',	
18 => '露营点',	
	
        );
    }
     
}