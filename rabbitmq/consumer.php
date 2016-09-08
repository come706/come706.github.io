<?php
/**
 * queue => [
 *   auto 是否監控此queue(自動啟閉)
 *   comsumer  處理訊息的功能 [class, function]
 *   max_listen_nums 最多存在的consumer數量
 *   min_listen_nums 最少存在的consumer數量
 *   msg_load_nums   每個consumer的負載數(用以計算必須有多少個consumer)
 * ]
 */
$config = [
    'send1' => [
        'auto' => TRUE,
        'comsumer' => ['test', 's1'],
        'max_listen_nums' => 5,
        'min_listen_nums' => 0,
        'msg_load_nums'   => 50
    ],
    'send2' => [
        'auto' => FALSE,
        'comsumer' => ['test', 's2'],
        'max_listen_nums' => 5,
        'min_listen_nums' => 0,
        'msg_load_nums'   => 50
    ],
    'send3' => [
        'auto' => TRUE,
        'comsumer' => ['test', 's3'],
        'max_listen_nums' => 5,
        'min_listen_nums' => 0,
        'msg_load_nums'   => 50
    ],
];