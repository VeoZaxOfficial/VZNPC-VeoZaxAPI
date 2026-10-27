<?php
namespace vznpc\tasks;
use pocketmine\Player;
use pocketmine\scheduler\PluginTask;
use vznpc\entities\VZENTITIES\TypeShowcaseEntities\VZNPCHuman;
use vznpc\Main;
class VZNPCTask extends PluginTask
{
    private $plugin;
    public function __construct(Main $plugin)
    {
        parent::__construct($plugin);
        $this->plugin = $plugin;
    }
    public function onRun($currentTick)
    {
        $server = $this->plugin->getServer();
        foreach($server->getLevels() as $level)
            {
            foreach($level->getEntities() as $entity)
                {
                if(!($entity instanceof VZNPCHuman))
                    {
                    continue;
                }
                if($entity->namedtag->hasTag("HasPlaceholders")
                    && (int)$entity->namedtag->getTag("HasPlaceholders")->getValue() === 1)
                {
                    $entity->updateNametag();
                }
            }
        }
        $this->plugin->syncAllWorlds();
    }
}
