<?php



class DownloadAction extends CommonAction {

    public function index() {
        $iPhone  = stripos($_SERVER['HTTP_USER_AGENT'],"iPhone");
        $iPad    = stripos($_SERVER['HTTP_USER_AGENT'],"iPad");
        $Android = stripos($_SERVER['HTTP_USER_AGENT'],"Android");

        if( $iPad || $iPhone ){
            header('Location: ' . $this->CONFIG['site']['ios']);
        }else if($Android){
            header('Location: ' . $this->CONFIG['site']['android']);
        }

    }

}
