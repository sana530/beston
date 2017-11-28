<?php
class ShopreceiptorderModel extends CommonModel{
    protected $pk   = 'order_id';
    protected $tableName =  'shop_receipt_order';
    public $status = array(
        0 => 'The order has not been paid',
        1 => 'The order has been paid',
        2 => 'The order has expired',
        3 => 'The order\'s status is abnormal '
    );
    public $settled = array(
        0 => 'The order has not been settled to the merchant',
        1 => 'The order has been settled to the merchant',
    );

    /* 订单完成后处理 */
    public function overOrder($order_id) {
        $detail = $this->find($order_id);
        if (empty($detail) || $detail['status'] != 1)
            return false;
        $shop = D('Shop')->find($detail['shop_id']);

        //算出商家结算价
        $charge_rate = D('Shopreceipt')->where(array('shop_id'=>$detail['shop_id']))->getField('charge_rate');
        $settlement_price = (int) $detail['need_pay'] * (1 - $charge_rate/100);

        if ($this->save(array('order_id' => $order_id, 'is_settled' => 1, 'settlement_price'=>$settlement_price, 'settlement_time' => NOW_TIME))) { //防止并发请求

            if ($settlement_price > 0) {
                //记录商家资金日志
                D('Shopmoney')->add(array(
                    'shop_id' => $detail['shop_id'],
                    'city_id' => $shop['city_id'],
                    'area_id' => $shop['area_id'],
                    'type' => 'receipt',
                    'money' => $settlement_price ,
                    'create_ip' => get_client_ip(),
                    'create_time' => NOW_TIME,
                    'order_id' => $order_id,
                    'intro' => '商家收款订单:' . $order_id
                ));

                D('Users')->Money($shop['user_id'], $settlement_price, '商户收款资金结算:' . $order_id);    //写入商家用户金块
            }

            //更新商家收款使用数
            $shop_id = D('Shopreceiptorder')->where(array('order_id'=>$order_id))->getField('shop_id');
            D('Shopreceipt')->where(array('shop_id'=>$shop_id))->setInc('use_count');

            //如果使用了红包，将该红包设置为已用，更新商家红包的使用数量
            if ($detail['receive_id']) {
                $data = array('is_used'=>1, 'used_time'=>NOW_TIME, 'used_ip'=>get_client_ip());
                $hongbaoReceive = D('Shophongbaoreceive');
                $hongbaoReceive->data($data)->where(array('receive_id'=>$detail['receive_id']))->save();
                $hongbao_id= $hongbaoReceive->where(array('receive_id'=>$detail['receive_id']))->getField('hongbao_id');
                D('Shophongbao')->where(array('hongbao_id'=>$hongbao_id))->setInc('used_num');
            }

            S('receipt_paid_'.$detail['token'], $detail['token'], 200); //写缓存文件，作为商家收款设备异步轮询查询条件
        }
        return true;

    }

}