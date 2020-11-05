<?php
/**
 * Desc: 登录token
 * Date: 2020-11-05
 */
namespace library\tools;

use Predis\Client;
use think\facade\Config;

class ApiToken
{
    private $cryptTools = null;
    private $redisClient = null;
    private $option = [];

    /**
     * ApiToken constructor.
     */
    public function __construct()
    {
        $this->option = Config::get("token.");
        $this->setRedisClient();
        $this->setCryptTools();
    }

    /**
     * 设置Redis
     */
    protected function setRedisClient()
    {
        if (!$this->redisClient) {
            $this->redisClient = new Client($this->option);
            $this->redisClient->select($this->option['database']);
        }
    }

    /**
     * 设置加密工具
     */
    protected function setCryptTools()
    {
        if (!$this->cryptTools) {
            $key = isset($this->option['key']) && is_string($this->option['key']) ? $this->option['key'] : "";
            $this->cryptTools = new AesCrypt($key);
        }
    }

    /**
     * 设置Token
     * @param String $mark
     * @param String $data
     * @return mixed
     */
    public function set(String $mark, String $data)
    {
        $key = $this->getkey($mark);
        $token = $this->cryptTools->encrypts($data);
        if (isset($this->option['ttl']) && is_numeric($this->option['ttl']) && $this->option['ttl'] > 0) {
            $this->redisClient->setex($key, $this->option['ttl'], $token);
        } else {
            $this->redisClient->set($key, $token);
        }
        return $token;
    }

    /**
     * 验证数据
     */
    public function check(String $mark, String $token)
    {
        $key = $this->getkey($mark);
        $value = $this->redisClient->get($key);
        return (empty($value) || $value != $token) ? false : $this->cryptTools->decrypts($value);
    }

    /**
     * @param $mark
     * @return string
     */
    protected function getkey($mark)
    {
        $prefix = isset($this->option['prefix']) && is_string($this->option['prefix']) ? $this->option['prefix'] : "token";
        return sprintf("%s_%s", $prefix, $mark);
    }
}
