<?php

/**
 * 扩展后支持主从的数据库操作类
 *
 * Read More: http://devtoby.github.io/yii-db-read-write-splitting
 *
 * File: MDbConnection.php
 * Date: 14-6-14
 * Author: Toby<quflylong@qq.com>
 */
class MDbConnection extends CDbConnection {

    /**
     * @var int 连接数据库超时时间
     */
    public $timeout = 3;
    
    /**
     * @var bool 是否开启从库缓存检查
     */
    public $checkSlave = true;
    
    /**
     * @var int 设置检查从库是否正常的缓存时间 秒
     */
    public $checkSlaveTime = 10;
    
    /**
     * @var array 从库配置数组
     * @example array( array('connectionString'=>'mysql://<slave01>'), array('connectionString'=>'mysql://<slave02>'),...)
     */
    public $slaves = array();
    
    /**
     * @var MDbConnection
     */
    public $_slave;

    /**
     * @var bool 是否开启从库自动继承主库的部分属性
     */
    public $isAutoExtendsProperty = true;

    /**
     * @var bool 强制使用主库
     */
    private $_forceUseMaster = false;

    /**
     * @var array 从库自动继承主库的属性
     */
    private $_autoExtendsProperty = array(
        'username', 'password', 'charset', 'tablePrefix', 'timeout', 'emulatePrepare', 'enableParamLogging',
    );

    /**
     * @var array 数据库读操作的SQL前缀（前4个字符）
     */
    private $_readSqlPrefix = array(
        'SELE'
    );

    /**
     * 创建一个 command.
     *
     * @param string $sql
     * @return CDbCommand
     */
    public function createCommand($sql = null) {
        if (!$this->_forceUseMaster && $this->slaves && !$this->getCurrentTransaction()) {
            $this->getSlave();
        }
        return new MCDbCommand($this, $sql);
    }

    /**
     * 强制使用Master，为避免主库过大压力，请随用随关
     * 【注意】除非你有足够的理由，否则请勿使用
     *
     * @param bool $value
     */
    public function forceUseMaster($value = false) {
        $this->_forceUseMaster = $value;
    }

    /**
     * 打开或关闭数据库连接
     *
     * @param boolean $value whether to open or close DB connection
     * @throws CException if connection fails
     */
    public function setActive($value) {
        if ($value != $this->getActive() && $value) {
            $this->setAttribute(PDO::ATTR_TIMEOUT, $this->timeout);
        }

        parent::setActive($value);
    }

    /**
     * 获取从库连接
     *
     * @return MDbSlaveConnection
     */
    private function getSlave() {
        if (!$this->_slave && $this->slaves && is_array($this->slaves)) {
            shuffle($this->slaves);//随机获取从库
            
            foreach ($this->slaves as $slaveConfig) {
                if($this->checkSlaveDb($slaveConfig['connectionString'])) continue; //检查是否在故障期间
                if ($this->isAutoExtendsProperty) {// 自动属性继承
                    foreach ($this->_autoExtendsProperty as $property) {
                        isset($slaveConfig[$property]) || $slaveConfig[$property] = $this->$property;
                    }
                }
                
                $slaveConfig['class'] = 'MDbConnection';
                try {
                    $slave = Yii::createComponent($slaveConfig);

                    $slave->setAttribute(PDO::ATTR_TIMEOUT, $this->timeout);
                    $slave->setActive(true);

                    $this->_slave = $slave;
                    break;
                } catch (Exception $e) {
                    $this->checkSlaveDb($slaveConfig['connectionString'], true);
                    Yii::log("Slave database connection failed! Connection string:{$slaveConfig['connectionString']}", 'warning');
                }
            }
        }

        return $this->_slave;
    }

    /**
     * 检查从库是否需要检查 写入读取缓存
     * 
     * @param string $connectionString 从库链接配置
     * @param bool $set 是否set 缓存   true设置缓存 false获取缓存
     * 
     * @return bool
     */
    private function checkSlaveDb($connectionString, $set=false){
        if(!$this->checkSlave) return false;
        if(!isset(Yii::app()->cacheKeep)) return false;
        $cacehModel = Yii::app()->cacheKeep;
        $cacheKeyFix = 'slave_db_connect_fail_keyfix_'.$connectionString;
        if(!$set) {//获取 存在值则说明有过失败情况
            return $cacehModel->get($cacheKeyFix);
        } else {//设置
            return $cacehModel->set($cacheKeyFix, 1, $this->checkSlaveTime);
        }
    }

    /**
     * 是否为Read操作
     *
     * @param string $sql SQL语句
     *
     * @return bool
     */
    private function isReadOperation($sql) {
        $sqlPrefix = strtoupper(substr(ltrim($sql), 0, 4));
        foreach ($this->_readSqlPrefix as $prefix) {
            if ($sqlPrefix == $prefix) {
                return true;
            }
        }

        return false;
    }
}
