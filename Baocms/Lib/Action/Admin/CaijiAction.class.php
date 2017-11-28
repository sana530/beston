<?php

class CaijiAction extends CommonAction {

    public function index() {
        $this->display(); 
    }

    public function search() {
        $keyword = $this->_param('keyword');
        import('ORG.Util.Page');
        // 获取周边信息
        $type= '';
        $key = urldecode($keyword);
        $key = str_replace(' ', '+', $key);
        $place = D('Cloud')->getPlace($key);
        $lat = $place->results[0]->geometry->location->lat;
        $lng = $place->results[0]->geometry->location->lng;
        $placeid = $place->results[0]->place_id;
        $arr = D('Cloud')->GetDetail($placeid);
        $status = $arr->status;
        $value = $arr->result;

        $placeinfo = D('Cloud')->GetAddress($lat, $lng);
        $placeinfo = json_decode($placeinfo);
        $city_code = $placeinfo->result->addressComponent->city;

        $shops = array();
        foreach($value->address_components as $k => $v){
            foreach ($v->types as $k2 => $v2){
                if ($v2 == 'administrative_area_level_1') {
                    $shops['province'] = $v->long_name;
                }
                if ($v2 == 'locality') {
                    $shops['city'] = $v->long_name;
                }
                if ($v2 == 'sublocality') {
                    $shops['distinct'] = $v->long_name;
                }
            }
        }

        if ($status == 'OK') {
            $value->uid = $placeid;
            $value->location->lat = $lat;
            $value->location->lng = $lng;
            $value->address = $value->formatted_address;
            $value->types = implode(",",$value->types);
            //入库到商家库
            $shops['uid'] = $value->uid;
            $shops['shop_name'] = $value->name;
            $shops['type'] = $type;
            $shops['lat'] = $value->location->lat;
            $shops['lng'] = $value->location->lng;
            $shops['addr'] = $value->address;
            $shops['tags'] = $value->types;
            $shops['score'] = ($value->rating) *10;
            $shops['audit'] = 1;
            $shops['orderby'] = 100;
            $shops['create_time'] = NOW_TIME;
            //配置文件
            $city_id = D('City')->where(array('pinyin'=>$city_code))->getField();
            $collects_open = $this->_CONFIG['collects']['open'];

            if ($collects_open == 1) {
                if ($city_id) {
                    $shops['city_id'] = $city_id;
                    $shop = D('Shop')->where(array('uid' => $value->uid))->getField();
                    //采集商店logo以及缩略图
                    if ($value->photos[0]->photo_reference) {
                        $photo_api_url = "https://maps.googleapis.com/maps/api/place/photo?maxwidth=400&photoreference="
                            .$value->photos[0]->photo_reference."&key=AIzaSyBSoyUbiTvaa1kbqgVQj43kv46SsaKvjSU";
                        $headers = get_headers($photo_api_url, TRUE);
                        $photo_url = $headers['Location'];
                    } else {
                        $photo_url = "2017/coming-soon.png";
                    }
                    $shops['photo'] = $photo_url;
                    $shops['logo'] = $photo_url;
                    if ($value->photos[1]) {
                        $photo_api_url = "https://maps.googleapis.com/maps/api/place/photo?maxwidth=400&photoreference="
                            .$value->photos[1]->photo_reference."&key=AIzaSyBSoyUbiTvaa1kbqgVQj43kv46SsaKvjSU";
                        $headers = get_headers($photo_api_url, TRUE);
                        $shops['logo'] = $headers['Location'];
                    }
                    //采集电话
                    if (!empty($value->formatted_phone_number)) {
                        $shops['tel'] = $value->formatted_phone_number;
                    } else {
                        $shops['tel'] = $this->_CONFIG['site']['tel'];
                    }
                    //采集工作时间
                    $shops['business_time'] = implode('|',$value->opening_hours->weekday_text);
                    $find = array('Monday:', 'Tuesday:', 'Wednesday:', 'Thursday:', 'Friday: ', 'Saturday:', 'Sunday:');
                    $shops['business_time'] = str_replace($find,'',$shops['business_time']);
                    if (!empty($shop)) {
                        $shops['exist'] = 1;
                    } else {
                        $shops['exist'] = 0;
                    }
                }
            }
            $poi[] = $shops;
        }
        $Page = new Page(count($poi), 10);
        $show = $Page->show();
        // 分页显示输出
        $var = C('VAR_PAGE') ? C('VAR_PAGE') : 'p';
        $p = $_GET[$var];
        if ($Page->totalPages < $p) {
            die('0');
        }
        $this->assign('lat', $lat);
        $this->assign('lng', $lng);
        $this->assign('keyword', urldecode($keyword));
        $this->assign('poi', $poi);
        $this->assign('page', $show);
        $this->display();
    }

    public function save() {
        $exist = $this->_post('exist');
        $shops = array();
        $shops['uid'] = $this->_post('uid');
        $shops['shop_name'] = $this->_post('shop_name');
        $shops['type'] = $this->_post('type');
        $shops['lat'] = $this->_post('lat');
        $shops['lng'] = $this->_post('lng');
        $shops['addr'] = $this->_post('addr');
        $shops['tags'] = $this->_post('tags');
        $shops['score'] = $this->_post('score');
        $shops['city_id'] = $this->_post('city_id');
        $shops['logo'] = $this->_post('logo');
        $shops['photo'] = $this->_post('photo');
        $shops['tel'] = $this->_post('tel');
        $shops['audit'] = 1;
        $shops['orderby'] = 100;
        $shops['create_time'] = NOW_TIME;

        $city = $this->_post('city');
        $distinct = $this->_post('distinct');
        $areawhere['area_name'] = array('LIKE', '%'.$city.'%');
        $businesswhere['business_name'] = array('LIKE', '%'.$distinct.'%');
        $shops['area_id'] = D('Area')->where($areawhere)->getField('area_id');
        $shops['business_id'] = D('Business')->where($businesswhere)->getField('business_id');

        //保存到数据库
        if ($exist) {
            $id = D('Shop')->where(array('uid'=>$shops['uid']))->getField();
            $msg = '修改成功.';
            D('Shop')->where(array('shop_id'=>$id))->save($shops);
        } else {
            $id = D('Shop')->add($shops);
            $msg = '保存成功.';
        }
        $wei_pic = D('Weixin')->getCode($id, 1);
        $business_time = $this->_post('business_time');
        D('Shopdetails')->upDetails($id, array('business_time'=>$business_time,'wei_pic'=>$wei_pic));
        echo $msg . '<a href="index">返回</a>';die;
    }

}
