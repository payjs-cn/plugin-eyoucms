<?php
/**
 * 易优CMS
 * ============================================================================
 * 版权所有 2016-2028 海南赞赞网络科技有限公司，并保留所有权利。
 * 网站地址: http://www.eyoucms.com
 * ----------------------------------------------------------------------------
 * 如果商业用途务必到官方购买正版授权, 以免引起不必要的法律纠纷.
 * ============================================================================
 * Author: 小虎哥 <1105415366@qq.com>
 * Date: 2018-4-3
 */

namespace app\plugins\controller;

use app\plugins\model\PayjsOrders;
use app\plugins\logic\PayjsLogic;
use think\Log;

class Payjs extends Base
{
    protected $config = [];
    /**
     * 构造方法
     */
    public function __construct(){
        parent::__construct();
        $this->config = getPayjsConfig();
        if($this->config===false || empty($this->config['mchid']) || empty($this->config['appkey'])){
            $this->error('请先填写插件配置信息');
        }
    }

    /**
     * index
     */
    public function index()
    {
        $data['out_trade_no'] = input('out_trade_no') ?: generateOutTradeNo();
        $data['total_fee'] = input('total_fee') ? sprintf("%.2f", floatval(input('total_fee'))) : 0.01;
        $data['subject'] = input('subject') ?: '订单号：' . $data['out_trade_no'];
        $data['pay_channel'] = input('pay_channel') ?: $this->config['pay_channel'];
        $data['pay_mode'] = input('pay_mode') ?: 'native';
        if ($data['pay_mode'] == 'jsapi') return $this->jsapi($data);
        if ($data['pay_mode'] == 'cashier') return $this->cashier($data);
        $this->assign($data);
        return $this->fetch();
    }

    /**
     * 获取支付二维码
     */
    public function getQrcode()
    {
        $data['out_trade_no'] = input('out_trade_no') ?: generateOutTradeNo();
        $data['total_fee'] = input('total_fee') ? sprintf("%.2f", floatval(input('total_fee'))) : 0.01;
        $data['subject'] = input('subject') ?: '订单号：' . $data['out_trade_no'];
        $data['pay_channel'] = $this->config['pay_channel'] ?: 'all';
        $data['type'] = input('paymode') ?: 'weixin';
        //添加数据库订单
        PayjsOrders::unify($data);
        //获取支付二维码
        $payjsServeice = new PayjsLogic($this->config);
        $result = $payjsServeice->getQrcode($data);
        $arr = json_decode($result, true);
        if ($arr['return_code'] == 1) {
            //设置payjs平台订单号
            PayjsOrders::setPayjsOrderId($arr['out_trade_no'], $arr['payjs_order_id']);
        }
        echo $result;
        exit();
    }


    /**
     * jspai支付
     * @param $data
     */
    public function jsapi($data)
    {
        $payjsServeice = new PayjsLogic($this->config);
        //获取openid
        $data['openid'] = $payjsServeice->getOpenid();
        //获取jsapi参数
        $jsapiConfig = $payjsServeice->jsapi($data);
        //添加数据库订单
        $data['outer_tid'] = $jsapiConfig['outer_tid'];
        PayjsOrders::unify($data);
        $this->assign($jsapiConfig);
        return $this->fetch('jsapi');
    }

    /**
     * 收银台支付
     * @param $data
     */
    public function cashier($data)
    {
        $payjsServeice = new PayjsLogic($this->config);
        //收银台支付
        $url = $payjsServeice->cashier($data);
        if (in_array($data['pay_channel'],['all','alipay']) && !isAlipay() && !isWeixin()) {
            $url = "alipays://platformapi/startapp?appId=20000067&url=" . urlencode($url);
        }
        header("Location:{$url}");
        exit();
    }

    /**
     * 查询订单支付状态
     */
    public function checkOrder()
    {
        $data = PayjsOrders::orderQuery(input('out_trade_no'));
        return json($data);
    }

    /**
     * 支付结果显示页面
     */
    public function response()
    {
        $outerTid = PayjsOrders::getPayjsOrderId(input('out_trade_no'));
        if (!$outerTid) exit('支付失败：未找到该笔订单');
        $payjsServeice = new PayjsLogic($this->config);
        $result = $payjsServeice->orderquery($outerTid);
        $data = json_decode($result, true);
        if (isset($data['message'])) {
            exit('支付失败：' . $data['message']);
        }
        if ($data['return_code'] == 0) {
            exit('支付失败：' . $data['msg']);
        }
        $this->assign($data);
        return $this->fetch();
    }

    /**
     * 异步通知
     */
    public function notify()
    {
        $postdata = request()->post();
        $payjsServeice = new PayjsLogic($this->config);
        $result = $payjsServeice->check($postdata);
        if ($result === false) {
            exit('sign error');
        }
        $order = PayjsOrders::getOrderByTid($postdata['payjs_order_id']);
        if(!$order){
            echo 'order is not exist';
            exit();
        }
        if ($order->status == 1) {
            $data = [
                'status' => 0,
                'transaction_tid' => $postdata['transaction_id'],
                'pay_at' => $postdata['time_end'],
                'buyer_info' => $postdata['openid'],
            ];
            PayjsOrders::updateOrderByTid($postdata['payjs_order_id'], $data);
        }
        echo 'success';
        exit();
    }


}