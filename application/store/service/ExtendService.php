<?php

// +----------------------------------------------------------------------
// | framework
// +----------------------------------------------------------------------
// | 山西东方梅雅
// +----------------------------------------------------------------------
// dfhf.vip
// +----------------------------------------------------------------------
 
// +----------------------------------------------------------------------
 
// +----------------------------------------------------------------------

namespace app\store\service;

use library\tools\Http;
use think\Db;

/**
 * 业务扩展服务
 * Class ExtendService
 * @package app\store\service
 */
class ExtendService
{

    /**
     * 发送短信验证码
     * @param string $mid 会员ID
     * @param string $phone 手机号
     * @param string $content 短信内容
     * @param string $productid 短信通道ID
     * @return boolean
     * @throws \think\Exception
     * @throws \think\exception\PDOException
     */
    public static function sendSms($mid, $phone, $content, $productid = '676767')
    {
        $tkey = date("YmdHis");
        $data = [
            'tkey'      => $tkey,
            'mobile'    => $phone,
            'content'   => $content,
            'username'  => sysconf('sms_zt_username'),
            'productid' => $productid,
            'password'  => md5(md5(sysconf('sms_zt_password')) . $tkey),
        ];
        $result = Http::post('http://www.ztsms.cn/sendNSms.do', $data);
        list($code, $msg) = explode(',', $result . ',');
        $insert = ['mid' => $mid, 'phone' => $phone, 'content' => $content, 'result' => $result];
        Db::name('StoreMemberSmsHistory')->insert($insert);
        return intval($code) === 1;
    }

    /**
     * 查询短信余额
     * @return array
     * @throws \think\Exception
     * @throws \think\exception\PDOException
     */
    public static function querySmsBalance()
    {
        $tkey = date("YmdHis");
        $data = [
            'tkey'     => $tkey,
            'username' => sysconf('sms_zt_username'),
            'password' => md5(md5(sysconf('sms_zt_password')) . $tkey),
        ];
        $result = Http::post('http://www.ztsms.cn/balanceN.do', $data);
        if ($result > -1) {
            return ['code' => 1, 'num' => $result, 'msg' => '获取短信剩余条数成功！'];
        } elseif ($result > -2) {
            return ['code' => 0, 'num' => '0', 'msg' => '用户名或者密码不正确！'];
        } elseif ($result > -3) {
            return ['code' => 0, 'num' => '0', 'msg' => 'tkey不正确！'];
        } elseif ($result > -4) {
            return ['code' => 0, 'num' => '0', 'msg' => '用户不存在或用户停用！'];
        }
    }

    /**
     * 发送国际短信内容
     * @param string $mid
     * @param string $code 国家代码
     * @param string $phone 手机号码
     * @param string $content 发送内容
     * @return boolean
     * @throws \think\Exception
     * @throws \think\exception\PDOException
     */
    public static function sendSms2($mid, $code, $phone, $content)
    {
        $tkey = date("YmdHis");
        $data = [
            'tkey'     => $tkey,
            'code'     => $code,
            'mobile'   => $phone,
            'content'  => $content,
            'username' => sysconf('sms_zt_username2'),
            'password' => md5(md5(sysconf('sms_zt_password2')) . $tkey),
        ];
        $result = Http::post('http://intl.zthysms.com/intSendSms.do', $data);
        $insert = ['mid' => $mid, 'phone' => $phone, 'content' => $content, 'result' => $result];
        Db::name('StoreMemberSmsHistory')->insert($insert);
        return intval($code) === 1;
    }

    /**
     * 查询国际短信余额
     * @return array
     * @throws \think\Exception
     * @throws \think\exception\PDOException
     */
    public static function querySmsBalance2()
    {
        $tkey = date("YmdHis");
        $data = [
            'username' => sysconf('sms_zt_username2'), 'tkey' => $tkey,
            'password' => md5(md5(sysconf('sms_zt_password2')) . $tkey),
        ];
        $result = Http::post('http://intl.zthysms.com/intBalance.do', $data);
        if ($result > -1) {
            return ['code' => 1, 'num' => $result, 'msg' => '获取短信剩余条数成功！'];
        } elseif ($result > -2) {
            return ['code' => 0, 'num' => '0', 'msg' => '用户名或者密码不正确！'];
        } elseif ($result > -3) {
            return ['code' => 0, 'num' => '0', 'msg' => 'tkey不正确！'];
        } elseif ($result > -4) {
            return ['code' => 0, 'num' => '0', 'msg' => '用户不存在或用户停用！'];
        }
    }

    /**
     * 获取国际地域编号
     * @return array
     */
    public static function getRegionMap()
    {
        return [
            ['title' => '中国 台湾', 'english' => 'Taiwan', 'code' => 886],
            ['title' => '东帝汶民主共和国', 'english' => 'DEMOCRATIC REPUBLIC OF TIMORLESTE', 'code' => 670],
            ['title' => '中非共和国', 'english' => 'Central African Republic', 'code' => 236],
            ['title' => '丹麦', 'english' => 'Denmark', 'code' => 45],
            ['title' => '乌克兰', 'english' => 'Ukraine', 'code' => 380],
            ['title' => '乌兹别克斯坦', 'english' => 'Uzbekistan', 'code' => 998],
            ['title' => '乌干达', 'english' => 'Uganda', 'code' => 256],
            ['title' => '乌拉圭', 'english' => 'Uruguay', 'code' => 598],
            ['title' => '乍得', 'english' => 'Chad', 'code' => 235],
            ['title' => '也门', 'english' => 'Yemen', 'code' => 967],
            ['title' => '亚美尼亚', 'english' => 'Armenia', 'code' => 374],
            ['title' => '以色列', 'english' => 'Israel', 'code' => 972],
            ['title' => '伊拉克', 'english' => 'Iraq', 'code' => 964],
            ['title' => '伊朗', 'english' => 'Iran', 'code' => 98],
            ['title' => '伯利兹', 'english' => 'Belize', 'code' => 501],
            ['title' => '佛得角', 'english' => 'Cape Verde', 'code' => 238],
            ['title' => '俄罗斯', 'english' => 'Russia', 'code' => 7],
            ['title' => '保加利亚', 'english' => 'Bulgaria', 'code' => 359],
            ['title' => '克罗地亚', 'english' => 'Croatia', 'code' => 385],
            ['title' => '关岛', 'english' => 'Guam', 'code' => 1671],
            ['title' => '冈比亚', 'english' => 'The Gambia', 'code' => 220],
            ['title' => '冰岛', 'english' => 'Iceland', 'code' => 354],
            ['title' => '几内亚', 'english' => 'Guinea', 'code' => 224],
            ['title' => '几内亚比绍', 'english' => 'Guinea - Bissau', 'code' => 245],
            ['title' => '列支敦士登', 'english' => 'Liechtenstein', 'code' => 423],
            ['title' => '刚果共和国', 'english' => 'The Republic of Congo', 'code' => 242],
            ['title' => '刚果民主共和国', 'english' => 'Democratic Republic of the Congo', 'code' => 243],
            ['title' => '利比亚', 'english' => 'Libya', 'code' => 218],
            ['title' => '利比里亚', 'english' => 'Liberia', 'code' => 231],
            ['title' => '加拿大', 'english' => 'Canada', 'code' => 1],
            ['title' => '加纳', 'english' => 'Ghana', 'code' => 233],
            ['title' => '加蓬', 'english' => 'Gabon', 'code' => 241],
            ['title' => '匈牙利', 'english' => 'Hungary', 'code' => 36],
            ['title' => '南非', 'english' => 'South Africa', 'code' => 27],
            ['title' => '博茨瓦纳', 'english' => 'Botswana', 'code' => 267],
            ['title' => '卡塔尔', 'english' => 'Qatar', 'code' => 974],
            ['title' => '卢旺达', 'english' => 'Rwanda', 'code' => 250],
            ['title' => '卢森堡', 'english' => 'Luxembourg', 'code' => 352],
            ['title' => '印尼', 'english' => 'Indonesia', 'code' => 62],
            ['title' => '印度', 'english' => 'India', 'code' => 91918919],
            ['title' => '危地马拉', 'english' => 'Guatemala', 'code' => 502],
            ['title' => '厄瓜多尔', 'english' => 'Ecuador', 'code' => 593],
            ['title' => '厄立特里亚', 'english' => 'Eritrea', 'code' => 291],
            ['title' => '叙利亚', 'english' => 'Syria', 'code' => 963],
            ['title' => '古巴', 'english' => 'Cuba', 'code' => 53],
            ['title' => '吉尔吉斯斯坦', 'english' => 'Kyrgyzstan', 'code' => 996],
            ['title' => '吉布提', 'english' => 'Djibouti', 'code' => 253],
            ['title' => '哥伦比亚', 'english' => 'Colombia', 'code' => 57],
            ['title' => '哥斯达黎加', 'english' => 'Costa Rica', 'code' => 506],
            ['title' => '喀麦隆', 'english' => 'Cameroon', 'code' => 237],
            ['title' => '图瓦卢', 'english' => 'Tuvalu', 'code' => 688],
            ['title' => '土库曼斯坦', 'english' => 'Turkmenistan', 'code' => 993],
            ['title' => '土耳其', 'english' => 'Turkey', 'code' => 90],
            ['title' => '圣卢西亚', 'english' => 'Saint Lucia', 'code' => 1758],
            ['title' => '圣基茨和尼维斯', 'english' => 'Saint Kitts and Nevis', 'code' => 1869],
            ['title' => '圣多美和普林西比', 'english' => 'Sao Tome and Principe', 'code' => 239],
            ['title' => '圣文森特和格林纳丁斯', 'english' => 'Saint Vincent and the Grenadines', 'code' => 1784],
            ['title' => '圣皮埃尔和密克隆群岛', 'english' => 'Saint Pierre and Miquelon', 'code' => 508],
            ['title' => '圣赫勒拿岛', 'english' => 'Saint Helena', 'code' => 290],
            ['title' => '圣马力诺', 'english' => 'San Marino', 'code' => 378],
            ['title' => '圭亚那', 'english' => 'Guyana', 'code' => 592],
            ['title' => '坦桑尼亚', 'english' => 'Tanzania', 'code' => 255],
            ['title' => '埃及', 'english' => 'Egypt', 'code' => 20],
            ['title' => '埃塞俄比亚', 'english' => 'Ethiopia', 'code' => 251],
            ['title' => '基里巴斯', 'english' => 'Kiribati', 'code' => 686],
            ['title' => '塔吉克斯坦', 'english' => 'Tajikistan', 'code' => 992],
            ['title' => '塞内加尔', 'english' => 'Senegal', 'code' => 221],
            ['title' => '塞尔维亚', 'english' => 'Serbia and Montenegro', 'code' => 381],
            ['title' => '塞拉利昂', 'english' => 'Sierra Leone', 'code' => 232],
            ['title' => '塞浦路斯', 'english' => 'Cyprus', 'code' => 357],
            ['title' => '塞舌尔', 'english' => 'Seychelles', 'code' => 248],
            ['title' => '墨西哥', 'english' => 'Mexico', 'code' => 52],
            ['title' => '多哥', 'english' => 'Togo', 'code' => 228],
            ['title' => '多米尼克', 'english' => 'Dominica', 'code' => 1767],
            ['title' => '奥地利', 'english' => 'Austria', 'code' => 43],
            ['title' => '委内瑞拉', 'english' => 'Venezuela', 'code' => 58],
            ['title' => '孟加拉', 'english' => 'Bangladesh', 'code' => 880],
            ['title' => '安哥拉', 'english' => 'Angola', 'code' => 244],
            ['title' => '安圭拉岛', 'english' => 'Anguilla', 'code' => 1264],
            ['title' => '安道尔', 'english' => 'Andorra', 'code' => 376],
            ['title' => '密克罗尼西亚', 'english' => 'Federated States of Micronesia', 'code' => 691],
            ['title' => '尼加拉瓜', 'english' => 'Nicaragua', 'code' => 505],
            ['title' => '尼日利亚', 'english' => 'Nigeria', 'code' => 234],
            ['title' => '尼日尔', 'english' => 'Niger', 'code' => 227],
            ['title' => '尼泊尔', 'english' => 'Nepal', 'code' => 977],
            ['title' => '巴勒斯坦', 'english' => 'Palestine', 'code' => 970],
            ['title' => '巴哈马', 'english' => 'The Bahamas', 'code' => 1242],
            ['title' => '巴基斯坦', 'english' => 'Pakistan', 'code' => 92],
            ['title' => '巴巴多斯', 'english' => 'Barbados', 'code' => 1246],
            ['title' => '巴布亚新几内亚', 'english' => 'Papua New Guinea', 'code' => 675],
            ['title' => '巴拉圭', 'english' => 'Paraguay', 'code' => 595],
            ['title' => '巴拿马', 'english' => 'Panama', 'code' => 507],
            ['title' => '巴林', 'english' => 'Bahrain', 'code' => 973],
            ['title' => '巴西', 'english' => 'Brazil', 'code' => 55],
            ['title' => '布基纳法索', 'english' => ' Burkina Faso', 'code' => 226],
            ['title' => '布隆迪', 'english' => 'Burundi', 'code' => 257],
            ['title' => '希腊', 'english' => ' Greece', 'code' => 30],
            ['title' => '帕劳', 'english' => 'Palau', 'code' => 680],
            ['title' => '库克群岛', 'english' => ' Cook Islands', 'code' => 682],
            ['title' => '开曼群岛', 'english' => 'Cayman Islands', 'code' => 1345],
            ['title' => '德国', 'english' => ' Germany', 'code' => 49],
            ['title' => '意大利', 'english' => 'Italy', 'code' => 39],
            ['title' => '所罗门群岛', 'english' => ' Solomon Islands', 'code' => 677],
            ['title' => '托克劳', 'english' => 'Tokelau', 'code' => 690],
            ['title' => '拉脱维亚', 'english' => 'Latvia', 'code' => 371],
            ['title' => '挪威', 'english' => 'Norway', 'code' => 47],
            ['title' => '捷克共和国', 'english' => 'Czech Republic', 'code' => 420],
            ['title' => '摩尔多瓦', 'english' => 'Moldova', 'code' => 373],
            ['title' => '摩洛哥', 'english' => 'Morocco', 'code' => 212],
            ['title' => '摩纳哥', 'english' => 'Monaco', 'code' => 377],
            ['title' => '文莱', 'english' => 'Brunei Darussalam', 'code' => 673],
            ['title' => '斐济', 'english' => 'Fiji', 'code' => 679],
            ['title' => '斯威士兰王国', 'english' => 'The Kingdom of Swaziland', 'code' => 268],
            ['title' => '斯洛伐克', 'english' => 'Slovakia', 'code' => 421],
            ['title' => '斯洛文尼亚', 'english' => 'Slovenia', 'code' => 386],
            ['title' => '斯里兰卡', 'english' => 'Sri Lanka', 'code' => 94],
            ['title' => '新加坡', 'english' => 'Singapore ', 'code' => 65],
            ['title' => '新喀里多尼亚', 'english' => 'New Caledonia', 'code' => 687],
            ['title' => '新西兰', 'english' => 'New Zealand', 'code' => 64],
            ['title' => '日本', 'english' => 'Japan', 'code' => 81],
            ['title' => '智利', 'english' => 'Chile', 'code' => 56],
            ['title' => '朝鲜', 'english' => 'Korea, North', 'code' => 850],
            ['title' => '柬埔寨 ', 'english' => 'Cambodia', 'code' => 855],
            ['title' => '格林纳达', 'english' => 'Grenada', 'code' => 1473],
            ['title' => '格陵兰', 'english' => 'Greenland', 'code' => 299],
            ['title' => '格鲁吉亚', 'english' => 'Georgia', 'code' => 995],
            ['title' => '比利时', 'english' => 'Belgium', 'code' => 32],
            ['title' => '毛里塔尼亚', 'english' => 'Mauritania', 'code' => 222],
            ['title' => '毛里求斯', 'english' => 'Mauritius', 'code' => 230],
            ['title' => '汤加', 'english' => 'Tonga', 'code' => 676],
            ['title' => '沙特阿拉伯', 'english' => 'Saudi Arabia', 'code' => 966],
            ['title' => '法国', 'english' => 'France', 'code' => 33],
            ['title' => '法属圭亚那', 'english' => 'French Guiana', 'code' => 594],
            ['title' => '法属波利尼西亚', 'english' => 'French Polynesia', 'code' => 689],
            ['title' => '法属西印度群岛', 'english' => 'french west indies', 'code' => 596],
            ['title' => '法罗群岛', 'english' => 'Faroe Islands', 'code' => 298],
            ['title' => '波兰', 'english' => 'Poland', 'code' => 48],
            ['title' => '波多黎各', 'english' => 'The Commonwealth of Puerto Rico', 'code' => 17871939],
            ['title' => '波黑', 'english' => 'Bosnia and Herzegovina ', 'code' => 387],
            ['title' => '泰国', 'english' => 'Thailand', 'code' => 66],
            ['title' => '津巴布韦', 'english' => 'Zimbabwe', 'code' => 263],
            ['title' => '洪都拉斯', 'english' => 'Honduras', 'code' => 504],
            ['title' => '海地', 'english' => 'Haiti', 'code' => 509],
            ['title' => '澳大利亚', 'english' => 'Australia', 'code' => 61],
            ['title' => '澳门', 'english' => 'Macao', 'code' => 853],
            ['title' => '爱尔兰', 'english' => 'Ireland', 'code' => 353],
            ['title' => '爱沙尼亚', 'english' => 'Estonia', 'code' => 372],
            ['title' => '牙买加 ', 'english' => 'Jamaica', 'code' => 1876],
            ['title' => '特克斯和凯科斯群岛', 'english' => 'Turks and Caicos Islands', 'code' => 1649],
            ['title' => '特立尼达和多巴哥', 'english' => 'Trinidad and Tobago', 'code' => 1868],
            ['title' => '玻利维亚', 'english' => 'Bolivia', 'code' => 591],
            ['title' => '瑙鲁', 'english' => 'Nauru', 'code' => 674],
            ['title' => '瑞典', 'english' => 'Sweden', 'code' => 46],
            ['title' => '瑞士', 'english' => 'Switzerland', 'code' => 41],
            ['title' => '瓜德罗普', 'english' => 'Guadeloupe', 'code' => 590],
            ['title' => '瓦利斯和富图纳群岛', 'english' => 'Wallis et Futuna', 'code' => 681],
            ['title' => '瓦努阿图', 'english' => 'Vanuatu', 'code' => 678],
            ['title' => '留尼汪 ', 'english' => 'Reunion', 'code' => 262],
            ['title' => '白俄罗斯', 'english' => 'Belarus', 'code' => 375],
            ['title' => '百慕大', 'english' => 'Bermuda', 'code' => 1441],
            ['title' => '直布罗陀', 'english' => 'Gibraltar', 'code' => 350],
            ['title' => '福克兰群岛', 'english' => 'Falkland', 'code' => 500],
            ['title' => '科威特', 'english' => 'Kuwait', 'code' => 965],
            ['title' => '科摩罗和马约特', 'english' => 'Comoros', 'code' => 269],
            ['title' => '科特迪瓦', 'english' => 'Cote d’Ivoire', 'code' => 225],
            ['title' => '秘鲁', 'english' => 'Peru', 'code' => 51],
            ['title' => '突尼斯', 'english' => 'Tunisia', 'code' => 216],
            ['title' => '立陶宛', 'english' => 'Lithuania', 'code' => 370],
            ['title' => '索马里', 'english' => 'Somalia', 'code' => 252],
            ['title' => '约旦', 'english' => 'Jordan', 'code' => 962],
            ['title' => '纳米比亚', 'english' => 'Namibia', 'code' => 264],
            ['title' => '纽埃岛', 'english' => 'Island of Niue', 'code' => 683],
            ['title' => '缅甸  ', 'english' => 'Burma', 'code' => 95],
            ['title' => '罗马尼亚', 'english' => 'Romania', 'code' => 40],
            ['title' => '美国', 'english' => 'United States of America', 'code' => 1],
            ['title' => '美属维京群岛', 'english' => 'Virgin Islands', 'code' => 1340],
            ['title' => '美属萨摩亚', 'english' => 'American Samoa', 'code' => 1684],
            ['title' => '老挝', 'english' => 'Laos', 'code' => 856],
            ['title' => '肯尼亚', 'english' => 'Kenya', 'code' => 254],
            ['title' => '芬兰', 'english' => 'Finland', 'code' => 358],
            ['title' => '苏丹', 'english' => 'Sudan', 'code' => 249],
            ['title' => '苏里南', 'english' => 'Suriname', 'code' => 597],
            ['title' => '英国', 'english' => 'United Kingdom', 'code' => 44],
            ['title' => '英属维京群岛', 'english' => 'British Virgin Islands', 'code' => 1284],
            ['title' => '荷兰', 'english' => 'Netherlands', 'code' => 31],
            ['title' => '荷属安的列斯', 'english' => 'Netherlands Antilles', 'code' => 599],
            ['title' => '莫桑比克', 'english' => 'Mozambique', 'code' => 258],
            ['title' => '莱索托', 'english' => 'Lesotho', 'code' => 266],
            ['title' => '菲律宾', 'english' => 'Philippines', 'code' => 63],
            ['title' => '萨尔瓦多', 'english' => 'El Salvador', 'code' => 503],
            ['title' => '萨摩亚', 'english' => 'Samoa', 'code' => 685],
            ['title' => '葡萄牙', 'english' => 'Portugal', 'code' => 351],
            ['title' => '蒙古', 'english' => 'Mongolia', 'code' => 976],
            ['title' => '西班牙', 'english' => 'Spain', 'code' => 34],
            ['title' => '贝宁', 'english' => 'Benin', 'code' => 229],
            ['title' => '赞比亚', 'english' => 'Zambia', 'code' => 260],
            ['title' => '赤道几内亚', 'english' => 'Equatorial Guinea', 'code' => 240],
            ['title' => '越南', 'english' => 'Vietnam', 'code' => 84],
            ['title' => '阿塞拜疆', 'english' => 'Azerbaijan', 'code' => 994],
            ['title' => '阿富汗', 'english' => 'Afghanistan', 'code' => 93],
            ['title' => '阿尔及利亚', 'english' => 'Algeria', 'code' => 213],
            ['title' => '阿尔巴尼亚', 'english' => 'Albania', 'code' => 355],
            ['title' => '阿拉伯联合酋长国', 'english' => 'United Arab Emirates', 'code' => 971],
            ['title' => '阿曼', 'english' => 'Oman', 'code' => 968],
            ['title' => '阿根廷', 'english' => 'Argentina', 'code' => 54],
            ['title' => '阿鲁巴', 'english' => 'Aruba', 'code' => 297],
            ['title' => '韩国', 'english' => 'Korea, South)', 'code' => 82],
            ['title' => '香港', 'english' => 'Hong Kong(SAR)', 'code' => 852],
            ['title' => '马其顿', 'english' => 'Macedonia', 'code' => 389],
            ['title' => '马尔代夫', 'english' => 'Maldives  ', 'code' => 960],
            ['title' => '马拉维', 'english' => ' Malawi', 'code' => 265],
            ['title' => '马来西亚', 'english' => 'Malaysia', 'code' => 60],
            ['title' => '马绍尔群岛', 'english' => 'Marshall Islands', 'code' => 692],
            ['title' => '马耳他', 'english' => 'Malta', 'code' => 356],
            ['title' => '马达加斯加', 'english' => 'Madagascar', 'code' => 261],
            ['title' => '马里', 'english' => 'Mali', 'code' => 223],
            ['title' => '黎巴嫩', 'english' => 'Lebanon', 'code' => 961],
            ['title' => '黑山共和国', 'english' => 'The Republic of Montenegro', 'code' => 382],
        ];
    }

}