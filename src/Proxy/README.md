# Proxy
##扫描代理方式<br>
1.抓取代理网站（不管以下面哪种方式，最后执行完都要返回IP二维数组，其中子数组包含两个元素url和https标识）<br>
实现方式：<br>
(1)创建类放到Source目录下
此类一定要实现handler方法
在调用扫描前注册改类
```shell
require_once(__DIR__ . '../../../vendor/autoload.php');

use Spider\Proxy\Proxy;

$proxy = new Proxy();
$proxy->registerSourceHandler(['Ip181','Goubanjia']);    
$proxy->scanFromSource('','ScanProxy');//第二个参数指定进程名，不是false的时候创建子进程并发执行多个source句柄
```
(2)动态注册函数
第一个参数能像上面方法那样传递数组，只能传递字符串
```shell
$proxy->registerSourceHandler('xxx',function(){

  yourcode
}); 
```
2.网段扫描
