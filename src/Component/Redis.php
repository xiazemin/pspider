<?php
/*************************************************************
 * 该类正常执行需reids 版本(Redis Version >= 2.2.3)
 * 该类支持多IDC redis读写操作；程序默认配置的第一组redis服务器地址为本地IDC机房的redis(即本程序运行所在机房)
 * 该类涉及日志记录，所以使用本类时需确保程序入口已初始化公共日志类Logger::init()
 * 
 * @Config
 * $servers = array( 
     array(
         'master' => '127.0.0.1',
         'slave' => '127.0.0.1',
         'db' => 0,
         'pwd' => '',
         'port' => 6379,
         'timeout' =>1,
     ),
     array(
         ...
     ), 
     ...
   );
 * @Usage
 * $redisObj = RedisClient::getInstance($servers);
 * $redisObj->setex($key, $expire, $value); 
 * $redisObj->get($key);
 * $redisObj->delete($key);
 * $redisObj->incr($key);
 * 更多方法调用请参照redis官方提供的方法列表按说明使用
 * 若调用的方法涉及数据变动即写方法，请务必将方法名添加至_redisWFuncs配置的数组列表中，以便读写分离
 * 当设置多机房模式时，写操作的执行结果以本地机房redis执行结果为准，出错的机房仅记录日志作监控
 * **********************************************************/

namespace Spider\Component;

class Redis
{
    static $_instance = array();
    private $_options = array('multi_idc' => false, 'auth' => true);
     
    private $_localRedisOp;
    private $_allRedisOp = array();
    private $_redisWFuncs = array(
        'set','setex','setnx','delete','mset','hset', 'incr', 'incrBy', 'decr','decrBy'
    );
   
    public function __construct($servers=array())
    {
        $pos = 0;
        
        foreach($servers as $server)
        {
            $this -> _allRedisOp[$pos]['host'] = $server;
            $this -> _allRedisOp[$pos]['rRedis'] = new \Redis();
            $this -> _allRedisOp[$pos]['wRedis'] = new \Redis();
            $pos ++;
        }
        
        $this -> _localRedisOp = $this -> _allRedisOp[0];//默认第一组配置为本地IDC机房的redis
    }
    
    public static function getInstance($servers = array())
    {
        $serverKey = md5(serialize($servers));
        if(!isset(self::$_instance[$serverKey]))
        {
            self::$_instance[$serverKey] = new self($servers);
        }
        return self::$_instance[$serverKey];
    }

    public function setOptions($options)
    {
        $this->_options = array_merge($this->_options, $options);
    }
    
    private function _connectLocalIDC($opcode)
    {
        $db = $this->_localRedisOp['host']['db'];
        $pwd = $this->_localRedisOp['host']['pwd'];
        $port = $this->_localRedisOp['host']['port'];
        $timeout = $this->_localRedisOp['host']['timeout'];

        if($opcode == 'master')
        {
            if(!$this->_localRedisOp['wRedis']->IsConnected())
            {
                $ip = $this->_localRedisOp['host']['master'];
                $this->_localRedisOp['wRedis'] -> pconnect($ip, $port, $timeout);

                if($this->_options['auth'] && !$this->_localRedisOp['wRedis']->auth($pwd))
                {                        
                    #Logger::error("redis auth failed ip:$ip, port:$port, pwd:$pwd", 'redis/redis');
                }
                $this->_localRedisOp['wRedis'] -> select($db);
            }
        } 
        else {
            if(!$this->_localRedisOp['rRedis']->IsConnected())
            {
                $ip = $this->_localRedisOp['host']['slave'];
                $this->_localRedisOp['rRedis'] -> pconnect($ip, $port, $timeout);

                if($this->_options['auth'] && !$this->_localRedisOp['rRedis']->auth($pwd))
                {
                    #Logger::error("redis auth failed ip:$ip, port:$port, pwd:$pwd", 'redis/redis');
                }

                $this->_localRedisOp['rRedis'] -> select($db);
            }
        }
    }

    /**
     * 连接远程IDC配置的redis
     *
     * @param string $opcode 是否连接主redis的标识
     */
    private function _connectRemoteIDC($opcode)
    {
        if($opcode == 'master')
        {
            foreach($this -> _allRedisOp as &$redis)
            {
                if(!$redis['wRedis']->IsConnected())
                {
                    $ip   = $redis['host']['master'];
                    $port = $redis['host']['port'];
                    $db  = $redis['host']['db'];
                    $pwd = $redis['host']['pwd'];
                    $timeout = $redis['host']['timeout'];
                    
                    $redis['wRedis'] -> pconnect($ip, $port, $timeout);

                    if($this->_options['auth'] && !$redis['wRedis']->auth($pwd))
                    {
                        #Logger::error("redis auth failed ip:$ip, port:$port, pwd:$pwd", 'redis/redis');
                    }
                    $redis['wRedis'] -> select($db);
                }
            }
        } 
        else 
        {
            foreach($this -> _allRedisOp as &$redis)
            {
                if(!$redis['rRedis']->IsConnected())
                {
                    $ip  = $redis['host']['slave'];
                    $port= $redis['host']['port'];
                    $db  = $redis['host']['db'];
                    $pwd = $redis['host']['pwd'];
                    $timeout = $redis['host']['timeout'];
                    
                    $redis['rRedis'] -> pconnect($ip, $port, $timeout);
                    
                    if($this->_options['auth'] && !$redis['rRedis']->auth($pwd))
                    {
                        #Logger::error("redis auth failed ip:$ip, port:$port, pwd:$pwd", 'redis/redis');
                    }
                    
                    $redis['rRedis'] -> select($db);
                }
            }
        }
    }

    public function __call($method, $args)
    {
    	$errLog = array('method' => $method, 'args' => $args);
        #Logger::access($errLog, 'redis/redis');
        $logErrServers = array();
        $isWrite = in_array($method, $this->_redisWFuncs);

        if($this->_options['multi_idc'] && $isWrite)
        {
            $this->_connectRemoteIDC('master');
            $index = 0;

            foreach($this->_allRedisOp as $redis)
            {
                $result = call_user_func_array(array($redis['wRedis'],$method),$args);

                if(!$result) //记录执行失败的server
                {
                    $logErrServers[] = $redis['host']['master'];
                }

                if($index==0) //多机房模式的写操作，以第一组redis执行结果返回
                {
                    $ret = $result;
                }
                $index++;
            }
        } 
        else 
        {
            $isWrite ? $this->_connectLocalIDC('master') : $this->_connectLocalIDC('slave');
            $isWrite ? $redis = $this->_localRedisOp['wRedis'] : $redis = $this->_localRedisOp['rRedis'];
            $ret = call_user_func_array(array($redis, $method), $args);

            if($isWrite && !$ret)
            {
                $logErrServers[] = $this->_localRedisOp['host']['master'];
            }
        }

        if($isWrite && ($ret === false))
        {
            $errLog['failsevers'] = $logErrServers;
            #Logger::error($errLog, 'redis/redis');
        }
        
        return $ret;
    }
}