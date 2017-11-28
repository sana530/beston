<?php



class DeliveryAction extends CommonAction {

    private $create_fields = array('user_id', 'first_name', 'last_name', 'identity', 'license', 'license_version', 'passport', 'email', 'mobile', 'addr', 'photo_id');
    private $edit_fields = array('user_id', 'first_name', 'last_name', 'identity', 'license', 'license_version', 'passport', 'email', 'mobile', 'addr', 'photo_id');


    public function index() {
        $obj = D('Delivery');
        import('ORG.Util.Page');
        $map = array('closed' => 0, 'audit' => 1);
        if ($keyword = $this->_param('keyword', 'htmlspecialchars')) {
            $map['email|mobile'] = array('LIKE', '%' . $keyword . '%');
            $this->assign('keyword', $keyword);
        }
        $count = $obj->where($map)->count();

        $Page = new Page($count, 25);
        $show = $Page->show();
        $list = $obj->order(array('add_time' => 'asc'))->where($map)->limit($Page->firstRow . ',' . $Page->listRows)->select();
        $ids = array();
        foreach ($list as $k => $val) {
            $list[$k]['photo_id_type'] = substr($val['photo_id'], -3);
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
        $obj = D('Delivery');
        import('ORG.Util.Page');
        $map = array('audit' => 0, 'closed'=> 0);
        if ($keyword = $this->_param('keyword', 'htmlspecialchars')) {
            $map['email|mobile'] = array('LIKE', '%' . $keyword . '%');
            $this->assign('keyword', $keyword);
        }
        $count = $obj->where($map)->count();

        $Page = new Page($count, 25);
        $show = $Page->show();
        $list = $obj->order(array('id' => 'asc'))->where($map)->limit($Page->firstRow . ',' . $Page->listRows)->select();
        $ids = array();
        foreach ($list as $k => $val) {
            $list[$k]['photo_id_type'] = substr($val['photo_id'], -3);
            if ($val['user_id']) {
                $ids[$val['user_id']] = $val['user_id'];
            }
        }
        $this->assign('users', D('Users')->itemsByIds($ids));
        $this->assign('list', $list);
        $this->assign('page', $show);
        $this->display();
    }

    public function audit($id = 0){
        $this->baoError('您没有相关权限');
        $obj = D('Delivery');
        if (is_numeric($id) && ($id = (int) $id)) {
            $obj->save(array('id' => $id, 'audit' => 1));
            $this->baoSuccess('审核成功！', U('delivery/apply'));
        } else {
            $ids = $this->_post('id', false);
            if (is_array($ids)) {
                foreach ($ids as $id) {
                    $obj->save(array('id' => $id, 'audit' => 1));
                }
                $this->baoSuccess('审核成功！', U('delivery/apply'));
            }
            $this->baoError('请选择要审核的用户');
        }
    }

    public function delete($id = 0)
    {
        $this->baoError('您没有相关权限');
        $obj = D('Delivery');
        if (is_numeric($id) && ($id = (int) $id)) {
            $obj->save(array('id' => $id, 'closed' => 1));
            $this->baoSuccess('删除成功！', $_SERVER['HTTP_REFERER']);
        } else {
            $ids = $this->_post('id', false);
            if (is_array($ids)) {
                foreach ($ids as $id) {
                    $obj->save(array('id' => $id, 'closed' => 1));
                }
                $this->baoSuccess('删除成功！', $_SERVER['HTTP_REFERER']);
            }
            $this->baoError('请选择要删除的用户');
        }
    }

    public function create(){
        $this->baoError('您没有相关权限');
        if ($this->isPost()) {
            $data = $this->createCheck();
            if (D('Delivery')->add($data)) {
                $this->baoSuccess('恭喜您申请成功！', U('delivery/index'));
            }
            $this->baoError('申请失败！');
        } else {
            $this->display();
        }

    }

    private function createCheck()
    {
        $data = $this->checkFields($this->_post('data', false), $this->create_fields);
        if (empty($data['user_id'])) {
            $this->baoError('用户不能为空');
        }
        if (empty($data['first_name'])) {
            $this->baoError('名字不能为空');
        }
        if (empty($data['last_name'])) {
            $this->baoError('姓氏不能为空');
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
        $data['mobile'] = htmlspecialchars($data['mobile']);
        if (empty($data['mobile'])) {
            $this->baoError('手机不能为空');
        }
        if (!isPhone($data['mobile']) && !isMobile($data['mobile']) && !isCnMobile($data['mobile'])) {
            $this->baoError('手机格式不正确');
        }
        if(D('Delivery')->where('mobile ='.$data['mobile'])->find()){
            $this->baoError('重复的手机号！');
        }
        $data['addr'] = htmlspecialchars($data['addr']);
        if (empty($data['addr'])) {
            $this->baoError('地址不能为空');
        }
        $data['photo_id'] = htmlspecialchars($data['photo_id']);
        if (empty($data['photo_id'])) {
            $this->baoError('请上传身份证明');
        }
        if (!isImage($data['photo_id']) && !isPdf($data['photo_id'])) {
            $this->baoError('身份证明格式不正确');
        }
        $data['add_time'] = NOW_TIME;
        $data['audit'] = 1;
        return $data;
    }

    public function edit($id = 0)
    {
        $this->baoError('您没有相关权限');
        if ($id = (int) $id) {
            $obj = D('Delivery');
            if (!($detail = $obj->find($id))) {
                $this->baoError('请选择要编辑的快递员');
            }
            $detail['photo_id_type'] = substr($detail['photo_id'], -3);
            if ($this->isPost()) {
                $data = $this->editCheck();
                $data['id'] = $id;
                if (false !== $obj->save($data)) {
                    if ($obj->where(array('id' => $id,'audit' => 1))->count()) {
                        $this->baoSuccess('操作成功', U('delivery/index'));
                    } else {
                        $this->baoSuccess('操作成功', U('delivery/apply'));
                    }
                }
                $this->baoError('操作失败');
            } else {
                $this->assign('user', D('Users')->find($detail['user_id']));
                $this->assign('detail', $detail);
                $this->display();
            }
        } else {
            $this->baoError('请选择要编辑的快递员');
        }
    }

    private function editCheck()
    {
        $data = $this->checkFields($this->_post('data', false), $this->edit_fields);
        $data['user_id'] = (int) $data['user_id'];
        if (empty($data['user_id'])) {
            $this->baoError('用户不能为空');
        }
        if (empty($data['first_name'])) {
            $this->baoError('名字不能为空');
        }
        if (empty($data['last_name'])) {
            $this->baoError('姓氏不能为空');
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
        $data['mobile'] = htmlspecialchars($data['mobile']);
        if (empty($data['mobile'])) {
            $this->baoError('手机不能为空');
        }
        if (!isPhone($data['mobile']) && !isMobile($data['mobile']) && !isCnMobile($data['mobile'])) {
            $this->baoError('手机格式不正确');
        }
        if(D('Delivery')->where('mobile ='.$data['mobile'])->find()){
            $this->baoError('重复的手机号！');
        }
        $data['addr'] = htmlspecialchars($data['addr']);
        if (empty($data['addr'])) {
            $this->baoError('地址不能为空');
        }
        $data['photo_id'] = htmlspecialchars($data['photo_id']);
        if (empty($data['photo_id'])) {
            //$this->baoError('请上传身份证明');
        }
        if (!isImage($data['photo_id']) && !isPdf($data['photo_id'])) {
            //$this->baoError('身份证明格式不正确');
        }
        $data['audit'] = 1;
        return $data;
    }

    public function lists(){

        $id = I('id','','intval,trim');
        if(!$id){
            $this->baoError('没有选择！');
        }else{
            $this->assign('delivery',D('Delivery')->where('id ='.$id)->find());
            $dvo = D('DeliveryOrder');
            import('ORG.Util.Page');// 导入分页类
            $count      = $dvo->where('delivery_id ='.$id)->count();// 查询满足要求的总记录数
            $Page       = new Page($count,25);// 实例化分页类 传入总记录数和每页显示的记录数
            $show       = $Page->show();// 分页显示输出
            // 进行分页数据查询 注意limit方法的参数要使用Page类的属性
            $list = $dvo->where('delivery_id ='.$id)->order('order_id desc')->limit($Page->firstRow.','.$Page->listRows)->select();
            $this->assign('list',$list);// 赋值数据集
            $this->assign('page',$show);// 赋值分页输出
            $this->display(); // 输出模板
        }

    }


}
