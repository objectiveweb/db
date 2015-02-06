<?php
/**
 * Created by IntelliJ IDEA.
 * User: guigouz
 * Date: 19/12/14
 * Time: 12:32
 */
namespace Objectiveweb\DB;


class Util
{

    public static function where($args = null, $glue = "AND")
    {

        if (!$args) {
            return '';
        }

        $bindings = [];

        if (is_array($args)) {
            $cond = [];

            // TODO suportar _and, _or
            foreach ($args as $key => $value) {
                $cond[] = "$key = :where_$key";
                $bindings[":where_$key"] = $value;
            }

            $args = implode(" $glue ", $cond);
        }


        return [$args, $bindings];
    }
}