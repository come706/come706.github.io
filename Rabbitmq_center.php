<?php
ini_set('display_errors', 'On');
error_reporting(E_ALL ^ E_NOTICE);

class Rabbitmq_center
{
    public function consumer_control()
    {
        $def_min_listen = 0;
        $def_max_listen = 3;
        $def_msg_load   = 50;
        $conn           = [];
        
        $mq = new Rabbitmq_library($conn);
        if (FALSE == $mq) {
            die();
        }
        
        $cofig_producer = 'rabbitmq/consumer.php';
        if (!file_exists($cofig_producer)) {
            die('config not exists');
        }
        
        foreach ($cofig_producer as $queue => $_config) {
            if (TRUE != $_config['auto']) {
                continue;
            }
            
            $queue_fullname = $mq->get_prefix() . $queue;
            
            $info = $mq->get_queue_info($queue_fullname);
            if (FALSE == $info) {
                var_dump($mq->get_error());
                die();
            }
            
            $info          = array_shift($info);
            $msg_load_nums = (!is_numeric($_config['msg_load_nums']) || 1 > $_config['msg_load_nums'])? $def_msg_load : $_config['msg_load_nums'];
            $min_listen    = (!is_numeric($_config['min_listen_nums']) || 0 > $_config['min_listen_nums'])? $def_min_listen : $_config['min_listen_nums'];
            $max_listen    = (!is_numeric($_config['max_listen_nums']) || 0 > $_config['max_listen_nums'])? $def_max_listen : $_config['max_listen_nums'];
            
            $need_listen = ceil($info['msg_nums'] / $msg_load_nums);
            if ($need_listen > $max_listen) {
                $need_listen = $max_listen;
            }
            elseif ($need_listen < $min_listen) {
                 $min_listen = $min_listen;
            }
            
            if ($need_listen > $info['listen_nums']) {
                $this->add_listen($queue, $need_listen - $info['listen_nums']);
            }
            elseif ($need_listen < $info['listen_nums']) {
                for($i = 0; $info['listen_nums'] - $need_listen; $i ++) {
                    $mq->quit_consumer($queue_fullname);
                }
            }
            
        }
    }
    
    public function add_listen($queue = '')
    {
        // exec listen
    }
}