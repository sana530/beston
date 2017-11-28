<?php
class ShophongbaoModel extends CommonModel{
    protected $pk   = 'hongbao_id';
    protected $tableName =  'shop_hongbao';

    /* 红包领取类型分类，计算出实际领取的红包金额 */
	public function Obtain_Hongbao_Price($detail){

        if ($detail['type'] == 0) { //普通红包
            $hongbao_price = $detail['amount'];
        } elseif ($detail['type'] == 1) {   //随机红包
            $hongbao_price = rand($detail['mini_amount'], $detail['amount']);
        } else {
            $hongbao_price = 0;
        }
        return $hongbao_price;

	}
						
}
