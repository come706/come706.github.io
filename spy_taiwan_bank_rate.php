<?php

$rate = new taiwan_bank_rate();

// get one date detail
//$rs = $rate->get_rate_change_list(['date' => '20150919']);

// get range date first rate
$rs = $rate->get_first_rate_list_between_date('20150918', '20150921');

var_dump($rs);

/**
 * spy taiwan bank rate
 */
class taiwan_bank_rate
{
    private $not_sell_msg = '很抱歉，本次查詢找不到任何一筆資料！';
    private $url       = 'http://rate.bot.com.tw/Pages/UIP004/UIP00421.aspx';
    private $url_param = array(
        // 語系
        'lang'       => 'zh-TW',
        // 匯率
        'whom1'      => 'USD',
        // 感覺像比較匯率，但是不知道怎麼開啟
        'whom2'      => '',
        //查詢日期
        'date'       => '20150917',
        // int 功能不明
        'entity'     => '1',
        // 好像沒用
        'year'       => '2015',
        // 好像沒用
        'month'      => '09',
        // 好像沒用
        'term'       => '99',
        // 0:開盤, 1:收盤後
        'afterOrNot' => '0',
        // 好像沒用
        'view'       => '1'
    );
    private $field_list = array(
        'DATE'      => '', // 日期(2015/09/17  09:01:12)
        'TYPE'      => '', // 幣別(美金 (USD))
        'CASH_BUY'  => '', // 現金買入
        'CASH_SELL' => '', // 現金賣出
        'SPOT_BUY'  => '', // 即期買入
        'SPOT_SELL' => '' // 即期賣出
    );

    public function __construct($option = array())
    {
        return ($this->set_option($option))? TRUE : FALSE;
    }

    /**
     * set param
     * @param type $option
     * @return boolean
     */
    public function set_option($option = array())
    {
        if (!is_array($option))
        {
            return FALSE;
        }

        foreach ($option as $k => $v)
        {
            if (array_key_exists($k, $this->url_param))
            {
                $this->url_param[$k] = $v;
            }
        }

        return TRUE;
    }

    /**
     * get one day detail
     * @param type $date
     * @return boolean
     */
    public function get_rate_change_list($date = '')
    {
        if ('' != strval($date))
        {
            $this->set_option(['date' => $date]);
        }

        // get table
        $content = $this->curl_post($this->url, $this->url_param);
        $start   = stripos($content, '<table title="歷史匯率"');
        $end     = stripos(substr($content, $start), '</table>');
        $html    = substr($content, $start, $end);
        if ('' == $start
                || '' == $end)
        {
            var_dump("start:{$start}, end:{$end}");
            return FALSE;
        }

        $rs_arr = array();
        preg_match_all("/\<td.*?\>(.*?)\<\/td\>/", $html, $rs_arr);
        if (empty($rs_arr))
        {
            return [];
        }

        $new_rs = array();
        while (!empty($rs_arr[1]))
        {
            $row = array();
            foreach ($this->field_list as $k => $v)
            {
                $row[$k] = htmlspecialchars(array_shift($rs_arr[1]));
            }
            if ($this->not_sell_msg == $row['DATE'])
            {
                $new_rs[] = $this->field_list;
            }
            else
            {
                $new_rs[] = $row;
            }
        }

        return $new_rs;
    }

    /**
     * get first open sell rate of between date
     * @param type $date
     * @return boolean
     */
    public function get_first_rate_list_between_date($start_date = '', $end_date = '')
    {
        if ('' == strval($start_date)
                || '' == strval($end_date))
        {
            return FALSE;
        }

        // check date format
        $format     = 'Ymd';
        $start_date = date($format, strtotime($start_date));
        $end_date   = date($format, strtotime($end_date));
        if (empty($start_date)
                || empty($end_date))
        {
            return FALSE;
        }

        $i = 1;
        $rs = array();
        do
        {
            $date_rs = $this->get_rate_change_list($start_date);
            if (!is_array($date_rs))
            {
                //set log?
                $rs[$start_date] = $this->field_list;
            }
            else
            {
                $rs[$start_date] = array_shift($date_rs);
            }

            $start_date = date($format, strtotime($start_date . ' +1 days'));
        }while ($start_date <= $end_date);

        return $rs;
    }

    /**
     * get curl data
     * @param type $url
     * @param type $data
     * @param type $type
     * @return boolean
     */
    private function curl_post($url = '', $data = array(), $type = 'get')
    {
        if ('' == strval($url))
        {
            return FALSE;
        }

        $postData = '';
        $_referer = $_SERVER['SERVER_NAME'] . $_SERVER['REQUEST_URI'];

        if (is_array($data))
        {
            switch ($type)
            {
                case 'get':
                    $url .= '?' . http_build_query($data, '', '&');
                    break;
                case 'post':
                    $postData = json_encode($data);
                    break;
            }
        }

        // set curl
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_REFERER, $_referer);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $postData);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($ch, CURLOPT_IPRESOLVE, CURL_IPRESOLVE_V4);

        $content = curl_exec($ch);
    //    $curl_info = curl_getinfo($ch);
        curl_close($ch);

        return $content;
    }
}

// End File