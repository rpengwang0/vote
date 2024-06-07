<?php
use Think\Cache\Driver\Redis;
function dd($data)
{
    echo "<pre>";
    print_r($data);
    echo "</pre>";
}

function return_data($code, $msg = '', $data = array())
{
    //$return = array('code' => (int)$code, 'msg' => (string)$msg, 'data' => $data);
    if ($code == 1 || $code == 302) {
        $return = array('code' => (int)$code, 'msg' => (string)$msg, 'data' => $data);
    } else {
        $return = array('code' => (int)$code, 'msg' => (string)$msg, 'data' => (object)$data);
    }
    header('Content-Type:application/json; charset=utf-8');
    echo json_encode($return);
}
function resultInfo($code = 0, $msg = "", $data = []){
    $info = [];
    $info['code'] = $code;
    $info['msg'] = $msg;
    $info['data'] = $data;
    return $info;
}
//生成唯一订单号
function generateOrderSn($sn = ""){
    $sn .= date('Ymd').substr(implode(NULL, array_map('ord', str_split(substr(uniqid(), 7, 13), 1))), 0, 8).substr(microtime(), 2, 5) . mt_rand(10000,99999);
    return $sn;
}

/*
* 获取用户真实IP地址
*/
function get_ip()
{
    if(!empty($_SERVER['HTTP_CLIENT_IP'])){
        $cip = $_SERVER['HTTP_CLIENT_IP'];
    }
    else if(!empty($_SERVER['HTTP_X_FORWARDED_FOR'])){
        $cip = $_SERVER["HTTP_X_FORWARDED_FOR"];
    }
    else if(!empty($_SERVER["REMOTE_ADDR"])){
        $cip = $_SERVER["REMOTE_ADDR"];
    }else{
        $cip = '';
    }
    preg_match("/[\d\.]{7,15}/", $cip, $cips);
    $cip = isset($cips[0]) ? $cips[0] : 'unknown';
    unset($cips);
    return $cip;
}

function exportExcel($expTitle, $expCellName, $expTableData, $topData=[])
{
    include_once \think\facade\Env::get('root_path') . 'vendor/phpoffice/phpexcel/Classes/PHPExcel.php';
    $xlsTitle = iconv('utf-8', 'gb2312', $expTitle);//文件名称
    $fileName = $xlsTitle;//or $xlsTitle 文件名称可根据自己情况设定
    $cellNum = count($expCellName);
    $dataNum = count($expTableData);
    $topNum = count($topData);

    $objPHPExcel = new PHPExcel();
    $cellName = array('A', 'B', 'C', 'D', 'E', 'F', 'G', 'H', 'I', 'J', 'K', 'L', 'M', 'N', 'O', 'P', 'Q', 'R', 'S', 'T', 'U', 'V', 'W', 'X', 'Y', 'Z', 'AA', 'AB', 'AC', 'AD', 'AE', 'AF', 'AG', 'AH', 'AI', 'AJ', 'AK', 'AL', 'AM', 'AN', 'AO', 'AP', 'AQ', 'AR', 'AS', 'AT', 'AU', 'AV', 'AW', 'AX', 'AY', 'AZ');

    $objPHPExcel->getActiveSheet(0)->mergeCells('A1:' . $cellName[$cellNum - 1] . '1');//合并单元格
    $objPHPExcel->setActiveSheetIndex(0)->setCellValue('A1', $expTitle);

    for ($i = 0; $i < count($topData); $i++) {
        for ($j = 0; $j < count($topData[$i]); $j++) {
            $objPHPExcel->setActiveSheetIndex(0)->setCellValue($cellName[$j] . ($i + 2), $topData[$i][$j]);
        }
    }

    for ($i = 0; $i < $cellNum; $i++) {
        $objPHPExcel->setActiveSheetIndex(0)->setCellValue($cellName[$i] . ($topNum + 2), $expCellName[$i][1]);
    }
    // Miscellaneous glyphs, UTF-8
    for ($i = 0; $i < $dataNum; $i++) {
        for ($j = 0; $j < $cellNum; $j++) {
            $objPHPExcel->getActiveSheet(0)->setCellValue($cellName[$j] . ($i + $topNum + 3), $expTableData[$i][$expCellName[$j][0]]);
        }
    }
    ob_clean();
    header('pragma:public');
    header('Content-type:application/vnd.ms-excel;charset=utf-8;name="' . $xlsTitle . '.xls"');
    header("Content-Disposition:attachment;filename=$fileName.xls");//attachment新窗口打印inline本窗口打印
    $objWriter = \PHPExcel_IOFactory::createWriter($objPHPExcel, 'Excel5');
    $objWriter->save('php://output');
    exit;
}

/**
 * curl模拟post请求
 * @param $url
 * @param array $data
 * @param array $headers
 * @return array|mixed
 * @author ph
 * Time: 2019-05-29
 */
function requestUrl($url, $data = array(), $headers = array()) {

    $curl = curl_init();
    //设置抓取的url
    curl_setopt($curl, CURLOPT_URL, $url);
    //设置头文件的信息作为数据流输出
    curl_setopt($curl, CURLOPT_HEADER, 0);
    //设置获取的信息以文件流的形式返回，而不是直接输出。
    curl_setopt($curl, CURLOPT_RETURNTRANSFER, 1);
    curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, false);
    curl_setopt($curl, CURLOPT_USERAGENT, 'Dalvik/2.1.0 (Linux; U; Android 7.1.2; Redmi 5 Plus MIUI/V9.6.3.0.NEGCNFD)');
    if ($data) {
        curl_setopt($curl, CURLOPT_POST, 1);
        curl_setopt($curl, CURLOPT_POSTFIELDS, $data);
    }
    if ($headers) {
        curl_setopt($curl, CURLOPT_HTTPHEADER, $headers);
    }
    //执行命令
    $data = curl_exec($curl);
    //关闭URL请求
    curl_close($curl);
    return $data;
}
function S(){
	$con = new \Redis();
	
        $con->connect('127.0.0.1', 6379, 5);
        $con->auth("Ajinritemaidfmy20332");
        return $con;
}
?>