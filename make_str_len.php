<?php

function make_str_len($str = '', $max_len = 1, $code = 'Big5', $pad_str = ' ', $pad_type = STR_PAD_LEFT) {
  $len = strlen($str);
  
  if ($max_len >= $len) {
    return str_pad($str, $max_len, $pad_str, $pad_type);
  }
  
  $mb_len = mb_strlen($str);
  if ($len == $mb_len) {
    return substr($str, 0 - $max_len);
  }
  
  $arr     = utf8_str_split($str);
  $new_len = 0;
  $new_str = '';
  while ($str = array_pop($arr)) {
    $str = mb_convert_encoding($str, $code);
    
    $new_len += strlen($str);
    if ($max_len < $new_len) {
      break;
    }
    
    $new_str = $str . $tmp;
  }
  
  return str_pad($new_str, $max_len, $pad_str, $pad_type);
}

/**
* @from Joomla
* @version $Id: str_split.php 10381 2008-06-01 03:35:53Z pasamio $
* @package utf8
* @subpackage strings
*/
function utf8_str_split($str, $split_len = 1)
{
    if (!preg_match('/^[0-9]+$/', $split_len) || $split_len < 1)
        return FALSE;
 
    $len = mb_strlen($str, 'UTF-8');
    if ($len <= $split_len)
        return array($str);
 
    preg_match_all('/.{'.$split_len.'}|[^\x00]{1,'.$split_len.'}$/us', $str, $ar);
 
    return $ar[0];
}
