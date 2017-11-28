<?php
class MoneyAction extends CommonAction
{
    public function index(){
        $this->assign('payment', D('Payment')->getPayments(true));
        $this->display();
    }
    public function moneypay(){
        $money = (int) ($this->_post('money') * 100);
        $code = $this->_post('code', 'htmlspecialchars');
        if ($money <= 0) {
            $this->error('请填写正确的充值金额！');
        }
        if ($money > 1000000) {
            $this->error('每次充值金额不能大于1万');
        }
        $payment = D('Payment')->checkPayment($code);
        if (empty($payment)) {
            $this->error('该支付方式不存在');
        }
        $logs = array(
			'user_id' => $this->uid, 
			'type' => 'money', 
			'code' => $code, 
			'order_id' => 0, 
			'need_pay' => $money, 
			'create_time' => NOW_TIME, 
			'create_ip' => get_client_ip()
		);
        $logs['log_id'] = D('Paymentlogs')->add($logs);
        $this->assign('button', D('Payment')->getCode($logs));
        $this->assign('money', $money);
        $this->assign('logs', $logs);
        $this->display();
    }
    public function recharge(){
        //代金券充值
        if ($this->isPost()) {
            $card_key = $this->_post('card_key', 'htmlspecialchars');
            if (!D('Lock')->lock($this->uid)) {
                $this->fengmiMsg('服务器繁忙，1分钟后再试');
            }
            if (empty($card_key)) {
                D('Lock')->unlock();
                $this->fengmiMsg('充值卡号不能为空');
            }
            if (!($detail = D('Rechargecard')->where(array('card_key' => $card_key))->find())) {
                D('Lock')->unlock();
                $this->fengmiMsg('该充值卡不存在');
            }
            if ($detail['is_used'] == 1) {
                D('Lock')->unlock();
                $this->fengmiMsg('该充值卡已经使用过了');
            }
            $member = D('Users')->find($this->uid);
            $member['money'] += $detail['value'];
            if (D('Users')->save(array('user_id' => $this->uid, 'money' => $member['money']))) {
                D('Usermoneylogs')->add(array(
					'user_id' => $this->uid, 
					'money' => +$detail['value'], 
					'create_time' => NOW_TIME, 
					'create_ip' => get_client_ip(), 
					'intro' => '代金券充值' . $detail['card_id']
				));
                $res = D('Rechargecard')->save(array('card_id' => $detail['card_id'], 'is_used' => 1));
                if (!empty($res)) {
                    D('Rechargecard')->save(array('card_id' => $detail['card_id'], 'user_id' => $this->uid, 'used_time' => NOW_TIME));
                }
                $this->fengmiMsg('充值成功！', U('money/recharge'));
            }
            D('Lock')->unlock();
        } else {
            $this->display();
        }
    }
	
	 //积分兑换余额
      public function exchange(){
        if($this->isPost()){
			$config = D('Setting')->fetchAll();
			$integral_buy = $config['integral']['buy'];
			//判断积分设置是否合法
			if (false == D('Users')->check_integral_buy($integral_buy)) {
				$this->fengmiMsg('网站后台积分设置不合法，请联系管理员');
			}
			
            $exchange = (int)$this->_post('exchange');
			if($exchange <=0){
                $this->fengmiMsg('要兑换的数量不能为空！');
            }
			$scale  = D('Users')->obtain_integral_scale($integral_buy);//获取积分比例便于同步
			
			//批量检测积分兑换余额批量代码封装
			if (!D('Users')->check_integral_exchange_legitimate($exchange,$scale)) {
				$this->fengmiMsg(D('Users')->getError());	  
			}
	
            if($this->member['integral'] < $exchange*$scale){
                $this->fengmiMsg('账户积分不足');
            }
			$actual_integral = $exchange*$scale;
			$money = $actual_integral - intval(($actual_integral*$config['integral']['integral_exchange_tax'])/100);
			if($money > 0){
				if(D('Users')->addMoney($this->uid,$money,'积分兑换现金')){
					D('Users')->addIntegral($this->uid,-$exchange,'扣除兑换余额使用积分');          
				} 
			}
            $this->fengmiMsg('您成功兑换余额'.round($money/100,2).'元',U('logs/moneylogs')); 
        }else{
             $this->display();
        }
    }

    //好友转账
    public function transfer(){
        if($this->isPost()){
            $config = D('Setting')->fetchAll();
            $obj = D('Usertransferlogs');
            $cash_is_transfer = $config['cash']['is_transfer'];

            //判断网站后台设置是否合法
            if (false == $obj->check_admin_is_transfer($cash_is_transfer)) {
                $this->fengmiMsg('网站后台设置不合法，请联系管理员');
            }

            //检测被赠送的用户手机封装
            $mobile = $this->_post('mobile');
            if (false == $obj->check_transfer_user_mobile($mobile,$this->member['mobile'])) {
                $this->fengmiMsg($obj->getError());
            }

            //检测余额小于0，用户余额是不是不足，超过最大限制，最小限制，检测用户转账间隔时间
            $money = (int)(($this->_post('money'))*100);

            if (false == $obj->check_transfer_user_money($money,$this->uid)) {
                $this->fengmiMsg($obj->getError());
            }

            /*$yzm = $this->_post('yzm');
            if (empty($mobile) || empty($yzm))
                $this->fengmiMsg('请填写正确的手机及手机收到的验证码！');
            $session_mobile = session('mobile');
            $session_code = session('code');
            if ($this->member['mobile'] != $session_mobile)
                $this->fengmiMsg('手机号码和收取验证码的手机号不一致！');
            if ($yzm != $session_code){
                $this->fengmiMsg('Incorrect verification code');
            }*/

            if(!empty($config['cash']['is_transfer_commission'])){
                $commission = intval(($money*$config['cash']['is_transfer_commission'])/100);
                $receive_money = $money + $commission ;//实际扣除
            } else {
                $receive_money = $money;
            }

            //获取接收的USER
            $users = $obj->get_receive_users($mobile);
            $intro = $this->member['nickname'].'给您转账了'.round($money/100,2).'元';
            $intro1 = $this->member['nickname'].'给'.$users['nickname'].'转账了'.round($money/100,2).'元，手续费'.round($commission/100,2).'元';
            if($money > 0){
                if(D('Users')->addMoney($users['user_id'],$money,$intro)){
                    $logs = array();
                    $logs['user_id'] = $this->uid;
                    $logs['uid'] = $users['user_id'];
                    $logs['money'] = $money;
                    $logs['commission'] = $commission;
                    $logs['intro'] = $intro1;
                    $logs['create_time'] = time();
                    $logs['create_ip'] = get_client_ip();
                    $log_id = $obj->add($logs);
                    if($log_id){
                        $intro2 = '您给'.$users['nickname'].'转账了'.round($money/100,2).'元，手续费'.round($commission/100,2).'元';
                        if(D('Users')->addMoney($this->uid,-$receive_money,$intro2)){
                            D('Weixintmpl')->weixin_transfer_balance_user($log_id);//会员账户余额变动通知双方
                            $this->fengmiMsg('恭喜您转账成功',U('member/index'));
                        }else{
                            $this->fengmiMsg('Failed');
                        }
                    }else{
                        $this->fengmiMsg('Failed');
                    }
                }
            }

        }else{
            //扫描收款码后的相应处理
            $token = isset($_GET['token']) ? $this->_get('token') : NULL;
            if ($token) {

                $token_array = explode("||", D('Passport')->encryption($token, 1));
                (sizeof($token_array) != 4) && ($this->error('Hacking Attempt!'));
                $token_array[3] = D('Passport')->encryption($token_array[3], 1);
                (($token_array[0] == 'user') && ((intval($token_array[3]) + 60) < time())) && ($this->error('QRcode Expired!'));
                (($token_array[0] == 'shangjia') && ((intval($token_array[3]) + 60 * 60 * 24 * 365 * 10) < time())) && ($this->error('QRcode Expired!'));
                ($token_array[0] == 'supplier') && $this->assign('collect_type', 'shangjia');
                $user = D('Users')->where(array('user_id'=>$token_array[1]))->field('nickname','mobile','closed','pay_type')->find();
                (!$user['mobile']) && $this->error('该用户未绑定手机');
                ($user['closed'] != 0) && $this->error('This user does not exist any more.');
                $this->assign('user', $user);

            }
            $this->display();
        }
    }

    //检测手机号合法
    public function check_mobile(){
        $mobile = $this->_get('mobile');
        if(!empty($mobile)){
            $count_mobile = D('Users')->where(array('mobile' => $mobile))->count();
            if($count_mobile == 1){
                $user = D('Users')->where(array('mobile' => $mobile))->find();//这个版本不加手机号
                if (empty($user) || $user['mobile'] == $this->member['mobile']) {
                    echo '0';
                } else {
                    echo '您转账该对方昵称是'.'<font size="8" color="#F00">'.$user['nickname'].'</font>'.'请核对后转账，转账后无法退款';
                }
            }else{
                echo '0';
            }
        }else{
            echo '0';
        }

    }

    public function collectioncode(){
        $user_id = getUid();
        $user_name = D('Users')->where(array('user_id'=>$user_id))->getField('nickname');
        $shangjia = D('Shop')->where(array('user_id'=>$user_id))->field('shop_id,shop_name,closed,create_time')->find();

        if ($shangjia && ($shangjia['closed'] == 0)) {

            $token = D('Passport')->encryption("shangjia||".$user_id."||||".D('Passport')->encryption($shangjia['create_time'].''));
            $token = 'http://'.$_SERVER['HTTP_HOST'].'/mcenter/money/transfer/token/'.$token;
            $token_img = baoQrCode($token,$token);
            $token_url = "<img src='http://".$_SERVER['HTTP_HOST']."/attachs/".$token_img."' style='width:80%; margin-left:10%; margin-top:30px;'>";
            $this->assign('token_url', $token_url);
            $this->assign('shangjia', $shangjia);

        } else {

            $token = D('Passport')->encryption("user||".$user_id."||||".D('Passport')->encryption(time().''));
            $token = 'http://'.$_SERVER["HTTP_HOST"].'/mcenter/money/transfer/token/'.$token;
            $token_img = baoQrCode($token,$token);
            $token_url = "<img src='http://".$_SERVER['HTTP_HOST']."/attachs/".$token_img."' style='width:80%; margin-left:10%; margin-top:30px;'>";
            $this->assign('token_url', $token_url);

        }

        $this->assign('user_name', $user_name);
        $this->display();
    }

    /* 显示商家收款二维码页面,生成一条商家收款订单记录 */
    public function receipt () {

        $receiptOrder = D('Shopreceiptorder');
        $order_encryption_key = $this->_CONFIG['key']['receiptorder'];
        $encryption = D('Passport')->encryption("receipt||".rand(100,999)."||".NOW_TIME, 0, $order_encryption_key);

        //插入一条待扫描记录
        $data = array(
            'user_id'=>$this->uid,
            'token'=>$encryption,
            'encrypt_order_id'=>NOW_TIME, //encrypt_order_id是唯一的，先放入时间戳，之后得到插入的order_id后再加密更新
            'create_time'=>NOW_TIME,
            'create_ip'=>get_client_ip()
        );
        $order_id = $receiptOrder->data($data)->add();

        //更新加密后的order_id
        $encrypt_order_id = D('Crypter')->encrypt($order_id, 'cfb');
        $receiptOrder->where(array('order_id'=>$order_id))->data(array('encrypt_order_id'=>$encrypt_order_id))->save();

        //生成二维码图片
        $token = __HOST__ . U('store/money/receipt', array('token'=>$encryption));
        $token_img = baoQrCode('receipt_order_'.$encryption, $token);
        $token_url = "<img src='http://".$_SERVER['HTTP_HOST']."/attachs/".$token_img."' style='width:20%; margin-left:40%; margin-top:30px;'>";

        //模板分配及显示
        $this->assign('token_url', $token_url);
        $this->assign('encryption', $encryption);
        $this->assign('encrypt_order_id', $encrypt_order_id);
        $this->display();

    }

    /* 该方法仅用于客户端商家收款二维码页面，异步轮回查询商家是否将该单扫起 */
    public function receiptCheck () {

        if ($this->isAjax()) {
            $token = ($this->_post('token')) ? $this->_post('token') : NULL;
            if ($token) {

                //查询缓存中是否存在相应token的缓存
                $receiptOrder = D('Shopreceiptorder');
                if (!(S('receipt_order_'.$token))) {

                    //对token进行解密并验证
                    $order_encryption_key = $this->_CONFIG['key']['receiptorder'];
                    $token_array = explode("||", D('Passport')->encryption($token, 1, $order_encryption_key));
                    if (sizeof($token_array) != 3) {
                        $receiptOrder->data(array('error'=>3))->where(array('token'=>$token))->save();
                        die(json_encode(array('msg'=>'Hacking Attempt!', 'error'=>1, 'code'=>11)));
                    }
                    if ((intval($token_array[2]) + 300) < NOW_TIME) {
                        $receiptOrder->data(array('error'=>2))->where(array('token'=>$token))->save();
                        die(json_encode(array('msg'=>'QRcode Expired!', 'error'=>1, 'code'=>12)));
                    }
                    die(json_encode(array('msg'=>'Not scan yet.', 'error'=>-1)));

                } else {

                    S('receipt_order_'.$token, NULL);  //清除缓存

                }

                //得到扫描状态信息
                $scan = $receiptOrder->field('error, order_id')->where(array('token'=>$token))->find();
                $error = $scan['error'];

                //处理扫描信息并返回结果
                if ($scan) {
                    if ($error == 0) {
                        $result = array('msg'=>'Scanned Successful!', 'error'=>0);
                    } elseif ($error == 2) {
                        $result = array('msg'=>'QRcode Expired!', 'error'=>1, 'code'=>12);
                    } elseif ($error == 3) {
                        $result = array('msg'=>'Hacking Attempt!', 'error'=>1, 'code'=>11);
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

    /* 该方法仅用于客户端商家收款二维码页面，异步轮回查询商家输入金额状态 */
    public function receiptAmountCheck(){
        if ($this->isAjax()) {
            $token = ($this->_post('token')) ? $this->_post('token') : NULL;
            if ($token) {

                //查询缓存中是否存在相应token的缓存
                $receiptOrder = D('Shopreceiptorder');
                if (!(S('receipt_amount_'.$token))) {

                    //对token进行解密并验证
                    $order_encryption_key = $this->_CONFIG['key']['receiptorder'];
                    $token_array = explode("||", D('Passport')->encryption($token, 1, $order_encryption_key));
                    if (sizeof($token_array) != 3) {
                        $receiptOrder->data(array('error'=>3))->where(array('token'=>$token))->save();
                        die(json_encode(array('msg'=>'Hacking Attempt!', 'error'=>1, 'code'=>11)));
                    }
                    if ((intval($token_array[2])+3600) < NOW_TIME) {
                        $receiptOrder->data(array('status'=>2))->where(array('token'=>$token, 'status'=>0))->save();
                        die(json_encode(array('msg'=>'This order has expired.', 'error'=>1, 'code'=>14)));
                    }
                    die(json_encode(array('msg'=>'Not enter amount yet.', 'error'=>-1)));

                } else {

                    S('receipt_amount_'.$token, NULL);    //清除缓存

                }

                //得到订单信息
                $order = $receiptOrder->field('status,order_id')->where(array('token'=>$token, 'error'=>0))->find();

                //处理返回订单状态
                if($order) {

                    if ($order['status'] == 0) {
                        $result = array('msg'=>'Successful!', 'error'=>0);
                    } elseif ($order['status'] == 1) {
                        $result = array('msg'=>'This order has already been paid.', 'error'=>1, 'code'=>19);
                    } elseif ($order['status'] == 2) {
                        $result = array('msg'=>'This order has expired.', 'error'=>1, 'code'=>14);
                    } elseif ($order['status'] == 3) {
                        $result = array('msg'=>'This order has been canceled.', 'error'=>1, 'code'=>15);
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

    /* 商家输入金额后,用户进入支付页面 */
    public function receiptPay () {

        $token = ($this->_get('token'))? $this->_get('token') : NULL;
        $receiptOrder = D('Shopreceiptorder');

        if ($token) {

            //对token进行解密并验证
            $order_encryption_key = $this->_CONFIG['key']['receiptorder'];
            $token_array = explode("||", D('Passport')->encryption($token, 1, $order_encryption_key));
            if (sizeof($token_array) != 3) {
                $receiptOrder->data(array('error'=>3))->where(array('token'=>$token))->save();
                die(json_encode(array('msg'=>'Hacking Attempt!', 'error'=>1, 'code'=>11)));
            }
            $order = $receiptOrder->where(array('token'=>$token))->find();
            if ($order) {
                //判断订单是否过期
                if (($order['create_time'] + 3600) < NOW_TIME) {
                    $receiptOrder->data(array('status'=>2))->where(array('token'=>$token))->save();
                    $this->error('二维码过期，请重新下单。');
                }

                //判断二维码是否被支付过
                ($order['status'] !=0) && $this->error('该订单已被使用过');

                //查询订单商家来源
                $shop_name = D('Shop')->where(array('shop_id'=>$order['shop_id']))->getField('shop_name');

                //检测该用户是否有该商家可用的红包(在最终payment/payment页面会做再次检测红包合法性)
                $where = array('user_id'=>$this->uid, 'shop_id'=>$order['shop_id'], 'is_used'=>0);
                $hongbao = D('Shophongbaoreceive')->where($where)->find();
                if ($hongbao) {

                    $hongbao_where = array('hongbao_id'=>$hongbao['hongbao_id'], 'expire_date'=>array('gt', TODAY), 'closed'=>0);
                    $hongbao_valid = D('Shophongbao')->where($hongbao_where)->getField();
                    if ($hongbao_valid) {

                        //判断红包金额是否大于支付总金额。是的话需支付金额为0.01NZD
                        if ($hongbao['hongbao_price'] < $order['total_price']) {
                            $need_pay = $order['total_price'] - $hongbao['hongbao_price'];
                        } else {
                            $need_pay = 1;
                        }

                        //将红包信息插入到订单表中（后期如果检测到红包不合法，则信息会被删除）
                        $data = array(
                            'receive_id'=>$hongbao['receive_id'],
                            'hongbao_price'=>$hongbao['hongbao_price'],
                            'need_pay'=>$need_pay
                        );
                        $receiptOrder->data($data)->where(array('token'=>$token))->save();

                        //更新分配信息
                        $order['receive_id'] = $hongbao['receive_id'];
                        $order['hongbao_price'] = $hongbao['hongbao_price'];
                    }

                }

                //扫描成功，进入付款页面
                $this->assign('shop_name', $shop_name);
                $this->assign('order', $order);
                $this->assign('payment', D('Payment')->getPayments());
                $this->display();

            } else {

                $this->error('未找到该订单');

            }

        } else {

            $this->error('缺少token信息');

        }

    }

    /* 创建支付订单到Payment log中 */
    public function receiptPay2 () {

        $user_id = $this->uid;
        if (empty($user_id)) {
            $this->ajaxLogin();
        }
        if (!$this->member['mobile']) {
            $this->fengmiMsg('请先绑定手机号码再提交！');
        }
        $order_id = (int) $this->_get('order_id');
        $receiptOrder = D('Shopreceiptorder');
        $order = $receiptOrder->find($order_id);
        if (empty($order) || $order['status'] != 0 || $order['user_id'] != $user_id) {
            $this->fengmiMsg('该订单不存在');
        }
        if (!($code = $this->_post('code'))) {
            $this->fengmiMsg('请选择支付方式！');
        }

        $payment = D('Payment')->checkPayment($code);
        if (empty($payment)) {
            $this->error('该支付方式不存在');
        }

        $need_pay = $order['need_pay'];

        //检查相应的payment log是否存在，存在的话更新need_pay以及支付code，不存在则插入
        $logs = D('Paymentlogs')->getLogsByOrderId('receipt', $order_id);
        if (empty($logs)) {
            $logs = array(
                'type' => 'receipt',
                'user_id' => $user_id,
                'order_id' => $order_id,
                'code' => $code,
                'need_pay' => $need_pay,
                'create_time' => NOW_TIME,
                'create_ip' => get_client_ip(),
                'is_paid' => 0
            );
            $logs['log_id'] = D('Paymentlogs')->add($logs);
        } else {
            $logs['need_pay'] = $need_pay;
            $logs['code'] = $code;
            D('Paymentlogs')->save($logs);
        }
        $this->fengmiMsg('选择支付方式成功！下面请进行支付！', U('mobile/payment/payment', array('log_id' => $logs['log_id'])));

    }

}