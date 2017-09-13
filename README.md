PHP Spider
===
### 注意事项
1.目前只是简陋开发版本。<br>
2.多进程只能在Linux运行
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
