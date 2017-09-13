PHP Spider
===
#### 依赖安装
```shell
composer require ervin-meng/pspider:dev-master
```

#### 代码示例
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
