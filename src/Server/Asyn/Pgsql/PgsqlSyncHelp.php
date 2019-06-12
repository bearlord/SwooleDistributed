<?php
/**
 * SwooleDistributed Postgresql Extension
 * @author Zhenqiang.zhang (565364226@qq.com)
 *
 */

namespace Server\Asyn\Pgsql;


use ArrayAccess;

class PgsqlSyncHelp implements ArrayAccess
{
    private $elements;
    private $pgsql;

    public function __construct($pgsql, $data)
    {
        $this->pgsql = $pgsql;
        $this->elements = $data;
    }

    /**
     * 获取结果
     * @return mixed
     */
    public function getResult()
    {
        return $this->elements;
    }

    public function dump()
    {
        secho("PGSQL", $this->pgsql);
        return $this;
    }

    public function offsetExists($offset)
    {
        return isset($this->elements[$offset]);
    }

    public function offsetSet($offset, $value)
    {
        $this->elements[$offset] = $value;
    }

    public function offsetGet($offset)
    {
        return $this->elements[$offset];
    }

    public function offsetUnset($offset)
    {
        unset($this->elements[$offset]);
    }

    /**
     * @return mixed
     */
    public function result_array()
    {
        return $this->elements['result'];
    }

    /**
     * @param $index
     * @return null
     */
    public function row_array($index)
    {
        return $this->elements['result'][$index] ?? null;
    }

    /**
     * @return null
     */
    public function row()
    {
        return $this->elements['result'][0] ?? null;
    }

    /**
     * @return int
     */
    public function num_rows()
    {
        return count($this->elements['result']);
    }

    /**
     * @return mixed
     */
    public function insert_id()
    {
        return $this->elements['insert_id'];
    }

    /**
     * @return mixed
     */
    public function affected_rows()
    {
        return $this->elements['affected_rows'];
    }
}