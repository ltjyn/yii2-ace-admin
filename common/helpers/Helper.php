<?php

namespace common\helpers;

use yii\helpers\ArrayHelper;

/**
 * Class Helper
 * 辅助处理类，一般用来定义公共方法
 * @author  liujx
 * @package common\helpers
 */
class Helper
{
    /**
     * map() 使用ArrayHelper 处理数组, 并添加其他信息
     * @param  mixed  $array 需要处理的数据
     * @param  string $id    键名
     * @param  string $name  键值
     * @param  array $params 其他数据
     * @return array
     */
    public static function map($array, $id, $name, $params = ['请选择'])
    {
        $array = ArrayHelper::map($array, $id, $name);
        if ($params) {
            foreach ($params as $key => $value) $array[$key] = $value;
        }

        ksort($array);
        return $array;
    }

    /**
     * getIpAddress() 获取IP地址
     * @return string 返回字符串
     */
    public static function getIpAddress()
    {
        if (isset($_SERVER)) {
            if (isset($_SERVER['HTTP_X_FORWARDED_FOR'])) {
                $strIpAddress = $_SERVER['HTTP_X_FORWARDED_FOR'];
            } else if (isset($_SERVER['HTTP_CLIENT_IP'])) {
                $strIpAddress = $_SERVER['HTTP_CLIENT_IP'];
            } else {
                $strIpAddress = isset($_SERVER['REMOTE_ADDR']) ? $_SERVER['REMOTE_ADDR'] : '';
            }
        } else {
            if (getenv('HTTP_X_FORWARDED_FOR')) {
                $strIpAddress = getenv('HTTP_X_FORWARDED_FOR');
            } else if (getenv('HTTP_CLIENT_IP')) {
                $strIpAddress = getenv('HTTP_CLIENT_IP');
            } else {
                $strIpAddress = getenv('REMOTE_ADDR') ? getenv('REMOTE_ADDR') : '';
            }
        }

        return $strIpAddress;
    }

    /**
     * 处理通过请求参数对应yii2 where 查询条件
     * @param array $params 请求参数数组
     * @param array $where  定义查询处理方式数组
     * @param string $join  默认查询方式是and
     * @return array
     */
    public static function handleWhere($params, $where, $join = 'and')
    {
        $arrReturn = [];
        if ($where) {
            // 处理默认查询条件
            if (isset($where['where']) && !empty($where['where'])) {
                $arrReturn = $where['where'];
                unset($where['where']);
            }

            // 处理其他查询
            if ($where && $params) {
                /**
                 * 循环使用$params,前端处理了空值的情况(提交查询时候，空值不提交进查询参数中)
                 * $params 的个数小于等于$where 个数
                 */
                foreach ($params as $key => $value) {
                    // 判断不能查询请求的数据不能为空，且定义了查询参数对应查询处理方式
                    if ($value !== '' && isset($where[$key])) {
                        // 根据定义查询处理方式，拼接查询数组
                        switch ($where[$key]) {
                            // 字符串
                            case 'string':
                                $arrReturn[] = [$where[$key], $key, $value];
                                break;

                            // 数组
                            case 'array':
                                // 处理函数
                                if (isset($where[$key]['func']) && function_exists($where[$key]['func'])) {
                                    $value = $where[$key]['func']($value);
                                }

                                // 对应字段
                                if (empty($where[$key]['field'])) $where[$key]['field'] = $key;

                                // 查询连接类型
                                if (empty($where[$key]['and'])) $where[$key]['and'] = '=';

                                $arrReturn[] = [$where[$key]['and'], $where[$key]['field'], $value];
                                break;

                            // 对象(匿名函数)
                            case 'object':
                                $arrReturn[] = $where[$key]($value);
                                break;

                            // 其他类型
                            default:
                                $arrReturn[] = ['=', $key, $value];
                        }
                    }
                }
            }

            // 存在查询条件，数组前面添加 连接类型
            if ($arrReturn) array_unshift($arrReturn, $join);
        }

        return $arrReturn;
    }

    /**
     * 将一个多维数组连接为一个字符串
     * @param  array $array 数组
     * @return string
     */
    public static function arrayToString($array)
    {
        $str = '';
        if (!empty($array)) {
            foreach ($array as $value) {
                $str .= is_array($value) ? implode('', $value) : $value;
            }
        }

        return $str;
    }

    /**
     * 通过指定字符串拆分数组，然后各个元素首字母，最后拼接
     *
     * @example $strName = 'yii_user_log',$and = '_', return YiiUserLog
     * @param string $strName 字符串
     * @param string $and 拆分的字符串(默认'_')
     * @return string
     */
    public static function strToUpperWords($strName, $and = '_')
    {
        // 通过指定字符串拆分为数组
        $value = explode($and, $strName);
        if ($value) {
            // 首字母大写，然后拼接
            $strReturn = '';
            foreach ($value as $val) {
                $strReturn .= ucfirst($val);
            }
        } else {
            $strReturn = ucfirst($strName);
        }

        return $strReturn;
    }
}