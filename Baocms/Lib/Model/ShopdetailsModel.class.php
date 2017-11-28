<?php



class ShopdetailsModel extends CommonModel{
    protected $pk   = 'shop_id';
    protected $tableName =  'shop_details';
     public function upDetails($shop_id,$data){
        $shop_id = (int)$shop_id;
        $data['shop_id'] = $shop_id;
        $rows = $this->find($shop_id);
        if($rows){
            $this->save($data);
        }else{
            $this->add($data);
        }
        return true;
    }

    //重新处理得到的商铺详情数据，处理工作时间
    public function changeBTime($value) {
        $business_time = explode("|",$value['business_time']);
        $xingqi = date('N', time());

        if (sizeof($business_time) > 1) {
            if (empty($business_time[$xingqi-1])) {
                $business_time[$xingqi-1] = '休息';
            }
            $value['business_time'] = '周'.$this->hanzi($xingqi).': '.$business_time[$xingqi-1];
            for ($i=0; $i<7; $i++) {
                if (empty($business_time[$i])) {
                    $business_time[$i] = '休息';
                }
                $value['business_times'][$i] = '周'.$this->hanzi($i+1).': '.$business_time[$i];
            }
        }
        return $value;
    }

    //把数字改成汉字
    private function hanzi($xingqi){
         $xingqi = intval($xingqi);
        switch ($xingqi) {
            case "1":
                $hanzi = '一';
                break;
            case "2":
                $hanzi = '二';
                break;
            case "3":
                $hanzi = '三';
                break;
            case "4":
                $hanzi = '四';
                break;
            case "5":
                $hanzi = '五';
                break;
            case "6":
                $hanzi = '六';
                break;
            default:
                $hanzi = '日';
        }
        return $hanzi;
    }

}