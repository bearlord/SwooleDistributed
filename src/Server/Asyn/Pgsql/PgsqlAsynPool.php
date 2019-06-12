<?php
/**
 * SwooleDistributed Postgresql Extension
 * @author Zhenqiang.zhang (565364226@qq.com)
 *
 */

namespace Server\Asyn\Pgsql;

use Server\Asyn\IAsynPool;
use Server\CoreBase\SwooleException;
use Server\Coroutine\CoroutineBase;
use Server\Memory\Pool;

class PgsqlAsynPool implements IAsynPool
{
    const AsynName = 'pgsql';
    protected $pool_chan;
    protected $pgsql_arr;
    private $active;
    protected $config;
    protected $name;
    /**
     * @var Miner
     */
    protected $pgsql_client;
    private $client_max_count;

    protected $query_chan;

    public function __construct($config, $active)
    {
        $this->active = $active;
        $this->config = get_instance()->config;
        $this->client_max_count = $this->config->get('pgsql.asyn_max_count', 10);
        if (get_instance()->isTaskWorker()) return;
        $this->pool_chan = new \chan($this->client_max_count);

        for ($i = 0; $i < $this->client_max_count; $i++) {
            $client = new \Swoole\Coroutine\PostgreSQL();
            $client->id = $i;
            $client->connected = false;
            $this->pushToPool($client);
        }

        $this->query_chan = new \chan(1);
    }

    /**
     * @return mixed
     */
    public function getActveName()
    {
        return $this->active;
    }

    /**
     * @return Miner
     * @throws SwooleException
     */
    public function installDbBuilder()
    {
        return Pool::getInstance()->get(Miner::class)->setPool($this);
    }

    protected function getDsn()
    {
        $set = $this->config['pgsql'][$this->active];
        $dsn = sprintf("host=%s port=%s dbname=%s user=%s password=%s", $set['host'], $set['port'], $set['database'], $set['user'], $set['password']);
        return $dsn;
    }

    /**
     * @param $sql
     * @param null $client
     * @param PgSqlCoroutine $pgsqlCoroutine
     * @return mixed
     * @throws \Throwable
     */
    public function query($sql, $client = null, PgsqlCoroutine $pgsqlCoroutine)
    {
        $notPush = false;
        if ($client == null) {
            $client = $this->pool_chan->pop();
        } else {
            $notPush = true;
        }

        $dsn = $this->getDsn();

        \Swoole\Coroutine::create(function () use ($client, $dsn, $sql, $pgsqlCoroutine) {
            $conn  = $client->connect($dsn);
            if (!$conn) {
                $this->pushToPool($client);
                $pgsqlCoroutine->getResult(new SwooleException("[err]: connect failed, no error"));
                return false;
            }
            $resource = $client->query($sql);
            if ($resource === false) {
                $this->pushToPool($client);
                $pgsqlCoroutine->getResult(new SwooleException("[err]: " . $client->error));
                return false;
            }

            $res = $client->fetchAll($resource);
            $affectedRows = $client->affectedRows($resource);

            $data['result'] = $res;
            $data['affected_rows'] = $affectedRows;
            $data['insert_id'] = null;
            $data['client_id'] = $client->id;

            $result = new PgsqlSyncHelp($sql, $data);
            $this->query_chan->push($result);

            $client->connected = true;
            $this->pushToPool($client);
        });

        return $this->getResult();
    }

    /**
     * 用chan获取查询的协程数据
     * @return mixed
     */
    public function getResult()
    {
        while (1) {
            $row = $this->query_chan->pop();
            if (!empty($row)) {
                return $row;
            }
        }
    }

    /**
     * @param $sql
     * @param $statement
     * @param $holder
     * @param null $client
     * @param PgsqlCoroutine $pgsqlCoroutine
     * @return mixed
     * @throws \Throwable
     */
    public function prepare($sql, $statement, $holder, $client = null, MySqlCoroutine $pgsqlCoroutine)
    {
        //暂不支持----------
        $delayRecv = false;
        //-----------------
        $notPush = false;
        if ($client == null) {
            $client = $this->pool_chan->pop();
        } else {
            $notPush = true;
        }
        if (!$client->connected) {
            $set = $this->config['pgsql'][$this->active];
            $dsn = sprintf("host=%s port=%s dbname=%s user=%s password=%s", $set['host'], $set['port'], $set['database'], $set['user'], $set['password']);
            $result = $client->connect($dsn);
            if (!$result) {
                $this->pushToPool($client);
                throw new SwooleException($client->error);
            }
            $client->connected = true;
        }

        $res = $client->prepare($statement);

        if ($res != false) {
            $res = $res->query($holder, $pgsqlCoroutine->getTimeout() / 1000);
        }
        if ($res === false) {
            $this->pushToPool($client);
            $pgsqlCoroutine->getResult(new SwooleException("[sql]:$sql,[err]:$client->error"));
        }
        $pgsqlCoroutine->destroy();
        return $res;

        $data['result'] = $res;
        $data['affected_rows'] = $client->affected_rows;
        $data['insert_id'] = null;
        $data['client_id'] = $client->id;

        if (!$notPush) {
            $this->pushToPool($client);
        }
        return new PgsqlSyncHelp($sql, $data);
    }

    public function getAsynName()
    {
        return self::AsynName . ":" . $this->name;
    }

    public function pushToPool($client)
    {
        $this->pool_chan->push($client);
    }

    public function getSync()
    {
        if ($this->pgsql_client != null) return $this->pgsql_client;
        $activeConfig = $this->config['pgsql'][$this->active];
        $this->pgsql_client = new Miner();
        $this->pgsql_client->pdoConnect($activeConfig);
        return $this->pgsql_client;
    }

    /**
     * @param $name
     */
    public function setName($name)
    {
        $this->name = $name;
    }
}