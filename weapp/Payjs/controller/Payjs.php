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
 * Date: 2018-06-28
 */

namespace weapp\Payjs\controller;

use app\plugins\model\PayjsOrders;
use think\exception\HttpResponseException;
use think\Page;
use think\Db;
use app\common\controller\Weapp;
use think\response\Redirect;
use weapp\Payjs\model\PayjsModel;

/**
 * 插件的控制器
 */
class Payjs extends Weapp
{
    /**
     * 实例化模型
     */
    private $model;

    /**
     * 实例化对象
     */
    private $db;

    /**
     * 插件基本信息
     */
    private $weappInfo;

    /**
     * 构造方法
     */
    public function __construct()
    {
        parent::__construct();
        $this->model = new PayjsModel;
        $this->db = Db::name('WeappPayjsOrders');

        /*插件基本信息*/
        $this->weappInfo = $this->getWeappInfo();
        $this->assign('weappInfo', $this->weappInfo);
        /*--end*/
    }

    /**
     * 插件使用指南
     */
    public function doc()
    {
        return $this->fetch('doc');
    }

    /**
     * 系统内置钩子show方法（没用到这个方法，建议删掉）
     * 用于在前台模板显示片段的html代码，比如：QQ客服、对联广告等
     *
     * @param mixed $params 传入的参数
     */
    public function show($params = null)
    {
        $list = $this->db->select();
        $this->assign('list', $list);
        echo $this->fetch('show');
    }

    public function detail()
    {
        $id = input('id/d', 0);
        $row = $this->db->find($id);
        if (empty($row)) {
            $this->error('找不到该订单！');
            exit;
        }
        $this->assign('row', $row);
        return $this->fetch();
    }

    /**
     * 插件后台管理 - 列表
     */
    public function index()
    {
        $list = array();
        $keywords = input('keywords/s');

        $map = array();
        if (!empty($keywords)) {
            $map['out_trade_no'] = array('LIKE', "%{$keywords}%");
        }

        $count = $this->db->where($map)->count('id');// 查询满足要求的总记录数
        $pageObj = new Page($count, config('paginate.list_rows'));// 实例化分页类 传入总记录数和每页显示的记录数
        $list = $this->db->where($map)->order('id desc')->limit($pageObj->firstRow . ',' . $pageObj->listRows)->select();
        $pageStr = $pageObj->show(); // 分页显示输出
        $this->assign('list', $list); // 赋值数据集
        $this->assign('pageStr', $pageStr); // 赋值分页输出
        $this->assign('pageObj', $pageObj); // 赋值分页对象

        return $this->fetch('index');
    }


    /**
     * 删除订单
     */
    public function del()
    {
        $id_arr = input('del_id/a');
        $id_arr = eyIntval($id_arr);
        if (!empty($id_arr) && IS_POST) {
            $result = $this->db->where("id", 'IN', $id_arr)->select();
            $deleteId = [];
            foreach ($result as $item) {
                if ($item['status'] == 1) {
                    $deleteId[] = $item['id'];
                }
            }
            $r = $this->db->where("id", 'IN', $deleteId)->delete();
            if ($r) {
                adminLog('删除订单：' . implode(',', $deleteId));
                $this->success("操作成功!");
            } else {
                $this->error("操作失败!");
            }
        } else {
            $this->error("参数有误!");
        }
    }

    /**
     * 退款
     */
    public function refund()
    {
        $id_arr = input('del_id/a');
        $id_arr = eyIntval($id_arr);
        $id = intval($id_arr[0]);
        if ($id > 0 && IS_POST) {
            $order = $this->db->where(['id' => $id])->find();
            if (!$order) {
                $this->error('未找到该订单');
            }
            if ($order['status'] != 0) {
                $this->error('订单当前状态不支持退款');
            }
            $result = PayjsOrders::refund($order['out_trade_no']);
            if ($result['status'] != 'success') {
                $this->error($result['msg']);
            } else {
                adminLog('订单退款：' . $order['out_trade_no']);
                $this->success($result['msg']);
                exit();
            }
        } else {
            $this->error("参数有误!");
        }
    }

    public function UnifyAction()
    {
        return $this->conf();
    }

    /**
     * 插件配置
     */
    public function conf()
    {
        if (IS_POST) {
            $post = input('post.');
            if (!empty($post['code'])) {
                $data = array(
                    'mchid' => $post['mchid'],
                    'appkey' => $post['appkey'],
                    'pay_channel' => $post['pay_channel'],
                    'notify_url' => $post['notify_url'],
                );
                M('weapp')->where('code', 'eq', $post['code'])->update(['data' => json_encode($data)]);
                \think\Cache::clear('hooks');
                adminLog('编辑' . $this->weappInfo['name'] . '：插件配置'); // 写入操作日志
                $this->setPayApiConfig();
                $this->success("操作成功!", weapp_url('Payjs/Payjs/conf'));
            }
            $this->error("操作失败!");
        }

        $row = M('weapp')->where('code', 'eq', 'Payjs')->find();
        $data = json_decode($row['data'],true);
        $data['notify_url'] = htmlspecialchars_decode($data['notify_url']);
        $this->assign('row', $row);
        $this->assign('data', $data);

        return $this->fetch('conf');
    }

    private function setPayApiConfig()
    {
        $configRow = M('PayApiConfig')->where('pay_name', 'Payjs')->find();
        if(!$configRow){
            $data['pay_name'] = 'Payjs';
            $data['pay_mark'] = 'Payjs';
            $data['pay_info'] = 'a:1:{s:11:"is_open_pay";i:0;}';
            $data['pay_terminal'] = '';
            $data['system_built'] = 0;
            $data['status'] = 1;
            $data['lang'] = 'cn';
            $data['add_time'] = '1604997848';
            $data['update_time'] = '1604997848';
            M('PayApiConfig')->insert($data);
        }
    }

    public function test()
    {
        $this->redirect('plugins/payjs/index', ['total_fee' => 0.01]);
    }

    public function UnifyGetPayAction($PayInfo, $Order)
    {
        $cause = unserialize($Order['cause']) ? unserialize($Order['cause']) : $Order['cause'];
        $subject = $cause['type_name'] ? $cause['type_name'] : $Order['cause'];
        return [
            'url' => url('plugins/payjs/index', ['out_trade_no' => $Order['order_number'], 'total_fee' => $Order['money'], 'subject' => $subject]),
            'data' => [
                'appId' => null,
                'url_qrcode' => null
            ]
        ];
    }

    public function OtherPayProcessing($PayInfo, $outTradeNo)
    {
        $order = PayjsOrders::getPayjsOrder($outTradeNo);
        if($order && $order['status']==0){
            return (array)$order;
        }
        return false;
    }
}