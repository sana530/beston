<?php
class CreditAction extends CommonAction
{
    private $create_fields = array('title', 'first_name', 'last_name', 'born_year', 'born_month', 'born_day', 'identity', 'license', 'license_version', 'passport', 'email', 'mobile', 'addr', 'credit_limit', 'bank_account', 'photo_id', 'photo_bk');
    public function index()
    {
        $Privacy = D('Systemcontent')->find(array('where' => array('content_id' =>4))); // 《1STPAY 隐私权政策》
        $credit = D('Systemcontent')->find(array('where' => array('content_id' =>7))); // 《1STPAY O2O信用账期服务关键条款》

        if (empty($this->uid)) {
            header("Location:" . U('passport/login'));
            die;
        }
        if (D('Credit')->find(array('where' => array('user_id' => $this->uid)))) {
            $this->error('您已经申請過了！', U('mcenter/member/index'));
        }
        if ($this->isPost()) {
            $data = $this->createCheck();
            $obj = D('Credit');
            if ($credit_id = $obj->add($data)) {
                $account =  D('Users')->where(array('user_id' => $data['user_id']))->getfield('account');
                $mobile = $this->_CONFIG['site']['config_mobile'];
                //通知用户
                if (isCnMobile($mobile)) {
                    D('Sms')->CnSms($this->_CONFIG['site']['sitename'], 'sms_remind_credit_admin', $mobile,  array('name' => $account));
                } elseif (isMobile($mobile)) {
                    if ($this->_CONFIG['sms']['dxapi'] == 'bu') {
                        D('Sms')->BuSms($this->_CONFIG['site']['sitename'], 'sms_remind_credit_admin', $mobile, array('name' => $account));
                    } elseif ($this->_CONFIG['sms']['dxapi'] == 'dy') {
                        D('Sms')->DySms($this->_CONFIG['site']['sitename'], 'sms_remind_credit_admin', $mobile, array('name' => $account));
                    }
                }
                $this->fengmiMsg('恭喜您申请成功！', U('mcenter/member/index'));
            }
            $this->fengmiMsg('申请失败！');
        } else {
            $user = D('Users')->find(array('where' => array('user_id' => $this->uid)));
            empty($user['email']) && strstr($user['account'],'@') && $user['email'] = $user['account'];
            $this->assign('user', $user);
            $this->assign('cates', array('先生','女士','小姐'));
            $this->assign('Privacy', $Privacy);
            $this->assign('credit', $credit);
            $this->display();
        }
    }
    private function createCheck()
    {
        $data = $this->checkFields($this->_post('data', false), $this->create_fields);
        if (empty($data['title'])) {
            $this->fengmiMsg('称呼不能为空');
        }
        if (empty($data['first_name'])) {
            $this->fengmiMsg('名字不能为空');
        }
        if (empty($data['last_name'])) {
            $this->fengmiMsg('姓氏不能为空');
        }
        if (empty($data['born_year'])) {
            $this->fengmiMsg('出生年不能为空');
        }
        if (empty($data['born_month'])) {
            $this->fengmiMsg('出生月不能为空');
        }
        if (empty($data['born_day'])) {
            $this->fengmiMsg('出生日不能为空');
        }
        $data['birth_date'] = $data['born_year'] ."-" . $data['born_month'] ."-" . $data['born_day'];
        if (empty($data['identity'])) {
            $this->fengmiMsg('请选择身份证明');
        } elseif ($data['identity'] == "1") {
            if (empty($data['license'])) {
                $this->fengmiMsg('驾照号不能为空');
            }
            if (empty($data['license_version'])) {
                $this->fengmiMsg('驾照 版本号不能为空');
            }
        } elseif ($data['identity'] == "2") {
            if (empty($data['passport'])) {
                $this->fengmiMsg('护照号不能为空');
            }
        }
        if (empty($data['email'])) {
            $this->fengmiMsg('邮箱不能为空');
        }
        if (!isEmail($data['email'])) {
            $this->fengmiMsg('邮箱格式不正确');
        }
        $data['tel'] = htmlspecialchars($data['mobile']);
        if (empty($data['mobile'])) {
            $this->fengmiMsg('手机不能为空');
        }
        if (!isPhone($data['mobile']) && !isMobile($data['mobile']) && !isCnMobile($data['mobile'])) {
            $this->fengmiMsg('手机格式不正确');
        }
        $data['addr'] = htmlspecialchars($data['addr']);
        if (empty($data['addr'])) {
            $this->fengmiMsg('地址不能为空');
        }
        if (empty($data['credit_limit'])) {
            $this->fengmiMsg('拟申请信用额度不能为空');
        }
        if (!is_numeric($data['credit_limit'])) {
            $this->fengmiMsg('拟申请信用额度请输入数字');
        } elseif ($data['credit_limit']>500) {
            $this->fengmiMsg('拟申请信用额度请输入数字500以下新西兰元');
        }
        if (empty($data['bank_account'])) {
            $this->fengmiMsg('自动还款银行账号不能为空');
        }
        $data['photo_id'] = htmlspecialchars($data['photo_id']);
        if (strpos($data['photo_id'],"pdf")) {
            $data['photo_id'] = str_replace('thumb_', '', $data['photo_id']);
        }
        if (empty($data['photo_id'])) {
            $this->fengmiMsg('请上传身份证明');
        }
        if (!isImage($data['photo_id'])) {
            $this->fengmiMsg('身份证明格式不正确');
        }
        $data['photo_bk'] = htmlspecialchars($data['photo_bk']);
        if (strpos($data['photo_bk'],"pdf")) {
            $data['photo_bk'] = str_replace('thumb_', '', $data['photo_bk']);
        }
        if (empty($data['photo_bk'])) {
            $this->fengmiMsg('请上传银行账号对账单');
        }
        if (!isImage($data['photo_bk'])) {
            $this->fengmiMsg('银行账号对账单格式不正确');
        }
        $data['user_id'] = $this->uid;
        $data['create_time'] = NOW_TIME;
        $data['create_ip'] = get_client_ip();
        return $data;
    }
}