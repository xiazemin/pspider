<?php
    namespace Spider;
    
    use GuzzleHttp;
    
    use Spider\Proxy\Proxy;
    use Spider\Multiprocess\Process;
    use Spider\Container\Collection;
    use Spider\Container\LineList;
    use Spider\Parsers\HtmlParser;
    
    use Spider\Utils\Format;
    use Spider\Utils\Hook;
    use Spider\Utils\UserAgent;
    
    use Spider\Exception\SpiderException;

    class Spider 
    {
        public $name;
        public $max = 0;
        public $interval = 5;
        public $tick;
        public $logfile = 'exception.log';
        
        protected $_content;
        protected $_downloading;
        
        protected $_config;
        protected $_seeds = [];
        protected $_options = [
            'proxy'=>false,
            'timeout'=>8,
            'headers'=>['User-Agent'=>'pc']
        ];
        protected $_partterns = [];
        
        protected $_crawlCollection;
        protected $_discoverList;
        protected $_process;
        protected $_proxy;
        protected $_params = [];
        
        public function getWorkerId()
        {
            $id = 0;
            
            if(is_object($this->_process))
            {
                $id = $this->_process->getPid();
            }
            
            return $id;
        }
                
        public function getParams()
        {
            return $this->_params;
        }
        
        public function getContent()
        {
            return $this->_content;
        }
        
        public function loadConfig()
        {
            return include 'Config.php';
        }
        
        public function initDiscover()
        {   
            $this->_discoverList = new LineList('SpiderDiscvoer','redis',$this->_config['redis']);
            $this->_crawlCollection = new Collection('SpiderCrawl','redis',$this->_config['redis']);
            
            $this->_discoverList->clean();
            
            foreach($this->_seeds as $seed)
            {
                $this->_discoverList->add($seed);
                $this->_crawlCollection->delete($seed);
            }
        }
                
        public function __construct($seeds,$options=[],$patterns=[]) 
        {          
            set_exception_handler([$this,'exceptionHandler']);

            foreach ((array)$seeds as $seed) 
            {
                if(is_string($seed))
                {
                    $seed = ['method'=>'get','url'=>$seed,'options'=>[]];
                }

                $this->_seeds[] = json_encode($seed);                
            }
            
            $this->_options = array_merge($this->_options,$options);
            $this->_partterns = (array) $patterns;
            $this->_config = $this->loadConfig();
            
            Hook::register('onStart',[$this,'initDiscover']);
        }
        
        public function exec($workers=0)
        {
            if($workers>0)
            {
                $this->_process = new Process('Spider');
                $this->_process->run(function(){
                    $this->run();
                },$workers);
            }
            else{
                Hook::invoke('onStart');
                $this->run();
                Hook::invoke('onStop');
            }
        }
        
        public function run()
        {   
            if(empty($this->_proxy))
            {
                $this->_proxy = new Proxy();
            }
            
            while($this->_discoverList->len()>0)
            {
                try {
                    Hook::invoke('beforeCrawl');
                    $this->crawl();
                    Hook::invoke('afterCrawl');
                    $this->discover();
                    Hook::invoke('afterDiscover');
                }
                catch(\Exception $e){
                    $this->exceptionHandler($e);
                }
                
                sleep($this->interval);
            }
        }
                                                                                                                                         
        public function crawl()
        {
            if ($this->max > 0 && $this->_crawlCollection->count() >= $this->max) 
            {
                throw new SpiderException("[PID:{$this->getWorkerId()}] The crawl set more than max");
                exit(1);
            }

            do
            { 
                if($this->_discoverList->len()==0)
                {
                    throw new SpiderException("[PID:{$this->getWorkerId()}] The discover list is empty");
                }               
                
                $this->_downloading = $this->_discoverList->next();
                
                $this->_downloading = json_decode($this->_downloading,true);
                
            }while($this->_crawlCollection->isMember(json_encode($this->_downloading))|| empty($this->_downloading['url']));
    
            $url = $this->_downloading['url'];
            $method = !empty($this->_downloading['method']) ? $this->_downloading['method'] : 'GET';
            $options = is_array($this->_downloading['options'])?array_merge($this->_options,$this->_downloading['options']):$this->_options;
            
            if (isset($options['headers']['User-Agent']) && $options['headers']['User-Agent']) 
            {
                $options['headers']['User-Agent'] = UserAgent::rand($options['headers']['User-Agent']);
            }
            
            if(isset($options['proxy']) && $options['proxy']===true)
            {
                $https = strpos($this->_downloading['url'],'https')?true:false;
                $options['proxy'] = $this->_proxy->get($https);
            }
            
            $this->_params = ['method'=>$method,'url'=>$url,'options'=>$options];

            $client = new GuzzleHttp\Client();
            $this->_content = $client->request($method,$url,$options)->getBody()->getContents();             
            $this->_crawlCollection->add(json_encode($this->_downloading));
        }
        
        public function discover()
        {
            $urls = HtmlParser::load($this->_content)->findText('//a/@href');
            $urls = Format::url($urls,$this->_downloading['url']);
            $urls = array_unique($urls);
            
            $method = isset($this->_downloading['method']) ? $this->_downloading['method']:'';
            $options = isset($this->_downloading['options'])? $this->_downloading['options']:[];

            foreach ($urls as $url) 
            {
                $seed = json_encode(['url'=>$url,'method'=>$method,'options'=>$options]);
                
                if($this->_crawlCollection->isMember($seed)) 
                {
                    continue;
                }
                
                if (!empty($this->_partterns)) 
                {
                    foreach ($this->_partterns as $pattern) 
                    {
                        if (preg_match($pattern,$url)) 
                        {
                            $this->_discoverList->add($seed);
                        }
                    }
                }
                else{
                    $this->_discoverList->add($seed);
                }
            }
        }
                                                                                       
        public function exceptionHandler($e)
        {                        
            if($e instanceof \RedisException)
            {
                $this->log("[Exception:Redis]\t[MSG:{$e->getMessage()}]");
            }
            else if($e instanceof SpiderException)
            {
                $this->log("[Exception:Spider]\t[MSG:{$e->getMessage()}]");
            }
            else if($e instanceof GuzzleHttp\Exception\ConnectException)
            {
                $this->log("[Exception:Connect]\t[MSG:{$e->getMessage()}]\t[URL:{$this->_params['url']}]\t[PROXY:{$this->_params['options']['proxy']}]");
                $this->_discoverList->add(json_encode($this->_downloading));
            }
            else if($e instanceof GuzzleHttp\Exception\ClientException)
            {
                if ($e->getResponse()->getStatusCode()!=404) 
                {
                    $this->log("[Exception:Client]\t[MSG:{$e->getMessage()}]\t[URL:{$this->_params['url']}]\t[PROXY:{$this->_params['options']['proxy']}]");
                    $this->_discoverList->add(json_encode($this->_downloading));
                } 
            }
            else{
                $this->log("[Exception:".get_class($e)."]\t[MSG:{$e->getMessage()}]\t[File:{$e->getFile()} {$e->getLine()}]\t[URL:{$this->_params['url']}]\t[PROXY:{$this->_params['options']['proxy']}]");
                $this->_discoverList->add(json_encode($this->_downloading));
            }
        }
        
        public function log($msg,$logfile='')
        {
            if(empty($logfile))
            {
                $logfile =  __DIR__ .'/'.$this->logfile;
            }
            
            file_put_contents($logfile,date('Y-m-d H:i:s')."\t".$msg."\n",FILE_APPEND);
        }
    }
    
