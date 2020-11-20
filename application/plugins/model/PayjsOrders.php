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

namespace app\plugins\model;

use app\plugins\logic\PayjsLogic;
use think\Model;
use think\Db;

/**
 * 模型
 */
class PayjsOrders extends Model
{
    /**
     * 数据表名，不带前缀
     */
    public $name = 'weapp_payjs_orders';

    //初始化
    protected function initialize()
    {
        // 需要调用`Model`的`initialize`方法
        parent::initialize();
    }

    public static function unify($data)
    {
        unset($data['pay_channel']);
        unset($data['openid']);
        unset($data['pay_mode']);
        $data['created_at'] = date('Y-m-d H:i:s');
        $data['updated_at'] = date('Y-m-d H:i:s');
        return self::create($data);
    }

    /**
     * 设置payjs平台订单号
     * @param string $outTradeNo
     * @param string $orderId
     * @return PayjsOrder
     */
    public static function setPayjsOrderId($outTradeNo, $orderId)
    {
        return self::where(['out_trade_no' => $outTradeNo])->update([
            'outer_tid' => $orderId
        ]);
    }

    /**
     * 根据订单号获取订单记录
     * @param $outTradeNo
     * @return array|bool|\PDOStatement|string|Model|null
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\ModelNotFoundException
     * @throws \think\exception\DbException
     */
    public static function getPayjsOrderId($outTradeNo)
    {
        return self::where(['out_trade_no' => $outTradeNo])->value('outer_tid');
    }

    /**
     * 根据订单号获取订单记录
     * @param $outTradeNo
     * @return array|bool|\PDOStatement|string|Model|null
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\ModelNotFoundException
     * @throws \think\exception\DbException
     */
    public static function getPayjsOrder($outTradeNo)
    {
        return self::where(['out_trade_no' => $outTradeNo])->find();
    }

    /**
     * 根据payjs订单号查询订单记录
     * @param string $tid
     * @return mixed
     */
    public static function getOrderByTid($tid)
    {
        return self::where(['outer_tid' => $tid])->find();
    }

    /**
     * 根据payjs订单号修改订单记录
     * @param $outTradeNo
     * @param int $status
     */
    public static function updateOrderByTid($tid, $data)
    {
        return self::where(['outer_tid' => $tid])->update($data);
    }

    /**
     * 修改订单状态
     * @param string $outTradeNo
     * @param int $status
     * @return PayjsOrder
     */
    public static function changeOrderStatus($outTradeNo, $status = 1)
    {
        return self::where(['out_trade_no' => $outTradeNo])->update([
            'status' => intval($status)
        ]);
    }

    /**
     * 订单状态查询
     * @param string $outTradeNo 商户订单号
     */
    public static function orderQuery($outTradeNo)
    {
        $data['status'] = 'error';
        $data['code'] = 2;      //未支付状态
        $order = self::getPayjsOrder($outTradeNo);
        if (!$order->outer_tid) {
            $data['msg'] = '未找到该笔订单';
            return $data;
        }
        $config = [];
        $row = M('weapp')->where('code', 'eq', 'Payjs')->find();
        if ($row['data']) {
            $config = json_decode($row['data'], true);
            $config['notify_url'] = htmlspecialchars_decode($config['notify_url']);
        }
        $payjsServeice = new PayjsLogic($config);
        $result = $payjsServeice->orderquery($order->outer_tid);
        $result = json_decode($result, true);
        if ($result['return_code'] == 1) {
            //查询成功
            $data['status'] = 'success';
            if ($result['status'] == 1 && $order->status == 1) {
                self::changeOrderStatus($outTradeNo, 0);
            }
            if ($result['status'] == 1) $data['code'] = 0;     //修改为已支付状态
            return $data;
        } else {
            $data['msg'] = $result['return_msg'];
            return $data;
        }
    }

    /**
     * 退款
     * @param string $outTradeNo 商户订单号
     * @return mixed
     */
    public static function refund($outTradeNo)
    {
        $data['status'] = 'error';
        $order = self::getPayjsOrder($outTradeNo);
        if (!$order) {
            $data['msg'] = '没有找到该笔订单';
            return $data;
        }
        if ($order->status != 0) {
            $data['msg'] = '该订单状态不能退款';
            return $data;
        }
        $config = getPayjsConfig();
        $payjsServeice = new PayjsLogic($config);
        $result = $payjsServeice->refund($order->outer_tid);
        $result = json_decode($result, true);
        if ($result['return_code'] == 1) {
            //退款成功，修改订单状态
            self::changeOrderStatus($outTradeNo, 2);
            $data['status'] = 'success';
            $data['msg'] = '退款成功';
            return $data;
        } else {
            $data['msg'] = $result['return_msg'];
            return $data;
        }
    }
}