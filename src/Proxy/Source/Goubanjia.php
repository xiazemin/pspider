<?php
    namespace Spider\Proxy\Source;
    
    use GuzzleHttp\Client;
    use GuzzleHttp\Cookie\CookieJar;
    
    use Spider\Utils\UserAgent;
    use Spider\Parsers\HtmlParser;
    
    class Goubanjia
    {
        public function handler()
        {
            $client = new Client();

            $method = 'get';
            $url = 'http://www.goubanjia.com/';

            $headers  = [
                'Connection'=>'keep-alive',
                'Cache-Control'=>'max-age=0',
                'Upgrade-Insecure-Requests'=> '1',
                'User-Agent'=>UserAgent::rand(),
                'Accept'=>'text/html,application/xhtml+xml,application/xml;q=0.9,image/webp,*/*;q=0.8',
                'Accept-Encoding'=>'gzip, deflate, sdch',
                'Accept-Language'=>'zh-CN,zh;q=0.8'
            ];

            $cookiearr = [
                'auth'=>'29fee4c6dd8c89e43a8ddde0f134c014',
            ];

            $cookie = CookieJar::fromArray($cookiearr,'www.goubanjia.com');

            $options = [
                'headers'=>$headers,
                'cookies'=>$cookie
            ];

            $html = $client->request($method,$url,$options)->getBody()->getContents();
            $parser = HtmlParser::load($html);
            $nodeList = $parser->find('tr');
            
            unset($nodeList[0]);
            
            $ips = [];

            foreach($nodeList as $node)
            {
                $arr = explode(PHP_EOL,$node->text());

                $level = trim($arr[1]);
                $ip = trim($arr[0]);
                $https = stristr($arr[2],'s')?true:false;

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