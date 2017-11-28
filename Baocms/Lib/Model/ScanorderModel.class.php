<?php
class ScanorderModel extends CommonModel{
    protected $pk   = 'order_id';
    protected $tableName =  'scan_order';
    public $status = array(
        0 => 'The order has not been paid',
        1 => 'The order has been paid',
        2 => 'The order has expired',
        3 => 'The order\'s status is abnormal '
    );
    public $settled = array(
        0 => 'The order has not been settled to the merchant',
        1 => 'The order has been settled to the merchant',
    );

}