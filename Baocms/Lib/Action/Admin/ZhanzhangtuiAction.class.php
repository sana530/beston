<?php


class ZhanzhangtuiAction extends CommonAction{

    public function index(){
        $Tui = D('Delivery');
        import('ORG.Util.Page');// 导入分页类
        $map = array();
        if($keyword = $this->_param('keyword',  'htmlspecialchars')){
           $map['first_name|last_name'] = array('LIKE', '%'.$keyword.'%');
        }
        $this->assign('keyword',$keyword);

        $map['audit'] = 1;
        $map['closed'] = 0;
        $map['is_zhanzhang'] = 1;
        $count = $Tui->where($map)->count();// 查询满足要求的总记录数
        $Page = new Page($count,25);// 实例化分页类 传入总记录数和每页显示的记录数
        $show = $Page->show();// 分页显示输出
        $list = $Tui->where($map)->order(array('id'=>'desc'))->limit($Page->firstRow.','.$Page->listRows)->select();
        $this->assign('list',$list);// 赋值数据集
        $this->assign('page',$show);// 赋值分页输出
        $this->display(); // 输出模板
    }

    /* 驿站站长下属绑定的会员列表页 */
    public function associate() {
        $usersModel = D('Users');
        import('ORG.Util.Page');// 导入分页类
        $zhanzhang_uid = $this->_param('zhanzhang',  'intval');
        if ($zhanzhang_uid <= 0) {
            $this->error('该站长不存在');
        }
        $where = array('user_id' => $zhanzhang_uid, 'audit' => 1, 'closed' => 0, 'is_zhanzhang' => 1);
        $zhanzhang = D('Delivery')->where($where)->find();
        if (!$zhanzhang) {
            $this->error('该站长不存在');
        }
        $map = array();
        if($keyword = $this->_param('keyword',  'htmlspecialchars')){
            $map['nickname|account|mobile'] = array('LIKE', '%'.$keyword.'%');
        }
        $this->assign('keyword',$keyword);
        $map['bang_zhanzhang'] = $zhanzhang_uid;
        $count = $usersModel->where($map)->count();
        $Page = new Page($count,25);// 实例化分页类 传入总记录数和每页显示的记录数
        $show = $Page->show();// 分页显示输出
        $list = $usersModel->where($map)->limit($Page->firstRow.','.$Page->listRows)->select();
        $this->assign('list',$list);// 赋值数据集
        $this->assign('page',$show);// 赋值分页输出
        $this->display(); // 输出模板
    }

    /* 修改会员要绑定的驿站站长 */
    public function changeAssociate($user_id = 0){
        if($user_id =(int) $user_id){
            $obj = D('Users');
            if(!$detail = $obj->find($user_id)){
                $this->baoError('您要换绑的用户不存在');
            }
            if ($this->isPost()) {
                $data = $this->changeAssociateCheck();
                $save['user_id'] = $user_id;
                $save['bang_zhanzhang'] = $data['user_id'];
                if(false!==$obj->save($save)){
                    $this->baoSuccess('操作成功',U('zhanzhangtui/associate', array('zhanzhang'=>$data['user_id'])));
                }
                $this->baoError('操作失败');

            }else{
                $map['audit'] = 1;
                $map['closed'] = 0;
                $map['is_zhanzhang'] = 1;
                $list = D('Delivery')->where($map)->order(array('id'=>'desc'))->select();
                $this->assign('list',$list);
                $this->assign('detail',$detail);
                $this->display();
            }
        }else{
            $this->baoError('请选择要换绑的用户');
        }
    }

    private function changeAssociateCheck() {
        $zhanzhang_uid = $this->_post('zhanzhang', 'intval');
        if(empty($zhanzhang_uid)){
            $this->baoError('站长不能为空');
        }
        $zhanzhang = D('Delivery')->where(array('user_id'=>$zhanzhang_uid, 'audit'=>1, 'closed'=>0, 'is_zhanzhang'=>1))->find();
        if(empty($zhanzhang)){
            $this->baoError('不能选择该站长');
        }
        return $zhanzhang;
    }

    public function delete($zhanzhang = 0){
         if(is_numeric($zhanzhang) && ($zhanzhang = (int)$zhanzhang)){
             $obj =D('Delivery');
             $obj->delete($zhanzhang);
             $this->baoSuccess('删除成功！',U('tui/index'));
         }
    }

    
   
}
