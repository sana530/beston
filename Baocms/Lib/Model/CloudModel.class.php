<?php
class CloudModel extends CommonModel{
	private $curl = null;
    public function __construct() {
        import("@/Net.Curl");
        $this->curl = new Curl();
    }
	

    public function GetLocation($lat,$lng) { //通过云端校准坐标
		$str = file_get_contents($url);
        return $str;
    }
	
	
    public function GetAddress($lat,$lng) { //通过云端校准以坐标获取详细地址
        $url ='http://api.map.baidu.com/geocoder/v2/?ak=C9613fa45f450daa331d85184c920119&location='.$lat.','.$lng.'&output=json&pois=1';
		$str = file_get_contents($url);
        return $str;
    }
	
	
	
    public function NearData($lat,$lng,$key,$p) { //通过云端获取POIS数据
        $url = 'https://maps.googleapis.com/maps/api/place/nearbysearch/json?location='.$lat.','.$lng.'&radius=5000&types=&name='.$key.'&key=AIzaSyBSoyUbiTvaa1kbqgVQj43kv46SsaKvjSU';
        //$url = 'http://api.map.baidu.com/place/v2/search?query='.$key.'&location='.$lat.','.$lng.'&radius=2000&output=json&ak=C9613fa45f450daa331d85184c920119';
        $str = file_get_contents_url($url);
		//$arr = json_decode($str);
		return $str;
    }

    public function GetDetail($placeid) { //通过云端获取相应地址商铺的详细数据
        $url = 'https://maps.googleapis.com/maps/api/place/details/json?placeid='.$placeid.'&key=AIzaSyBSoyUbiTvaa1kbqgVQj43kv46SsaKvjSU';
        $str = file_get_contents_url($url);
        return $str;
    }

    public function GetPlace($key) { //通过云端获取place
        $url = 'https://maps.googleapis.com/maps/api/geocode/json?&address='.$key.'&key=AIzaSyBSoyUbiTvaa1kbqgVQj43kv46SsaKvjSU';
        $str = file_get_contents_url($url);
        return $str;
    }

    public function GetTel($place_id, $config_tel) {  //通过云端获取商家电话
        $tel = $this->GetDetail($place_id)->result->formatted_phone_number;
        if (!empty($tel)) {
            return $tel;
        } else {
            return $config_tel;
        }
    }

		
}