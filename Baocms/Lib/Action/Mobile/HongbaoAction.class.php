<?php
class HongbaoAction extends CommonAction {

    public function _initialize() {
        parent::_initialize();
        if ($this->_CONFIG['operation']['hongbao'] == 0) {
            $this->error('此功能已关闭');
            die;
        }
    }

    public function index() {

        //取出地址栏中传递的参数信息
	    $keyword = $this->_param('keyword', 'htmlspecialchars');
        $this->assign('keyword', $keyword);
        $cat = (int) $this->_param('cat');
        $this->assign('cat', $cat);
        $order = (int) $this->_param('order');

        //列出所有的商店分类列表（红包列表引用商店分类列表）
        $shopcates = D('Shopcate')->fetchAll();
        foreach ($shopcates as $key => $v) {
            $shopcates[$key]['count'] = 0;
        }

        //取出所有合法红包，并记录到缓存中
        $hongbao = fetchCalldata(array('mdl'=>'Shophongbao', 'where'=>'audit=1 AND closed=0 AND expire_date > "'.TODAY.'"','cache'=>3600));

        //计算出每个商铺分类下有多少红包
        foreach ($hongbao as $key => $v) {
            $shopcate = D('Shop')->where(array('shop_id'=>$v['shop_id']))->getField('cate_id');
            if ($shopcate) {
                $shopcates[$shopcate]['count']++;
                $parent_id = $shopcates[$shopcate]['parent_id'];
                $parent_id != 0 && ($shopcates[$parent_id]['count']++);
            }
        }

        //分配模板
        $this->assign('shopcates', $shopcates);
        $areas = D('Area')->fetchAll();
        $area_id = (int) $this->_param('area');
        $this->assign('area_id', $area_id);
        $this->assign('areas', $areas);
        $this->assign('nextpage', LinkTo('hongbao/loaddata', array('cat' => $cat, 't' => NOW_TIME, 'area_id' => $area_id, 'order' => $order,  'keyword' => $keyword, 'p' => '0000')));
        $this->display(); // 输出模板
    }


    /* 加载出所有红包内容 */
    public function loaddata() {

        $Shophongbao = D('Shophongbao');
        import('ORG.Util.Page'); // 导入分页类

        //组合查询条件
        $map = array('audit' => 1,'city_id'=>$this->city_id, 'closed' => 0, 'expire_date' => array('EGT', TODAY));
        if ($keyword = $this->_param('keyword', 'htmlspecialchars')) {
            $map['intro'] = array('LIKE', '%' . $keyword . '%');
        }
        $area_id = (int) $this->_param('area_id');
        if ($area_id) {
            $map['area_id'] = $area_id;
        }
        $cat = (int) $this->_param('cat');
        if ($cat) {
            $catids = D('Shopcate')->getChildren($cat);
            if (!empty($catids)) {
                $map['cate_id'] = array('IN', $catids);
            } else {
                $map['cate_id'] = $cat;
            }
        }
        $order = $this->_param('order', 'htmlspecialchars');
        $orderby = '';
        switch ($order) {
            case 1:
                $orderby = array('receive_num' => 'desc');
                break;
            default:
                  $orderby = array('amount' => 'desc');
                break;
        }
     
        $this->assign('order', $order);
        $count = $Shophongbao->where($map)->count(); // 查询满足要求的总记录数
        $Page = new Page($count, 10); // 实例化分页类 传入总记录数和每页显示的记录数
        $show = $Page->show(); // 分页显示输出

        //分页
        $var = C('VAR_PAGE') ? C('VAR_PAGE') : 'p';
        $p = $_GET[$var];
        if ($Page->totalPages < $p) {
            die('0');
        }

        //查询出所有红包信息，并生成相应领取二维码
        $list = $Shophongbao->where($map)->order($orderby)->limit($Page->firstRow . ',' . $Page->listRows)->select();
        foreach ($list as $k => $val) {
            if ($val['shop_id']) {
                $shop_ids[$val['shop_id']] = $val['shop_id'];
            }
            $list[$k]['photo'] =  baoQrCode('hongbao_'.$val['hongbao_id'], U('hongbao/receive', array('hongbao_id'=>$val['hongbao_id'])));
        }

        //获取位置信息
        $lat = addslashes(cookie('lat'));
        $lng = addslashes(cookie('lng'));
        if (empty($lat) || empty($lng)) {
            $lat = $this->city['lat'];
            $lng = $this->city['lng'];
        }

        //计算出相应店铺离用户的距离
        if ($shop_ids) {
            $shops = D('Shop')->itemsByIds($shop_ids);
            $ids = array();
            foreach ($shops as $k => $val) {
                $shops[$k]['d'] = getDistance($lat, $lng, $val['lat'], $val['lng']);
                $d = getDistanceNone($lat, $lng, $val['lat'], $val['lng']);
                $ids[$d][] = $k; //防止同样的距离出现 
            }
            ksort($ids);
            $showshops = array();
            foreach ($ids as $arr1) {
                foreach ($arr1 as $val) {
                    $showshops[$val] = $shops[$val];
                }
            }
            $this->assign('shops', $showshops);
        }

        $this->assign('list', $list); // 赋值数据集
        $this->assign('page', $show); // 赋值分页输出
        $this->display(); // 输出模板

    }

    /* 红包详情页 */
    public function detail() {

        $hongbao_id = (int) $this->_get('hongbao_id');
        if (empty($hongbao_id)) {
            $this->error('该红包不存在！');
            die;
        }
		
        $Shophongbao = D('Shophongbao');
        if (!$detail = $Shophongbao->find($hongbao_id)) {
            $this->error('该红包不存在！');
            die;
        }
        $Shophongbao->updateCount($hongbao_id, 'views');
        $shop = D('Shop')->find($detail['shop_id']);
        $this->assign('shop', $shop);
        $this->assign('ex', D('Shopdetails')->changeBTime(D('Shopdetails')->find($detail['shop_id'])));
        $this->assign('detail', $detail);
        $this->seodatas['shop_name'] = $shop['shop_name'];
        $this->seodatas['title'] = $detail['title'];
        $this->display(); // 输出模板

    }

    /* 红包领取 */
    public function receive() {

        //红包以及个人合法性验证
        if (empty($this->uid)) {
           $this->error('登录状态失效!', U('passport/login'));
        }

        if (empty($this->member['mobile'])) {
            $this->error('亲还没有验证手机号码！', U('Mcenter/information/index'));
        }

        $hongbao_id = (int) $this->_get('hongbao_id');
        if (empty($hongbao_id)) {
            $this->error('该红包不存在！');
            die;
        }

        $Shophongbao = D('Shophongbao');
        if (!$detail = $Shophongbao->where(array('audit'=>1, 'closed'=>0, 'hongbao_id'=>$hongbao_id))->find()) {
            $this->error('该红包不存在！');
            die;
        }

        if ($detail['expire_date'] < TODAY) {
            $this->error('该红包已经过期');
        }
        
        if($detail['num'] <= $detail['receive_num']){
            $this->error('该红包已经领完了，下次要赶紧抢哦！');
        }

        if (D('Shophongbaoreceive')->where(array('user_id'=>$this->uid, 'is_used'=>0, 'hongbao_id'=>$hongbao_id))->find()) {
            $this->error('您已经领取过该红包了，赶快使用吧！');
        }

        //计算出实际领取的红包金额
        $hongbao_price = D('Shophongbao')->Obtain_Hongbao_Price($detail);

        //成功领取红包，添加纪录
        $data = array(
            'hongbao_id' => $hongbao_id,
            'hongbao_price' => $hongbao_price,
            'shop_id' => $detail['shop_id'],
            'user_id' => $this->uid,
            'create_time' => NOW_TIME,
            'create_ip' => get_client_ip()
        );
        if (D('Shophongbaoreceive')->add($data)) {
            $Shophongbao->updateCount($hongbao_id, 'receive_num');  //更新红包领取数量记录
            $this->success('恭喜您成功领取' . $hongbao_price/100 . 'NZD红包！', U('mcenter/hongbao/index'));
        }
        $this->error('领取失败！');

    }

}
