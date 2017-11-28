<?php
class GuideAction extends CommonAction
{
    public function _initialize(){
        parent::_initialize();

        $cache = cache(array('type' => 'File', 'expire' => 600));
        if (!($counts = $cache->get('index_count'))) {
            $counts['shop'] = D('Shop')->count();
            $counts['coupon'] = D('Coupon')->count();
            $counts['users'] = D('Users')->count();
            $counts['life'] = D('Life')->count();
            $counts['post'] = D('Post')->count();
            $counts['community'] = D('Community')->count();
            $cache->set('index_count', $counts);
        }
        $this->assign('counts', $counts);
    }
    public function index()
    {
        $this->loaddata(1);
    }

    public function shangjia ()
    {
        $this->loaddata(2);
    }

    private function loaddata ($cate_type) {

        $Article = D('Article');
        if (!$Article->checkPriv(strtolower(ACTION_NAME))) {
            $this->error('抱歉,您没有权限进入查看');
        }
        import('ORG.Util.Page');// 导入分页类
        $map = array('closed' => 0, 'audit' => 1);//搜索开始
        if ($kword = $this->_param('kword', 'htmlspecialchars')) {
            $map['title|details'] = array('LIKE', '%' . $kword . '%');
            $this->assign('kword', $kword);
        }
        $cat = (int) $this->_param('cat');
        $catesAll = D('Articlecate')->fetchAll();
        $cates = array();
        foreach ($catesAll as $k => $val) {//限制显示分类
            if ($val['cate_type'] == $cate_type) {
                $cates[$k] = $val;
            }
        }
        $catesId = implode(',', array_keys($cates));//获取所有相应手册分类的id
        $map['cate_id'] = array('IN', $catesId);
        if ($cates[$cat]) {
            $catids = D('Articlecate')->getChildren($cat);
            if (!empty($catids)) {
                $map['cate_id'] = array('IN', $catids);
            } else {
                $map['cate_id'] = $cat;
            }
            $this->assign('parent_id', $cates[$cat]['parent_id'] == 0 ? $cates[$cat]['cate_id'] : $cates[$cat]['parent_id']);
            $this->seodatas['cate_name'] = $cates[$cat]['cate_name'];
        }

        $order = (int) $this->_param('order');
        switch ($order) {
            case 2:
                $orderby = array('views' => 'desc');
                break;
            default:
                $orderby = array('article_id' => 'desc');
                break;
        }
        $count = $Article->where($map)->count();// 查询满足要求的总记录数
        $Page = new Page($count, 15);// 实例化分页类 传入总记录数和每页显示的记录数
        $show = $Page->show(); // 分页显示输出
        $list = $Article->where($map)->order($orderby)->limit($Page->firstRow . ',' . $Page->listRows)->select();
        $shop_ids = array();
        foreach ($list as $k => $val) {
            if (!empty($val['shop_id'])) {
                $shop_ids[$val['shop_id']] = $val['shop_id'];
            }
        }

        //目前只有两级分类，所以如果$cat不是顶级分类，则其parent_id对应的分类为顶级分类
        if ($cat) {
            if ($cates[$cat]['parent_id'] == 0) {
                $topcat = $cat;
            } else {
                $topcat = $cates[$cat]['parent_id'];
            }
        } else {
            $topcat = 0;
        }

        $this->assign('cat',$cat);
        $this->assign('topcat', $topcat);
        $this->assign('shops', D('Shop')->itemsByIds($shop_ids));
        $this->assign('list', $list);// 赋值数据集
        $this->assign('count', $count);
        $this->assign('page', $show);// 赋值分页输出
        $this->assign('cates', $cates);
        $this->display('common'); // 输出模板

    }

    public function detail($article_id = 0)
    {
        if ($article_id = (int) $article_id) {
            $obj = D('Article');
            if (!($detail = $obj->find($article_id))) {
                $this->error('没有该文章');
            }
            if ($detail['closed'] != 0) {
                $this->error('该文章已删除');
            }

            if (!$obj->checkType($article_id)) {
                $this->error('抱歉,您没有权限阅读该文章');
            }
			
            $Article_cate = D('Articlecate')->fetchAll();//p($_SERVER["REQUEST_URI"]);die;
			if ($Article_cate[$detail['cate_id']]['parent_id'] == 0) {
				$this->assign('catstr', $Article_cate[$detail['cate_id']]['cate_name']);
			} else {
				$this->assign('catstr', $Article_cate[$Article_cate[$detail['cate_id']]['parent_id']]['cate_name']);
				$this->assign('cat', $Article_cate[$detail['cate_id']]['parent_id']);
				$this->assign('catestr', $Article_cate[$detail['cate_id']]['cate_name']);
			}
		
		
            $obj->updateCount($article_id, 'views');
            $shop_id = $detail['shop_id'];
            $shop = D('Shop')->find($shop_id);
            $this->assign('shops', $shop);
			
			
			//取得文章位置
            $cate_type = $Article_cate[$detail['cate_id']]['cate_type'];
            if ($cate_type == 1 ) {
                $from = 'index';
            } elseif ($cate_type == 2 ) {
                $from = 'shangjia';
            } elseif ($cate_type == 3 ) {
                $from = 'admin';
            }
            $back = '/guide/'.$from;

			//回复列表
            $Articlecomment = D('Articlecomment');
            import('ORG.Util.Page');// 导入分页类
            $map = array('city_id' => $this->city_id, 'post_id' => $article_id);
            $count_comment = $Articlecomment->where($map)->count();// 查询满足要求的总记录数
            $Page_comment = new Page($count_comment, 15); // 实例化分页类 传入总记录数和每页显示的记录数
            $show_comment = $Page_comment->show();// 分页显示输出
            $this->assign('count', $count);
            $list = $Articlecomment->where($map)->order()->limit($Page_comment->firstRow . ',' . $Page_comment->listRows)->select();
            $user_ids = array();
            foreach ($list as $k => $val) {
                if (!empty($val['user_id'])) {
                    $user_ids[$val['user_id']] = $val['user_id'];
                }
            }
            $this->assign('users', D('Users')->itemsByIds($user_ids));
            $this->assign("list", $list);
            $this->assign('page_comment', $show_comment);
			
			//打赏列表
            $Articledonate = D('Articledonate');
            import('ORG.Util.Page');// 导入分页类
            $map_donate = array('city_id' => $this->city_id, 'article_id' => $detail['article_id']);
            $count_donate = $Articledonate->where($map_donate)->count();// 查询满足要求的总记录数
            $Page = new Page($count_donate, 15); // 实例化分页类 传入总记录数和每页显示的记录数
            $show = $Page->show();// 分页显示输出
            $donate = $Articledonate->where($map_donate )->order()->limit($Page->firstRow . ',' . $Page->listRows)->select();

            $donate_user_ids = array();
            foreach ($donate as $k => $val) {
                if (!empty($val['user_id'])) {
                    $donate_user_ids[$val['user_id']] = $val['user_id'];
                }
            }

            $this->assign('dusers', D('Users')->itemsByIds($donate_user_ids));
			$this->assign('donate', $donate);
            $this->assign('page', $show);
            // 赋值分页输出
            $this->assign('cate_type',$cate_type);
            $this->assign('detail', $detail);
            $this->assign('back', $back);//赋值返回地址
            $this->assign('parent_id', D('Articlecate')->getParentsId($detail['cate_id']));
            $this->assign('cates', $cates);
            $this->assign('cate', $cates[$detail['cate_id']]);
            $this->seodatas['title'] = $detail['title'];
            $this->seodatas['cate_name'] = $cates[$detail['cate_id']];
            $this->seodatas['keywords'] = $detail['keywords'];
            if (!empty($detail['desc'])) {
                $this->seodatas['desc'] = $detail['desc'];
            } else {
                $this->seodatas['desc'] = bao_msubstr($detail['details'], 0, 200, false);
            }

            //$this->assign('articlecomments', D('Articlecomment')->where(array('post_id' => $article_id))->count());
			//$this->assign('pics', D('Articlephoto')->getPics($detail['life_id']));//调用图片
            $this->display();
        } else {
            $this->error('没有该文章');
        }
    }
    public function system(){
        $content_id = (int) $this->_get('content_id');
        if (empty($content_id)) {
            $this->error('该内容不存在');
            die;
        }
        $contents = D('Systemcontent')->fetchAll();
        if (!$contents[$content_id]) {
            $this->error('该内容不存在');
            die;
        }
        $this->assign('detail', $contents[$content_id]);
        $this->assign('contents', $contents);
        $this->assign('content_id', $content_id);
        $this->seodatas['title'] = $contents[$content_id]['title'];
        $this->display();
    }
    public function reply(){
        if (empty($this->uid)) {
            $this->ajaxReturn(array('status'=>'login'));
        }
        $data = $this->checkFields($this->_post('data', false), array('article_id', 'parent_id', 'content'));
        if (empty($data['content'])) {
			$this->ajaxReturn(array('status'=>'error','msg'=>'评论内容不能为空'));
        }
        if ($words = D('Sensitive')->checkWords($content)) {
			$this->ajaxReturn(array('status'=>'error','msg'=>'商家介绍含有敏感词：' . $words));
        }

        if (empty($data['article_id'])) {
			$this->ajaxReturn(array('status'=>'error','msg'=>'文章编号不正确'));
        }
        if (!($detail = D('Article')->find($data['article_id']))) {
			$this->ajaxReturn(array('status'=>'error','msg'=>'没有该文章'));
        }
		$data['post_id'] = $data['article_id'];		
        $data['nickname'] = $this->member['nickname'];
        $data['user_id'] = $this->uid;
        $data['zan'] = 0;
        $data['audit'] = $this->_CONFIG['site']['article_reply_audit'];
        //评论是免审核
        $data['create_time'] = NOW_TIME;
        $data['create_ip'] = get_client_ip();
		
 		if ($comment_id = D('Articlecomment')->add($data)) {
                $photos = $this->_post('photos', false);
                if (!empty($photos)) {
                    D('Articlephoto')->upload_comment($comment_id, $photos);//更新回复，如果是新闻用其他的	
                }
                $this->ajaxReturn(array('status'=>'success','msg'=>'回复成功！', U('news/detail', array('article_id' => $detail['post_id']))));
        }else{
			$this->ajaxReturn(array('status'=>'error','msg'=>'操作失败'));	
		}	
	
    }
    public function zan(){
		if (empty($this->uid)) {
            $this->ajaxReturn(array('status'=>'login'));
        }
		$article_id = I('article_id', 0, 'trim,intval');
        $detail = D('Article')->find($article_id);
        if (empty($detail)) {
			$this->ajaxReturn(array('status'=>'error','msg'=>'您点赞的内容不存在'));
        }
        D('Article')->updateCount($article_id, 'zan');
		$this->ajaxReturn(array('status'=>'success','msg'=>'恭喜您，点赞成功！', U('news/detail', array('article_id' => $detail['post_id']))));
    }
	


}