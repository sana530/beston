<?php
class IndexAction extends CommonAction
{
    public function index()
    {
        $data = $this->weixin->request();
        if ($data['Latitude']) {
            S('location_'.$data['FromUserName'], $data['Latitude'].'_'.$data['Longitude']);
        }
        switch ($data['MsgType']) {
            //
            case 'event':
                if ($data['Event'] == 'subscribe') {
                    if (isset($data['EventKey']) && !empty($data['EventKey'])) {
                        $this->events();
                    } else {
                        $this->event();
                    }
                }
                if ($data['Event'] == 'SCAN') {
                    $this->scan();
                }
                if ($data['Event'] == 'LOCATION') {
                    if (!S('location_openOA_'.$data['FromUserName'])) {
                        $this->openOA($data);
                    }
                }
                break;
            case 'location':
                $this->location($data);
                break;
            default:
                //其余的类型都算关键词
                $this->keyword($data);
                break;
        }
    }
    private function location($data)
    {
        $lat = $data['Location_X'];
        $lng = $data['Location_Y'];
        $list = D('Shop')->where(array('audit' => 1, 'closed' => 0))->order(" (ABS(lng - '{$lng}') +  ABS(lat - '" . $lat . '\') )  asc ')->limit(0, 10)->select();
        if (!empty($list)) {
            $content = array();
            foreach ($list as $item) {
                $content[] = array($item['shop_name'], $item['addr'], $this->getImage($item['photo']), __HOST__ . '/mobile/shop/detail/shop_id/' . $item['shop_id'] . '.html');
            }
            $this->weixin->response($content, 'news');
        } else {
            $this->weixin->response('很抱歉没有合适的商家推荐给您', 'text');
        }
    }
    private function keyword($data){
        if (empty($data['Content'])) {
            return;
        }

        if ($this->shop_id == 0) {
            $key = explode(' ', $data['Content']);
            $keyword = D('Weixinkeyword')->checkKeyword($key[0]);
            if ($keyword) {
                switch ($keyword['type']) {
                    case 'text':
                        $this->weixin->response($keyword['contents'], 'text');
                        break;
                    case 'news':
                        $content = array();
                        $content[] = array(
                            $keyword['title'],
                            $keyword['contents'],
                            $this->getImage($keyword['photo']),
                            $keyword['url'],
                        );
                        $this->weixin->response($content, 'news');
                        break;
                }

            } else {
                // 没有特定关键词则查询POIS信息
                $openid = $data['FromUserName'];
                $con = D('Connect')->getConnectByOpenid('weixin', $openid);
                $usr = D('Users')->where(array('user_id' => $con['uid']))->find();
                $search_type = $this->_CONFIG['weixin']['search_type']; //search_type(1:商家; 2:黄页)
                if (S('location_'.$data['FromUserName'])) {
                    $lat_lng = explode('_', S('location_'.$data['FromUserName']));
                    $lat = $lat_lng[0];
                    $lng = $lat_lng[1];
                }
                if (empty($lat) || empty($lng)) {
                    $lat = $this->_CONFIG['site']['lat'];
                    $lng = $this->_CONFIG['site']['lng'];
                }
                $squares = returnSquarePoint($lng, $lat, 2);
                $map = array();
                $map['lat'] > $squares['right-bottom']['lat'];
                $map['lat'] < $squares['left-top']['lat'];
                $map['lng'] > $squares['left-top']['lng'];
                $map['lng'] > $squares['right-bottom']['lng'];
                if ($search_type == 3) {
                    //如果是全局搜索
                    $this->searchAll($key, $openid, $data, $usr);
                    die;
                }
                if ($search_type == 2) {
                    $map['name|tag'] = array('LIKE', array('%' . $key[0] . '%', '%' . $key[0], $key[0] . '%', 'OR'));
                } elseif ($search_type == 1) {
                    $map['shop_name'] = array('LIKE', array('%' . $key[0] . '%', '%' . $key[0], $key[0] . '%', 'OR'));
                    $map['audit'] = 1;
                    $map['closed'] = 0;
                }
                $orderby = 'orderby asc';
                //查询包年固顶
                $word = D('Nearword')->where(array('text' => $key[0]))->find();
                $word_pois = $word['pois_id'];
                if ($word_pois) {
                    $ding = D('Near')->find($word_pois);
                }
                if ($ding) {
                    if ($search_type == 2) {
                        $map['pois_id'] != $word_pois;
                    }
                    if ($ding['shop_id']) {
                        $url = $this->_CONFIG['site']['host'] . '/mobile/shop/detail/shop_id/' . $ding['shop_id'] . '.html';
                    } else {
                        $url = $this->_CONFIG['site']['host'] . '/mobile/biz/detail/pois_id/' . $ding['pois_id'] . '.html';
                    }
                    $text = '<a href="' . $url . '">' . $ding['name'] . '</a> ★★★★★ /:strong
' . $ding['address'] . '
' . $ding['telephone'] . '

';
                }
                if ($search_type == 1) {
                    $list = D('Shop')->where($map)->order($orderby)->limit(0, 9)->select();
                    //判断是否从POIS中获取到信息
                    if (count($list) > 0) {
                        foreach ($list as $val) {
                            $url = $this->_CONFIG['site']['host'] . '/mobile/shop/detail/shop_id/' . $val['shop_id'] . '.html';
                            $distance = getDistanceCN($val['lat'], $val['lng'], $lat, $lng);
                            if (!empty($val['tel'])) {
                                $text .= '<a href="' . $url . '">' . $val['shop_name'] . '</a>
' . $val['addr'] . ' (' . $distance . ')
' . $val['tel'] . '

';
                            } else {
                                $text .= '<a href="' . $url . '">' . $val['shop_name'] . '</a>
' . $val['addr'] . ' (' . $distance . ')

';
                            }
                        }
                    }
                } elseif ($search_type == 2) {
                    $list = D('Near')->where($map)->order($orderby)->limit(0, 9)->select();
                    //判断是否从POIS中获取到信息
                    if (count($list) > 0) {
                        foreach ($list as $val) {
                            if (intval($val['pois_id']) != intval($word_pois)) {
                                if (intval($val['shop_id']) > 0) {
                                    $url = $this->_CONFIG['site']['host'] . '/mobile/shop/detail/shop_id/' . $val['shop_id'] . '.html';
                                } else {
                                    $url = $this->_CONFIG['site']['host'] . '/mobile/biz/detail/pois_id/' . $val['pois_id'] . '.html';
                                }
                                $distance = getDistanceCN($val['lat'], $val['lng'], $lat, $lng);
                                if (!empty($val['telephone'])) {
                                    $text .= '<a href="' . $url . '">' . $val['name'] . '</a>
' . $val['address'] . ' (' . $distance . ')
' . $val['telephone'] . '

';
                                } else {
                                    $text .= '<a href="' . $url . '">' . $val['name'] . '</a>
' . $val['address'] . ' (' . $distance . ')

';
                                }
                            }
                        }
                    }
                }
                if (empty($ding) && count($list) == 0) {
                    $text = '回禀圣上，臣翻阅了整个新华字典也没找到你要的东东。依臣所见，还是点击下面菜单试试吧！';
                }
                //发送信息到客户
                $this->weixin->response($text, 'text');
            }
        } else {
            $keyword = D('Shopweixinkeyword')->checkKeyword($this->shop_id, $data['Content']);
            if ($keyword) {
                switch ($keyword['type']) {
                    case 'text':
                        $this->weixin->response($keyword['contents'], 'text');
                        break;
                    case 'news':
                        $content = array();
                        $content[] = array(
                            $keyword['title'],
                            $keyword['contents'],
                            $this->getImage($keyword['photo']),
                            $keyword['url'],
                        );
                        $this->weixin->response($content, 'news');
                        break;
                }
            } else {
                $this->event();
            }
        }
    }

    /* 当允许地址访问并一打开公众号时 */
    private function openOA ($data) {
        //记录位置信息到缓存，记录位置信息打开状态到缓存，并更新缓存
        if ($data['Latitude']) {
            S('location_openOA_'.$data['FromUserName'], NULL);
            S('location_openOA_'.$data['FromUserName'], $data['Latitude'].'_'.$data['Longitude'], 5);     //记录openOA使用时间
            S('location_open_'.$data['FromUserName'], NULL);
            S('location_open_'.$data['FromUserName'], $data['Latitude'].'_'.$data['Longitude'], 24*3600);   //记录客人打开了位置信息
        }

        //查询相应的用户信息
        $openid = $data['FromUserName'];
        $con = D('Connect')->getConnectByOpenid('weixin', $openid);
        $user = D('Users')->where(array('user_id' => $con['uid']))->find();

        //更新用户数据
        $save_user = array (
            'user_id' => $user['user_id'],
            'last_weixin_time' => NOW_TIME,
            'last_weixin_lat' => $data['Latitude'],
            'last_weixin_lng' => $data['Longitude']
        );
        D('Users')->save($save_user);

        //如果有时段广告，则推送时段广告
        $period_ad = D('Weixinperiodad')->fetchAll();
        $ad_ids = array();
        foreach ($period_ad as $k => $v) {
            if (NOW_TIME > $v['end_at'] || NOW_TIME < $v['start_from']) {
                unset($period_ad[$k]);
            } else {
                $ad_ids[$k] = $k;
            }
        }

        //如果固定广告被推送了两次以上，则不再推送
        $push_times_where = array(
            'content_id' => array('IN', array_keys($ad_ids)),
            'content_type' => 1,
            'receive_uid' => $user['user_id']
        );
        $weixinPushTimes = D('Weixinpushtimes');
        $push_times = $weixinPushTimes->where($push_times_where)->select();
        $push_times_ad_ids = array();
        foreach ($push_times as $k => $v) {
            if ($v['times'] >= 2) {
                unset($period_ad[$v['content_id']]);
            } else {
                $push_times_ad_ids[$v['content_id']] = $v['times_id'];
            }
        }

        //如果有时段广告则推送时段广告，如果没有则推送别的内容
        if ($period_ad) {
            $content = array();
            foreach ($period_ad as $k => $v) {
                $content[] = array($v['title'], $v['contents'], $this->getImage($v['photo']), $v['url']);

                //更新推送次数表
                if (in_array($v['ad_id'], array_keys($push_times_ad_ids))) {
                    $weixinPushTimes->where(array('times_id'=>$push_times_ad_ids[$v['ad_id']]))->setInc('times');
                } else {
                    $weixin_push_times_add = array(
                        'content_type' => 1,
                        'content_id' => $v['ad_id'],
                        'receive_uid' => $user['user_id'],
                        'times' => 1
                    );
                    $weixinPushTimes->add($weixin_push_times_add);
                }

                //更新推送记录表
                D('Weixinpushrecord')->record($v['shop_id'], 1, 1, $v['ad_id'], $user['user_id']);
            }

            //得出要组合的广告数组
            $period_num = count($period_ad);
            $ad = $this->getAd($data['Longitude'], $data['Latitude'], $user, 8-$period_num);
            $content = array_merge($content, $ad);
            $this->weixin->response($content, 'news');
        } else {
            $random = rand(1,2);
            if ($random == 1) { //发送Tips
                if (!D('Weixintips')->responseTips($user)) {    //如果没有要发送的Tips则发送广告
                    //得出要组合的广告数组
                    $ad = $this->getAd($data['Longitude'], $data['Latitude'], $user);
                    $this->weixin->response($ad, 'news');
                }
            } elseif ($random == 2) {
                //得出要组合的广告数组
                $ad = $this->getAd($data['Longitude'], $data['Latitude'], $user);
                $this->weixin->response($ad, 'news');
            }
        }

    }


    //响应用户的事件
    private function event(){
        if ($this->shop_id == 0) {
            if ($this->_CONFIG['weixin']['type'] == 1) {
                $this->weixin->response($this->_CONFIG['weixin']['description'], 'text');
            } else {
                $content[] = array($this->_CONFIG['weixin']['title'], $this->_CONFIG['weixin']['description'], $this->getImage($this->_CONFIG['weixin']['photo']), $this->_CONFIG['weixin']['linkurl']);
                $this->weixin->response($content, 'news');
            }
        } else {
            //
            $data['get'] = $_GET;
            $data['post'] = $_POST;
            $data['data'] = $this->weixin->request();
            file_put_contents('/www/web/bao_baocms_cn/public_html/Baocms/Lib/Action/Weixin/aaa.txt', var_export($data, true));
            $weixin_msg = unserialize($this->shopdetails['weixin_msg']);
            if ($weixin_msg['type'] == 1) {
                $this->weixin->response($weixin_msg['description'], 'text');
            } else {
                $content[] = array($weixin_msg['title'], $weixin_msg['description'], $this->getImage($weixin_msg['photo']), $this->_CONFIG['weixin']['linkurl']);
                $this->weixin->response($content, 'news');
            }
        }
    }
    private function events()
    {
        $data['get'] = $_GET;
        $data['post'] = $_POST;
        $data['data'] = $this->weixin->request();
        //file_put_contents('/www/web/bao_baocms_cn/public_html/Baocms/Lib/Action/Weixin/aaa.txt', var_export($data['data'], true));
        if (!empty($data['data'])) {
            $datas = explode('_', $data['data']['EventKey']);
            $id = $datas[1];
            if (!($detail = D('Weixinqrcode')->find($id))) {
                die;
            }
            $type = $detail['type'];
            //file_put_contents('/www/web/bao_baocms_cn/public_html/Baocms/Lib/Action/Weixin/bbb.txt', var_export($content, true));
            $result = D('Connect')->getConnectByOpenid('weixin', $data['data']['FromUserName']);    //查看此微信号是否已从平台注册过
            if (empty($result)) {
                //如果此微信号未从平台注册, 则利用其open_id先为其生成一个会员，后期登陆的时候再调用其信息补充头像昵称等信息
                $register = array('type' => 'weixin', 'open_id' => $data['data']['FromUserName'], 'token' => '', 'nickname' => '', 'headimgurl' => '');
                D('Weixin')->oa_register($register);
                $result = D('Connect')->getConnectByOpenid('weixin', $data['data']['FromUserName']);
            }
            if ($type == 1) {
                $shop_id = $detail['soure_id'];
                $shop = D('Shop')->find($shop_id);
                $content[] = array($shop['shop_name'], $shop['addr'], $this->getImage($shop['photo']), __HOST__ . '/mobile/shop/detail/shop_id/' . $shop_id . '.html');
                if (!empty($result)) {
                    $user_id = $result['uid'];
                    $ymd = date('Y-m-d', NOW_TIME);
                    $ymdarr = explode('-', $ymd);
                    if (!($de = D('Census')->where(array('user_id' => $user_id))->find())) {
                        $datac = array('user_id' => $user_id, 'year' => $ymdarr[0], 'month' => $ymdarr[1], 'day' => $ymdarr[2]);
                        D('Census')->add($datac);
                    }
                    if (!($fans = D('Shopfavorites')->where(array('user_id' => $user_id, 'shop_id' => $shop_id))->find())) {
                        $dataf = array('user_id' => $user_id, 'shop_id' => $shop_id, 'create_time' => NOW_TIME, 'create_ip' => get_client_ip());
                        D('Shopfavorites')->add($dataf);
                        D('Shop')->updateCount($shop_id, 'fans_num');
                    } else {
                        if ($fans['closed'] == 1) {
                            D('Shopfavorites')->save(array('favorites_id' => $fans['favorites_id'], 'closed' => 0));
                        }
                    }
                }
            } elseif ($type == 2) {
                //抢购
                $tuan_id = $detail['soure_id'];
                $tuan = D('Tuan')->find($tuan_id);
                $content[] = array($tuan['title'], $tuan['intro'], $this->getImage($tuan['photo']), __HOST__ . '/mobile/tuan/detail/tuan_id/' . $tuan_id . '.html');
                if (!empty($result)) {
                    $user_id = $result['uid'];
                    $ymd = date('Y-m-d', NOW_TIME);
                    $ymdarr = explode('-', $ymd);
                    if (!($de = D('Census')->where(array('user_id' => $user_id))->find())) {
                        $datac = array('user_id' => $user_id, 'year' => $ymdarr[0], 'month' => $ymdarr[1], 'day' => $ymdarr[2]);
                        D('Census')->add($datac);
                    }
                    if (!($fans = D('Shopfavorites')->where(array('user_id' => $user_id, 'shop_id' => $tuan['shop_id']))->find())) {
                        $dataf = array('user_id' => $user_id, 'shop_id' => $tuan['shop_id'], 'create_time' => NOW_TIME, 'create_ip' => get_client_ip());
                        D('Shopfavorites')->add($dataf);
                        D('Shop')->updateCount($tuan['shop_id'], 'fans_num');
                    } else {
                        if ($fans['closed'] == 1) {
                            D('Shopfavorites')->save(array('favorites_id' => $fans['favorites_id'], 'closed' => 0));
                        }
                    }
                }
            } elseif ($type == 3) {
                //购物
                $goods_id = $detail['soure_id'];
                $goods = D('Goods')->find($goods_id);
                $shops = D('Shop')->find($goods['shop_id']);
                $content[] = array($goods['title'], $shops['shop_name'], $this->getImage($goods['photo']), __HOST__ . '/mobile/mall/detail/goods_id/' . $goods_id . '.html');
                if (!empty($result)) {
                    $user_id = $result['uid'];
                    $ymd = date('Y-m-d', NOW_TIME);
                    $ymdarr = explode('-', $ymd);
                    if (!($de = D('Census')->where(array('user_id' => $user_id))->find())) {
                        $datac = array('user_id' => $user_id, 'year' => $ymdarr[0], 'month' => $ymdarr[1], 'day' => $ymdarr[2]);
                        D('Census')->add($datac);
                    }
                    if (!($fans = D('Shopfavorites')->where(array('user_id' => $user_id, 'shop_id' => $goods['shop_id']))->find())) {
                        $dataf = array('user_id' => $user_id, 'shop_id' => $goods['shop_id'], 'create_time' => NOW_TIME, 'create_ip' => get_client_ip());
                        D('Shopfavorites')->add($dataf);
                        D('Shop')->updateCount($goods['shop_id'], 'fans_num');
                    } else {
                        if ($fans['closed'] == 1) {
                            D('Shopfavorites')->save(array('favorites_id' => $fans['favorites_id'], 'closed' => 0));
                        }
                    }
                }
            } elseif ($type == 4) {
                //会员推荐
                $content = false;
                if (!empty($result)) {
                    $user_id = $result['uid'];
                    $ymd = date('Y-m-d', NOW_TIME);
                    $ymdarr = explode('-', $ymd);
                    if (!($de = D('Census')->where(array('user_id' => $user_id))->find())) {
                        $datac = array('user_id' => $user_id, 'year' => $ymdarr[0], 'month' => $ymdarr[1], 'day' => $ymdarr[2]);
                        D('Census')->add($datac);
                    }
                }
            }
            if (!$result['follow_time']) {
                //如果是首次关注公众号，则发送关注送红包提示消息
                D('Weixin')->custom_send($this->_CONFIG['weixin']['hongbao_subscribe_description'], $data['data']['FromUserName']);
            }
            D('Connect')->save(array('connect_id' => $result['connect_id'],
                'follow_type' => $type,
                'follow_id' => $detail['soure_id'],
                'is_weixin_followed' => 1,
                'follow_time'=>NOW_TIME));  //将关注方式，关注时间等信息更新
            if ($content) {
                $this->weixin->response($content, 'news');
            }
        }
    }
    public function scan()
    {
        $data['data'] = $this->weixin->request();
        //file_put_contents('/www/web/bao_baocms_cn/public_html/Baocms/Lib/Action/Weixin/ccc.txt', var_export($data['data'], true));
        if (!empty($data['data'])) {
            $id = $data['data']['EventKey'];
            if (!($detail = D('Weixinqrcode')->find($id))) {
                die;
            }
            $type = $detail['type'];
            if ($type == 1) {
                $shop_id = $detail['soure_id'];
                $shop = D('Shop')->find($shop_id);
                $content[] = array($shop['shop_name'], $shop['addr'], $this->getImage($shop['photo']), __HOST__ . '/mobile/shop/detail/shop_id/' . $shop_id . '.html');
                //file_put_contents('/www/web/bao_baocms_cn/public_html/Baocms/Lib/Action/Weixin/bbb.txt', var_export($content, true));
                $result = D('Connect')->getConnectByOpenid('weixin', $data['data']['FromUserName']);
                if (!empty($result)) {
                    $user_id = $result['uid'];
                    $ymd = date('Y-m-d', NOW_TIME);
                    $ymdarr = explode('-', $ymd);
                    if (!($fans = D('Shopfavorites')->where(array('user_id' => $user_id, 'shop_id' => $shop_id))->find())) {
                        $dataf = array('user_id' => $user_id, 'shop_id' => $shop_id, 'create_time' => NOW_TIME, 'create_ip' => get_client_ip());
                        D('Shopfavorites')->add($dataf);
                        D('Shop')->updateCount($shop_id, 'fans_num');
                    } else {
                        if ($fans['closed'] == 1) {
                            D('Shopfavorites')->save(array('favorites_id' => $fans['favorites_id'], 'closed' => 0));
                        }
                    }
                }
                $this->weixin->response($content, 'news');
            } elseif ($type == 2) {
                //抢购
                $tuan_id = $detail['soure_id'];
                $tuan = D('Tuan')->find($tuan_id);
                $content[] = array($tuan['title'], $tuan['intro'], $this->getImage($tuan['photo']), __HOST__ . '/mobile/tuan/detail/tuan_id/' . $tuan_id . '.html');
                //file_put_contents('/www/web/bao_baocms_cn/public_html/Baocms/Lib/Action/Weixin/aaa.txt', var_export($content, true));
                $result = D('Connect')->getConnectByOpenid('weixin', $data['data']['FromUserName']);
                if (!empty($result)) {
                    $user_id = $result['uid'];
                    if (!($fans = D('Shopfavorites')->where(array('user_id' => $user_id, 'shop_id' => $tuan['shop_id']))->find())) {
                        $dataf = array('user_id' => $user_id, 'shop_id' => $tuan['shop_id'], 'create_time' => NOW_TIME, 'create_ip' => get_client_ip());
                        D('Shopfavorites')->add($dataf);
                        D('Shop')->updateCount($tuan['shop_id'], 'fans_num');
                    } else {
                        if ($fans['closed'] == 1) {
                            D('Shopfavorites')->save(array('favorites_id' => $fans['favorites_id'], 'closed' => 0));
                        }
                    }
                }
                $this->weixin->response($content, 'news');
            } elseif ($type == 3) {
                //购物
                $goods_id = $detail['soure_id'];
                $goods = D('Goods')->find($goods_id);
                $shops = D('Shop')->find($goods['shop_id']);
                $content[] = array($goods['title'], $shops['shop_name'], $this->getImage($goods['photo']), __HOST__ . '/mobile/mall/detail/goods_id/' . $goods_id . '.html');
                //file_put_contents('/www/web/bao_baocms_cn/public_html/Baocms/Lib/Action/Weixin/aaa.txt', var_export($content, true));
                $result = D('Connect')->getConnectByOpenid('weixin', $data['data']['FromUserName']);
                if (!empty($result)) {
                    $user_id = $result['uid'];
                    if (!($fans = D('Shopfavorites')->where(array('user_id' => $user_id, 'shop_id' => $goods['shop_id']))->find())) {
                        $dataf = array('user_id' => $user_id, 'shop_id' => $goods['shop_id'], 'create_time' => NOW_TIME, 'create_ip' => get_client_ip());
                        D('Shopfavorites')->add($dataf);
                        D('Shop')->updateCount($goods['shop_id'], 'fans_num');
                    } else {
                        if ($fans['closed'] == 1) {
                            D('Shopfavorites')->save(array('favorites_id' => $fans['favorites_id'], 'closed' => 0));
                        }
                    }
                }
                $this->weixin->response($content, 'news');
            }
        }
    }

    private function searchAll($key, $openid, $data, $user) {
        if (S('location_'.$data['FromUserName'])) {
            $lat_lng = explode('_', S('location_'.$data['FromUserName']));
            $lat = $lat_lng[0];
            $lng = $lat_lng[1];
        }
        if (empty($lat) || empty($lng)) {
            $lat = $this->_CONFIG['site']['lat'];
            $lng = $this->_CONFIG['site']['lng'];
        }

        $this->city_id = 1;
        $where_article = array('audit'=>1,'closed' => 0,'city_id'=>$this->city_id);
        $where_coupon = array('audit' => 1,'closed' => 0,'city_id'=>$this->city_id,'is_online_show'=>1, 'expire_date' => array('EGT', TODAY));
        $where_farm = array('audit'=>1,'closed' => 0,'city_id'=>$this->city_id);
        $where_life = array('audit' => 1,'city_id'=>$this->city_id);
        $where_shop = array('closed'=>0,'audit' =>1,'city_id'=>$this->city_id);
        $where_post = array('audit'=>1,'closed' => 0,'city_id'=>$this->city_id);
        $where_tuan = array('closed'=>0,'audit' =>1,'city_id'=>$this->city_id,'end_date'=>array('EGT',TODAY));

        $where_article_map = array();
        $where_coupon_map = array();
        $where_farm_map = array();
        $where_life_map = array();
        $where_shop_map = array();
        $where_post_map = array();
        $where_tuan_map = array();
        for($i=0; $i<sizeof($key); $i++) {
            if ($i > 0 && sizeof($key) > 1) {
                $where_article_map[$i]['_complex'] = $where_article_map[$i-1];
            }
            $where_article_map[$i]['title|keywords'] = array('LIKE', '%' . $key[$i] . '%');
            $where_coupon_map[$i]['title'] = array('LIKE', '%' . $key[$i] . '%');
            $where_farm_map[$i]['farm_name|intro|tel|addr'] = array('LIKE', '%' . $key[$i] . '%');
            $where_life_map[$i]['qq|mobile|contact|title|num1|num2'] = array('LIKE', '%' . $key[$i] . '%');
            $where_shop_map[$i]['shop_name|tags|addr'] = array('LIKE','%'.$key[$i].'%');
            $where_post_map[$i]['title'] = array('LIKE', '%' . $key[$i] . '%');
            $where_tuan_map[$i]['title'] = array('LIKE', '%' . $key[$i] . '%');
        }
        $where_article['_complex'] = $where_article_map;
        $where_coupon['_complex'] = $where_coupon_map;
        $where_farm['_complex'] = $where_farm_map;
        $where_life['_complex'] = $where_life_map;
        $where_shop['_complex'] = $where_shop_map;
        $where_post['_complex'] = $where_post_map;
        $where_tuan['_complex'] = $where_tuan_map;


        $Article = D('Article');
        $Coupon = D('Coupon');
        $Farm = D('Farm');
        $Life = D('Life');
        $Shop=D('Shop');
        $Post = D('Post');
        $Tuan=D('Tuan');

        $list_article=$Article->Field('"新闻" as t_name,"news/detail" as t_url,"article_id" as t_param,article_id as t_id,title as t_title,photo as t_photo,FROM_UNIXTIME(create_time,"%Y-%m-%d") as t_note')->order($orderby)->where($where_article)->select();
        $list_coupon=$Coupon->Field('"优惠券" as t_name,"coupon/detail" as t_url,"coupon_id" as t_param,coupon_id as t_id,title as t_title,photo as t_photo,FROM_UNIXTIME(create_time,"%Y-%m-%d") as t_note')->order($orderby)->where($where_coupon)->select();
        $list_farm=$Farm->Field('"景点" as t_name,"farm/detail" as t_url,"farm_id" as t_param,farm_id as t_id,farm_name as t_title,photo as t_photo,FROM_UNIXTIME(create_time,"%Y-%m-%d") as t_note')->order($orderby)->where($where_farm)->select();
        $list_life=$Life->Field('"分类" as t_name,"life/detail" as t_url,"life_id" as t_param,life_id as t_id,title as t_title,photo as t_photo,FROM_UNIXTIME(create_time,"%Y-%m-%d") as t_note')->order($orderby)->where($where_life)->select();
        $list_shop =$Shop->Field('"商家" as t_name,"shop/detail" as t_url,"shop_id" as t_param,shop_id as t_id,shop_name as t_title,logo as t_photo,tel as t_note, addr, lat, lng')->order($orderby)->where($where_shop)->select();
        $list_post=$Post->Field('"帖子" as t_name,"tieba/detail" as t_url,"post_id" as t_param,post_id as t_id,title as t_title,photo as t_photo,FROM_UNIXTIME(create_time,"%Y-%m-%d") as t_note')->order($orderby)->where($where_post)->select();
        $list_tuan=$Tuan->Field('"抢购" as t_name,"tuan/detail" as t_url,"tuan_id" as t_param,tuan_id as t_id,title as t_title,photo as t_photo,concat("$",round(tuan_price/100,2)) as t_note')->order($orderby)->where($where_tuan)->select();

        $list = array();
        if(!empty($list_shop)){
            foreach ($list_shop as $k => $v) {
                $v['distance'] = getDistance($v['lat'], $v['lng'], $lat, $lng);
                $addr = explode(',', $v['addr']);
                $list_shop[$k]['t_title'] = $v['t_title'] . '
'.$addr[0].' ('.$v['distance'].')';
            }
            $list=array_merge($list,$list_shop);
        }
        if (!empty($this->_CONFIG['operation']['news'])){
            if(!empty($list_article)){
                $list=array_merge($list,$list_article);
            }
        }
        if(!empty($list_farm)){
            $list=array_merge($list,$list_farm);
        }
        if (!empty($this->_CONFIG['operation']['life'])){
            if(!empty($list_life)){
                $list=array_merge($list,$list_life);
            }
        }
        if(!empty($list_post)){
            $list=array_merge($list,$list_post);
        }
        if(!empty($list_coupon)){
            $list=array_merge($list,$list_coupon);
        }
        if(!empty($list_tuan)){
            $list=array_merge($list,$list_tuan);
        }

        if ($list) {
            if (S('location_open_'.$openid)) {
                foreach ($list as $k => $v) {
                    if ($k < 5) {
                        $content[] = array($v['t_name'] . ': ' . $v['t_title'], $v['t_note'], $this->getImage($v['t_photo']), __HOST__ . 'mobile/' . $v['t_url'] . '/' . $v['t_param'] . $v['t_id']);
                    } else {
                        unset($list[$k]);
                    }
                }
                $ad = $this->getAd($lng, $lat, $user, 3);
                $content = array_merge($content, $ad);
                $this->weixin->response($content, 'news');
            } else {
                foreach ($list as $k => $v) {
                    if ($k < 8) {
                        $list[$k]['title'] = $v['t_name'] . ': ' . $v['t_title'];
                        $list[$k]['contents'] = $v['t_note'];
                        $list[$k]['url'] = __HOST__ . 'mobile/' . $v['t_url'] . '/' . $v['t_param'] . $v['t_id'];
                        $list[$k]['photo'] = $this->getImage($v['t_photo']);
                    } else {
                        unset($list[$k]);
                    }
                }
                $this->weixin->custom_send($list, $openid ,'news');
                D('Weixintips')->responseTips($user, false);
            }
        } else {
            $this->weixin->response('没有找到相关信息', 'text');
        }

    }

    private function getAd($lng, $lat, $user, $num=8) {
        $content = array();
        if ($num >= 1) {
            //Ad1内容
            $ad1 = D('Shop')->where(array('audit' => 1, 'closed' => 0))->order(" (ABS(lng - '{$lng}') +  ABS(lat - '" . $lat . '\') )  asc ')->find();
            array_push($content, array('离您最近的店: '.$ad1['shop_name'], $ad1['addr'], $this->getImage($ad1['logo']),  __HOST__ . '/mobile/shop/detail/shop_id/' . $ad1['shop_id'] . '.html'));
            $this->adPush($ad1['shop_id'], $user['user_id'], $ad1['shop_id'], 2, 2);
        }

        if ($num >= 2) {
            //Ad2内容
            $ad2 = D('Shop')->where(array('audit' => 1, 'closed' => 0))->order(" (ABS(lng - '{$lng}') +  ABS(lat - '" . $lat . '\') )  asc ')->limit(0,10)->select();
            $ad2_weight = 0;
            foreach ($ad2 as $k => $v) {
                $ad2[$k]['weight_from'] = $ad2_weight;
                $ad2[$k]['weight_to'] = $ad2_weight + $v['weight'];
                $ad2_weight += $v['weight'];
            }
            $ad2_random = rand(0, $ad2_weight);
            foreach ($ad2 as $k => $v) {
                if (($ad2_random >= $v['weight_from']) && ($ad2_random < $v['weight_to'])) {
                    array_push($content, array('附近推荐的店: '.$v['shop_name'], $v['addr'], $this->getImage($v['logo']),  __HOST__ . '/mobile/shop/detail/shop_id/' . $v['shop_id'] . '.html'));
                    $this->adPush($v['shop_id'], $user['user_id'], $v['shop_id'], 2, 3);
                }
            }
        }

        if ($num >= 3) {
            //Ad3内容
            $ad3 = D('Article')->where(array('audit' => 1, 'closed' => 0))->order('article_id asc ')->find();
            array_push($content, array('推荐文章: '.$ad3['title'], $ad3['details'], $this->getImage($ad3['photo']),  __HOST__ . '/mobile/news/detail/article_id/' . $ad3['article_id'] . '.html'));
            $this->adPush($ad3['shop_id'], $user['user_id'], $ad3['article_id'], 4, 2);
        }

        return $content;
    }

    private function adPush ($shop_id, $user_id, $content_id, $content_type, $push_type) {
        $weixinPushTimes = D('Weixinpushtimes');
        $weixinPushRecord = D('Weixinpushrecord');
        //更新推送次数表
        $ad1_push_times_where = array(
            'content_id' => $content_id,
            'content_type' => $content_type,
            'receive_uid' => $user_id
        );
        $ad1_push_times = $weixinPushTimes->where($ad1_push_times_where)->find();
        if ($ad1_push_times) {
            $weixinPushTimes->where(array('times_id'=>$ad1_push_times['times_id']))->setInc('times');
        } else {
            $ad1_weixin_push_times_add = array(
                'content_type' => $content_type,
                'content_id' => $content_id,
                'receive_uid' => $user_id,
                'times' => 1
            );
            $weixinPushTimes->add($ad1_weixin_push_times_add);
        }

        //更新推送记录表
        $weixinPushRecord->record($shop_id, $push_type, $content_type, $content_id, $user_id);
    }

    private function getImage($img)
    {
        if(strstr($img,"http")){
            $img = $img;
        }elseif(empty($img)){
            $img = 'http://'.$_SERVER['HTTP_HOST'].'/attachs/default.jpg';
        }else{
            if(strstr($img,"attachs")){
                $img = 'http://'.$_SERVER['HTTP_HOST'].'/'.$img;
            }else{
                $img = 'http://'.$_SERVER['HTTP_HOST'].'/attachs/'.$img;
            }
        }
        return  $img;
    }
}