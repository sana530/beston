<?php



class WeixintipsAction extends CommonAction {

    private $create_fields = array('keyword', 'type', 'title', 'contents', 'url', 'photo');
    private $edit_fields = array('keyword', 'type', 'title', 'contents', 'url', 'photo');

    public function index() {
        $Weixintips = D('Weixintips');
        import('ORG.Util.Page'); // 导入分页类
        $map = array();
        if ($keyword = $this->_param('keyword', 'htmlspecialchars')) {
            $map['keyword'] = array('LIKE', '%' . $keyword . '%');
        }
        $count = $Weixintips->where($map)->count(); // 查询满足要求的总记录数
        $Page = new Page($count, 15); // 实例化分页类 传入总记录数和每页显示的记录数
        $show = $Page->show(); // 分页显示输出
        $list = $Weixintips->where($map)->order(array('tips_id' => 'desc'))->limit($Page->firstRow . ',' . $Page->listRows)->select();
        $this->assign('list', $list); // 赋值数据集
        $this->assign('page', $show); // 赋值分页输出
        $this->display(); // 输出模板
    }

    public function create() {
        if ($this->isPost()) {
            $data = $this->createCheck();
            $obj = D('Weixintips');
            if ($obj->add($data)) {
                $obj->cleanCache();
                $this->baoSuccess('添加成功', U('Weixintips/index'));
            }
            $this->baoError('操作失败！');
        } else {
            $this->display();
        }
    }

    private function createCheck() {
        $data = $this->checkFields($this->_post('data', false), $this->create_fields);
        $data['keyword'] = htmlspecialchars($data['keyword']);
        if (empty($data['keyword'])) {
            $this->baoError('小提示关键字不能为空');
        }
        if (D('Weixintips')->checkKeyword($data['keyword'])) {
            $this->baoError('小提示关键字已经存在');
        }

        if (empty($data['type'])) {
            $this->baoError('类型不能为空');
        }
        $data['title'] = htmlspecialchars($data['title']);


        if (empty($data['contents'])) {
            $this->baoError('回复内容不能为空');
        }
        if ($words = D('Sensitive')->checkWords($data['contents'])) {
            $this->baoError('内容含有敏感词：' . $words);
        }
        $data['url'] = htmlspecialchars($data['url']);
        $data['photo'] = htmlspecialchars($data['photo']);
        if (!empty($data['photo']) && !isImage($data['photo'])) {
            $this->baoError('缩略图格式不正确');
        }
        return $data;
    }

    public function edit($tips_id = 0) {
        if ($tips_id = (int) $tips_id) {
            $obj = D('Weixintips');
            if (!$detail = $obj->find($tips_id)) {
                $this->baoError('请选择要编辑的微信小提示');
            }
            if ($this->isPost()) {
                $data = $this->editCheck();
                $data['tips_id'] = $tips_id;
                $local = $obj->checkKeyword($data['keyword']);
                if ($local && $local['tips_id'] != $tips_id) {
                    $this->baoError('关键字已经存在');
                }

                if (false !== $obj->save($data)) {
                    $obj->cleanCache();
                    $this->baoSuccess('操作成功', U('Weixintips/index'));
                }
                $this->baoError('操作失败');
            } else {
                $this->assign('detail', $detail);
                $this->display();
            }
        } else {
            $this->baoError('请选择要编辑的微信小提示');
        }
    }

    private function editCheck() {
        $data = $this->checkFields($this->_post('data', false), $this->edit_fields);
        $data['keyword'] = htmlspecialchars($data['keyword']);
        if (empty($data['keyword'])) {
            $this->baoError('关键字不能为空');
        }
        if (empty($data['type'])) {
            $this->baoError('类型不能为空');
        }
        $data['title'] = htmlspecialchars($data['title']);

        if (empty($data['contents'])) {
            $this->baoError('回复内容不能为空');
        }
        if ($words = D('Sensitive')->checkWords($data['contents'])) {
            $this->baoError('内容含有敏感词：' . $words);
        }
        $data['url'] = htmlspecialchars($data['url']);
        $data['photo'] = htmlspecialchars($data['photo']);
        if (!empty($data['photo']) && !isImage($data['photo'])) {
            $this->baoError('缩略图格式不正确');
        }
        return $data;
    }

    public function delete($tips_id = 0) {
        if (is_numeric($tips_id) && ($tips_id = (int) $tips_id)) {
            $obj = D('Weixintips');
            $obj->delete($tips_id);
            $obj->cleanCache();
            $this->baoSuccess('删除成功！', U('Weixintips/index'));
        } else {
            $tips_id = $this->_post('tips_id', false);
            if (is_array($tips_id)) {
                $obj = D('Weixintips');
                foreach ($tips_id as $id) {
                    $obj->delete($id);
                }
                $obj->cleanCache();
                $this->baoSuccess('删除成功！', U('Weixintips/index'));
            }
            $this->baoError('请选择要删除的微信小提示');
        }
    }

}
