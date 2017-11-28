<?php
class InfoAction extends CommonAction{
    public function face(){
        $this->display();
    }
	public function wxpayexception(){
		$this->error('非法错误，请联系网站管理员解决', U('member/index'));
        $this->display();
    }
    public function nickname(){
        if ($this->isPost()) {
            $nickname = $this->_post('nickname');
            $user = D('Users')->where(array('nickname' => $nickname))->find();
            if (!empty($user)) {
                $this->fengmiMsg('该昵称已被使用');
            }
            D('Users')->save(array('nickname' => $nickname, 'user_id' => $this->uid));
            $this->fengmiMsg('昵称已经更新', U('info/nickname'));
        }
        $this->display();
    }
	
	public function set(){
        if ($this->isPost()) {
            $data['job'] = $this->_post('job', 'htmlspecialchars');
            $data['sex'] = (int) $this->_post('sex');
            $data['star_id'] = (int) $this->_post('star_id');
            $data['born_year'] = (int) $this->_post('born_year');
            $data['born_month'] = (int) $this->_post('born_month');
            $data['born_day'] = (int) $this->_post('born_day');
            $detail = D('Usersex')->getUserex($this->uid);
            $data['user_id'] = $detail['user_id'];
			$data2['user_id'] = $detail['user_id'];
			$data2['ext_account'] = $this->_post('ext_account', 'htmlspecialchars');
			$data2['ext_mobile'] = $this->_post('ext_mobile', 'htmlspecialchars');		
            if (false !== D('Usersex')->save($data)) {
				if (false === D('Users')->save($data2)) {
					$this->fengmiMsg('保存二维码失败');
				}
                $this->fengmiMsg('基本信息设置成功！', U('info/set'));
            }
            $this->fengmiMsg('基本信息设置失败');
        } else {
            $usersex = D('Usersex')->find($this->uid);
            $stars = D('Usersex')->getStar();
            $this->assign('stars', $stars);
            $this->assign('usersex', $usersex);
            $this->display();
        }
    }
	
	
    public function nickcheck(){
        $nickname = $this->_get('nickname');
        $user = D('Users')->where(array('nickname' => $nickname))->find();
        if (empty($user)) {
            echo '1';
        } else {
            echo '0';
        }
    }
    public function sendsms()
    {
        $country = $this->_post('country');
        if (!($mobile = htmlspecialchars($this->_post('mobile')))) {  $this->ajaxReturn(array('status'=>'error','msg'=>'请输入手机号码')); }
        if ($country == 'nz') {
            if (!isMobile($mobile)) { $this->ajaxReturn(array('status'=>'error','msg'=>'请输入正确的NZ手机号码')); }
        } elseif ($country == 'cn') {
            if (!isCnMobile($mobile)) { $this->ajaxReturn(array('status'=>'error','msg'=>'请输入正确的中國手机号码')); }
        }
        session('mobile', $mobile);
        $randstring = session('code');
        if (empty($randstring)) {
            $randstring = rand_string(6, 1);
            session('code', $randstring);
        }
        if (isCnMobile($mobile)) {
            $result = D('Sms')->CnSms($this->_CONFIG['site']['sitename'], 'sms_yzm', $mobile, array('sitename' => $this->_CONFIG['site']['sitename'], 'code' => $randstring));
        } elseif (isMobile($mobile)) {
            if ($this->_CONFIG['sms']['dxapi'] == 'bu') {
                $result = D('Sms')->BuSms($this->_CONFIG['site']['sitename'], 'sms_yzm', $mobile, array('sitename' => $this->_CONFIG['site']['sitename'], 'code' => $randstring));
            } elseif ($this->_CONFIG['sms']['dxapi'] == 'dy') {//大于短信
                $result = D('Sms')->DySms($this->_CONFIG['site']['sitename'], 'sms_yzm', $mobile, array('sitename' => $this->_CONFIG['site']['sitename'], 'code' => $randstring));
            } else {
                $result = D('Sms')->sendSms('sms_code', $mobile, array('code' => $randstring));
            }
        }
            if ($result) {
                $data['status'] = 'success';
                $data['msg'] = '发送成功';
            } else {
                $data['status'] = 'fail';
                $data['msg'] = '发送失败，请稍后再试';
            }
        $this->ajaxReturn($data);
    }
    public function password()
    {
        if ($this->isPost()) {
            $newpwd = $this->_post('newpwd', 'htmlspecialchars');
            if (empty($newpwd)) {
                $this->fengmiMsg('请输入新密码');
            }
            $pwd2 = $this->_post('pwd2', 'htmlspecialchars');
            if (empty($pwd2) || $newpwd != $pwd2) {
                $this->fengmiMsg('两次密码输入不一致！');
            }
            if (D('Users')->save(array('user_id' => $this->uid, 'password' => md5($newpwd)))) {
                $this->fengmiMsg('更改密码成功！', U('index/index'));
            }
            $this->fengmiMsg('修改密码失败！');
        } else {
            $this->display();
        }
    }
    public function account()
    {
        if ($this->isPost()) {
            $mobile = $this->_post('mobile');
            $yzm = $this->_post('yzm');
            if (empty($mobile) || empty($yzm)) {
                $this->fengmiMsg('请填写正确的手机及手机收到的验证码！');
            }
            $s_mobile = session('mobile');
            $s_code = session('code');
            if ($mobile != $s_mobile) {
                $this->fengmiMsg('手机号码和收取验证码的手机号不一致！');
            }
            if ($yzm != $s_code) {
                $this->fengmiMsg('验证码不正确');
            }
            $user_id = D('Users')->where(array('mobile' => $mobile))->getField('user_id');
            $uids = D('Users')->where(array('user_id' => $this->uid))->getField('user_id');
            $connect = M('Connect');
            //连接connect表
            $open_id = $connect->where(array('uid' => $uids))->getField('open_id');
            $result = $connect->where(array('open_id' => $open_id))->setField('uid', $user_id);
            D('Passport')->logout();
            $this->fengmiMsg('您的帐号已经更新！', U('mobile/index/index'));
        }
        $this->display();
    }

    //设置支付密码
    public function paypass() {
        $uid = getUid();
        if ($this->isPost()) {
            //验证手机验证码
            $yzm = $this->_post('yzm');
            if (empty($yzm))
                $this->fengmiMsg('请填写手机验证码！');
            $session_mobile = session('mobile');
            $session_code = session('code');
            if ($this->member['mobile'] != $session_mobile)
                $this->fengmiMsg('手机号码和收取验证码的手机号不一致！');
            if ($yzm != $session_code){
                $this->fengmiMsg('请填写正确的手机验证码');
            }

            $data = $this->_post('data');
            if (!empty($data['pay_code']) && strlen($data['pay_code']) != 4) {
                $this->fengmiMsg('请输入四位数密码');
            }
            $save = array();
            $save['pay_type'] = $data['type'];
            (!empty($data['pay_code'])) && $save['pay_code'] = md5($data['pay_code']);
            (!empty($data['pay_pass'])) && $save['pay_password'] = md5($data['pay_pass']);
            if (D('Users')->data($save)->where(array('user_id' => $uid))->save()) {
                $this->fengmiMsg('支付密码信息已修改', U('member/index'));
            } else {
                $this->fengmiMsg('修改失败，请重新修改');
            }
        } else {
            $user = D('Users')->where(array('user_id' => $uid))->find();
            $this->assign('user', $user);
            $this->display();
        }
    }
}