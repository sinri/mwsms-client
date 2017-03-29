<?php
/**
 * Created by PhpStorm.
 * User: Sinri
 * Date: 2017/3/29
 * Time: 12:00
 */

namespace sinri\mwsmsclient;


class MWSMSClient
{
    const API_VERSION = '3.4';
    const IN_DEBUG=false;

    const API_REQ_TYPE_SUBMIT=1;
    const API_REQ_TYPE_DELIVERY=2;

    //信息类型(上行标志1),时间,上行手机号,上行通道号,扩展子号(若无扩展这里填*),*,上行信息内容
    const API_REPORT_REQ_TYPE="req_host";//信息类型(上行标志1)(状态报告标志2)
    const API_REPORT_DATETIME="datetime";//时间
    const API_REPORT_MOBILE="mobile";//手机号
    const API_REPORT_ROUTE="route";
    const API_REPORT_SUB_PORT="sub_port";
    const API_REPORT_EXT_1="ext_1";
    const API_REPORT_CONTENT="content";
    //信息类型(状态报告标志2),时间,平台消息编号,通道号,手机号,MongateSendSubmit时填写的MsgId（用户自编消息编号）,*,状态值(0:成功 非0:失败),详细错误原因
    const API_REPORT_PLATFORM_ID="platform_id";//平台消息编号
    const API_REPORT_MSG_ID="msg_id";//MongateSendSubmit时填写的MsgId（用户自编消息编号）
    const API_REPORT_STATUS="status";//状态值(0:成功 非0:失败)
    const API_REPORT_ERROR="error";//详细错误原因

    private $baseApiUrl = "";//such as xx.aspx
    private $userId = '';
    private $password = '';

    public function __construct($params = [])
    {
        $this->baseApiUrl = self::readArray($params, 'api');
        $this->userId = self::readArray($params, 'user');
        $this->password = self::readArray($params, 'password');
    }

    public function doMongateSendSubmit($mobile_list, $sms_string)
    {
        $mobile_string = $mobile_list;
        $mobile_count = 1;
        if (is_array($mobile_list)) {
            $mobile_string = implode(',', $mobile_list);
            $mobile_count = count($mobile_list);
        }

        $sub_port = '*';
        $msg_id = intval(microtime(true) * 10000, 10) . '0' . rand(1000000, 9999999);

        $url = $this->baseApiUrl . '/MongateSendSubmit';
        $data = [
            'userId' => $this->userId,
            'password' => $this->password,
            'pszMobis' => $mobile_string,
            'pszMsg' => $sms_string,
            'iMobiCount' => $mobile_count,
            'pszSubPort' => $sub_port,
            'MsgId' => $msg_id,
        ];

        $response = self::curlPost($url, $data);
        $platform_sms_id = self::parseXmlToGetOneValue($response);
        $result= [
            'done'=>true,
            'request_id'=>$msg_id,
            'data'=>$platform_sms_id,
        ];
        $error=$this->queryErrorDictionary($platform_sms_id);
        if($error!==false){
            $result['done']=false;
            $result['data']=$error;
        }
        return $result;
    }

    public function doMongateQueryBalance(){
        $url=$this->baseApiUrl.'/MongateQueryBalance';
        $data=['userId'=>$this->userId,'password'=>$this->password];
        $response = self::curlPost($url, $data);
        $balance = self::parseXmlToGetOneValue($response);
        return $balance;
    }

    public function doMongateGetDeliver($req_type){
        $url=$this->baseApiUrl.'/MongateGetDeliver';
        $data=[
            'userId'=>$this->userId,
            'password'=>$this->password,
            'iReqType'=>$req_type,
        ];
        $response=self::curlPost($url,$data);
        $list=self::parseXmlToArray($response);
        return $list;
    }

    public function doMongateGetDeliverForSubmit(){
        $list=$this->doMongateGetDeliver(self::API_REQ_TYPE_SUBMIT);
        if(self::IN_DEBUG)var_dump($list);
        if(empty($list)){
            return [];
        }
        //1,2008-01-23 15:43:34,15986756631,10657302056780408,*,*,上行信息1
        $items=[];
        foreach ($list as $item) {
            $matches=[];
            $found=preg_match_all('/^([^,]+),([^,]+),([^,]+),([^,]+),([^,]+),([^,]+),(.+)$/', $item, $matches);
            if($found>0){
                //print_r($matches);
                $items[]=[
                    self::API_REPORT_REQ_TYPE => $matches[1],
                    self::API_REPORT_DATETIME => $matches[2],
                    self::API_REPORT_MOBILE => $matches[3],
                    self::API_REPORT_ROUTE => $matches[4],
                    self::API_REPORT_SUB_PORT => $matches[5],
                    self::API_REPORT_EXT_1 => $matches[6],
                    self::API_REPORT_CONTENT => $matches[7],
                ];
            }
        }

        return $items;
    }
    public function doMongateGetDeliverForDeliver(){
        $list=$this->doMongateGetDeliver(self::API_REQ_TYPE_DELIVERY);
        if(self::IN_DEBUG)var_dump($list);
        if(empty($list)){
            return [];
        }
        //2,2008-01-23 15:43:34,0518153837115735, 10657302056780408,132xxxxxxxx,456123457895210124,*,0,DELIVRD
        $items=[];
        foreach ($list as $item) {
            $matches=[];
            $found=preg_match_all('/^([^,]+),([^,]+),([^,]+),([^,]+),([^,]+),([^,]+),([^,]+),([^,]+),([^,]+)$/', $item, $matches);
            if($found>0){
                print_r($matches);
                $items[]=[
                    self::API_REPORT_REQ_TYPE=>$matches[1],
                    self::API_REPORT_DATETIME=>$matches[2],
                    self::API_REPORT_PLATFORM_ID=>$matches[3],
                    self::API_REPORT_ROUTE=>$matches[4],
                    self::API_REPORT_MOBILE=>$matches[5],
                    self::API_REPORT_MSG_ID=>$matches[6],
                    self::API_REPORT_EXT_1=>$matches[7],
                    self::API_REPORT_STATUS=>$matches[8],
                    self::API_REPORT_ERROR=>$matches[9],
                ];
            }
        }

        return $items;
    }

    public function queryErrorDictionary($code)
    {
        //返回错误编号->错误说明
        static $dic = [
            -1 => '参数为空。信息、电话号码等有空指针，登陆失败',
            -12 => '有异常电话号码',
            -14 => '实际号码个数超过100',
            -999 => '服务器内部错误',
            -10001 => '用户登陆不成功(帐号不存在/停用/密码错误)',
            -10003 => '用户余额不足',
            -10011 => '信息内容超长',
            -10029 => '此用户没有权限从此通道发送信息(用户没有绑定该性质的通道，比如：用户发了小灵通的号码)',
            -10030 => '不能发送移动号码',
            -10031 => '手机号码(段)非法',
            -10057 => 'IP受限',
            -10056 => '连接数超限',
        ];
        if(isset($dic[$code])){
            return $dic[$code];
        }
        return false;
    }

    // toolkit
    public static function readArray($array, $key, $default = '')
    {
        if (empty($array)) {
            return $default;
        }
        if (!isset($array[$key])) {
            return $default;
        }
        return $array[$key];
    }

    public static function curlPost($url, $data = [])
    {
        $curl = curl_init($url);
        curl_setopt($curl, CURLOPT_POST, TRUE);
// ↓はmultipartリクエストを許可していないサーバの場合はダメっぽいです
// @DrunkenDad_KOBAさん、Thanks
//curl_setopt($curl,CURLOPT_POSTFIELDS, $POST_DATA);
        curl_setopt($curl, CURLOPT_POSTFIELDS, http_build_query($data));
        curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, FALSE);  // オレオレ証明書対策
        curl_setopt($curl, CURLOPT_SSL_VERIFYHOST, FALSE);  //
        curl_setopt($curl, CURLOPT_RETURNTRANSFER, TRUE);
//        curl_setopt($curl, CURLOPT_COOKIEJAR, 'cookie');
//        curl_setopt($curl, CURLOPT_COOKIEFILE, 'tmp');
        curl_setopt($curl, CURLOPT_FOLLOWLOCATION, TRUE); // Locationヘッダを追跡
//curl_setopt($curl,CURLOPT_REFERER,        "REFERER");
//curl_setopt($curl,CURLOPT_USERAGENT,      "USER_AGENT");

        $output = curl_exec($curl);

        if(self::IN_DEBUG){
            echo "[URL: {$url}]".PHP_EOL;
            echo $output.PHP_EOL;
            echo "[FIN]".PHP_EOL;
        }

        return $output;
    }

    public static function parseXmlToGetOneValue($xml_string)
    {
        try {
            $xml = @simplexml_load_string($xml_string);
            if ($xml === FALSE) {
                return false;
            }
            $array = (array)$xml;
            if (!isset($array[0])) {
                return false;
            }
            return $array[0];
        } catch (\Exception $exception) {
            return false;
        }
    }
    public static function parseXmlToArray($xml_string)
    {
        try {
            $xml = @simplexml_load_string($xml_string);
            if ($xml === FALSE) {
                return false;
            }
            $array=(array)$xml;
            $array=array_values($array);
            if(!isset($array[0])){
                return false;
            }
            return $array[0];
        } catch (\Exception $exception) {
            return false;
        }
    }
}