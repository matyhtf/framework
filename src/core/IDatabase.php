<?php
namespace SPF;

/**
 * Database Driver接口
 * 数据库驱动类的接口
 * @author Tianfeng.Han
 *
 */
interface IDatabase
{
    public function query($sql);

    public function connect();

    public function close();

    public function lastInsertId();

    public function getAffectedRows();

    public function errno();

    public function quote($str);
}
