<?php
namespace vznpc\entities\VZENTITIES\TypeShowcaseEntities;
use pocketmine\entity\Entity;
use pocketmine\entity\Human;
use pocketmine\math\Vector3;
use pocketmine\network\mcpe\protocol\AddPlayerPacket;
use pocketmine\network\mcpe\protocol\PlayerListPacket;
use pocketmine\network\mcpe\protocol\SetActorDataPacket;
use pocketmine\network\mcpe\protocol\types\AbilitiesData;
use pocketmine\network\mcpe\protocol\types\AbilitiesLayer;
use pocketmine\network\mcpe\protocol\types\PlayerListEntry;
use pocketmine\network\mcpe\protocol\types\PlayerPermissions;
use pocketmine\network\mcpe\protocol\types\entity\EntityMetadataProperties;
use pocketmine\Player;
use vznpc\utils\Placeholders;
class VZNPCHuman extends Human
{
    use \vznpc\utils\LegacyDataPropertyCompat;
    public function getDisplayName(Player $player)
    {
        $rawBase = $this->namedtag->hasTag("CustomName")
            ? $this->namedtag->getString("CustomName", "")
            : (string)$this->getDataProperty(2);
        $base = Placeholders::parse($rawBase, $this);
        $base = str_ireplace(
            ["{name}", "{display_name}", "{nametag}"],
            [$player->getName(), $player->getDisplayName(), $player->getNametag()],
            $base
        );
        if($player->hasPermission("vznpc.seeId"))
            {
            $base .= "\n" . \pocketmine\utils\TextFormat::GREEN . "Entity ID: " . $this->getId();
        }

        return $base;
    }
    public function updateNametag()
    {
        foreach($this->hasSpawned as $player)
            {
            if(!($player instanceof Player)
                || !$player->isOnline()){
                continue;
            }
            $this->sendNametagTo($player);
        }
    }
    public function sendNametagTo(Player $player)
    {
        $pk = new SetActorDataPacket();
        $pk->entityRuntimeId = $this->getId();
        $pk->metadata = [
            0 => [Entity::DATA_TYPE_LONG, 1 << Entity::DATA_FLAG_ALWAYS_SHOW_NAMETAG],
            4 => [Entity::DATA_TYPE_STRING, $this->getDisplayName($player)],
            80 => [Entity::DATA_TYPE_BYTE, 1]
        ];
        $player->dataPacket($pk);
    }

    public function spawnTo(Player $player):void
    {
        if($player === $this
        || isset($this->hasSpawned[$player->getLoaderId()]))
        {
            return;
        }
        $this->hasSpawned[$player->getLoaderId()] = $player;
        $uuid     = $this->getUniqueId();
        $entityId = $this->getId();
        $pk               = new AddPlayerPacket();
        $pk->uuid         = $uuid;
        $pk->username     = "";
        $pk->entityRuntimeId = $entityId;
        $pk->position     = new Vector3($this->x, $this->y, $this->z);
        $pk->motion       = new Vector3(0, 0, 0);
        $pk->yaw          = $this->yaw;
        $pk->pitch        = $this->pitch;
        $pk->item         = $this->getInventory()->getItemInHand();
        $pk->metadata = [
            0  => [7, 1 << Entity::DATA_FLAG_ALWAYS_SHOW_NAMETAG],
            4  => [4, $this->getDisplayName($player)],
            80 => [0, $this->getDataProperty(3)],
            15 => [0, 1],
            EntityMetadataProperties::DATA_LEAD_HOLDER_EID => [7, -1]
        ];
        $pk->abilitiesData = new AbilitiesData(
            0,
            PlayerPermissions::VISITOR,
            $entityId,
            [
                new AbilitiesLayer(AbilitiesLayer::LAYER_BASE, [], null, null, null)
            ]
        );
        $player->dataPacket($pk);
        $this->getArmorInventory()->sendContents($player);
        $menuName = $this->namedtag->hasTag("MenuName") ? (string)$this->namedtag["MenuName"] : "";
        $add          = new PlayerListPacket();
        $add->type    = PlayerListPacket::TYPE_ADD;
        $add->entries[] = PlayerListEntry::createAdditionEntry($uuid, $entityId, $menuName, $this->skin);
        $player->dataPacket($add);

        if($menuName === "")
            {
            $remove          = new PlayerListPacket();
            $remove->type    = PlayerListPacket::TYPE_REMOVE;
            $remove->entries[] = PlayerListEntry::createRemovalEntry($uuid);
            $player->dataPacket($remove);
        }
    }
}