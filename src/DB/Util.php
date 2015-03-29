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

        $bindings = null;

        if ($args && is_array($args)) {
            $cond = array();
            $bindings = array();

            // TODO suportar _and, _or
            foreach ($args as $key => $value) {
                $cond[] = sprintf("`%s` %s :where_%s", $key, is_null($value) ? 'is' : '=', $key);
                $bindings[":where_$key"] = $value;
            }

            $args = implode(" $glue ", $cond);
        }


        return array( $args, $bindings );
    }
}