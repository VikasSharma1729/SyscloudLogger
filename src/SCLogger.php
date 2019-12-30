<?php

namespace SyscloudLogger\SCLogger;


use Monolog\Logger;
use Monolog\Handler\StreamHandler;
use Monolog\Handler\RedisHandler;


class SCLogger
{
    private $_config;
    private $_handler;
    private $_formatType;
    
    private static $_redis = null;
    private static $_fileStreamHandler = null;
    private static $_redisStreamHandler = null;
    
    /**
     * initialize logger
     * @param type $channel - Channel Name.
     * @param type $appName - Application Name.
     * @param type $configs - This parameter accepts keys (logfilepath,redisHost)
     * logfilepath (Example:- /home/ubuntu/SCSGBackup/SCSLog/)
     * redisHost (Example:- elasticcachehost.XXXXXX.XX.XXXX.XXX.cache.amazonaws.com)
     */
    public function __construct($channel, $appName, $configs = array())
    {
        $this->_config =  new SCLogConfig($channel, $appName, $configs);
        $this->_redishost = $this->_config->_redishost;
        
        $logger = new Logger($this->_config->_channel);
        $this->_handler = $logger->pushHandler($this->getStreamHandler());
    }
    
    public function __destruct() 
    {
        
    }
    
    /**
     * function to post error log in respective stream
     * @param type $message
     */
    public function error_log($message, $errorType, $errorCode = 0)
    {
        try
        {
            
            $errorMessage = $this->getFormattedError($errorCode, $message);
        
            switch($errorType)
            {
                case ErrorIntensity::SYS_LOG_INFO:
                    $this->_handler->addInfo($errorMessage);
                    break;

                case ErrorIntensity::SYS_LOG_ERROR:
                    $this->_handler->addError($errorMessage);
                    break;

                case ErrorIntensity::SYS_LOG_WARNING:
                    $this->_handler->addWarning($errorMessage);
                    break;
                
                case ErrorIntensity::SYS_LOG_ALERT:
                    $this->_handler->addAlert($errorMessage);
                    break;
                
                case ErrorIntensity::SYS_LOG_EMERGENCY:
                    $this->_handler->addEmergency($errorMessage);
                    break;
            }
            
        } 
        catch (Exception $ex) 
        {
            error_log("Problem with monologger");
        }
        
        return true;
    }
    
    /**
     * function to format the error.
     * @param type $errorCode - Error code.
     * @param type $message - Error Message.
     * @return type
     */
    private function getFormattedError($errorCode, $message)
    {
        $errorText = "";
        switch($this->_formatType)
        {
            case "text": 
                $errorText = "Code: " . $errorCode . " Message: " . $message;
                break;
            
            case "json":
                $errorText = array(
                    "Code" => $errorCode,
                    "Message" => $message
                );
                $errorText = json_encode($errorText);
                break;
            
        }
        
        return $errorText;
    }
    
    /**
     * function to select streams depends upon channels
     * @return type
     */
    private function getStreamHandler()
    {
        $stream = "";
        switch($this->_config->_stream)
        {
             case "file":
                 $stream = $this->getFileStreamHandler();
                 $this->_formatType = "text";
                 break;
             
             case "redis":
                 $stream = $this->getRedisStreamHandler();
                 $this->_formatType = "json";
                 break;
             
             default: 
                 $stream = $this->getFileStreamHandler();
                 $this->_formatType = "text";
                 break;
                 
        }
        return $stream;
    }
    
    /**
     * function to get file stream
     * @return StreamHandler
     */
    private function getFileStreamHandler()
    {
        if(!self::$_fileStreamHandler)
        {
            $filename = $this->_config->_filename;
            $path = pathinfo($filename, PATHINFO_DIRNAME);

            if(!file_exists($path))
            {
                mkdir($path, DIRECTORY_PERMISSIONS, true);
            }
            chmod($path, DIRECTORY_PERMISSIONS);

            self::$_fileStreamHandler =  new StreamHandler($filename, Logger::DEBUG);
        }
        
        return self::$_fileStreamHandler;
    }
    
    /**
     * function to get Redis cache stream
     * @return RedisHandler
     */
    private function getRedisStreamHandler()
    {
        if(!self::$_redis)
        {
            $host = $this->_redishost;
            $redis = new \Redis();
            $redis->pconnect($host, 6379);
            self::$_redis = $redis;
            unset($redis);
        }
        
        $listName = $this->_config->_businessUserId . ":" . $this->_config->_userId . ":" . $this->_config->_cloudId;
        
        if(!self::$_redisStreamHandler)
        {
            self::$_redisStreamHandler =  new RedisHandler(self::$_redis, $listName, 'prod');
        }
        
        return self::$_redisStreamHandler;
    }
    
}


