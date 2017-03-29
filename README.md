# mwsms-client
梦网平台短信接口，对应文档3.4，采用HTTP POST方式实现。

## Usage

```PHP
// 由autoload控制，也可以自行引用
require_once 'MWSMSClient.php';
// 建立client
$params=[
    "api"=>'http://test.com:9006/MWGate/wmgw.asmx',
    "user"=>'U',
    "password"=>'P',
];
$client = new \sinri\mwsmsclient\MWSMSClient($params);

//群发短信
$result=$client->doMongateSendSubmit(['13000000000','18000000000'],"同事您好，感谢您对此次测试的配合。123456");
var_export($result);
/*
array (
  'done' => true, //是不是有错
  'request_id' => '1490775230135809251990',//发送方 MsgId
  'data' => '4521856470400363903',//成功，接收方 SmsId 或者失败，错误信息
)
*/

//查还有几条能发
$result=$client->doMongateQueryBalance();
var_export($result);

//查询上行
$result=$client->doMongateGetDeliverForSubmit();
var_export($result);

//查询发送状况
$result=$client->doMongateGetDeliverForDeliver();
var_export($result);
```

虚无……
