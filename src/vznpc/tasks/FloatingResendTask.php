<?php
namespace vznpc\tasks;
use pocketmine\Player;
use pocketmine\plugin\Plugin;
use pocketmine\scheduler\PluginTask;
use vznpc\entities\VZENTITIES\TypeShowcaseEntities\VZNPCFloatingText;
class FloatingResendTask extends PluginTask
{
    private $entity;
    private $player;
    public function __construct(
        Plugin $owner, VZNPCFloatingText $entity, Player $player
        )
    {
        parent::__construct($owner);
        $this->entity = $entity;
        $this->player = $player;
    }

    public function onRun($currentTick)
    {
        $this->entity->refreshViewer($this->player);
    }
}
