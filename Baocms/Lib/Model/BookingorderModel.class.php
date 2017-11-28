<?php

class BookingorderModel extends CommonModel{
    protected $pk   = 'order_id';
    protected $tableName =  'booking_order';
	
    public function getStatus(){
        return array(
            -1 => '已取消',
            0  => '未付款',
            1  => '已付款',
            2  => '已完成',
        );
    }

    

    public function cancel($order_id){
        if(!$order_id = (int)$order_id){
            return false;
        }elseif(!$detail = $this->find($order_id)){
            return false;
        }else{
            if($detail['order_status'] ==1||$detail['order_status'] ==0){
                if(false !== $this->save(array('order_id'=>$order_id,'order_status'=>-1,'update_time'=>NOW_TIME))){
                    if($detail['order_status'] == 1){
                        D('Users')->addMoney($detail['user_id'],(int)$detail['amount'],'订座订单取消,ID:'.$order_id.'，返还定金');
                    }
                    return true;
                }else{
                    return false;
                }
            }else{
                return false;
            }
            
        }                                 
    }
     
    public function plqx($shop_id){
        if($shop_id = (int)$shop_id){
            $ntime = date('H:i',NOW_TIME);
            $order = $this->where("`shop_id` = ".$shop_id." AND `ding_date` <".TODAY." OR (`ding_date` =".TODAY." AND `ding_time` <".$ntime.") ")->select();
            foreach ($order as $k=>$val){
                $this->cancel($val['order_id']);
            }
            return true;
        }else{
            return false;
        }
    }
    
    public function complete($order_id){
        if(!$order_id = (int)$order_id){
            return false;
        }elseif(!$detail = $this->find($order_id)){
            return false;
        }else{
            $shop = D('Shop')->find($detail['shop_id']);
			$info = '订座结算，订单号：'.$order_id;
            if($detail['order_status'] == 1){
                if(false !== $this->save(array('order_id'=>$order_id,'order_status'=>2,'update_time'=>NOW_TIME))){
                    D('Users')->Money($shop['user_id'], $detail['amount'], '订座订单完成,ID:'.$order_id.'，结算定金'); //结算金额如需设置改这里
					D('Shopmoney')->add(array(
						'shop_id' => $shop['shop_id'], 
						'city_id' => $shop['city_id'], 
						'area_id' => $shop['area_id'], 
						'money' => $detail['amount'], 
						'create_time' => NOW_TIME, 
						'create_ip' => get_client_ip(),
						'type' => 'booking', 
						'order_id' => $order_id, 
						'intro' => $info
					 ));
                    return true;
                }else{
                    return false;
                }
            }else{
                return false;
            }  
        }  
    }

public function get_ding($shop_id,$list){
		$dings = $arr = $rooms = $tmp =  array();
		if($list){
			foreach($list as $k => $v){
				$dings[] = $v['ding_id'];
			}
		}
		$Cfg = D('Bookingsetting')->getCfg();
		$type = D('Bookingroom')->getType();
		$arr = D('Bookingyuyue')->itemsByIds($dings);
		$room = D('Bookingroom')->where('shop_id = '.$shop_id)->select();
		foreach($room as $k => $v){
			$rooms[$v['room_id']] = $v;
		}
		foreach($arr as $k => $v){
			if($v['room_id'] == 0){
				$arr[$k]['room_id'] = '大厅';
			}else{
				$arr[$k]['room_id'] = $rooms[$v['room_id']]['name'];
			}
			$arr[$k]['last_t'] = $Cfg[$v['last_t']];
			$arr[$k]['number'] = $type[$v['number']];
		}
		return $arr;
	}

	public function get_detail($shop_id,$order,$yuyue){
		$Cfg = D('Bookingsetting')->getCfg();
		$type = D('Bookingroom')->getType();
		$room = D('Bookingroom')->where('shop_id = '.$shop_id)->select();
		foreach($room as $k => $v){
			$rooms[$v['room_id']] = $v;
		}
		if($yuyue['room_id'] == 0){
			$yuyue['room_id'] = '大厅';
		}else{
			$yuyue['room_id'] = $rooms[$yuyue['room_id']]['name'];
		}
		$yuyue['last_t'] = $Cfg[$yuyue['last_t']];
		$yuyue['number'] = $type[$yuyue['number']];
		$arr = array_merge($yuyue,$order);
		
		$a = substr($arr['menu'],0,-1);
		$arr1 = explode('|',$a);
		foreach($arr1 as $k => $v){
			$arr2[] = explode(':',$v);
		}
		$arr['menu'] = $arr2;
		return $arr;
	}

	public function get_d($yuyue){
		$Cfg = D('Bookingsetting')->getCfg();
		$type = D('Bookingroom')->getType();
		$tem =array();
		foreach($yuyue as $k => $v){
			$yuyue[$k]['last_t'] = $Cfg[$v['last_t']];
			$yuyue[$k]['number'] = $type[$v['number']];
		}
		
		return $yuyue;
	}

    public function ding_print($order_id,$addr_id) {
        $order_id = (int) $order_id;
        $addr_id = (int) $addr_id;
        $order = D('Bookingorder')->find($order_id);
        if (empty($order))//没有找到订单返回假
            return false;
        $member = D('Users')->find($order['user_id']);//会员信息
        if(!empty($addr_id)){
            $addr_id = $addr_id;
        }else{
            $addr_id = $order['addr_id'];
        }
        $shop_print = D('Shop')->where(array('shop_id'=> $order['shop_id']))->find();//商家信息
        $msg .= '@@2订座清单__________NO:' . $order['order_id'] . '\r';
        $msg .= '店名：' . $shop_print['shop_name'] . '\r';
        $msg .= '联系人：' . $order['name'] . '\r';
        $msg .= '电话：' . $order['mobile'] . '\r';
        $msg .= '订位人数：' . $order['ding_num'] . '\r';
        $msg .= '订位费用：' . $order['amount']/100 .'元' . '\r';
        $msg .= '订位日期：' . $order['ding_date'] . '\r';
        $msg .= '订位时间：' . $order['ding_time'] . '\r';
        $msg .= '用餐地址：' . $shop_print['addr'] . '\r';
        $msg .= '商家电话：' . $shop_print['tel'] . '\r';
        $msg .= '----------------------\r';
        $msg .= '@@2菜品明细\r';
        $products = D('Bookingordermenu')->where(array('order_id' => $order['order_id']))->select();
        foreach ($products as $key => $value) {
            $product = D('Bookingmenu')->where(array('menu_id' => $value['menu_id']))->find();
            $msg		  .= ($key+1).'.'.$product['menu_name'].'—'.($product['ding_price']/100).'元'.'*'.$value['num'].'份\r';
        }
        $msg .= '----------------------\r';
        $msg .= '菜品金额：' . $order['menu_amount'] / 100 . '元\r';
        $msg .= '留言：'.$order['note'].'\r';

        return $msg;//返回数组
    }
    
}