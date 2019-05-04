<?php
namespace Concrete5GraphqlWebsocket\GraphQl;

defined('C5_EXECUTE') or die("Access Denied.");

use Concrete\Core\Support\Facade\Facade;

class WebsocketHelpers
{
    public static function start($port)
    {
        $app = Facade::getFacadeApplication();
        if ((bool)$app->make('config')->get('concrete.websocket.debug')) {
            shell_exec("php " . DIR_BASE . "/index.php --websocket-port " . $port . " >> /var/log/subscription_server.log 2>&1 &");
        } else {
            shell_exec("php " . DIR_BASE . "/index.php --websocket-port " . $port . " > /dev/null 2>/dev/null &");
        }
    }

    public static function stop($pid)
    {
        $command = 'kill ' . $pid;
        exec($command);
        if (self::status($pid) == false) return true;
        else return false;
    }

    public static function status($pid)
    {
        $command = 'ps -p ' . $pid;
        exec($command, $op);
        if (!isset($op[1])) return false;
        else return true;
    }
}