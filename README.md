易优CMS对接 PAYJS 插件
======
## 前提
已安装好易优CMS

安装文档：https://www.eyoucms.com/help/azwt/2020/1012/11028.html

注：如果web环境是nginx，还需设置伪静态规则：
```
location / {
	if (!-e $request_filename){
		rewrite  ^(.*)$  /index.php?s=$1  last;   break;
	}
}
```

## 安装

1. 下载插件压缩包：https://github.com/payjs-cn/plugin-eyoucms/archive/main.zip

2. 解压压缩包，将解压后的文件夹覆盖到网站的根目录。

3. 登录后台，在菜单中选择`插件应用`，点击`安装`按钮。

4. 在`插件应用`中点击`PAYJS`下的`管理`链接，点击`插件配置`，填写配置信息。

5. 填写好配置信息后即可在`订单列表`中点击`测试支付`进行测试，也可以登录前台会员中心测试在线充值。

## 卸载

登录后台，在菜单中选择`插件应用`，点击`卸载`按钮。


## 使用
### 如何支付
指定订单金额：
http://yourname/?m=plugins&c=payjs&a=index&total_fee=0.01

指定订单号：
http://yourname/?m=plugins&c=payjs&a=index&total_fee=0.01&out_trade_no=123456

指定订单标题：
http://yourname/?m=plugins&c=payjs&a=index&total_fee=0.01&subject=测试

指定支付通道：
http://yourname/?m=plugins&c=payjs&a=index&total_fee=0.01&pay_channel=weixin

指定使用JSAPI支付
http://yourname/?m=plugins&c=payjs&a=index&total_fee=0.01&pay_mode=jsapi

指定使用收银台支付
http://yourname/?m=plugins&c=payjs&a=index&total_fee=0.01&pay_mode=cashier

全都指定：
http://yourname/?m=plugins&c=payjs&a=index&total_fee=0.01?out_trade_no=123456&subject=测试&pay_channel=weixin

### 异步通知
异步通知在application/plugins/controller/Payjs.php中的notify方法

### 退款
退款请参考application/plugins/model/PayjsOrders.php中的refund方法
