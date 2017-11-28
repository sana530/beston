<?php
class FirstpayAction extends CommonAction {

    private $create_fields = array('user_id','title', 'first_name', 'last_name', 'birth_date', 'identity', 'license', 'license_version', 'passport', 'email', 'mobile', 'addr', 'credit_limit', 'bank_account', 'photo_id', 'photo_bk');

    private $edit_fields = array('user_id','title', 'first_name', 'last_name', 'birth_date', 'identity', 'license', 'license_version', 'passport', 'email', 'mobile', 'addr', 'credit_limit', 'bank_account', 'photo_id', 'photo_bk');

    public function index(){
        $obj = D('Credit');
        import('ORG.Util.Page');
        $map = array('closed' => 0, 'audit' => 1);
        if ($keyword = $this->_param('keyword', 'htmlspecialchars')) {
            $map['email|mobile'] = array('LIKE', '%' . $keyword . '%');
            $this->assign('keyword', $keyword);
        }
        $count = $obj->where($map)->count();

        $Page = new Page($count, 25);
        $show = $Page->show();
        $list = $obj->order(array('credit_id' => 'asc'))->where($map)->limit($Page->firstRow . ',' . $Page->listRows)->select();
        $ids = array();
        foreach ($list as $k => $val) {
            $list[$k]['photo_id_type'] = substr($val['photo_id'], -3);
            $list[$k]['photo_bk_type'] = substr($val['photo_bk'], -3);
            if ($val['user_id']) {
                $ids[$val['user_id']] = $val['user_id'];
            }
        }
        $this->assign('users', D('Users')->itemsByIds($ids));
        $this->assign('list', $list);
        $this->assign('page', $show);
        $this->display();
    }

    public function apply(){
        $obj = D('Credit');
        import('ORG.Util.Page');
        $map = array('closed' => 0, 'audit' => 0);
        if ($keyword = $this->_param('keyword', 'htmlspecialchars')) {
            $map['email|mobile'] = array('LIKE', '%' . $keyword . '%');
            $this->assign('keyword', $keyword);
        }
        $count = $obj->where($map)->count();

        $Page = new Page($count, 25);
        $show = $Page->show();
        $list = $obj->order(array('credit_id' => 'asc'))->where($map)->limit($Page->firstRow . ',' . $Page->listRows)->select();
        $ids = array();
        foreach ($list as $k => $val) {
            $list[$k]['photo_id_type'] = substr($val['photo_id'], -3);
            $list[$k]['photo_bk_type'] = substr($val['photo_bk'], -3);
            if ($val['user_id']) {
                $ids[$val['user_id']] = $val['user_id'];
            }
        }
        $this->assign('users', D('Users')->itemsByIds($ids));
        $this->assign('list', $list);
        $this->assign('page', $show);
        $this->display();
    }

    public function create(){
        if ($this->isPost()) {
            $data = $this->createCheck();
            if ($credit_id = D('Credit')->add($data)) {
                $this->baoSuccess('恭喜您申请成功！', U('firstpay/apply'));
            }
            $this->baoError('申请失败！');
        } else {
            $this->assign('cates', array('先生','女士','小姐'));
            $this->display();
        }
    }

    private function createCheck()
    {
        $data = $this->checkFields($this->_post('data', false), $this->create_fields);
        if (empty($data['user_id'])) {
            $this->baoError('用户不能为空');
        }
        if (empty($data['title'])) {
            $this->baoError('称呼不能为空');
        }
        if (empty($data['first_name'])) {
            $this->baoError('名字不能为空');
        }
        if (empty($data['last_name'])) {
            $this->baoError('姓氏不能为空');
        }
        if (empty($data['birth_date'])) {
            $this->baoError('出生日期不能为空');
        }
        if (!isDate($data['birth_date'])) {
            $this->baoError('出生日期格式不正确');
        }
        if (empty($data['identity'])) {
            $this->baoError('请选择身份证明');
        } elseif ($data['identity'] == "1") {
            if (empty($data['license'])) {
                $this->baoError('驾照号不能为空');
            }
            if (empty($data['license_version'])) {
                $this->baoError('驾照 版本号不能为空');
            }
        } elseif ($data['identity'] == "2") {
            if (empty($data['passport'])) {
                $this->baoError('护照号不能为空');
            }
        }
        if (empty($data['email'])) {
            $this->baoError('邮箱不能为空');
        }
        if (!isEmail($data['email'])) {
            $this->baoError('邮箱格式不正确');
        }
        $data['tel'] = htmlspecialchars($data['mobile']);
        if (empty($data['mobile'])) {
            $this->baoError('手机不能为空');
        }
        if (!isPhone($data['mobile']) && !isMobile($data['mobile'])) {
            $this->baoError('手机格式不正确');
        }
        $data['addr'] = htmlspecialchars($data['addr']);
        if (empty($data['addr'])) {
            $this->baoError('地址不能为空');
        }
        if (empty($data['credit_limit'])) {
            $this->baoError('拟申请信用额度不能为空');
        }
        if (!is_numeric($data['credit_limit'])) {
            $this->baoError('拟申请信用额度请输入数字');
        } elseif ($data['credit_limit']>500) {
            $this->baoError('拟申请信用额度请输入数字500以下新西兰元');
        }
        if (empty($data['bank_account'])) {
            $this->baoError('自动还款银行账号不能为空');
        }
        $data['photo_id'] = htmlspecialchars($data['photo_id']);
        if (strpos($data['photo_id'],"pdf")) {
            $data['photo_id'] = str_replace('thumb_', '', $data['photo_id']);
        }
        if (empty($data['photo_id'])) {
            $this->baoError('请上传身份证明');
        }
        if (!isImage($data['photo_id']) && !isPdf($data['photo_id'])) {
            $this->baoError('身份证明格式不正确');
        }
        $data['photo_bk'] = htmlspecialchars($data['photo_bk']);
        if (strpos($data['photo_bk'],"pdf")) {
            $data['photo_bk'] = str_replace('thumb_', '', $data['photo_bk']);
        }
        if (empty($data['photo_bk'])) {
            $this->baoError('请上传银行账号对账单');
        }
        if (!isImage($data['photo_bk']) && !isPdf($data['photo_bk'])) {
            $this->baoError('银行账号对账单格式不正确');
        }
        $data['create_time'] = NOW_TIME;
        $data['create_ip'] = get_client_ip();
        return $data;
    }

    public function edit($credit_id = 0)
    {
        if ($credit_id = (int) $credit_id) {
            $obj = D('Credit');
            if (!($detail = $obj->find($credit_id))) {
                $this->baoError('请选择要编辑的用户');
            }
            $detail['photo_id_type'] = substr($detail['photo_id'], -3);
            $detail['photo_bk_type'] = substr($detail['photo_bk'], -3);
            if ($this->isPost()) {
                $data = $this->editCheck();
                $data['credit_id'] = $credit_id;
                if (false !== $obj->save($data)) {

                    if ($obj->where(array('credit_id' => $credit_id,'audit' => 1))->count()) {
                        $userCredit = $obj->find($credit_id);
                        D('Users')->save(array('user_id' => $userCredit['user_id'], 'money_negative'=> $userCredit['credit_limit']*100));
                        $this->baoSuccess('操作成功', U('firstpay/index'));
                    } else {
                        $this->baoSuccess('操作成功', U('firstpay/apply'));
                    }
                }
                $this->baoError('操作失败');
            } else {
                $this->assign('user', D('Users')->find($detail['user_id']));
                $this->assign('detail', $detail);
                $this->assign('cates', array('先生','女士','小姐'));
                $this->display();
            }
        } else {
            $this->baoError('请选择要编辑的用户');
        }
    }

    private function editCheck()
    {
        $data = $this->checkFields($this->_post('data', false), $this->edit_fields);
        $data['user_id'] = (int) $data['user_id'];
        if (empty($data['user_id'])) {
            $this->baoError('用户不能为空');
        }
        if (empty($data['title'])) {
            $this->baoError('称呼不能为空');
        }
        if (empty($data['first_name'])) {
            $this->baoError('名字不能为空');
        }
        if (empty($data['last_name'])) {
            $this->baoError('姓氏不能为空');
        }
        if (empty($data['birth_date'])) {
            $this->baoError('出生日期不能为空');
        }
        if (!isDate($data['birth_date'])) {
            $this->baoError('出生日期格式不正确');
        }
        if (empty($data['identity'])) {
            $this->baoError('请选择身份证明');
        } elseif ($data['identity'] == "1") {
            if (empty($data['license'])) {
                $this->baoError('驾照号不能为空');
            }
            if (empty($data['license_version'])) {
                $this->baoError('驾照 版本号不能为空');
            }
        } elseif ($data['identity'] == "2") {
            if (empty($data['passport'])) {
                $this->baoError('护照号不能为空');
            }
        }
        if (empty($data['email'])) {
            $this->baoError('邮箱不能为空');
        }
        if (!isEmail($data['email'])) {
            $this->baoError('邮箱格式不正确');
        }
        $data['tel'] = htmlspecialchars($data['mobile']);
        if (empty($data['mobile'])) {
            $this->baoError('手机不能为空');
        }
        if (!isPhone($data['mobile']) && !isMobile($data['mobile'])) {
            $this->baoError('手机格式不正确');
        }
        $data['addr'] = htmlspecialchars($data['addr']);
        if (empty($data['addr'])) {
            $this->baoError('地址不能为空');
        }
        if (empty($data['credit_limit'])) {
            $this->baoError('拟申请信用额度不能为空');
        }
        if (!is_numeric($data['credit_limit'])) {
            $this->baoError('拟申请信用额度请输入数字');
        } elseif ($data['credit_limit']>500) {
            $this->baoError('拟申请信用额度请输入数字500以下新西兰元');
        }
        if (empty($data['bank_account'])) {
            $this->baoError('自动还款银行账号不能为空');
        }
        $data['photo_id'] = htmlspecialchars($data['photo_id']);
        if (empty($data['photo_id'])) {
            $this->baoError('请上传身份证明');
        }
        if (!isImage($data['photo_id']) && !isPdf($data['photo_id'])) {
            $this->baoError('身份证明格式不正确');
        }
        $data['photo_bk'] = htmlspecialchars($data['photo_bk']);
        if (empty($data['photo_bk'])) {
            $this->baoError('请上传银行账号对账单');
        }
        if (!isImage($data['photo_bk']) && !isPdf($data['photo_bk'])) {
            $this->baoError('银行账号对账单格式不正确');
        }
        return $data;
    }

    public function delete($credit_id = 0)
    {
        $obj = D('Credit');
        if (is_numeric($credit_id) && ($credit_id = (int) $credit_id)) {
            $obj->save(array('credit_id' => $credit_id, 'closed' => 1));
            $this->baoSuccess('删除成功！', U('firstpay/index'));
        } else {
            $credit_id = $this->_post('credit_id', false);
            if (is_array($credit_id)) {
                foreach ($credit_id as $id) {
                    $obj->save(array('credit_id' => $id, 'closed' => 1));
                }
                $this->baoSuccess('删除成功！', U('firstpay/index'));
            }
            $this->baoError('请选择要删除的用户');
        }
    }

    public function audit($credit_id = 0){
        $obj = D('Credit');
        if (is_numeric($credit_id) && ($credit_id = (int) $credit_id)) {
            $obj->save(array('credit_id' => $credit_id, 'audit' => 1));
            $userCredit = $obj->find($credit_id);
            D('Users')->save(array('user_id' => $userCredit['user_id'], 'money_negative'=> $userCredit['credit_limit']*100));
            $this->baoSuccess('审核成功！', U('firstpay/index'));
        } else {
            $credit_id = $this->_post('credit_id', false);
            if (is_array($credit_id)) {
                foreach ($credit_id as $id) {
                    $obj->save(array('credit_id' => $id, 'audit' => 1));
                }
                $this->baoSuccess('审核成功！', U('firstpay/index'));
            }
            $this->baoError('请选择要审核的用户');
        }
    }

    public function recovery(){
        $obj = D('Credit');
        import('ORG.Util.Page');
        // 导入分页类
        $map = array('closed' => 1);
        if ($keyword = $this->_param('keyword', 'htmlspecialchars')) {
            $map['email|mobile'] = array('LIKE', '%' . $keyword . '%');
            $this->assign('keyword', $keyword);
        }
        $count = $obj->where($map)->count();

        $Page = new Page($count, 25);
        $show = $Page->show();
        $list = $obj->order(array('credit_id' => 'asc'))->where($map)->limit($Page->firstRow . ',' . $Page->listRows)->select();
        $ids = array();
        foreach ($list as $k => $val) {
            $list[$k]['photo_id_type'] = substr($val['photo_id'], -3);
            $list[$k]['photo_bk_type'] = substr($val['photo_bk'], -3);
            if ($val['user_id']) {
                $ids[$val['user_id']] = $val['user_id'];
            }
        }
        $this->assign('users', D('Users')->itemsByIds($ids));
        $this->assign('list', $list);
        $this->assign('page', $show);
        $this->display();
    }

    public function recovery2($credit_id = 0)
    {
        $obj = D('Credit');
        if (is_numeric($credit_id) && ($credit_id = (int) $credit_id)) {
            $obj->save(array('credit_id' => $credit_id, 'closed' => 0));
            if ($obj->where(array('credit_id' => $credit_id,'audit' => 1))->count()) {
                $this->baoSuccess('恢复成功！', U('firstpay/index'));
            } else {
                $this->baoSuccess('恢复成功', U('firstpay/apply'));
            }
        } else {
            $credit_id = $this->_post('credit_id', false);
            if (is_array($credit_id)) {
                foreach ($credit_id as $id) {
                    $obj->save(array('credit_id' => $id, 'closed' => 0));
                }
                if ($obj->where(array('credit_id' => $credit_id,'audit' => 1))->count()) {
                    $this->baoSuccess('恢复成功！', U('firstpay/index'));
                } else {
                    $this->baoSuccess('恢复成功', U('firstpay/apply'));
                }
            }
            $this->baoError('请选择要恢复的用戶');
        }
    }

    public function delete2($credit_id = 0) {
        $credit_id = (int) $credit_id;
        if(!empty($credit_id)){
            D('Credit')->delete($credit_id);
            $this->baoSuccess('彻底删除成功！', U('firstpay/recovery'));
        }else{
            $this->baoError('操作失败');
        }
    }
}

