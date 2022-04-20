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
    function query($sql);

    function connect();

    function close();

    function lastInsertId();

    function getAffectedRows();

    function errno();

    function quote($str);
}
