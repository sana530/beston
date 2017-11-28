<?php
class MoneyAction extends CommonAction{
	
	public function finance(){
		$bg_time = strtotime(TODAY);
		$counts = array();
			//财务管理
		$counts['money'] = (int) D('Shopmoney')->where(array('shop_id' => $this->shop_id))->sum('money');

		$counts['money_goods'] = (int) D('Shopmoney')->where(array('type'=>goods,'shop_id' => $this->shop_id))->sum('money');
		$counts['money_tuan'] = (int) D('Shopmoney')->where(array('type'=>tuan,'shop_id' => $this->shop_id))->sum('money');
		$counts['money_ele'] = (int) D('Shopmoney')->where(array('type'=>ele,'shop_id' => $this->shop_id))->sum('money');
		$counts['money_ding'] = (int) D('Shopmoney')->where(array('type'=>ding,'shop_id' => $this->shop_id))->sum('money');
		//这个统计今日，要求统计昨日数据，+最近7天总收入。
		
		$str = '-1 day';
		$bg_time_yesterday =strtotime(date('Y-m-d',strtotime($str)));

		$counts['money_day'] = (int) D('Shopmoney')->where(array('create_time' => array(array('ELT', NOW_TIME), array('EGT', $bg_time)),'shop_id' => $this->shop_id,))->sum('money');
		//昨日总收入
		$counts['money_day_yesterday'] = (int) D('Shopmoney')->where(array('create_time' => array(array('ELT', $bg_time), array('EGT', $bg_time_yesterday)),'shop_id' => $this->shop_id,))->sum('money');						
					
		$counts['money_day_goods'] = (int) D('Shopmoney')->where(array('create_time' => array(array('ELT', NOW_TIME), array('EGT', $bg_time)),'shop_id' => $this->shop_id,'type'=>goods,))->sum('money');
				
		$counts['money_day_goods_yesterday'] = (int) D('Shopmoney')->where(array('create_time' => array(array('ELT', $bg_time), array('EGT', $bg_time_yesterday)),'shop_id' => $this->shop_id,'type'=>goods))->sum('money');
				
					
		$counts['money_day_tuan'] = (int) D('Shopmoney')->where(array('create_time' => array(array('ELT', NOW_TIME), array('EGT', $bg_time)),'shop_id' => $this->shop_id,'type'=>tuan,))->sum('money');
					
		$counts['money_day_tuan_yesterday'] = (int) D('Shopmoney')->where(array('create_time' => array(array('ELT', $bg_time), array('EGT', $bg_time_yesterday)),'shop_id' => $this->shop_id,'type'=>tuan))->sum('money');
					
		$counts['money_day_ele'] = (int) D('Shopmoney')->where(array('create_time' => array(array('ELT', NOW_TIME), array('EGT', $bg_time)),'shop_id' => $this->shop_id,'type'=>ele,))->sum('money');			
			
				
		$counts['money_day_ele_yesterday'] = (int) D('Shopmoney')->where(array('create_time' => array(array('ELT', $bg_time), array('EGT', $bg_time_yesterday)),'shop_id' => $this->shop_id,'type'=>ele))->sum('money');			
					
		$counts['money_day_ding'] = (int) D('Shopmoney')->where(array('create_time' => array(array('ELT', NOW_TIME), array('EGT', $bg_time)),'shop_id' => $this->shop_id,'type'=>ding,))->sum('money');			
					
		$counts['money_day_ding_yesterday'] = (int) D('Shopmoney')->where(array('create_time' => array(array('ELT', $bg_time), array('EGT', $bg_time_yesterday)),'shop_id' => $this->shop_id,'type'=>ding))->sum('money');	
		
		//商城待确认收货
		$counts['money_day_goods_recipient'] = (int) D('Ordergoods')->where(array('status' => 1,'is_daofu' => 0,'shop_id' => $this->shop_id))->sum('js_price');
		
		//团购待验证金额
		$Tuanorder = D('Tuanorder')->where(array('status' => 1,'shop_id' => $this->shop_id))->select();
				$order_ids = array();
				foreach ($Tuanorder as $k => $val) {
					$order_ids[$val['order_id']] = $val['order_id'];
				}
		$Tuancode['order_id'] =  array('IN', $order_ids);
		$Tuancode['status'] =  array('eq', 0);
		$Tuancode['is_used'] =  array('eq', 0);
		$Tuancode['shop_id'] = array('eq',$this->shop_id); 
		$counts['money_day_tuan_recipient'] = D('Tuancode')->where($Tuancode)->sum('settlement_price');//团购结算
		
		
		$counts['money_day_ele_recipient'] = (int) D('Eleorder')->where(array('status' => 2,'shop_id' => $this->shop_id))->sum('need_pay');
		//外卖待确认收货
			$is_pei = D('Shop')->where(array('shop_id' => $this->shop_id))->find();
			if($is_pei == 0){
					$zong = (int) D('Eleorder')->where(array('is_pay' => 1,'status' => 2,'shop_id' => $this->shop_id))->sum('need_pay');
					$logistics = (int) D('Eleorder')->where(array('is_pay' => 1,'status' => 2,'shop_id' => $this->shop_id))->sum('logistics');
					$counts['money_day_ele_recipient'] = $zong - $logistics ;
				}else{
					$counts['money_day_ele_recipient'] = (int) D('Eleorder')->where(array('is_pay' => 1,'status' => 2,'shop_id' => $this->shop_id))->sum('need_pay');
			}
			
		//统计订座
		$counts['money_day_ding_recipient'] = (int) D('Shopdingyuyue')->where(array('status' => 1,'shop_id' => $this->shop_id))->sum('need_price');
		$this->assign('counts', $counts);
		$this->display();
		
	}
    public function index(){
        $map = array('user_id' => $this->uid);
        if (($bg_date = $this->_param('bg_date', 'htmlspecialchars')) && ($end_date = $this->_param('end_date', 'htmlspecialchars'))) {
            $bg_time = strtotime($bg_date);
            $end_time = strtotime($end_date);
            $map['create_time'] = array(array('ELT', $end_time), array('EGT', $bg_time));
            $this->assign('bg_date', $bg_date);
            $this->assign('end_date', $end_date);
        } else {
            if ($bg_date = $this->_param('bg_date', 'htmlspecialchars')) {
                $bg_time = strtotime($bg_date);
                $this->assign('bg_date', $bg_date);
                $map['create_time'] = array('EGT', $bg_time);
            }
            if ($end_date = $this->_param('end_date', 'htmlspecialchars')) {
                $end_time = strtotime($end_date);
                $this->assign('end_date', $end_date);
                $map['create_time'] = array('ELT', $end_time);
            }
        }
        $Usermoneylogs = D('Usergoldlogs');
        import('ORG.Util.Page');
        $count = $Usermoneylogs->where($map)->count();
        $Page = new Page($count, 16);
        $show = $Page->show();
        $list = $Usermoneylogs->where($map)->order(array('log_id' => 'desc'))->limit($Page->firstRow . ',' . $Page->listRows)->select();
        $this->assign('list', $list);
        $this->assign('page', $show);
        $this->display();
    }
    public function shopmoney(){
        $map = array('shop_id' => $this->shop_id);
        if (($bg_date = $this->_param('bg_date', 'htmlspecialchars')) && ($end_date = $this->_param('end_date', 'htmlspecialchars'))) {
            $bg_time = strtotime($bg_date);
            $end_time = strtotime($end_date);
            $map['create_time'] = array(array('ELT', $end_time), array('EGT', $bg_time));
            $this->assign('bg_date', $bg_date);
            $this->assign('end_date', $end_date);
        } else {
            if ($bg_date = $this->_param('bg_date', 'htmlspecialchars')) {
                $bg_time = strtotime($bg_date);
                $this->assign('bg_date', $bg_date);
                $map['create_time'] = array('EGT', $bg_time);
            }
            if ($end_date = $this->_param('end_date', 'htmlspecialchars')) {
                $end_time = strtotime($end_date);
                $this->assign('end_date', $end_date);
                $map['create_time'] = array('ELT', $end_time);
            }
        }
		
		if (isset($_GET['st']) || isset($_POST['st'])) {
            $st = (int) $this->_param('st');
            if ($st == 1) {
                $map['type'] = tuan;
            }elseif($st == 2){
				$map['type'] = ele;
			}elseif($st == 3){
				$map['type'] = ding;
			}elseif($st == 4){
				$map['type'] = goods;
			}elseif($st == 5){
				$map['type'] = breaks;
			}
            $this->assign('st', $st);
        } else {
            $this->assign('st', 999);
        }
		
		
        $money = D('Shopmoney');
        import('ORG.Util.Page');// 导入分页类
        $count = $money->where($map)->count();
        $Page = new Page($count, 16);
        $show = $Page->show();
        $list = $money->where($map)->order(array('money_id' => 'desc'))->limit($Page->firstRow . ',' . $Page->listRows)->select();
		
		$shop_money_sum = $money->where($map)->sum('money');
        $this->assign('shop_money_sum',$shop_money_sum);
		
        $this->assign('list', $list);
        $this->assign('page', $show);
        $this->display();
    }
    public function tixian(){
        $user_ids = D('Shop')->where(array('shop_id' => $this->shop_id))->find();
        $user_id = $user_ids['user_id'];
        $userscash = D('Userscash')->where(array('user_id' => $user_ids['user_id']))->find();
        $user_mobile = D('Users')->where(array('user_id' => $user_ids['user_id']))->find();
        $shop = D('Shop')->where(array('user_id' => $user_id))->find();
        if ($shop == '') {
            $cash_money = $this->_CONFIG['cash']['user'];
            $cash_money_big = $this->_CONFIG['cash']['user_big'];
        } elseif ($shop['is_renzheng'] == 0) {
            $cash_money = $this->_CONFIG['cash']['shop'];
            $cash_money_big = $this->_CONFIG['cash']['shop_big'];
        } elseif ($shop['is_renzheng'] == 1) {
            $cash_money = $this->_CONFIG['cash']['renzheng_shop'];
            $cash_money_big = $this->_CONFIG['cash']['renzheng_shop_big'];
        } else {
            $cash_money = $this->_CONFIG['cash']['user'];
            $cash_money_big = $this->_CONFIG['cash']['user_big'];
        }
        if (IS_POST) {
            $gold = (int) ($_POST['gold'] * 100);
            if ($gold <= 0) {
                $this->baoError('提现金额不合法');
            }
            if ($gold < $cash_money * 100) {
                $this->baoError('提现金额小于最低提现额度');
            }
            if ($gold > $cash_money_big * 100) {
                $this->baoError('您单笔最多能提现' . $cash_money_big . '元');
            }
            if ($gold > $this->member['gold'] || $this->member['gold'] == 0) {
                $this->baoError('商户资金不足，无法提现');
            }
            if (!($data['bank_name'] = htmlspecialchars($_POST['bank_name']))) {
                $this->baoError('开户行不能为空');
            }
            if (!($data['bank_num'] = htmlspecialchars($_POST['bank_num']))) {
                $this->baoError('银行账号不能为空');
            }
            if (!($data['bank_realname'] = htmlspecialchars($_POST['bank_realname']))) {
                $this->baoError('开户姓名不能为空');
            }
            $data['bank_branch'] = htmlspecialchars($_POST['bank_branch']);
            $data['user_id'] = $this->uid;
            $arr = array();
            $arr['user_id'] = $this->uid;
            $arr['shop_id'] = $this->shop_id;
            $arr['city_id'] = $shop['city_id'];
            $arr['area_id'] = $shop['area_id'];
            $arr['money'] = $gold;
            $arr['type'] = shop;
            $arr['addtime'] = NOW_TIME;
            $arr['account'] = $this->member['account'];
            $arr['bank_name'] = $data['bank_name'];
            $arr['bank_num'] = $data['bank_num'];
            $arr['bank_realname'] = $data['bank_realname'];
            $arr['bank_branch'] = $data['bank_branch'];
            D("Userscash")->add($arr);
            D('Usersex')->save($data);
            D('Users')->Money($this->uid, -$gold, '商户申请提现，扣款');
            $this->baoSuccess('申请提现成功', U('money/index'));
        } else {
            $this->assign('info', D('Usersex')->getUserex($this->uid));
            $this->assign('gold', $this->member['gold']);
            $this->assign('cash_money', $cash_money);
            $this->assign('cash_money_big', $cash_money_big);
            $this->assign('userscash', $userscash);
            $this->display();
        }
    }
    public function tixianlog(){
        $map = array('shop_id' => $this->shop_id);
        if (($bg_date = $this->_param('bg_date', 'htmlspecialchars')) && ($end_date = $this->_param('end_date', 'htmlspecialchars'))) {
            $bg_time = strtotime($bg_date);
            $end_time = strtotime($end_date);
            $map['create_time'] = array(array('ELT', $end_time), array('EGT', $bg_time));
            $this->assign('bg_date', $bg_date);
            $this->assign('end_date', $end_date);
        } else {
            if ($bg_date = $this->_param('bg_date', 'htmlspecialchars')) {
                $bg_time = strtotime($bg_date);
                $this->assign('bg_date', $bg_date);
                $map['create_time'] = array('EGT', $bg_time);
            }
            if ($end_date = $this->_param('end_date', 'htmlspecialchars')) {
                $end_time = strtotime($end_date);
                $this->assign('end_date', $end_date);
                $map['create_time'] = array('ELT', $end_time);
            }
        }
        $Userscash = D('Userscash');
        import('ORG.Util.Page');
        $count = $Userscash->where($map)->count();
        $Page = new Page($count, 16);
        $show = $Page->show();
        $list = $Userscash->where($map)->order(array('cash_id' => 'desc'))->limit($Page->firstRow . ',' . $Page->listRows)->select();
        $this->assign('list', $list);
        $this->assign('page', $show);
        $this->display();
    }
    public function tjmonth(){
        $Shopmoney = D('Shopmoney');
        import('ORG.Util.Page');
        $count = $Shopmoney->tjmonthCount("", $this->shop_id);
        $Page = new Page($count, 15);
        $show = $Page->show();
        $list = $Shopmoney->tjmonth("", $this->shop_id, $Page->firstRow, $Page->listRows);
        $this->assign('list', $list);
        $this->assign('page', $show);
        $this->display();
    }
    public function tjday(){
        if (($bg_date = $this->_param('bg_date', 'htmlspecialchars')) && ($end_date = $this->_param('end_date', 'htmlspecialchars'))) {
            $bg_time = strtotime($bg_date);
            $end_time = strtotime($end_date) + 86400;
            $this->assign('bg_date', $bg_date);
            $this->assign('end_date', $end_date);
        } else {
            $bg_time = NOW_TIME - 86400 * 30;
            $bg_date = date('Y-m-d', $bg_time);
            $end_date = date('Y-m-d', NOW_TIME);
            $this->assign('bg_date', $bg_date);
            $this->assign('end_date', $end_date);
            $end_time = NOW_TIME + 86400;
        }
        $data = D('Shopmoney')->money($bg_time, $end_time, $this->shop_id);
        $this->assign('data', $data);
        $this->display();
    }

    /* 商家主动收款 */
    public function receipt(){

        //实例化需要用到的模型
        $scanReceipt = D('Shopreceipt');
        $receiptOrder = D('Shopreceiptorder');

        //验证商家是否开通了主动收款功能
        $where = array('shop_id'=>$this->shop_id, 'audit'=>1, 'is_open'=>1);
        $shop_receipt = $scanReceipt->where($where)->find();
        (!$shop_receipt) && $this->error('您未成功开通收款功能，请向平台申请开通');

        if ($this->isPost()) {

            //判断输入的金额
            $amount = $this->_post('amount', 'floatval');
            ($amount <= 0 || empty($amount)) && $this->baoError('您输入的金额不正确，请重新输入');

            //判断输入的barcode
            $barcode = $this->_post('barcode', 'intval');
            ($barcode <= 0 || empty($barcode)) && $this->baoError('您输入的barcode格式不正确，请重新输入');

            //查找相应订单并验证
            $order = $receiptOrder->where(array('encrypt_order_id'=>$barcode, 'error'=>-1, 'status'=>0))->find();
            if ($order) {

                (($order['create_time'] + 300) < NOW_TIME) && $this->baoError('二维码过期，请重新尝试');
                $total_price = intval($amount * 100);
                $save = array(
                    'error'=>0,
                    'shop_id'=>$this->shop_id,
                    'total_price'=>$total_price,
                    'need_pay'=>$total_price
                );
                $receiptOrder->data($save)->where(array('encrypt_order_id'=>$barcode))->save();    //写入需支付金额, 之后need_pay会因红包而变动
                S('receipt_order_'.$order['token'], $order['token'], 200); //写缓存文件，作为付款用户客户端异步轮回查询是否被扫起查询条件
                S('receipt_amount_'.$order['token'], $order['token'], 200); //写缓存文件，作为付款用户客户端异步轮回查询商家是否有输入金额查询条件
                $this->baoSuccess('请等待客人支付', U('money/receipt', array('token'=>$order['token'])));

            } else {

                $this->baoError('没有找到相应订单，请客人重新打开收款页面');

            }

        } else {

            $this->assign('token', $this->_get('token'));   //如果地址栏传入了token，则分配token
            $this->display();

        }

    }

    /* 该方法仅用于商家客户端商家收款页面，异步轮回查询客人是否已付款 */
    public function paidCheck () {

        if ($this->isAjax()) {
            $token = ($this->_post('token')) ? $this->_post('token') : NULL;
            if ($token) {

                //查询缓存中是否存在相应token的缓存
                $receiptOrder = D('Shopreceiptorder');
                if (!(S('receipt_paid_'.$token))) {

                    //对token进行解密并验证
                    $order_encryption_key = $this->CONFIG['key']['receiptorder'];
                    $token_array = explode("||", D('Passport')->encryption($token, 1, $order_encryption_key));
                    if (sizeof($token_array) != 3) {
                        $receiptOrder->data(array('error'=>3))->where(array('token'=>$token))->save();
                        die(json_encode(array('msg'=>'Hacking Attempt!', 'error'=>1, 'code'=>11)));
                    }
                    if ((intval($token_array[2]) + 3600) < NOW_TIME) {
                        $receiptOrder->data(array('status'=>2))->where(array('token'=>$token, 'status'=>0))->save();
                        S('receipt_paid_'.$token, $token, 200); //写缓存文件，作为商家收款设备异步轮询查询条件
                        die(json_encode(array('msg'=>'订单已过期', 'error'=>1, 'code'=>14)));
                    }
                    die(json_encode(array('msg'=>'Not paid yet.', 'error'=>-1)));

                } else {

                    S('receipt_paid_'.$token, NULL);  //清除缓存

                }

                //得到扫描状态信息
                $scan = $receiptOrder->field('status, order_id')->where(array('token'=>$token, 'shop_id'=>$this->shop_id))->find();
                $status = $scan['status'];

                //处理扫描信息并返回结果
                if ($scan) {
                    if ($status == 0) {
                        $result = array('msg'=>'订单未支付', 'error'=>1, 'code'=>13);
                    } elseif ($status == 1) {
                        $result = array('msg'=>'订单已支付', 'error'=>0);
                    } elseif ($status == 2) {
                        $result = array('msg'=>'订单已过期', 'error'=>1, 'code'=>14);
                    } elseif ($status == 3) {
                        $result = array('msg'=>'订单已取消', 'error'=>1, 'code'=>15);
                    }
                } else {
                    $result = array('msg'=>'This order does not exist.', 'error'=>1, 'code'=>16);
                }

                die(json_encode($result));

            }
        } else {

            $this->error('Wrong page.');

        }
    }

}