<?php
namespace vznpc\entities\VZENTITIES\TypeShowcaseEntities;
use pocketmine\entity\Entity;
use pocketmine\item\Item;
use pocketmine\level\particle\FloatingTextParticle;
use pocketmine\math\Vector3;
use pocketmine\network\mcpe\protocol\AddPlayerPacket;
use pocketmine\network\mcpe\protocol\PlayerListPacket;
use pocketmine\network\mcpe\protocol\ProtocolInfo;
use pocketmine\network\mcpe\protocol\RemoveActorPacket;
use pocketmine\network\mcpe\protocol\SetActorDataPacket;
use pocketmine\network\mcpe\protocol\types\AbilitiesData;
use pocketmine\network\mcpe\protocol\types\AbilitiesLayer;
use pocketmine\network\mcpe\protocol\types\PlayerListEntry;
use pocketmine\network\mcpe\protocol\types\PlayerPermissions;
use pocketmine\network\mcpe\protocol\types\entity\EntityMetadataProperties;
use pocketmine\Player;
use pocketmine\Server;
use vznpc\tasks\FloatingResendTask;
class VZNPCFloatingText extends VZNPCHuman
{
    const LINE_SPACING = 0.25;
    const BASE_Y_OFFSET = 0.3;
    private $particles = [];
    private $lineCounts = [];
    private $manualViewers = [];
    private function isModernClient(Player $player):bool
    {
        return
        $player->getProtocol() >= ProtocolInfo::PROTOCOL_110;
    }
    private function splitLines(string $text):array
    {
        $out = [];
        foreach(preg_split('/\{n\}|%n%|\r\n|\n/i', $text) as $line)
            {
            $out[] = trim($line);
        }
        return $out === [] ? [""] : $out;
    }
    private function scaleId():int
    {
        return defined(EntityMetadataProperties::class . "::DATA_SCALE")
            ? constant(EntityMetadataProperties::class . "::DATA_SCALE")
            : 38;
    }
    protected function initEntity():void
    {
        parent::initEntity();
        $this->setGenericFlag(Entity::DATA_FLAG_INVISIBLE, true);
        $this->setNameTagVisible(true);
        $this->setNameTagAlwaysVisible(true);
    }
    private static $resendDelays = [20, 60, 100];
    public function refreshViewer(Player $player):void
    {
        $key = $player->getLoaderId();
        if($this->isClosed()
            || !$player->isOnline()
        || !isset($this->hasSpawned[$key]))
        {
            return;
        }
        if($player->getLevel()
            !== $this->getLevel())
        {
            return;
        }
        if($this->isModernClient($player))
            {
            $this->sendModern($player);
        }else{
            $pk = new RemoveActorPacket();
            $pk->entityUniqueId = $this->getId();
            $player->dataPacket($pk);
            $this->sendLegacy($player);
        }
    }
    private function scheduleResends(Player $player):void
    {
        $plugin = Server::getInstance()->getPluginManager()->getPlugin("VZNPC");
        if($plugin === null)
            {
            return;
        }
        foreach(self::$resendDelays as $delay)
            {
            $plugin->getScheduler()->scheduleDelayedTask(new FloatingResendTask($plugin, $this, $player), $delay);
        }
    }
    public function spawnTo(Player $player):void
    {
        if($player === $this or isset($this->hasSpawned[$player->getLoaderId()]))
            {
            return;
        }
        if($player->getLevel() !== $this->getLevel())
            {
            return;
        }
        $this->hasSpawned[$player->getLoaderId()] = $player;

        if($this->isModernClient($player))
            {
            $this->sendModern($player);
        }else{
            $this->sendLegacy($player);
        }
        $this->scheduleResends($player);
    }
    private function sendLegacy(Player $player, bool $tiny = false):void
    {
        $uuid = $this->getUniqueId();
        $entityId = $this->getId();
        $text = $this->getDisplayName($player);
        $flags = (1 << Entity::DATA_FLAG_INVISIBLE) | (1 << Entity::DATA_FLAG_ALWAYS_SHOW_NAMETAG);
        if($tiny)
            {
            $flags |= (1 << Entity::DATA_FLAG_IMMOBILE);
        }
        $metadata = [
            Entity::DATA_FLAGS => [Entity::DATA_TYPE_LONG, $flags],
            EntityMetadataProperties::DATA_NAMETAG => [Entity::DATA_TYPE_STRING, $text],
            EntityMetadataProperties::DATA_ALWAYS_SHOW_NAMETAG => [Entity::DATA_TYPE_BYTE, 1],
            15 => [Entity::DATA_TYPE_BYTE, 1],
            EntityMetadataProperties::DATA_LEAD_HOLDER_EID => [Entity::DATA_TYPE_LONG, -1]
        ];
        if($tiny)
            {
            $metadata[$this->scaleId()] = [Entity::DATA_TYPE_FLOAT, 0.01];
        }
        $addToList = new PlayerListPacket();
        $addToList->type = PlayerListPacket::TYPE_ADD;
        $addToList->entries[] = PlayerListEntry::createAdditionEntry($uuid, $entityId, "", $this->skin);
        $player->dataPacket($addToList);
        $pk = new AddPlayerPacket();
        $pk->uuid = $uuid;
        $pk->username = "";
        $pk->entityRuntimeId = $entityId;
        $pk->position = new Vector3($this->x, $this->y, $this->z);
        $pk->motion = new Vector3(0, 0, 0);
        $pk->yaw = $this->yaw;
        $pk->pitch = $this->pitch;
        $pk->item = Item::get(Item::AIR);
        $pk->metadata = $metadata;
        $pk->abilitiesData = new AbilitiesData(
            0,
            PlayerPermissions::VISITOR,
            $entityId,
            [
                new AbilitiesLayer(AbilitiesLayer::LAYER_BASE, [], null, null, null)
            ]
        );
        $player->dataPacket($pk);
        $dataPk = new SetActorDataPacket();
        $dataPk->entityRuntimeId = $entityId;
        $dataPk->metadata = $metadata;
        $player->dataPacket($dataPk);
        $this->getArmorInventory()->sendContents($player);
        $removeFromList = new PlayerListPacket();
        $removeFromList->type = PlayerListPacket::TYPE_REMOVE;
        $removeFromList->entries[] = PlayerListEntry::createRemovalEntry($uuid);
        $player->dataPacket($removeFromList);
    }
    private function sendModern(Player $player):void
    {
        $key = $player->getLoaderId();
        $wasManual = isset($this->manualViewers[$key]);
        $this->removeParticles($player);

        $made = [];
        try{
            $level = $this->getLevel();
            $lines = $this->splitLines($this->getDisplayName($player));
            $count = count($lines);
            $baseY = $this->y + self::BASE_Y_OFFSET + (($count - 1) * self::LINE_SPACING);
            foreach($lines as $i => $line)
                {
                $pos = new Vector3($this->x, $baseY - ($i * self::LINE_SPACING), $this->z);
                $particle = new FloatingTextParticle($pos, "", $line);
                $level->addParticle($particle, [$player]);
                $made[] = $particle;
            }
            $this->particles[$key] = $made;
            $this->lineCounts[$key] = $count;
            return;
            }catch (\Throwable $e)
            {
            self::logOnce("Particle send failed, using fallback: " . $e->getMessage());
            foreach ($made as $particle)
                {
                $this->hideParticle($particle, $player);
            }
        }
        if($wasManual)
            {
            $rm = new RemoveActorPacket();
            $rm->entityUniqueId = $this->getId();
            $player->dataPacket($rm);
        }
        $this->manualViewers[$key] = true;
        $this->sendLegacy($player, true);
    }
    private static $logged = false;
    private static function logOnce(string $msg):void
    {
        if(self::$logged){
            return;
        }
        self::$logged = true;
        try{
            $plugin = Server::getInstance()->getPluginManager()->getPlugin("VZNPC");
            if($plugin !== null)
                {
                $plugin->getLogger()->warning("FloatingText: " . $msg);
            }
        }catch (\Throwable $e)
        {
        }
    }
    private function hideParticle(FloatingTextParticle $particle, Player $player):void
    {
        try{
            $level = $this->getLevel();
            if ($level !== null && $player->isOnline()){
                $particle->setInvisible(true);
                $level->addParticle($particle, [$player]);
            }
        }catch (\Throwable $e)
        {
        }
    }
    private function removeParticles(Player $player):void
    {
        $key = $player->getLoaderId();
        if(isset($this->particles[$key]))
            {
            foreach($this->particles[$key] as $particle)
                {
                $this->hideParticle($particle, $player);
            }
        }
        unset($this->particles[$key],
        $this->lineCounts[$key],
        $this->manualViewers[$key]);
    }
    public function sendNametagTo(Player $player)
    {
        $key = $player->getLoaderId();
        if(!isset($this->hasSpawned[$key]))
            {
            return;
        }
        if($this->isModernClient($player))
            {
            if(isset($this->manualViewers[$key]))
                {
                $this->sendTagData($player, true);
                return;
            }
            $lines = $this->splitLines($this->getDisplayName($player));
            if (isset($this->particles[$key]) && ($this->lineCounts[$key] ?? -1) === count($lines))
                {
                foreach($this->particles[$key] as $i => $particle)
                    {
                    if($particle->getTitle() === $lines[$i])
                        {
                        continue;
                    }
                    try{
                        $particle->setTitle($lines[$i]);
                        $this->getLevel()->addParticle($particle, [$player]);
                    }catch (\Throwable $e)
                    {
                    }
                }
            }else{
                $this->sendModern($player);
            }
            return;
        }
        $this->sendTagData($player, false);
    }
    private function sendTagData(Player $player, bool $tiny):void
    {
        $flags = (1 << Entity::DATA_FLAG_INVISIBLE) | (1 << Entity::DATA_FLAG_ALWAYS_SHOW_NAMETAG);
        if($tiny){
            $flags |= (1 << Entity::DATA_FLAG_IMMOBILE);
        }
        $pk = new SetActorDataPacket();
        $pk->entityRuntimeId = $this->getId();
        $pk->metadata = [
            Entity::DATA_FLAGS => [Entity::DATA_TYPE_LONG, $flags],
            EntityMetadataProperties::DATA_NAMETAG => [Entity::DATA_TYPE_STRING, $this->getDisplayName($player)],
            EntityMetadataProperties::DATA_ALWAYS_SHOW_NAMETAG => [Entity::DATA_TYPE_BYTE, 1]
        ];
        $player->dataPacket($pk);
    }
    public function despawnFrom(Player $player, bool $send = true):void
    {
        $this->removeParticles($player);
        parent::despawnFrom($player, $send);
    }
    public function close():void
    {
        foreach($this->hasSpawned as $player)
            {
            if($player instanceof Player)
                {
                $this->removeParticles($player);
            }
        }
        parent::close();
    }
}
