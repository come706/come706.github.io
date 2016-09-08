<?php
// require composer autoload
require_once 'composer/autoload.php';
use PhpAmqpLib\Connection\AMQPConnection;
use PhpAmqpLib\Message\AMQPMessage;

class Rabbitmq_library
{
    /**
     * DELIVERY_MODE_PERSISTENT 2 確保不丟失
     */
    const DELIVERY_MODE_PERSISTENT = 2;
    /**
     * @queue array 隊列列表(同時對多個Queue)
     * @consumer    自訂監聽者的tag
     * @routing_key  exchange 根據此key發送訊息至同個key的queue(max light 255)
     * @type [
     *   direct  直通
     *   fanout  發布，同時發到所有通道
     *   topic   直通模糊比對，可用特殊字元比對多個有類似名稱的key~ a.b.c( 分隔符號: .)
     *   headers 根據續席包裡面的值 送到目標queue
     * ]
     */
    private $config = [
        'queue'       => [],
        'routing_key' => '',
        'prefix'      => 'prefix_'
    ];
    /**
     * 連線參數
     * @vhost 分割區的概念，不同vhost不互相影響。 
     */
    private $conn_info = [
        'server'  => '',
        'post'    => '',
        'account' => '',
        'pwd'     => '',
        'vhost'   => ''
    ];
    protected $exchange  = 'default_exchange';
    protected $chg_type = 'direct';
    
    private $durable    = FALSE;
    private $passive    = FALSE;
    private $auto_del   = FALSE;
    private $exclusive = FALSE;
    
    public $error_exc  = NULL;
    public $error_code = '';
    public $error_msg = '';
    private $channel = NULL;
    private $connect = NULL;
    private $cofig_producer = 'rabbitmq/producer';
    
    /**
     * 
     * @param type $conn_config
     * @param type $option
     * @return boolean
     */
    public function __construct($conn_config = [], $option = [])
    {
        if (!is_array($conn_config)
                || !is_array($option)) {
            return FALSE;
        }
        
        foreach ($conn_config as $key => $v) {
            $this->conn_info[$key] = $v;
        }
        
        $routing_key = trim($option['routing_key']);
        if ('' != $routing_key) {
            $this->set_config($routing_key);
        }
    }
    
    public function __destruct()
    {
        $this->close();
    }

    /**
     * 
     * @param type $routing_key
     * @return boolean
     */
    public function set_config($routing_key = '')
    {
        if ('' == $routing_key) {
            return $this->set_error('routing key is empty');
        }
        
        $config = $this->load_config($routing_key);
        $prefix = $this->get_prefix();
        $this->config['routing_key'] = $prefix . $routing_key;
        
        if (!is_array($config)
                || empty($config)) {
            return $this->set_error('get config fail');
        }
        
        if (!is_null($this->channel)) {
            $this->close_channel();
        }
        
        $ch = $this->get_channel();
        if (FALSE == $ch) {
            return $this->set_error('get cnannel fail');
        }
        
        $this->channel->exchange_declare($this->exchange, $this->chg_type, $this->passive, $this->durable, $this->auto_del);
        
        foreach ($config['queue'] as $q_name) {
            $q_name = $prefix . trim($q_name);
            if ($prefix == $q_name) {
                return $this->set_error('queue config fail');
            }
            
            $this->config['queue'][] = $q_name;
            
            $ch->queue_declare($q_name, $this->passive, $this->durable, $this->exclusive, $this->auto_del);
            
            $ch->queue_bind($q_name, $this->exchange, $this->config['routing_key']);
        }
        
        return TRUE;
    }
    
    public function send_message($msg = '')
    {
        if (!is_string($msg)) {
            return $this->set_error('massage type only string');
        }
        
        $config = $this->get_config();
        $ch     = $this->get_channel();
        
        if (FALSE === $ch) {
            return $this->set_error('get channel fail');
        }
        
        // set max message size
        // AMQPChannel::SetSizeLimit();
        
        static $msg_pkg = NULL;
        if (is_null($msg)) {
            $msg_pkg = new AMQPMessage('', [
                'content_type'  => 'text/plain',
                'delivery_mode' => self::DELIVERY_MODE_PERSISTENT
            ]);
        }
        
        $msg_pkg->setBody($msg);
        
        $ch->basic_publish($msg_pkg, $this->exchange, $config['routing_key'], TRUE);
        
        $ch->wait_for_pending_acks();
    }

    public function quit_consumer($queue = '') {
        $queue = trim($queue);
        if ('' == $queue) {
            return $this->set_error('queue name is empty');
        }
        
        $ch = $this->get_channel();
        if (FALSE === $ch) {
            return $this->set_error('get channel fail');
        }
        
        $routing_key = $this->get_prefix() . $queue . '_quit';
        
        $ch->queue_bind($queue, $this->exchange, $routing_key);
        
        $msg_pkg = new AMQPMessage('quit', [
            'content_type'  => 'text/plain',
            'delivery_mode' => self::DELIVERY_MODE_PERSISTENT
        ]);
        
        $ch->basic_publish($msg_pkg, $this->exchange, $routing_key, TRUE);
        
        $ch->wait_for_pending_acks();
    }

    private function get_connect()
    {
        if (!is_null($this->connect)) {
            return $this->connect;
        }
        
        try {
            $this->connect = new AMQPConnection(
                    $this->conn_info['server'],
                    $this->conn_info['port'],
                    $this->conn_info['account'],
                    $this->conn_info['pw'],
                    $this->conn_info['vhost']
            );
        } catch (Exception $e) {
            $this->set_error($e->getMessage(), $e->getCode());
            $this->error_exc = $e;
            $this->connect   = FALSE;
        }
        
        return $this->connect;
    }

    public function get_channel()
    {
        if (!is_null($this->channel)) {
            return $this->channel;
        }
        
        $conn = $this->get_connect();
        if (FALSE === $conn) {
            return FALSE;
        }
        
        $channel = $conn->channel();
        if (FALSE === $channel) {
            return $this->set_error('get channel fail', -1);
        }
        
        return $this->channel = $channel;
    }
    
    public function get_queue_info($queue = '')
    {
        $ch = $this->get_channel();
        if (FALSE == $ch) {
            return $this->set_error('get channel fail');
        }
        
        if ('' != $queue) {
            $queue = [$queue];
        }
        else {
            $config = $this->get_config();
            $queue  = $config['queue'];
        }
        
        $rs = [];
        foreach ($queue as $q) {
            list($queue, $msg_nums, $consumer_count) = $ch->queue_declare($q, $this->passive, $this->durable, $this->exclusive, $this->auto_del);
            
            $rs[$q] = [
                'queue'       => $queue,
                'msg_nums'    => $msg_nums,
                'listen_nums' => $consumer_count
            ];
        }
        
        return $rs;
    }

    public function close($type = '')
    {
        switch ($type) {
            case 'channel':
                $this->close_channel();
                break;
            case 'connect':
                $this->close_connect();
                break;
            default:
                $this->close_channel();
                $this->close_connect();
        }
    }

    public function close_channel()
    {
        if (!is_null($this->channel)) {
            @$this->channel->close();
            $this->channel = NULL;
        }
    }
    
    public function close_connect()
    {
        if (!is_null($this->connect)) {
            @$this->connect->close();
            $this->connect = NULL;
        }
    }
    
    private function queue_clear($quere_name = '')
    {
        $ch = $this->get_channel();
        if (FALSE == $ch) {
            return $this->set_error('get channel fail');
        }
        
        $ch->queue_purge($quere_name);
    }

    public function get_prefix()
    {
        return $this->config['prefix'];
    }
    
    public function get_config()
    {
        return $this->config;
    }
    
    private function load_config($routing_key = '')
    {
        $cofig_producer = $this->cofig_producer . '.php';
        if (!file_exists($cofig_producer))
        {
            return FALSE;
        }
        
        require $cofig_producer;
        
        if (is_array($config)
                && array_key_exists($routing_key, $config)) {
            return $config[$routing_key];
        }
        
        return [];
    }
    
    public function get_error()
    {
        return ['error_code' => $this->error_code, 'error_msg' => $this->error_msg];
    }

    private function set_error($msg = '', $code = '')
    {
        array_push($this->error_code, $code);
        array_push($this->error_msg, $msg);
        
        return FALSE;
    }
}