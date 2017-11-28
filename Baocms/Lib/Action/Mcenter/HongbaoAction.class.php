<?php
class HongbaoAction extends CommonAction {

	public function index() {
        $aready = (int) $this->_param('aready');
		$this->assign('aready', $aready);
        $this->assign('nextpage', LinkTo('hongbao/hongbaoloading', array("t"=>NOW_TIME,"p"=>"0000","aready"=>$aready)));
		$this->display();
	}

	/* 加载个人红包内容 */
	public function hongbaoloading() {
		$hongbaoReceive = D('Shophongbaoreceive');
		import('ORG.Util.Page');
		$map = array('user_id' => $this->uid);

		//红包使用分类页面
        $aready = (int) $this->_param('aready');
		if ($aready == 2) {
			$map['is_used'] = array('egt',1);
		}elseif ($aready == 1) {
			$map['is_used'] = 0;
        }else{
            $aready == null;
        }

        //列出总条数，并分页
		$count = $hongbaoReceive->where($map)->count();
		$Page = new Page($count, 25);
		$show = $Page->show();
		$var = C('VAR_PAGE') ? C('VAR_PAGE') : 'p';
		$p = $_GET[$var];
		if ($Page->totalPages < $p) {
			die('0');
		}

		//列出所有红包以及相应商铺红包内容
		$list = $hongbaoReceive->where($map)->order('is_used asc')->limit($Page->firstRow . ',' . $Page->listRows)->select();
		$hongbao_ids = array();
		foreach ($list as $k => $val) {
			$hongbao_ids[$val['hongbao_id']] = $val['hongbao_id'];
            $shop_ids[$val['shop_id']] = $val['shop_id'];
		}
		$shops = D('Shop')->itemsByIds($shop_ids);
		$hongbao = D('Shophongbao')->itemsByIds($hongbao_ids);
		$this->assign('hongbao', $hongbao);
		$this->assign('shops', $shops);
		$this->assign('list', $list);
		$this->assign('page', $show);
		
		$this->display();
	}

	/* 删除个人红包 */
	public function hongbaodel($receive_id) {
		$receive_id = (int) $receive_id;
		if (empty($receive_id)) {
			$this->error('该红包不存在');
		}
		if (!$detail = D('Shophongbaoreceive')->find($receive_id)) {
			$this->error('该红包不存在');
		}
		if ($detail['user_id'] != $this->uid) {
			$this->error('请不要操作别人的红包');
		}
		D('Shophongbaoreceive')->delete($receive_id);
		$this->success('删除成功！', U('hongbao/index'));
	}

	/* 红包赠送 */
    public function give() {
        $receive_id = $this->_get('receive_id');
        $obj = D('Shophongbaoreceive');

        //验证红包状态
        if (!$detail = $obj->find($receive_id)) {
            $this->error('没有该红包');
        }
        if ($detail['user_id'] != $this->uid) {
            $this->error("红包不存在！");
        }
        if ( $detail['is_used'] != 0) {
            $this->error('该红包已被使用');
        }
		 
        if($this->isPost()){
            //再次检查用户合法性
            $nickname = $this->_post('nickname');
            $user = D('Users')->where(array('nickname'=>$nickname))->find();

            if(empty($user)){
                $this->fengmiMsg('用户不存在');
            }

            $my_user = $this->uid;

            if($my_user == $user['user_id'] ){
                $this->fengmiMsg('不能赠送给自己');
            }

            //检查被赠送的用户是否已经有该店的红包
            $where = array(
                'hongbao_id'=>$detail['hongbao_id'],
                'user_id'=>$user['user_id'],
                'is_used'=>0
            );
            $receiver_hongbao = $obj->where($where)->getField();
            if ($receiver_hongbao) {
                $this->fengmiMsg('您要赠送的用户已经有该红包了，还是自己用吧');
            }

            //修改红包拥有者
            $obj->save(array('receive_id' => $receive_id, 'user_id' => $user['user_id']));
            $this->fengmiMsg('恭喜您赠送成功', U('/mcenter/hongbao/index/'));
        }
		
        $this->assign('detail', $detail);
        $this->display();
	}
	
}