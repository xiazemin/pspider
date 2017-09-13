<?php
    namespace Spider\Proxy\Source;
    
    use GuzzleHttp\Client;
    use Spider\Parsers\HtmlParser;

    class Ip181
    {        
        public function handler()
        {
            $method = 'get';
            $url = 'http://www.ip181.com/';
            $options = [];
            
            $client = new Client();
            
            $html = $client->request($method,$url,$options)->getBody()->getContents();
            $parser = HtmlParser::load($html);
            $nodeList = $parser->find('tr');
            
            unset($nodeList[0]);
            
            $ips = [];
            
            foreach($nodeList as $node)
            {
                $arr = explode(PHP_EOL,$node->text());

                $ip = trim($arr[0]).':'.trim($arr[1]);
                $https = stristr($arr[3],'s')?true:false;
                $level = trim($arr[2]);
                $encoding = mb_detect_encoding($level);

                if($encoding!='UTF-8')
                {
                    $level = iconv($encoding,'UTF-8',$level);
                }

                if($level=='高匿')
                {
                    $ips [] = ['ip'=>$ip,'https'=>$https];
                }
            }
            
            return $ips;
        }
    }