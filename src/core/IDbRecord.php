<?php

namespace SPF;

/**
 * Database Driver接口
 * 数据库结果集的接口，提供2种接口
 * fetch 获取单条数据
 * fetch 获取全部数据到数组
 * @author Tianfeng.Han
 */
interface IDbRecord
{
    public function fetch();

    public function fetchall();
}
