# Yii数据库读写分离组件

这是一个供Yii Framework（以下统称Yii）使用的数据库读写分离组件，使用此组件只需通过简单的配置，即可使你的应用自动的实现读写分离。

## 开始之前

该组件是在 原开源项目基础上优化得来的，原项目地址 https://github.com/devtoby/yii-db-read-write-splitting

Yii读写分离包含两个组件：

1. `MDbConnection` 读写自动路由组件，使用这个名字更希望应用使用这个组件来替代Yii默认的CDbConnection
2. `MCDbCommand` 重写的Command组件，联合MDbConnection使用
3. `MDbSlaveConnection` 从库（Readonly）组件  （未使用，备用参考）

<a href="https://github.com/devtoby/yii-db-read-write-splitting">原有项目</a>有说明 自动分离 和 手动分离， 然而 手动分离 实际上是多加了一个数据库配置实现的，不做说明，这里主要介绍自动分离

### 安装步骤

very easy, 将down下来的组件包中的`MDbConnection.php`、`MCDbCommand.php`复制到你的应用组件目录中，正常来说路径应该在`protected/components`目录中。

#### 修改Yii应用的配置文件

修改Yii应用的配置文件，默认的配置文件为`protected/main.php`，然后在其中找components 部分下的 db 组件的配置，例如：

```php
...
'db'=>array(
    'connectionString' => 'mysql:host=192.168.10.100;dbname=testDb',
    'username' => 'appuser',
    'password' => 'apppassword',
    'charset' => 'utf8',
    'tablePrefix' => 'app_',
),
...
```

将其修改为：

```php
...
'db'=>array(
    'class' => 'MDbConnection', // 指定使用读写分离Class
    'connectionString' => 'mysql:host=192.168.10.203;dbname=test', // 主库配置
    'username' => 'admin',
    'password' => '123456',
    'charset' => 'utf8',
    'tablePrefix' => 'bage_',
    'timeout' => 3, // 增加数据库连接超时时间，默认3s
    'checkSlave' => true, //是否开启从库缓存检查  需开启 cacheKeep 配置
    'checkSlaveTime'=> 60, //从库链接失败后，再次尝试链接从库的时间间隔  需开启 cacheKeep 配置
    'slaves' => array(//从库配置
        array(
            'connectionString' => 'mysql:host=192.168.10.123;dbname=test',
            'username' => 'root',
            'password' => '123456',
        ),
        array(
            'connectionString' => 'mysql:host=192.168.10.248;dbname=test',
            'username' => 'root',
            'password' => '445566a',
        ),
    ),
),
...
```

在 components 下添加 cacheKeep 配置，如下：
```php
...
'components'=>array(
    ...
    /*添加缓存配置*/
    'cacheKeep' => array(
        'class' => 'CFileCache',//单机文件缓存
    ),
    ...
)
```

***注意：slaves中的配置必须是二维数组，可配置的值为CDbConnection中支持的全部值（属性）。***

### 配置继承

为简化应用配置的复杂度、以及结合大部分应用的使用场景，从库配置（部分配置）如果没有设置则会自动继承主库的配置，会继承的配置为：

* username
* password
* charset
* tablePrefix
* timeout
* emulatePrepare
* enableParamLogging

因此配置文件也可以简化为：

```php
...
'db'=>array(
    'class' => 'MDbConnection', // 指定使用读写分离Class
    'connectionString' => 'mysql:host=192.168.10.100;dbname=testDb', // 主库配置
    'username' => 'appuser',
    'password' => 'apppassword',
    'charset' => 'utf8',
    'tablePrefix' => 'app_',
    'slaves' => array(
        array(
            'connectionString' => 'mysql:host=192.168.10.101;dbname=testDb',
        ), // 从库 1
        array(
            'connectionString' => 'mysql:host=192.168.10.102;dbname=testDb',
        ), // 从库 2
    ), // 从库配置
),
...
```

### 关闭从库

如果需要临时关闭从库查询，或者没有从库只需注释掉slaves部分的配置即可。

###针对<a href="https://github.com/devtoby/yii-db-read-write-splitting">原有项目</a>的优化点

1、支持在ActiveRecord及QueryBuilder中的读写自动分离！！ （<a href="https://github.com/devtoby/yii-db-read-write-splitting">原有项目</a>也是这样写的，但是并没有支持）这也意味着如果用到多种数据查询方式的也可以直接使用了。当然，最好先测试下

2、优化主从同步延时导致的问题，但是需要在main.php  components下添加 cacheKeep 项配置如下
```php

'components'=>array(
    ...
    /*添加缓存配置*/
    'cacheKeep' => array(
        'class' => 'CFileCache',//单机文件缓存
    ),
    ...
)
```
若是单台机，可以直接用文件缓存; 若是分布式，请用redis/memcache进行缓存，否则会出现问题

## 反馈问题

[快来提一个Issue吧。](https://github.com/devtoby/yii-db-read-write-splitting/issues/new)
