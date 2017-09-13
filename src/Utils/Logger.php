<?php    
   /**
     * @日志通用类
     * 
     * @Usage
     * 在项目程序入口处初始化日志类
     * $logPath = '/data/log/';
     * Logger::init($logPath);//初始化 并设定日志目录
     * Logger::init();//初始化  未指定目录则默认日志根目录为BIZ_LOG_PATH
     * 在需要记录日志的地方调用类的方法记录相应日志
     * $logMsg = array('a'=>123,'b'=>array('c'=>345,'d'=>567));
     * $dirFileName = "trade/net_connet";//目录分隔符用'/'，按'/'分割后得到的末尾字符串为文件名;文件名可由‘数字/英文字母/下划线’组成；
     * Logger::access($logMsg,$dirFileName);//写流水日志
     * Logger::error($logMsg,$dirFileName);//写错误日志
     * Logger::except($logMsg,$dirFileName);//写异常日志
     * 最终生成日志文件路径形如：
     * '/data/log/trade/net_connet_access.20151020.txt' //日期部分按实际生成日期
     * '/data/log/trade/net_connet_error.20151020.txt' //日期部分按实际生成日期
     * '/data/log/trade/net_connet_except.20151020.txt' //日期部分按实际生成日期
     */
    namespace Spider\Utils;

    class Logger
    {
        public    $logPath;	        //日志根目录
        protected $errInFile;           //错误所在文件
        protected $errInLine;           //错误所在行
        protected $intStartTime;        //初始时间
        protected $intLogId;		//日志id,
        protected $logFileTag = "";	//文件名中包含的字符标记
        protected $logFileExt = ".txt";	//文件后缀名
        protected $linePreTag = "";     //日志行前缀（行头字符串）
        protected $lineSufTag = "\n";   //日志行后缀（行尾字符串）
        protected $ignoreItems = array(); //不需要默认记录的日志内容项 如time、ip、uri
        private static $instance = null;  //实例化对象

        /**
         * 构造函数
         *
         * @param float $intStartTime(值：microtime(true)*1000）单位毫秒
         */
        public  function __construct($intStartTime)
        {
            $this->intStartTime = $intStartTime;
            $this->intLogId     = $this->_logId();
        }

        /**
         * @初始化日志类
         * 
         * @param 日志根目录 $logRootPath
         */
        public static function init($logRootPath='')
        {
            if(!defined('PROCESS_START_TIME'))
            {
                define('PROCESS_START_TIME', microtime(true) * 1000);
            }
            
            if(!$logRootPath)
            {
                $logRootPath = BIZ_LOG_PATH;
            }
            
            Logger::getInstance()->logPath = $logRootPath;
        }

        public static function getInstance()
        {
            if( self::$instance === null )
            {
                if(defined('PROCESS_START_TIME'))
                {
                    $intStartTime = PROCESS_START_TIME;
                }
                elseif(isset($_SERVER['REQUEST_TIME']))
                {
                    $intStartTime = $_SERVER['REQUEST_TIME'] * 1000;
                }
                else
                {
                    $intStartTime = microtime(true)*1000;

                }
                self::$instance = new Logger($intStartTime);
            }

            return self::$instance;
        }

        /**
         * 流水日志（业务）
         * 参数说明参见本类write()方法
         */
        public static function access($msg,$name,$dateFormat='d',$ignoreItems=array())
        {
            Logger::getInstance()->logFileTag = '_access';
            return Logger::getInstance()->write($msg, $name,$dateFormat,$ignoreItems);
        }

        /**
         * 错误日志（业务）
         * 参数说明参见本类write()方法
         */
        public static function error($msg, $name,$dateFormat='d',$ignoreItems=array())
        {
            Logger::getInstance()->logFileTag = '_error';
            return Logger::getInstance()->write($msg, $name,$dateFormat,$ignoreItems);
        }

        /**
         * 异常日志（系统、程序、网络）
         * 参数说明参见本类write()方法
         */
        public static function except($msg,$name,$dateFormat='d',$ignoreItems=array())
        {
            Logger::getInstance()->logFileTag = '_except';
            return Logger::getInstance()->write($msg, $name,$dateFormat,$ignoreItems);
        }

        /**
         * 写日志（通用）
         *
         * @param mix $msg (字符串|布尔值|数组|对象)
         * @param string $name 日志路径及文件名
         * @param string $dateFormat 日志文件切分规则 (值为 y:按年切分 m:按月切分 d:按日切分 h:按小时切分 )
         * @param array $ignoreItems 不需要记录的默认日志内容项(值如：array('ip','uri','time'))
         * @return bool
         */
        public function write($msg,$name,$dateFormat='d',$ignoreItems=array())
        {
            try
            {
                if(empty($this->logPath))
                {
                    return false;
                }

                $name = rtrim(trim($name),"_");

                if(strpos($name,"/") !== false) //名称包含目录分隔符需要判断是否需要创建目录
                {
                    $name = ltrim($name,"/");
                    //目录分隔符/在末尾视为非法路径,因为文件名不能为空
                    $lastPos = strrpos($name,"/");
                    
                    if($lastPos == strlen($name)-1)
                    {
                        return false;
                    }

                    $dir = substr($name,0,$lastPos);
                    $targetDir = $this->logPath . $dir;
                    
                    if(!is_dir($targetDir))
                    {
                        @mkdir($targetDir,0777,true);
                    }
                }

                $dateFormatStr = $this->_getDateFormatStr($dateFormat);

                $logFile = $this->logPath.$name.$this->logFileTag.".".$dateFormatStr.$this->logFileExt;

                $trace = debug_backtrace();
                $file = basename($trace[1]['file']);
                $line = $trace[1]['line'];
                
                if($this->errInFile && $this->errInLine)
                {
                    $file = basename($this->errInFile);
                    $line = $this->errInLine;
                }

                $arrLog = array(
                    'time'  => date('Y-m-d H:i:s'),
                    'logId' => $this->intLogId,
                    'line'  => $file . ':' . $line,
                    'ip'    => $this->_getClientIp(),
                    'rip'   => $_SERVER['REMOTE_ADDR'],
                    'uri'   => isset($_SERVER['REQUEST_URI']) ? $_SERVER['REQUEST_URI'] : '',
                    'timeUsed' => intval(microtime(true)*1000 - $this->intStartTime),
                );

                if(is_array($ignoreItems) && is_array($this->ignoreItems))
                {
                    $ignoreItems = array_merge($ignoreItems,$this->ignoreItems);
                }

                if(is_array($ignoreItems))
                {
                    foreach($ignoreItems as $term)
                    {
                        if(isset($arrLog[$term]))
                        {
                            unset($arrLog[$term]);
                        }
                    }
                }

                $strLog = "";	

                foreach($arrLog as $k => $v)
                {
                    $strLog .= $k . '[' . $v . '] ';
                }

                if(is_string($msg))
                {
                    $strLog .= 'msg[' . $msg . ']';
                }
                else if(is_bool($msg))
                {
                    $msg = ($msg) ? 'true' : 'false';
                    $strLog .= 'msg[' . $msg . ']';
                }
                else if(is_array($msg))
                {
                    $strLog .= 'msg[' . $this->_arrToStr($msg) . ']';
                }
                else
                {
                    $strLog .= serialize($msg);
                }
                $strLog = preg_replace('/[\0\f\n\r\t\v]+/','',$strLog);

                $logLine  = $this->linePreTag.$strLog.$this->lineSufTag;
                error_log($logLine, 3, $logFile);
                @chmod($logFile, 0777);
                return true;
            }
            catch(Exception $e){
            }
        }

        /**
         * 获取loginId
         *
         * @return int
         */
        public static function getLogId()
        {
            return Logger::getInstance()->intLogId;
        }

        /**
         * 设置loginId
         */
        public static function setLogId($logId)
        {
           Logger::getInstance()->intLogId = $logId;
        }

        /**
         * 设置错误发生所在的文件名及行号
         */
        public static function setErrFileLine($errInFile='',$errInLine='')
        {
            if($errInFile && $errInLine)
            {
                Logger::getInstance()->errInFile = $errInFile;
                Logger::getInstance()->errInLine = $errInLine;
            }
        }


        /**
        * 设置不需要记录的默认日志内容项
        *
        * @param array $arrItems
        */
        public static function setIgnoreItems($arrItems=array())
        {
            Logger::getInstance()->ignoreItems = $arrItems;
        }

        /**
         * 数组转字符串
         *
         * @param array $arr
         * @param bool $needSerialize
         * @return string
         */
        private function _arrToStr($arr, $needSerialize = false)
        {
            $str = ''; 
            $i = 0;
            if(is_array($arr))
            {
                foreach($arr as $k=>$v)
                {
                    $str_k = ($k !== $i) ? ($k . '=') : '';
                    if(is_string($v) || is_numeric($v) || is_bool($v) || is_null($v))
                    { 
                        $str .= $str_k . $v . ',';
                    }
                    else
                    {
                        if($needSerialize)
                        {
                            $str .= $str_k . serialize($v) . ',';
                        }
                        else
                        { 
                            $str .= $str_k . '<@' . $this->_arrToStr($v, true) . '@>,';
                        }
                    }
                    $i++; 
                }   
            }
            else
            {
                $str = serialize($arr);
            }

            return rtrim($str, ',');
        }

        /**
         * 构造logid(优先使用上游调用传递过来的logid)
         *
         * @return mix (预期统一规范为数字字符串)
         */
        private static function _logId()
        {
            $inputs = $_POST + $_GET;

            if(isset($inputs['_logId_']))
            {
                return $inputs['_logId_'];
            }
            else
            {
                $arr = gettimeofday();
                return ((($arr['sec']*100000 + $arr['usec']/10) & 0x7FFFFFFF) | 0x80000000);
            }
        }

        /**
         * 获取日志切分精度的格式串(精度可为：年 月 日 小时)
         *
         * @return unknown
         */
        private function _getDateFormatStr($dateFormat)
        {
            switch ($dateFormat)
            {
                case 'y':
                    $dateFormatStr = Date('Y');
                break;
                case 'm':
                    $dateFormatStr = Date('Ym');
                break;
                case 'd':
                    $dateFormatStr = Date('Ymd');
                break;
                case 'h':
                    $dateFormatStr = Date('YmdH');
                break;
                default:
                    $dateFormatStr = '';

            }

            return $dateFormatStr;
        }

        /**
         * 获取客户端ip
         *
         * @return string
         */
        private static function _getClientIp()
        {
            if (isset($_SERVER['HTTP_CLIENT_IP']) && !empty($_SERVER['HTTP_CLIENT_IP']))
            {
                return $_SERVER['HTTP_CLIENT_IP'];
            }
            if (isset($_SERVER['HTTP_X_FORWARDED_FOR']) && !empty($_SERVER['HTTP_X_FORWARDED_FOR']))
            {
                $ip = strtok($_SERVER['HTTP_X_FORWARDED_FOR'], ',');
                do
                {
                    $ip = trim($ip);
                    $ip = ip2long($ip);
                    /*
                     * 0xFFFFFFFF = 4294967295  	255.255.255.255
                     * 0x7F000001 = 2130706433	 	127.0.0.1
                     * 0x0A000000 = 167772160		10.0.0.0
                     * 0x0AFFFFFF = 184549375		10.255.255.255
                     * 0xC0A80000 = 3232235520		192.168.0.0
                     * 0xC0A8FFFF = 3232301055		192.168.255.255
                     * 0xAC100000 = 2886729728		172.16.0.0
                     * 0xAC1FFFFF = 2887778303		172.31.255.255
                     */
                    if (!(($ip == 0) || ($ip == 0xFFFFFFFF) || ($ip == 0x7F000001) ||
                    (($ip >= 0x0A000000) && ($ip <= 0x0AFFFFFF)) ||
                    (($ip >= 0xC0A80000) && ($ip <= 0xC0A8FFFF)) ||
                    (($ip >= 0xAC100000) && ($ip <= 0xAC1FFFFF))))
                    {
                        return long2ip($ip);
                    }
                } while ($ip = strtok(','));
            }
            if (isset($_SERVER['HTTP_PROXY_USER']) && !empty($_SERVER['HTTP_PROXY_USER']))
            {
                return $_SERVER['HTTP_PROXY_USER'];
            }
            if (isset($_SERVER['REMOTE_ADDR']) && !empty($_SERVER['REMOTE_ADDR']))
            {
                return $_SERVER['REMOTE_ADDR'];
            }
            else
            {
                return "0.0.0.0";
            }
        }
    }
?>