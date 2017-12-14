<?php
class BookingAction extends CommonAction {
    private $edit_fields = array('status','last_date','last_t','menu','number');
    public function _initialize() {
        parent::_initialize();
        $dingtypes = D('Booking')->getDingType();
        $this->assign('dingtypes',$dingtypes);
		if ($this->_CONFIG['operation']['booking'] == 0) {
				$this->error('此功能已关闭');die;
		}
        $obj = D('Booking');
        if (empty($obj->where(array('shop_id' => $this->shop_id,'is_open' => 1))->find())) {
            $this->error('订座功能要和网站洽谈，由网站开通！');
        }
        $this->dingcates = D('Bookingcate')->where(array('shop_id' => $this->shop_id))->select();
        $this->assign('Bookingcate', $this->dingcates);
    }
    public function index() {
        $dingorder = D('Bookingorder');
        import('ORG.Util.Page'); // 导入分页类
        $map = array('closed' => 0);
        if ($order_no = $this->_param('order_no')) {
			$map['order_id'] = array('LIKE', '%' . $order_no . '%');
            $this->assign('order_no', $order_no);
        }

        if ($shop_id = $this->shop_id) {
            $map['shop_id'] = $shop_id;
            $this->assign('shop_id', $shop_id);
        }
		if ($status = $this->_param('status')) {
            $map['order_status'] = $status-2;
            $this->assign('status', $status);
        }else{
			$map['order_status'] = 1;
		}
        $count = $dingorder->where($map)->count(); // 查询满足要求的总记录数

        $Page = new Page($count, 25); // 实例化分页类 传入总记录数和每页显示的记录数
        $show = $Page->show(); // 分页显示输出
        $list = $dingorder->where($map)->order(array('create_time' => 'desc'))->limit($Page->firstRow . ',' . $Page->listRows)->select();
		$arr = $dingorder->get_ding($shop_id,$list);
		$this->assign('arr', $arr);
        $this->assign('list', $list); // 赋值数据集
        $this->assign('page', $show); // 赋值分页输出
        $this->display(); // 输出模板
    }


    public function detail($order_id){
		$dingorder = D('Bookingorder');

		$dingyuyue = D('Bookingyuyue');
		$dingmenu = D('Bookingmenu');
		if(!$order = $dingorder->where('order_id = '.$order_id)->find()){
			$this->error('该订单不存在');
        //}else if(!$yuyue = $dingyuyue->where('ding_id = '.$order['ding_id'])->find()){
        ////$this->error('该订单不存在');
		}else{
			$arr = $dingorder->get_detail($this->shop_id,$order,$yuyue); // 无预约数据
			$menu = $dingmenu->shop_menu($this->shop_id);
			$this->assign('yuyue', $yuyue);
			$this->assign('order', $order);
			$this->assign('order_id', $order_id);
			$this->assign('arr', $arr);
			$this->assign('menu', $menu);
			$this->display();
		}

	}
   
     public function refund($order_id) {
                $order_id = (int) $order_id;
				$dingorder = D('Bookingorder');
		        $dingyuyue = D('Bookingyuyue');
				if(!$order = $dingorder->where('order_id = '.$order_id)->find()){
					$this->ajaxReturn(array('status'=>'error','msg'=>'该订单不存在！'));
				}else if($order['status'] == -1){
					$this->ajaxReturn(array('status'=>'error','msg'=>'请不要重复退款'));
				}else if(!$yuyue = $dingyuyue->where('ding_id = '.$order['ding_id'])->find()){
					$this->ajaxReturn(array('status'=>'error','msg'=>'该订单不存在！'));
				}else if($yuyue['shop_id'] != $this->shop_id){
					$this->ajaxReturn(array('status'=>'error','msg'=>'非法操作！'));
				}else{
					
					D('Shopdingorder')->save(array('order_id' => $order_id, 'status' => -1));
					D('Users')->addMoney($order['user_id'], $order['need_price'], '订座退款');
					$this->ajaxReturn(array('status'=>'success','msg'=>'退款成功', U('ding/detail', array('order_id' => $order_id))));
				}
				
				
		
		}

	public function edit($order_id){
		$dingorder = D('Bookingorder');
		$dingyuyue = D('Bookingyuyue');
		$dingmenu = D('Bookingmenu');
		if(!$order = $dingorder->where('order_id = '.$order_id)->find()){
			$this->error('该订单不存在');
		//}else if(!$yuyue = $dingyuyue->where('ding_id = '.$order['ding_id'])->find()){
		//	$this->error('该订单不存在');
		// }else if($yuyue['shop_id'] != $this->shop_id){
			
		//	$this->error('非法操作1');
		}else{

			if(!$status = $this->_post('status')){
				$this->error('非法操作2');
			}else if($status == 2){
				$data['status'] = $status;
				D('Bookingorder')->where('order_id='.$order_id)->save($data);
				$shop = D('Shop')->find($dingorder['shop_id']);
				$shopmoney = D('Shopmoney');
				$shopmoney->add(array(
					'shop_id' => $dingorder['shop_id'],
					'city_id' => $shop['city_id'],
					'area_id' => $shop['area_id'],
					'branch_id' => $data['branch_id'],
					'money' => $order['need_price'],
					'create_ip' => get_client_ip(),
					'type' => 'booking',
					'create_time' => NOW_TIME,
					'create_ip' => get_client_ip(),
					'order_id' => $order['order_id'],
					'intro' => '订座已消费',
				));
				
				D('Users')->Money($shop['user_id'], $order['need_price'], '商户订座资金结算:'.$order['order_id']);
				//D('Users')->addMoney($shop['user_id'], $order['need_price'], '订座已消费');
                D('Users')->gouwu($order['user_id'],$order['need_price'],'订座成功返积分');
				$this->Success('订单修改成功', U('booking/detail', array('order_id' => $order_id)));
						}else{
				$this->error('非法操作');
			}
		}
	}
	
	 //旧版订座配置
    public function setting(){
        $obj = D('Bookingsetting');
        if(IS_POST){
            $data['shop_id'] = $this->shop_id;
            $data['mobile'] = htmlspecialchars($_POST['data']['mobile']);
            if(!isMobile($data['mobile']) && !isCnMobile($data['mobile'])){
                $this->fengmiMsg('请填写正确的手机号码！');
            }
            $data['money'] = (int)($_POST['data']['money']* 100);
			if(empty($data['money'])){
				$this->fengmiMsg('定金不能为空或者为0');
			}
            $data['bao_time'] = (int)$_POST['data']['bao_time'];
            $data['start_time'] = (int)$_POST['data']['start_time'];
	
            $data['end_time'] = (int)$_POST['data']['end_time'];
			
            $data['is_bao'] = (int)$_POST['data']['is_bao'];
            $data['is_ting'] = (int)$_POST['data']['is_ting'];
            $data['audit'] = 1; //自动审核
            $obj->save($data);
            $this->fengmiMsg('恭喜您设置成功！',U('booking/index'));
        }  else {
            $this->assign('cfg',$obj->getCfg());
            $this->assign('ctl','setting');
            $this->assign('detail',$obj->detail($this->shop_id));
            $this->display();
        }
    }

    //新版订座配置
    public function setbooking(){
        $obj = D('Booking');
        $booking = $obj->find($this->shop_id);
        if ($this->isPost()) {
            $data = $this->createCheck();
            $thumb = $this->_param('thumb', false);
            $type = $this->_param('type',false);
            foreach ($thumb as $k => $val) {
                if (empty($val)) {
                    unset($thumb[$k]);
                }
                if (!isImage($val)) {
                    unset($thumb[$k]);
                }
            }
            if (empty($booking)) {
                $data['create_time'] = NOW_TIME;
                $data['create_ip'] = get_client_ip();
                if($obj->add($data)){
                    foreach($thumb as $k=>$val){
                        D('Bookingpics')->add(array('shop_id'=>$this->shop_id,'photo'=>$val));
                    }
                    foreach($type as $k=>$val){
                        D('Bookingattr')->add(array('shop_id'=>$this->shop_id,'type_id'=>$val));
                    }

                    $this->fengmiMsg('设置成功', U('booking/index'));
                }else{
                    $this->fengmiMsg('设置失败');
                }
            }else{
                $data['update_time'] = NOW_TIME;
                $data['update_ip'] = get_client_ip();
                if(false !== $obj->save($data)){
                    D('Bookingpics')->where(array('shop_id'=>$this->shop_id))->delete();
                    foreach($thumb as $k=>$val){
                        D('Bookingpics')->add(array('shop_id'=>$this->shop_id,'photo'=>$val));
                    }
                    D('Bookingattr')->where(array('shop_id'=>$this->shop_id))->delete();
                    foreach($type as $k=>$val){
                        D('Bookingattr')->add(array('shop_id'=>$this->shop_id,'type_id'=>$val));
                    }
                    $this->fengmiMsg('修改成功', U('booking/index'));
                }else{
                    $this->fengmiMsg('修改失败');
                }
            }
        } else {
            $this->assign('booking',$booking);
            $thumb = D('Bookingpics')->where(array('shop_id'=>$this->shop_id))->select();
            $this->assign('thumb', $thumb);
            $have_type = D('Bookingattr')->where(array('shop_id'=>$this->shop_id))->select();
            $typess = array();
            foreach ($have_type as $k=>$val){
                $typess[$val['type_id']] = $val['type_id'];
            }
            $this->assign('cfg',$obj->getCfg());
            $this->assign('ctl','setbooking');//footer_booking显示选中设置
            $this->assign('have_type',$typess);
            $this->display();
        }
    }

    //订座配置检查
    private function createCheck() {
        $data = $this->checkFields($this->_post('data', false), array('shop_name', 'addr', 'city_id', 'area_id','business_id','price','mobile','stime','ltime','deposit','tel', 'details', 'photo', 'lng', 'lat','business_time'));
        $data['shop_id'] = $this->shop_id;
        $data['shop_name'] = htmlspecialchars($data['shop_name']);
        if (empty($data['shop_name'])) {
            $this->fengmiMsg('名称不能为空');
        }
        $data['addr'] = htmlspecialchars($data['addr']);
        if (empty($data['addr'])) {
            $this->fengmiMsg('地址不能为空');
        }
        $data['price'] = (int)$data['price'];
        if (empty($data['price'])) {
            $this->fengmiMsg('平均消费不能为空');
        }
        $data['tel'] = htmlspecialchars($data['tel']);
        if (empty($data['tel'])) {
            $this->fengmiMsg('联系电话不能为空');
        }
        $data['mobile'] = htmlspecialchars($data['mobile']);
        if (empty($data['mobile'])) {
            $this->fengmiMsg('手机号不能为空');
        }
        if (!isMobile($data['mobile']) && !isCnMobile($data['mobile'])){
            $this->fengmiMsg('手机号格式不正确');
        }
        $data['deposit'] = (int)$data['deposit'];
        $data['lng'] = htmlspecialchars($data['lng']);
        $data['lat'] = htmlspecialchars($data['lat']);
        if (empty($data['lng']) || empty($data['lat'])) {
            $this->fengmiMsg('请输入正确地址');
        }
        $data['area_id'] = $this->shop['area_id'];
        $data['business_id'] = $this->shop['business_id'];
        $data['city_id'] = $this->shop['city_id'];
        $data['photo'] = htmlspecialchars($data['photo']);
        if (empty($data['photo'])) {
            $this->fengmiMsg('请上传订座图');
        }
        if (!isImage($data['photo'])) {
            $this->fengmiMsg('上传图片格式不正确');
        }
        //$data['business_time'] = htmlspecialchars($data['business_time']);
        $data['stime'] = htmlspecialchars($data['stime']);
        $data['ltime'] = htmlspecialchars($data['ltime']);
        if (($data['stime'] == "00:30") && ($data['ltime'] == "00:30")) {
            $this->fengmiMsg('请选择营业时间');
        }
        $data['business_time'] = $data['stime'] . '-' . $data['ltime'];

        $data['details'] = SecurityEditorHtml($data['details']);
        if (empty($data['details'])) {
            $this->fengmiMsg('详情不能为空');
        }
        if ($words = D('Sensitive')->checkWords($data['details'])) {
            $this->fengmiMsg('详情含有敏感词：' . $words);
        }
        $data['audit'] = 1; //自动审核
        return $data;
    }


    //包厢首页
    public function room(){
        $obj = D('Bookingroom');
        import('ORG.Util.Page'); // 导入分页类
        $map = array('shop_id'=>  $this->shop_id);
        $keyword = trim($this->_param('keyword', 'htmlspecialchars'));
        if ($keyword) {
            $map['name'] = array('LIKE', '%'.$keyword.'%');
        }
        $this->assign('keyword',$keyword);
        if($type_id = (int)$this->_param('type_id')){
            $map['type_id'] = $type_id;
            $this->assign('type_id',$type_id);
        }        
        $count = $obj->where($map)->count(); // 查询满足要求的总记录数 
        $Page = new Page($count, 25); // 实例化分页类 传入总记录数和每页显示的记录数
        $show = $Page->show(); // 分页显示输出
        $list = $obj->where($map)->limit($Page->firstRow . ',' . $Page->listRows)->select();
        $this->assign('types',$obj->getType());
        $this->assign('list', $list); // 赋值数据集
        $this->assign('page', $show); // 赋值分页输出
       
        $this->display();
    }
    
    //添加包厢
    public function roomcreate(){
         $obj = D('Bookingroom');
         if(IS_POST){
             $data['name'] = htmlspecialchars($_POST['data']['name']);
             if(empty($data['name'])){
                 $this->fengmiMsg('包厢名称不能为空');
             }
             $data['type_id'] = (int)($_POST['data']['type_id']);
             if(empty($data['type_id'])){
                 $this->fengmiMsg('请选择房间大小');
             }
             $data['photo'] = htmlspecialchars($_POST['data']['photo']);
             if(empty($data['photo'])){
                 $this->fengmiMsg('请上传图片');
             }
             $data['intro'] = htmlspecialchars($_POST['data']['intro']);
             $data['money'] = (int)($_POST['data']['money']*100);
             $data['closed'] = (int)($_POST['data']['closed']);
             
             $data['shop_id'] = $this->shop_id;
             if($obj->add($data)){
                 $this->fengmiMsg('恭喜你操作成功',U('store/booking/room'));
             }
             $this->fengmiMsg('操作失败');
         }else{             
             $this->assign('types',$obj->getType());
             $this->display();
         }
    }
    //编辑包厢
    public function roomedit($room_id){
        $obj = D('Bookingroom');
        if(!$detail = $obj->find($room_id)){
            $this->error('参数错误');
        }
        if($detail['shop_id']!= $this->shop_id){
            $this->error('参数错误');
        }
        $obj = D('Bookingroom');
         if(IS_POST){
             $data['name'] = htmlspecialchars($_POST['data']['name']);
             if(empty($data['name'])){
                 $this->fengmiMsg('包厢名称不能为空');
             }
             $data['type_id'] = (int)($_POST['data']['type_id']);
             if(empty($data['type_id'])){
                 $this->fengmiMsg('请选择房间大小');
             }
             $data['photo'] = htmlspecialchars($_POST['data']['photo']);
             if(empty($data['photo'])){
                 $this->fengmiMsg('请上传图片');
             }
             $data['intro'] = htmlspecialchars($_POST['data']['intro']);
             $data['money'] = (int)($_POST['data']['money']*100);
             $data['closed'] = (int)($_POST['data']['closed']);
             $data['room_id'] = $room_id;
             $data['shop_id'] = $this->shop_id;
             if(false !== $obj->save($data)){
                 $this->fengmiMsg('恭喜你操作成功',U('store/booking/room'));
             }
             $this->fengmiMsg('操作失败');
         }else{             
             $this->assign('types',$obj->getType());
             $this->assign('detail',$detail);
             $this->display();
         }
    }
    //删除包厢
    public function roomdelete($room_id){
         $obj = D('Bookingroom');
        if($room_id = (int)$room_id){
            if(!$detail = $obj->find($room_id)){
                $this->ajaxReturn(array('status'=>'error','msg'=>'参数错误！'));
            }
            if($detail['shop_id']!= $this->shop_id){
                $this->ajaxReturn(array('status'=>'error','msg'=>'参数错误！'));
            }
            $data['closed'] = $detail['closed'] ? 0 : 1;
            $data['room_id'] = $room_id;
            if(false != $obj->save($data)){
				$this->ajaxReturn(array('status'=>'error','msg'=>'操作成功！',U('store/booking/room')));
            }
            $this->ajaxReturn(array('status'=>'error','msg'=>'操作失败！'));
        }else{
            $this->ajaxReturn(array('status'=>'error','msg'=>'参数错误！'));
        }        
    }
    
}

