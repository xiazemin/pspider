<?php
    namespace PSpider\Proxy;
    
    use GuzzleHttp\Client;
    
    use PSpider\Multiprocess\Process;
    use PSpider\Container\Collection;
    
    Class Proxy
    {
        protected $_config;
        protected $_source;
        protected $_httpPool;
        protected $_httpsPool;
        protected $_client;
        
        protected $_verifyUrl = [
            'http'=>'http://blog.jobbole.com/all-posts/',
            'https'=>'https://tech.meituan.com/'
        ];
        
        public function __construct() 
        {
            $this->_config = include __DIR__.'/../Config.php';
            $this->_initPools();
        }
        
        protected function _initPools()
        {
            $this->_httpPool = new Collection('HttpProxy','redis',$this->_config['redis']);
            $this->_httpsPool = new Collection('HttpsProxy','redis',$this->_config['redis']);
        }
                        
        public function get($https=false)
        {
            do{
                
                $ip = $this->_httpsPool->get(false);
                
                if(!$ip && !$https)
                {
                    $ip = $this->_httpPool->get(false);
                }
                
            }while($ip!==false && !$this->verify($ip,$https));
                  
            return $ip;
        }
        
        public function add($ip,$https=false)
        {
            $pool = $https?$this->_httpsPool:$this->_httpPool;
            
            return $pool->add($ip);
        }
        
        public function del($ip,$https=false)
        {
            $pool = $https?$this->_httpsPool:$this->_httpPool;
            
            return $pool->delete($ip);
        }
        
        public function verify($ip,$https=false)
        {
            if(empty($this->_client))
            {
                $this->_client = new Client();
            }
            
            $verifyUrl = $https?$this->_verifyUrl['https']:$this->_verifyUrl['http'];

            try
            {
                $this->_client->request('get',$verifyUrl,['proxy'=>$ip,'timeout'=>5,'verify'=>false]);
                return true;
            }catch (\Exception $e)
            {
                $this->del($ip,$https);
                return false;
            }
        }
        
        public function registerSourceHandler($source,$func='')
        {
            if(empty($func))
            {
                foreach ((array)$source as $class)
                {
                    $classname = __NAMESPACE__.'\Source\\'.$class;
                    $obj =  new $classname;

                    $this->_source[$class] = [$obj,'handler'];
                }
            }
            else{
                $this->_source[$source] = $func;
            }
        }
        
        public function scanFromSource($source='',$process=false)
        {            
            if(empty($source))
            {
                foreach($this->_source as $source=>$func)
                {
                    if($process)
                    {
                        $jobs [] = function()use($source){
                            $this->_initPools();
                            $this->scanFromSource($source);
                        };
                    }else{
                        $this->scanFromSource($source);
                    }
                }
                
                if($process)
                {
                    $process = new Process($process);
                    $process->run($jobs,1);
                }
            }
            else if(isset($this->_source[$source]))
            {
                file_put_contents('scan.log',date('Y-m-d H:i:s')." [SITE:{$source}]".PHP_EOL,FILE_APPEND);
                
                $ips = call_user_func_array($this->_source[$source],[]);

                if(!empty($ips))
                {
                    foreach($ips as $data)
                    {
                        if($this->verify($data['ip'],$data['https']))
                        {
                            $this->add($data['ip'],$data['https']);
                        }
                    }
                }
            }
            else{
                return false;
            }
        }
                
        public function scanFromIpSection($beginIp,$endIp,$ports=[80,8080])
        { 
            $beginIp = ip2Long($beginIp);
            $endIp = ip2Long($endIp);
            
            for($i = $beginIp;$i<=$endIp;$i++)
            {
                foreach((array)$ports as $port)
                {
                    $ip = long2ip($i).':'.$port;
                    
                    if($this->verify($ip,true))
                    {
                        $this->add($ip,true);
                    }
                    else if($this->verify($ip))
                    {
                        $this->add($ip);
                    }
                }
            }
        }
    }