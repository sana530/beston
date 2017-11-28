<?php
class DeliveryAction extends CommonAction
{
    private $create_fields = array('first_name', 'last_name', 'identity', 'license', 'license_version', 'passport', 'email', 'mobile', 'addr', 'photo_id');
    public function apply()
    {
        $Privacy = D('Systemcontent')->find(array('where' => array('content_id' =>9))); // 《1STPAY 隐私权政策》
        
        if (empty($this->uid)) {
            header("Location:" . U('passport/login'));
            die;
        }
        $obj = D('Delivery');
        if ($delivery = $obj->find(array('where' => array('user_id' => $this->uid)))) {
            if ($delivery['audit'] == 0) {
                $this->error('正在审核，请耐心等候！', U('mcenter/member/index'));
            } elseif ($delivery['closed'] == 1) {
                $this->error('您的账户状态不正常，请联系管理员！', U('mcenter/member/index'));
            } else {
                $this->error('您已经申请过了,请直接登陆！', U('delivery/index/index'));
            }
        }
        if ($this->isPost()) {
            $data = $this->createCheck();
            if ($obj->add($data)) {
                $this->fengmiMsg('恭喜您申请成功！', U('mcenter/member/index'));
            }
            $this->fengmiMsg('申请失败！');
        } else {
            $user = D('Users')->find(array('where' => array('user_id' => $this->uid)));
            empty($user['email']) && strstr($user['account'],'@') && $user['email'] = $user['account'];
            $this->assign('user', $user);
            $this->assign('Privacy', $Privacy);
            $this->display();
        }
    }
    private function createCheck()
    {
        $data = $this->checkFields($this->_post('data', false), $this->create_fields);

        if (empty($data['first_name'])) {
            $this->fengmiMsg('名字不能为空');
        }
        if (empty($data['last_name'])) {
            $this->fengmiMsg('姓氏不能为空');
        }
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
        $data['mobile'] = htmlspecialchars($data['mobile']);
        if (empty($data['mobile'])) {
            $this->fengmiMsg('手机不能为空');
        }
        if (!isPhone($data['mobile']) && !isMobile($data['mobile'])) {
            $this->fengmiMsg('手机格式不正确');
        }
        $data['addr'] = htmlspecialchars($data['addr']);
        if (empty($data['addr'])) {
            $this->fengmiMsg('地址不能为空');
        }
        $data['photo_id'] = htmlspecialchars($data['photo_id']);
        if (empty($data['photo_id'])) {
            $this->fengmiMsg('请上传身份证明');
        }
        if (!isImage($data['photo_id'])) {
            $this->fengmiMsg('身份证明格式不正确');
        }
        $data['user_id'] = $this->uid;
        $data['add_time'] = NOW_TIME;
        return $data;
    }
}