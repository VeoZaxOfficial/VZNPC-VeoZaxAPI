<?php
namespace vznpc\utils;
use pocketmine\entity\Entity;
use pocketmine\Server;
use pocketmine\utils\TextFormat;
class Placeholders
{
    public static $dynamicTokens = ["{o}", "{online}", "{c}", "{max}", "{maxplayers}", "{p}", "{tps}", "{date}", "{time}", "{world}", "{level}", "{x}", "{y}", "{z}", "{color}"];
    public static function isDynamic($text)
    {
        if($text === null
        || $text === "")
            {
            return false;
        }
        foreach(self::$dynamicTokens as $token)
            {
            if(stripos($text, $token)
                !== false)
                {
                return true;
            }
        }
        return false;
    }
    public static function parse($text, Entity $entity = null)
    {
        if($text === null
        || $text === "")
            {
            return $text;
        }
        $server  = Server::getInstance();
        $online  = count($server->getOnlinePlayers());
        $color   = ((int)time() % 2 === 0) ? TextFormat::AQUA : TextFormat::YELLOW;
        $trackedCount = $online;
        if($entity !== null && $entity->namedtag->hasTag("TrackWorld"))
            {
            $trackWorldName = $entity->namedtag->getString("TrackWorld", "");
            if($trackWorldName !== "")
                {
                $trackLevel = $server->getLevelByName($trackWorldName);
                if($trackLevel === null)
                    {
                    if($server->loadLevel($trackWorldName))
                        {
                        $trackLevel = $server->getLevelByName($trackWorldName);
                    }
                }
                if($trackLevel !== null)
                    {
                    $trackedCount = count($trackLevel->getPlayers());
                }
            }
        }
        $find = ["{o}", "{online}", "{c}", "{max}", "{maxplayers}", "{p}", "{tps}", "{date}", "{time}", "{color}", "{line}"];
        $replace = [
            $online, $online, $trackedCount,
            $server->getMaxPlayers(), $server->getMaxPlayers(), $server->getMaxPlayers(),
            round($server->getTicksPerSecond(), 2),
            date("d/m/y"), date("H:i:s"),
            $color, "\n"
        ];

        if($entity !== null
        && $entity->getLevel() !== null)
            {
            $find[]    = "{world}";
            $replace[] = $entity->getLevel()->getName();
            $find[]    = "{level}";
            $replace[] = $entity->getLevel()->getName();
            $find[]    = "{x}";
            $replace[] = (int)floor($entity->x);
            $find[]    = "{y}";
            $replace[] = (int)floor($entity->y);
            $find[]    = "{z}";
            $replace[] = (int)floor($entity->z);
        }
        return
        str_ireplace($find, $replace, $text);
    }
}
