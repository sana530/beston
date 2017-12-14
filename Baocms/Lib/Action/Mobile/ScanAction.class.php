<?php

/**
 * 声明：开发者开发扫一扫登陆以及扫一扫支付接口，旨在帮助公司扩大规模，扩大影响力，扩大营业范围。程序员尽全力优化代码，
 * 减少bug，减少被黑客入侵的可能性，但因技术能力限制，无法保证100%完美，无法保证100%安全。
 * 一切相关的法律责任（包括合法性，安全性等），开发者均不予承担。
 */
class ScanAction extends CommonAction
{
    public function _initialize()
    {
        parent::_initialize();
        if ($this->_CONFIG['operation']['scan'] == 0) {
            $this->error('此功能已关闭');
            die;
        }
    }

    /*显示扫描二维码登录页面*/
    public function login() {

        $key = $this->_get('key');
        $backurl = ($this->_get('backurl'))? base64_decode($this->_get('backurl')) : 'http://'.$_SERVER['HTTP_HOST'];

        //检查是否有授权密钥，以及密钥是否仍在生效
        $checkkey = D('Scanloginkey')->checkkey($key,$backurl);
        if ($checkkey['error'] != 0) {
            $this->error($checkkey['errormsg']);
        }
        $domain = empty($checkkey['domain']) ? $_SERVER['HTTP_HOST'] : $checkkey['domain'];

        $scanlogin = D('Scanlogin');
        //删除数据库中过期的未被扫描的记录
        $map['application_time'] = array('LT', NOW_TIME-3600);
        $map['user_id'] = array('EQ', 0);
        $encryption = D('Passport')->encryption("login||".rand(100,999)."||".time());
        $scanlogin->where($map)->delete();

        //插入一条待扫描记录
        $data = array('token'=>$encryption,
            'application_time'=>NOW_TIME,
            'key'=>$key,
            'backurl'=>$backurl
            );
        $scanlogin->data($data)->add();

        //生成二维码图片
        $token = 'http://'.$_SERVER['HTTP_HOST'].'/mobile/scan/loginact/token/'.$encryption;
        $token_img = baoQrCode('scan_login_'.$encryption,$token);
        $token_url = "<img src='http://".$_SERVER['HTTP_HOST']."/attachs/".$token_img."' style='width:20%; margin-left:40%; margin-top:30px;'>";

        //模板分配及显示
        $this->assign('token_url', $token_url);
        $this->assign('encryption', $encryption);
        $this->assign('backurl', $backurl);
        $this->assign('domain', $domain);
        $this->display();

    }

    /*扫描登陆二维码页面，1stPay PC端异步轮回查询扫描状态*/
    public function logincheck(){

        if ($this->isAjax()) {
            $token = ($this->_post('token')) ? $this->_post('token') : NULL;
            if ($token) {

                //查询缓存中是否存在相应token的缓存
                if (!(S('scan_login_'.$token))) {
                    die(json_encode(array('content'=>'Not scan yet.', 'error'=>-1)));
                } else {
                    S('scan_login_'.$token, NULL);  //清除相应缓存
                }

                //对token进行解密并验证
                $scanlogin = D('Scanlogin');
                $token_array = explode("||", D('Passport')->encryption($token, 1));
                if (sizeof($token_array) != 3) {
                    $scanlogin->where(array('token'=>$token))->delete();
                    die(json_encode(array('content'=>'Hacking Attempt!', 'error'=>1)));
                }
                if ((intval($token_array[2]) + 180) < NOW_TIME) {
                    $scanlogin->data(array('error'=>2))->where(array('token'=>$token))->save();
                    die(json_encode(array('content'=>'QRcode Expired!', 'error'=>2)));
                }

                //得到扫描登陆相关信息
                $scan = $scanlogin->where(array('token'=>$token))->find();
                $user_id = $scan['user_id'];
                if ($user_id) {
                    $closed = D('Users')->where(array('user_id'=>$user_id))->getField('closed');
                    if (($closed == NULL) || ($closed != 0)) {
                        $scanlogin->data(array('error'=>3))->where(array('token'=>$token))->save();
                        die(json_encode(array('content'=>'您的用户状态不正常，请联系1stPay客服', 'error'=>3)));
                    }
                    $scanlogin->data(array('error'=>0))->where(array('token'=>$token))->save();
                    die(json_encode(array('content'=>'Successful!', 'error'=>0)));
                }

            }
        } else {
            $this->error('Wrong page');
        }

    }

    /*扫描设备扫描后*/
    public function loginact(){

        $user_id = getUid();
        if (empty($user_id)) {
            cookie('backurl',$_SERVER['REQUEST_URI']);
            header("Location: " . u("mobile/passport/login"));
            exit;
        }
        $token = ($this->_get('token'))? $this->_get('token') : NULL;
        $scanlogin = D('Scanlogin');

        if ($token) {

            $token_array = explode("||", D('Passport')->encryption($token, 1));		//token解密
            (sizeof($token_array) != 3) && die(json_encode(array('content'=>'Hacking Attempt!', 'error'=>1)));
            if ((intval($token_array[2]) + 180) < NOW_TIME) {
                $this->error('二维码过期，请重新尝试');
            }
            $scan = $scanlogin->where(array('token'=>$token))->find();
            if ($scan) {
                //判断二维码是否被扫描过
                ($scan['user_id'] !=0 && $scan['user_id'] != '') && $this->error('该二维码已被使用过');
                //更新表，将扫描者user_id写入
                $scanlogin->data(array('user_id'=>$user_id))->where(array('token'=>$token))->save();
                //写缓存文件，作为被扫描设备异步轮询查询条件
                S('scan_login_'.$token,$token,200);
                //登陆成功，返回用户个人页面
                $this->success('登陆成功', U('mobile/index/index'));
            }

        } else {

            $this->error('缺少token信息');

        }

    }

    /*查询用户信息API*/
    public function getinfo() {

        //检查是否有授权密钥，以及密钥是否仍在生效
        $key = $this->_get('key');
        $checkkey = D('Scanloginkey')->checkkey($key);
        if ($checkkey['error'] != 0) {
            $this->error($checkkey['errormsg']);
        }
        //检查是否有token值，以及是否有相关记录
        $token = $this->_get('token');
        if (empty($token)) {
            die(json_encode(array('content'=>'缺少token信息', 'error'=>1)));
        }
        $scan = D('Scanlogin')->where(array('token'=>$token))->find();
        if (!$scan) {
            die(json_encode(array('content'=>'没有相关信息或信息已过期', 'error'=>1)));
        }
        if ($scan['error'] != 0) {
            if ($scan['error'] == -1) {
                $errormsg = '未被扫描';
            } elseif ($scan['error'] == 2) {
                $errormsg = '二维码已过期';
            } elseif ($scan['error'] == 3) {
                $errormsg = '用户信息状态不正常';
            }
            die(json_encode(array('content'=>$errormsg, 'error'=>1, 'status'=>$scan['error'])));
        }

        //取得用户相关信息, 并传出
        $data = D('Users')->field('account,nickname,user_id')->where(array('user_id'=>$scan['user_id']))->find();
        $data['user_id'] = D('Crypter')->encrypt($data['user_id']);
        die(json_encode(array('data'=>$data,'content'=>'成功', 'error'=>0, 'status'=>$scan['error'])));

    }

    /* 商家调用支付接口时，生成订单，返回订单二维码链接 */
    public function transaction () {

        $data = json_decode(file_get_contents("php://input"), true);    //获取API得到的信息
        $data = $this->check_api_data($data);   //处理检查API得到的信息
        if ($data['error'] == 1) {
            die(json_encode($data));
        }
        $checkkey = D('Scanapikey')->checkkey($data['appid'], $data['appkey']); //检查是否有授权密钥，以及密钥是否仍在生效
        if ($checkkey['error'] == 1) {
            die(json_encode($checkkey));
        }

        $merchantInfo = $checkkey['info'];
        $settlement_price = (int) $data['amount'] * (1 - $merchantInfo['charge_rate']/100); //算出结算给商家的费用
        $order_encryption_key = $this->CONFIG['key']['scanorder'];  //调用API付费时，特用的密钥
        $encryption = D('Passport')->encryption("scan_order||".rand(100,999)."||".time(), 0, $order_encryption_key);   //生成token值

        //插入数据库
        $insert = array(
            'user_id' => 0,
            'need_pay' => (int)$data['amount'],
            'settlement_price' => $settlement_price,
            'status' => 0,
            'create_time' => NOW_TIME,
            'create_ip' => get_client_ip(),
            'is_settled' => 0,
            'token' => $encryption,
            'appid' => $data['appid'],
            'error' => -1,
            'success_url' => $data['SuccessURL'],
            'failure_url' => $data['FailureURL'],
            'cancellation_url' => $data['CancellationURL'],
            'merchant_reference' => $data['MerchantReference']
        );
        if ($order_id = D('Scanorder')->add($insert)) {
            $navigateURL = __HOST__.U('scan/orderqrcode', array('token'=>$encryption));
            die(json_encode(array('error'=>0, 'NavigateURL'=>$navigateURL)));
        } else {
            die(json_encode(array('error'=>1, 'code'=>10, 'msg'=>'Please try again later.')));
        }

    }

    /* 调用扫一扫支付时，生成的二维码页面 */
    public function orderqrcode () {

        $encryption = $this->_get('token');
        $order = D('Scanorder')->where(array('token'=>$encryption))->find();    //找到相应的order信息
        $appid = $order['appid'];
        $domain = D('Scanapikey')->where(array('appid'=>$appid))->getField('domain');
        if (empty($order)) {
            $this->error('Wrong token value.');
        }

        //生成二维码图片
        $token = 'http://'.$_SERVER['HTTP_HOST'].'/mobile/scan/pay/token/'.$encryption;
        $token_img = baoQrCode('scan_order_'.$encryption,$token);
        $token_url = "<img src='http://".$_SERVER['HTTP_HOST']."/attachs/".$token_img."' style='width:20%; margin-left:40%; margin-top:30px;'>";

        //模板分配及显示
        $this->assign('token_url', $token_url);
        $this->assign('encryption', $encryption);
        $this->assign('domain', $domain);
        $this->assign('order', $order);
        $this->display();

    }

    /* 在扫一扫支付二维码页面，该方法仅用于1stPay前端，异步轮回查询扫描状态 */
    public function orderScanCheck(){

        if ($this->isAjax()) {
            $token = ($this->_post('token')) ? $this->_post('token') : NULL;
            if ($token) {

                //查询缓存中是否存在相应token的缓存
                $scanOrder = D('Scanorder');
                if (!(S('scan_order_'.$token))) {

                    //对token进行解密并验证
                    $order_encryption_key = $this->CONFIG['key']['scanorder'];
                    $token_array = explode("||", D('Passport')->encryption($token, 1, $order_encryption_key));
                    if (sizeof($token_array) != 3) {
                        $scanOrder->data(array('error'=>3))->where(array('token'=>$token))->save();
                        die(json_encode(array('msg'=>'Hacking Attempt!', 'error'=>1, 'code'=>11)));
                    }
                    if ((intval($token_array[2]) + 300) < NOW_TIME) {
                        $scanOrder->data(array('error'=>2))->where(array('token'=>$token))->save();
                        die(json_encode(array('msg'=>'QRcode Expired!', 'error'=>1, 'code'=>12)));
                    }
                    die(json_encode(array('msg'=>'Not scan yet.', 'error'=>-1)));

                } else {

                    S('scan_order_'.$token, NULL);  //清除缓存

                }

                //得到扫描状态信息
                $scan = $scanOrder->field('error, order_id')->where(array('token'=>$token))->find();
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

    /* 在扫一扫支付二维码页面，该方法仅用于1stPay前端，异步轮回查询支付状态 */
    public function scanPaidResultCheck(){
        if ($this->isAjax()) {
            $token = ($this->_post('token')) ? $this->_post('token') : NULL;
            if ($token) {

                //查询缓存中是否存在相应token的缓存
                $scanOrder = D('Scanorder');
                if (!(S('scan_paid_result_'.$token))) {

                    //对token进行解密并验证
                    $order_encryption_key = $this->CONFIG['key']['scanorder'];
                    $token_array = explode("||", D('Passport')->encryption($token, 1, $order_encryption_key));
                    if (sizeof($token_array) != 3) {
                        $scanOrder->data(array('error'=>3))->where(array('token'=>$token))->save();
                        die(json_encode(array('msg'=>'Hacking Attempt!', 'error'=>1, 'code'=>11)));
                    }
                    if ((intval($token_array[2])+3600) < NOW_TIME) {
                        $scanOrder->data(array('status'=>2))->where(array('token'=>$token))->save();
                        die(json_encode(array('msg'=>'This order has expired.', 'error'=>1, 'code'=>14)));
                    }
                    die(json_encode(array('msg'=>'Not paid yet.', 'error'=>-1)));

                } else {

                    S('scan_paid_result_'.$token, NULL);    //清除缓存

                }

                //得到扫描登陆相关信息
                $status = $scanOrder->where(array('token'=>$token))->getField('status');

                //处理返回订单状态
                if($status) {
                    if ($status == 0) {
                        $result = array('msg'=>'This order has not been paid yet.', 'error'=>1, 'code'=>13);
                    } elseif ($status == 1) {
                        $result = array('msg'=>'Successful!', 'error'=>0);
                    } elseif ($status == 2) {
                        $result = array('msg'=>'This order has expired.', 'error'=>1, 'code'=>14);
                    } elseif ($status == 3) {
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

    /* 扫描设备扫描后,进入支付页面 */
    public function pay () {

        $user_id = getUid();
        if (empty($user_id)) {
            cookie('backurl',$_SERVER['REQUEST_URI']);
            header("Location: " . u("mobile/passport/login"));
            exit;
        }
        $token = ($this->_get('token'))? $this->_get('token') : NULL;
        $scanOrder = D('Scanorder');

        if ($token) {

            //对token进行解密并验证
            $order_encryption_key = $this->CONFIG['key']['scanorder'];
            $token_array = explode("||", D('Passport')->encryption($token, 1, $order_encryption_key));
            if (sizeof($token_array) != 3) {
                $scanOrder->data(array('error'=>3))->where(array('token'=>$token))->save();
                die(json_encode(array('msg'=>'Hacking Attempt!', 'error'=>1, 'code'=>11)));
            }
            $scan = $scanOrder->where(array('token'=>$token))->find();
            if ($scan) {
                //判断订单是否过期
                if (($scan['create_time'] + 3600) < NOW_TIME) {
                    $scanOrder->data(array('status'=>2))->where(array('token'=>$token))->save();
                    $this->error('二维码过期，请重新下单。');
                }

                //判断二维码是否被扫描过
                ($scan['status'] !=0 && $scan['status'] != '') && $this->error('该二维码已被使用过');

                //更新表，将扫描者user_id写入，更新扫描状态
                $scanOrder->data(array('user_id'=>$user_id, 'error'=>0))->where(array('token'=>$token))->save();

                //写缓存文件，作为被扫描设备异步轮询查询条件
                S('scan_order_'.$token,$token,300);

                //查询订单来源
                $domain = D('Scanapikey')->where(array('appid'=>$scan['appid']))->getField('domain');

                //扫描成功，进入付款页面
                $this->assign('domain', $domain);
                $this->assign('order', $scan);
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
    public function pay2 () {

        $user_id = getUid();
        if (empty($user_id)) {
            $this->ajaxLogin();
        }
        if (!$this->member['mobile']) {
            $this->fengmiMsg('请先绑定手机号码再提交！');
        }
        $order_id = (int) $this->_get('order_id');
        $order = D('Scanorder')->find($order_id);
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
        $logs = D('Paymentlogs')->getLogsByOrderId('api', $order_id);
        if (empty($logs)) {
            $logs = array(
                'type' => 'api',
                'user_id' => $user_id,
                'order_id' => $order_id,
                'code' => $code,
                'need_pay' => $order['need_pay'],
                'create_time' => NOW_TIME,
                'create_ip' => get_client_ip(),
                'is_paid' => 0
            );
            $logs['log_id'] = D('Paymentlogs')->add($logs);
        } else {
            $logs['need_pay'] = $order['need_pay'];
            $logs['code'] = $code;
            D('Paymentlogs')->save($logs);
        }
        $this->fengmiMsg('选择支付方式成功！下面请进行支付！', U('payment/payment', array('log_id' => $logs['log_id'])));

    }

    /* 用Token换取订单信息 */
    public function getTransaction() {

        $data = json_decode(file_get_contents("php://input"), true);
        $data = $this->check_get_transaction_data($data);
        if ($data['error'] == 1) {
            die(json_encode($data));
        }
        //检查是否有授权密钥，以及密钥是否仍在生效
        $checkkey = D('Scanapikey')->checkkey($data['appid'], $data['appkey']);
        if ($checkkey['error'] == 1) {
            die(json_encode($checkkey));
        }

        //检查是否有token相关记录, 并取得order相关信息
        $field = 'user_id,need_pay,settlement_price,status,create_time,create_ip,paid_time,paid_ip,is_settled,settlement_time,
            token,merchant_reference';
        $scan = D('Scanorder')->field($field)->where(array('token'=>$data['token'],'appid'=>$data['appid']))->find();
        if (!$scan) {
            die(json_encode(array('error'=>1, 'code'=>16, 'msg'=>'This order does not exist.')));
        }
        if ($scan['error'] != 0) {
            if ($scan['error'] == -1) {
                $errormsg = 'The QRcode has not been scanned';
                $errorcode = 17;
            } elseif ($scan['error'] == 2) {
                $errormsg = 'The QRcode has expired';
                $errorcode = 12;
            } elseif ($scan['error'] == 3) {
                $errormsg = 'The status is abnormal';
                $errorcode = 18;
            }
            die(json_encode(array('error'=>1, 'code'=>$errorcode, 'msg'=>$errormsg)));
        }

        //转化相关信息, 并传出
        $data = $this->arrangeData($scan);
        die(json_encode(array('msg'=>'Success', 'error'=>0, 'data'=>$data)));

    }

    /* api支付时，检查商家网站传递过来的信息 */
    private function check_api_data ($data) {

        if (empty($data)) {
            $return = array('error'=>1, 'code'=>'1', 'msg'=>'No information.');
            return $return;
        }
        $auth = explode(":", htmlspecialchars(base64_decode($data['auth'])));
        if (sizeof($auth) != 2) {
            $return = array('error'=>1, 'code'=>'3', 'msg'=>'Wrong Authorization information.');
            return $return;
        } else {
            $result['appid'] = $auth[0];
            $result['appkey'] = $auth[1];
        }
        if (empty($result['appid']) || empty($result['appkey'])) {
            $return = array('error'=>1, 'code'=>'2', 'msg'=>'No Authorization information.');
            return $return;
        }
        $result['amount'] = intval(100 * floatval($data['amount']));
        if (empty($result['amount'])) {
            $return = array('error'=>1, 'code'=>'2', 'msg'=>'No amount information.');
            return $return;
        }
        if ($result['amount'] <= 0) {
            $return = array('error'=>1, 'code'=>'4', 'msg'=>'Wrong amount value.');
            return $return;
        }
        $result['SuccessURL'] = htmlspecialchars($data['SuccessURL']);
        if (empty($result['SuccessURL'])) {
            $return = array('error'=>1, 'code'=>'2', 'msg'=>'No Success URL.');
            return $return;
        }
        if (strpos($result['SuccessURL'], 'http') !== 0) {
            $return = array('error'=>1, 'code'=>'4', 'msg'=>'Wrong Success URL format.');
            return $return;
        }
        $result['FailureURL'] = htmlspecialchars($data['FailureURL']);
        if (empty($result['FailureURL'])) {
            $return = array('error'=>1, 'code'=>'2', 'msg'=>'No Failure URL.');
            return $return;
        }
        if (strpos($result['FailureURL'], 'http') !== 0) {
            $return = array('error'=>1, 'code'=>'4', 'msg'=>'Wrong Failure URL format.');
            return $return;
        }
        $result['CancellationURL'] = htmlspecialchars($data['CancellationURL']);
        if (empty($result['CancellationURL'])) {
            $return = array('error'=>1, 'code'=>'2', 'msg'=>'No Cancellation URL.');
            return $return;
        }
        if (strpos($result['CancellationURL'], 'http') !== 0) {
            $return = array('error'=>1, 'code'=>'4', 'msg'=>'Wrong Cancellation URL format.');
            return $return;
        }
        $result['MerchantReference'] = htmlspecialchars($data['MerchantReference']);
        if (empty($result['MerchantReference'])) {
            $return = array('error'=>1, 'code'=>'2', 'msg'=>'No Merchant Reference.');
            return $return;
        }
        return $result;

    }

    /* 检查token换取order信息时，传来的值 */
    private function check_get_transaction_data ($data) {

        if (empty($data)) {
            $return = array('error'=>1, 'code'=>'1', 'msg'=>'No information.');
            return $return;
        }
        $auth = explode(":", htmlspecialchars(base64_decode($data['auth'])));
        if (sizeof($auth) != 2) {
            $return = array('error'=>1, 'code'=>'3', 'msg'=>'Wrong Authorization information.');
            return $return;
        } else {
            $result['appid'] = $auth[0];
            $result['appkey'] = $auth[1];
        }
        if (empty($result['appid']) || empty($result['appkey'])) {
            $return = array('error'=>1, 'code'=>'2', 'msg'=>'No Authorization information.');
            return $return;
        }
        $result['token'] = htmlspecialchars($data['token']);
        if (empty($result['token'])) {
            $return = array('error'=>1, 'code'=>'2', 'msg'=>'No Token.');
            return $return;
        }
        return $result;

    }

    /* 处理token换取order信息时，返回的值 */
    private function arrangeData ($data) {

        $data['user_id'] = D('Crypter')->encrypt($data['user_id']);
        $data['need_pay'] = $data['need_pay'] / 100;
        $data['settlement_price'] = $data['settlement_price'] / 100;
        $data['status'] = D('Scanorder')->status[$data['status']];
        $data['is_settled'] = D('Scanorder')->settled[$data['is_settled']];
        return $data;

    }

}