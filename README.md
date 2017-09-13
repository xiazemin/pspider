PHP Spider
===
### 注意事项
1.目前只是简陋开发版本,还有很多功能代码未完善。<br>
2.多进程只能在Linux运行。<br>
3.需要Redis扩展,目前所有存储容器的实现都依赖于Redis，此点会慢慢改进。<br>
### 功能介绍
1.支持代理。启用代理后，如果用代理抓取失败，会重新加入抓取列表，并使用新的代理再次抓取。详细说明请点击[这里](https://github.com/ervin-meng/pspider/blob/master/src/Proxy/README.md)<br>
2.支持DOM操作,xpath css选择器(未完善)。详细说明请点击[这里](https://github.com/ervin-meng/pspider/blob/master/src/Parsers/README.md)<br>
3.支持多进程，守护进程方式，进程启动、停止、重启、平滑重启（未完善）,进程意外中断，会重启子进程继续爬取。详细说明请点击[这里](https://github.com/ervin-meng/pspider/blob/master/src/Multiprocess/README.md)<br>
4.支持钩子<br>
### 依赖安装
```shell
composer require ervin-meng/pspider:dev-master
```

### 代码示例
```shell
require_once(__DIR__ . '../../../vendor/autoload.php');

use Spider\Spider;
use Spider\Utils\Hook;

$seeds = [
    'http://blog.jobbole.com/all-posts/',
    'https://tech.imdada.cn/',
    'https://tech.meituan.com/'
];

$options = ['proxy'=>true,'verify'=>false];

$patterns = [
    '/^https:\/\/tech.meituan.com\/(.*)+\.html$/',
    '/^https:\/\/tech.imdada.cn\/(\d{4})\/(\d{2})\/(\d{2})\/(.*)+\/$/',
    '/^http:\/\/blog.jobbole.com\/(\d*)\/$/'
];

$spider = new Spider($seeds,$options,$patterns);

Hook::register('afterCrawl',function($spider){

    $date = date('Y-m-d');

    $dir = __DIR__.'/pages/'.$date.'/';

    if(!is_dir($dir))
    {
        mkdir($dir,0777,true);
    }

    $params = $spider->getParams();
    $content = $spider->getContent();

    file_put_contents($dir.md5($params['url']),$content);

    $spider->log("[PID:{$spider->getWorkerId()}]\t[PAGE:{$params['url']}]\t[PROXY:{$params['options']['proxy']}]",__DIR__.'/logs/'.$date.'.log');

},[$spider]);

$spider->exec(5); 
```
### 运行示例
#### 1.启动爬虫
##### 1.1 CLI 模式（目前只支持linux系统）：
(1) 非守护进程方式(停留终端)
```shell
php yourfile.php start 
```
(2) 守护进程方式(脱离终端)
```shell
php yourfile.php start -d
```
##### 1.2 CGI 模式：
在浏览器直接访问该文件，此模式不支持多进程。
#### 2.停止爬虫
```shell
php yourfile.php stop
```
