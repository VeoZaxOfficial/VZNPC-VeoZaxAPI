<?php
namespace vznpc;
use pocketmine\command\Command;
use pocketmine\command\CommandSender;
use pocketmine\command\ConsoleCommandSender;
use pocketmine\entity\Entity;
use pocketmine\entity\Skin;
use pocketmine\event\entity\EntityDamageByEntityEvent;
use pocketmine\event\entity\EntityDamageEvent;
use pocketmine\event\entity\EntitySpawnEvent;
use pocketmine\event\player\PlayerInteractEvent;
use pocketmine\event\Listener;
use pocketmine\item\Item;
use pocketmine\scheduler\ClosureTask;
use pocketmine\utils\Config;
use pocketmine\nbt\tag\ByteTag;
use pocketmine\nbt\tag\CompoundTag;
use pocketmine\nbt\tag\DoubleTag;
use pocketmine\nbt\tag\ListTag;
use pocketmine\nbt\tag\FloatTag;
use pocketmine\nbt\tag\IntTag;
use pocketmine\nbt\tag\ShortTag;
use pocketmine\nbt\tag\StringTag;
use pocketmine\Player;
use pocketmine\plugin\PluginBase;
use pocketmine\utils\TextFormat;
use vznpc\entities\VZENTITIES\TypeShowcaseEntities\VZNPCHuman;
use vznpc\entities\VZENTITIES\TypeShowcaseEntities\VZNPCFloatingText;
use vznpc\utils\Placeholders;
use vznpc\tasks\VZNPCTask;


class Main extends PluginBase implements Listener
{
    public $hitSessions = [];
    public $idSessions = [];
    public $updateSessions = [];
    private $npcCommandCooldown = [];
    const NPC_COMMAND_COOLDOWN_SECONDS = 0.75;
    const SPAWN_TYPES = [
        "human"        => ["Human", "VZNPCHuman"],
        "player"       => ["Human", "VZNPCHuman"],
        "floatingtext" => ["FloatingText", "VZNPCFloatingText"],
        "hologram"     => ["FloatingText", "VZNPCFloatingText"],
        "text"         => ["FloatingText", "VZNPCFloatingText"],
        "sign"         => ["FloatingText", "VZNPCFloatingText"],
    ];
    public $prefix = "§8[§bVZNPC§8] §7";
    public $noperm = "§8[§cVZNPC§8] §7You don't have permission to use this. §8(Ask a server admin if you think this is a mistake.)";
    public $helpHeader =
        (
            TextFormat::DARK_GRAY . "▬▬▬▬▬▬▬▬▬▬▬▬▬▬▬▬▬▬" . TextFormat::RESET . "\n" .
            TextFormat::AQUA . TextFormat::BOLD . "VZNPC " . TextFormat::RESET . TextFormat::GRAY . "Command Guide" . TextFormat::RESET . "\n" .
            TextFormat::DARK_GRAY . "▬▬▬▬▬▬▬▬▬▬▬▬▬▬▬▬▬▬" . TextFormat::RESET
        );

    private function ok(string $message) : string
    {
        return "§8[§aVZNPC§8] §7" . $message;
    }

    private function err(string $message) : string
    {
        return "§8[§cVZNPC§8] §7" . $message;
    }

    private function info(string $message) : string
    {
        return "§8[§bVZNPC§8] §7" . $message;
    }

    private function usage(string $message) : string
    {
        return "§8[§6VZNPC§8] §7" . $message;
    }

    public $mainArgs = [
        "§b/vznpc help §8» §7Shows this guide.",
        "§b/vznpc spawn §f<type> [world] [name] §8» §7Creates an NPC where you stand. Types: §fhuman§7, §ffloatingtext§7. The optional world is the one §f{c} §7counts.",
        "§b/vznpc edit §f<id> [option] §8» §7Changes an NPC. Type §b/vznpc edit <id> §7to see every option.",
        "§b/vznpc id §8» §7Then hit an NPC to see its ID (you need the ID for edit and remove).",
        "§b/vznpc remove §f[id] §8» §7Deletes an NPC by ID, or type it alone and hit the NPC.",
        "§b/vznpc cancel §8» §7Leaves remove or ID mode.",
        "§b/vznpc updateall §8» §7Updates old NPCs to the newest data format.",
        "§b/vznpc version §8» §7Shows the plugin version.",
        "§aExtra Removal Tip for §8» §7Ops: hold an emerald and hit or right-click an NPC to remove it instantly.",
        "§dTags §8» §f{color} §7= colour symbol (§f{color}a §7= green), §f{line} §7= new line.",
        "§dPlaceholders §8» §f{c}§7/§f{o} §7players online, §f{max}§7/§f{p} §7max players, §f{tps}§7, §f{date}§7, §f{time}§7.",
        "§dPlaceholders §8» §f{world} {x} {y} {z} §7= the NPC's world and position, §f{name} {display_name} {nametag} §7= the player looking at it."
    ];
    public $editArgs = [
        "§b/vznpc edit <eid> name §f<name> §8» §7Changes the displayed name. Supports §f{color}§7, §f{line} §7and placeholders.",
        "§b/vznpc edit <eid> namevisible §f<1/0> §8» §7Shows (1) or hides (0) the name above the NPC.",
        "§b/vznpc edit <eid> skin §8» §7Copies your current skin onto the NPC (humans only).",
        "§b/vznpc edit <eid> helmet §f<id> §8» §7Puts an item on the NPC's head (humans only).",
        "§b/vznpc edit <eid> chestplate §f<id> §8» §7Sets the NPC's chestplate (humans only).",
        "§b/vznpc edit <eid> leggings §f<id> §8» §7Sets the NPC's leggings (humans only).",
        "§b/vznpc edit <eid> boots §f<id> §8» §7Sets the NPC's boots (humans only).",
        "§b/vznpc edit <eid> hand §f<id> §8» §7Sets the item the NPC holds (humans only).",
        "§b/vznpc edit <eid> menuname §f<name/remove> §8» §7Sets the name shown in the player list, or clears it (humans only).",
        "§b/vznpc edit <eid> addcommand §f<command> §8» §7Adds a command that runs when someone taps the NPC.",
        "§b/vznpc edit <eid> delcommand §f<command> §8» §7Removes one of the NPC's tap commands.",
        "§b/vznpc edit <eid> listcommands §8» §7Shows all of the NPC's tap commands.",
        "§b/vznpc edit <eid> update §8» §7Converts old tap commands to the new format.",
        "§b/vznpc edit <eid> tphere §8» §7Teleports the NPC to you.",
        "§b/vznpc edit <eid> tpto §8» §7Teleports you to the NPC.",
        "§b/vznpc edit <eid> world §f<world> §8» §7Moves the NPC to another world.",
        "§b/vznpc edit <eid> worlds §f<w1,w2,.../clear/list> §8» §7Shows copies of the NPC in several worlds, removes them, or lists them.",
        "§b/vznpc edit <eid> trackworld §f<world/clear> §8» §7Makes §f{c} §7count only players in that world."
    ];

    public function onEnable()
    {
        Entity::registerEntity(VZNPCHuman::class, true);
        Entity::registerEntity(VZNPCFloatingText::class, true);
        $this->getServer()->getPluginManager()->registerEvents($this, $this);
        $this->getScheduler()->scheduleRepeatingTask(new VZNPCTask($this), 20);

    }

    public function onCommand(CommandSender $sender, Command $command, string $label, array $args):bool
    {
        switch(strtolower($command->getName()))
        {
            case 'rca':
                if(count($args) < 2)
                    {
                    $sender->sendMessage($this->usage("Usage: §e/rca <player> <command> §7- runs a command as another player, as if they typed it. Example: §e/rca Steve spawn"));
                    return true;
                }
                $player = $this->getServer()->getPlayer(array_shift($args));
                if($player instanceof Player){
                    $this->getServer()->dispatchCommand($player, trim(implode(" ", $args)));
                    return true;
                }else{
                    $sender->sendMessage($this->err("Player not found. Check the spelling and make sure they are online."));
                    return true;
                }
                break;
            case "vznpc":
                if($sender instanceof Player)
                    {
                    if(!(isset($args[0])))
                        {
                        if($sender->hasPermission("vznpc.command")
                            ||$sender->hasPermission("vznpc"))
                        {
                            $sender->sendMessage($this->info("Type §e/vznpc help §7to see everything this plugin can do."));
                            return true;
                        }else{
                            $sender->sendMessage($this->noperm);
                            return true;
                        }
                    }
                    $arg = array_shift($args);
                    switch ($arg)
                    {
                        case "id":
                            if($sender->hasPermission("vznpc.id")
                                ||$sender->hasPermission("vznpc"))
                            {
                                $this->idSessions[$sender->getName()] = true;
                                $sender->sendMessage($this->info("ID mode is on. Hit an NPC to see its ID. Type §e/vznpc cancel §7to stop."));
                                return true;
                            }else{
                                $sender->sendMessage($this->noperm);
                                return true;
                            }
                            break;
                        case "fixall":
                        case "updateall":
                        case "migrateall":
                            $server = $this->getServer();
                            $count = 0;
                            foreach($server->getLevels() as $level)
                                {
                                foreach($level->getEntities() as $entity)
                                    {
                                    if($entity instanceof VZNPCHuman)
                                        {
                                        $count++;
                                        if(!($entity->namedtag->hasTag("Commands")))
                                            {
                                            $entity->namedtag->setTag(new CompoundTag("Commands", []));
                                        }
                                        if(!($entity->namedtag->hasTag("HasPlaceholders")))
                                            {
                                            $rawName = $entity->namedtag->getString("CustomName", "");
                                            $entity->namedtag->setTag(new ByteTag("HasPlaceholders", Placeholders::isDynamic($rawName) ? 1 : 0));
                                        }
                                        if(!($entity->namedtag->hasTag("TrackWorld")))
                                            {
                                            $entity->namedtag->setTag(new StringTag("TrackWorld", ""));
                                        }
                                        $oldCmds = $this->getConfig()->get($entity->getName());
                                        if($oldCmds)
                                            {
                                            foreach($oldCmds as $oldCmd)
                                                {
                                                $entity->namedtag->getCompoundTag("Commands")[$oldCmd] = new StringTag($oldCmd, $oldCmd);
                                            }
                                        }
                                    }
                                    if($entity instanceof VZNPCHuman)
                                        {
                                        if($entity->getSkin()->getSkinId() === "")
                                            {
                                            $entity->setSkin(new Skin("Standard_Custom", $entity->getSkin()->getSkinData()));
                                            $entity->despawnFromAll();
                                            $entity->spawnToAll();
                                        }
                                    }
                                }
                            }
                            $sender->sendMessage($this->ok("Updated §f" . $count . " §7NPC(s) to the newest data format."));
                            return true;
                            break;
                         case "version":
                            if($sender->hasPermission("vznpc.version")
                                ||$sender->hasPermission("vznpc"))
                            {
                                $desc = $this->getDescription();
                                $authors = implode(", ", $desc->getAuthors());
                                $sender->sendMessage(
                                    TextFormat::DARK_GRAY . "▬▬▬▬▬▬▬▬▬▬▬▬▬▬▬▬▬▬" . TextFormat::RESET . "\n" .
                                    TextFormat::AQUA . TextFormat::BOLD . $desc->getName() . TextFormat::RESET . TextFormat::DARK_GRAY . " » " . TextFormat::YELLOW . "v" . $desc->getVersion() . "\n" .
                                    TextFormat::GRAY . "Author " . TextFormat::DARK_GRAY . "» " . TextFormat::AQUA . $authors . "\n" .
                                    TextFormat::DARK_GRAY . "▬▬▬▬▬▬▬▬▬▬▬▬▬▬▬▬▬▬"
                                );
                                return true;
                            }else{
                                $sender->sendMessage($this->noperm);
                                return true;
                            }
                            break;
                        case "cancel":
                        case "stopremove":
                        case "stopid":
                            unset($this->hitSessions[$sender->getName()]);
                            unset($this->idSessions[$sender->getName()]);
                            unset($this->updateSessions[$sender->getName()]);
                            $sender->sendMessage($this->ok("Cancelled. You are no longer in remove or ID mode."));
                            return true;
                            break;
                        case "remove":
                            if($sender->hasPermission("vznpc.remove")
                                ||$sender->hasPermission("vznpc"))
                            {
                                if(isset($args[0]))
                                    {
                                    $entity = $sender->getLevel()->getEntity($args[0]);
                                    if(!($entity == null))
                                        {
                                        if($entity instanceof VZNPCHuman)
                                        {
                                            if($entity instanceof VZNPCHuman)
                                                {
                                               $entity->getInventory()->clearAll(); 
                                            }
                                            $this->clearEntityWorlds($entity);
                                            $entity->kill();
                                            $sender->sendMessage($this->ok("NPC removed."));
                                        }else{
                                            $sender->sendMessage($this->err("That is not a VZNPC NPC, so this command can't change it. Use §e/vznpc id §7to check an NPC's ID."));
                                        }
                                    }else{
                                        $sender->sendMessage($this->err("No NPC with that ID exists in your current world. Type §e/vznpc id §7and hit the NPC to find its ID."));
                                    }
                                    return true;
                                }
                                $this->hitSessions[$sender->getName()] = true;
                                $sender->sendMessage($this->info("Remove mode is on. Hit the NPC you want to delete, or use §e/vznpc remove <id>§7. Type §e/vznpc cancel §7to stop."));
                            }else{
                                $sender->sendMessage($this->noperm);
                                return true;
                            }
                            return true;
                            break;
                        case "edit":
                            if($sender->hasPermission("vznpc.edit")
                                ||$sender->hasPermission("vznpc"))
                            {
                                if(isset($args[0]))
                                    {
                                    $level = $sender->getLevel();
                                    $entity = $level->getEntity($args[0]);
                                    if($entity !== null)
                                        {
                                        if($entity instanceof VZNPCHuman)
                                        {
                                            if(isset($args[1]))
                                                {
                                                switch($args[1])
                                                {
                                                    case "helm":
                                                    case "helmet":
                                                    case "head":
                                                    case "hat":
                                                    case "cap":
                                                        if($entity instanceof VZNPCHuman)
                                                            {
                                                            if(isset($args[2]))
                                                                {
                                                                $entity->getArmorInventory()->setHelmet(Item::fromString($args[2]));
                                                                $sender->sendMessage($this->ok("Helmet updated. The NPC is now wearing it."));
                                                            }else{
                                                                $sender->sendMessage($this->usage("Please enter an item ID. Example: §e/vznpc edit <id> helmet 306"));
                                                            }
                                                        }else{
                                                            $sender->sendMessage($this->err("Only humans can wear armor. This type of NPC can't."));
                                                        }
                                                        return true;
                                                    case "chest":
                                                    case "shirt":
                                                    case "chestplate":
                                                        if($entity instanceof VZNPCHuman)
                                                            {
                                                            if(isset($args[2]))
                                                                {
                                                                $entity->getArmorInventory()->setChestplate(Item::fromString($args[2]));
                                                                $sender->sendMessage($this->ok("Chestplate updated. The NPC is now wearing it."));
                                                            }else{
                                                                $sender->sendMessage($this->usage("Please enter an item ID. Example: §e/vznpc edit <id> chestplate 307"));
                                                            }
                                                        }else{
                                                            $sender->sendMessage($this->err("Only humans can wear armor. This type of NPC can't."));
                                                        }
                                                        return true;
                                                    case "pants":
                                                    case "legs":
                                                    case "leggings":
                                                        if($entity instanceof VZNPCHuman)
                                                            {
                                                            if(isset($args[2]))
                                                                {
                                                                $entity->getArmorInventory()->setLeggings(Item::fromString($args[2]));
                                                                $sender->sendMessage($this->ok("Leggings updated. The NPC is now wearing them."));
                                                            }else{
                                                                $sender->sendMessage($this->usage("Please enter an item ID. Example: §e/vznpc edit <id> leggings 308"));
                                                            }
                                                        }else{
                                                            $sender->sendMessage($this->err("Only humans can wear armor. This type of NPC can't."));
                                                        }
                                                        return true;
                                                    case "feet":
                                                    case "boots":
                                                    case "shoes":
                                                        if($entity instanceof VZNPCHuman)
                                                            {
                                                            if(isset($args[2]))
                                                                {
                                                                $entity->getArmorInventory()->setBoots(Item::fromString($args[2]));
                                                                $sender->sendMessage($this->ok("Boots updated. The NPC is now wearing them."));
                                                            }else{
                                                                $sender->sendMessage($this->usage("Please enter an item ID. Example: §e/vznpc edit <id> boots 309"));
                                                            }
                                                        }else{
                                                            $sender->sendMessage($this->err("Only humans can wear armor. This type of NPC can't."));
                                                        }
                                                        return true;
                                                    case "hand":
                                                    case "item":
                                                    case "holding":
                                                    case "arm":
                                                    case "held":
                                                        if($entity instanceof VZNPCHuman)
                                                            {
                                                            if(isset($args[2])){
                                                                $entity->getInventory()->setItemInHand(Item::fromString($args[2]));
                                                                $sender->sendMessage($this->ok("Held item updated. The NPC is now holding it."));
                                                            }else{
                                                                $sender->sendMessage($this->usage("Please enter an item ID. Example: §e/vznpc edit <id> hand 276"));
                                                            }
                                                        }else{
                                                            $sender->sendMessage($this->err("Only humans can hold items. This type of NPC can't."));
                                                        }
                                                        return true;
                                                    case "setskin":
                                                    case "changeskin":
                                                    case "editskin":
                                                    case "skin":
                                                        if($entity instanceof VZNPCHuman)
                                                            {
                                                            $entity->setSkin($sender->getSkin());
                                                            $entity->respawnToAll();
                                                            $sender->sendMessage($this->ok("Skin updated. The NPC now uses your skin."));
                                                        }else{
                                                            $sender->sendMessage($this->err("Only humans have skins. Floating text can't use one."));
                                                        }
                                                        return true;
                                                    case "name":
                                                    case "customname":
                                                        if(isset($args[2]))
                                                            {
                                                            array_shift($args);
                                                            array_shift($args);
                                                            $newName = str_replace("{color}", "§", str_replace("{line}", "\n", trim(implode(" ", $args))));
                                                            $entity->namedtag->setTag(new StringTag("CustomName", $newName));
                                                            $entity->namedtag->setTag(new ByteTag("HasPlaceholders", Placeholders::isDynamic($newName) ? 1 : 0));
                                                            $entity->setDataProperty(2, Entity::DATA_TYPE_STRING, $newName);
                                                            $entity->respawnToAll();
                                                            $sender->sendMessage($this->ok("Name updated. Live placeholders like §f{c} §7and §f{tps} §7refresh on their own."));
                                                        }else{
                                                            $sender->sendMessage($this->usage("Please enter a name. Example: §e/vznpc edit <id> name {color}aWelcome{line}{color}7{c} online"));
                                                        }
                                                        return true;
                                                    case "listname":
                                                    case "nameonlist":
                                                    case "menuname":
                                                        if($entity instanceof VZNPCHuman)
                                                            {
                                                            if(isset($args[2]))
                                                                {
                                                                $type = 0;
                                                                array_shift($args);
                                                                array_shift($args);
                                                                $input = trim(implode(" ", $args));
                                                                switch(strtolower($input))
                                                                {
                                                                    case "remove":
                                                                    case "":
                                                                    case "disable":
                                                                    case "off":
                                                                    case "hide":
                                                                        $type = 1;
                                                                }
                                                                if($type === 0)
                                                                    {
                                                                    $entity->namedtag->setTag(new StringTag("MenuName", $input));
                                                                }else{
                                                                    $entity->namedtag->setTag(new StringTag("MenuName", ""));
                                                                }
                                                                $entity->respawnToAll();
                                                                $sender->sendMessage($this->ok("Player-list name updated."));
                                                            }else{
                                                                $sender->sendMessage($this->usage("Please enter a player-list name, or §eremove §7to clear it."));
                                                                return true;
                                                            }
                                                        }else{
                                                            $sender->sendMessage($this->err("Only humans can have a player-list name."));
                                                        }
                                                        return true;
                                                        break;
                                                    case "world":
                                                    case "movetoworld":
                                                    case "changeworld":
                                                        if(isset($args[2]))
                                                            {
                                                            $worldName = trim($args[2]);
                                                            $targetLevel = $this->getServer()->getLevelByName($worldName);
                                                            if($targetLevel === null)
                                                                {
                                                                if(!$this->getServer()->loadLevel($worldName))
                                                                    {
                                                                    $sender->sendMessage($this->err("The world §f" . $worldName . " §7does not exist. Check the name of the world folder."));
                                                                    return true;
                                                                }
                                                                $targetLevel = $this->getServer()->getLevelByName($worldName);
                                                            }
                                                            if($targetLevel === null)
                                                                {
                                                                $sender->sendMessage($this->err("The world §f" . $worldName . " §7exists but could not be loaded."));
                                                                return true;
                                                            }
                                                            $entity->teleport(new \pocketmine\level\Position($entity->x, $entity->y, $entity->z, $targetLevel));
                                                            $entity->respawnToAll();
                                                            $sender->sendMessage($this->ok("NPC moved to world §f" . $worldName . "§7."));
                                                        }else{
                                                            $sender->sendMessage($this->usage("Please enter a world name. Example: §e/vznpc edit <id> world survival"));
                                                        }
                                                        return true;
                                                        break;
                                                    case "worlds":
                                                    case "multiworld":
                                                        if(isset($args[2]))
                                                            {
                                                            $sub = strtolower(trim($args[2]));
                                                            if($sub === "clear"
                                                            ||$sub === "remove"
                                                            ||$sub === "none")
                                                                {
                                                                $this->clearEntityWorlds($entity);
                                                                $sender->sendMessage($this->ok("Linked worlds cleared. All copies of this NPC in other worlds were removed."));
                                                            }elseif($sub === "list")
                                                            {
                                                                $list = [];
                                                                if($entity->namedtag->hasTag("Worlds"))
                                                                    {
                                                                    foreach($entity->namedtag->getListTag("Worlds") as $w)
                                                                        {
                                                                        $list[] = (string)$w;
                                                                    }
                                                                }
                                                                if(!empty($list))
                                                                    {
                                                                    $sender->sendMessage($this->info("Linked worlds: §f" . implode(", ", $list)));
                                                                }else{
                                                                    $sender->sendMessage($this->info("This NPC isn't linked to any other worlds."));
                                                                }
                                                            }else{
                                                                array_shift($args);
                                                                array_shift($args);
                                                                $worldInput = trim(implode(" ", $args));
                                                                $worldNames = array_filter(array_map("trim", explode(",", $worldInput)));
                                                                if(empty($worldNames))
                                                                    {
                                                                    $sender->sendMessage($this->usage("Please enter at least one world name, separated by commas. Example: §e/vznpc edit <id> worlds survival,lobby"));
                                                                    return true;
                                                                }
                                                                $this->setEntityWorlds($entity, array_values($worldNames));
                                                                $sender->sendMessage($this->ok("This NPC will now also appear in: §f" . implode(", ", $worldNames)));
                                                            }
                                                        }else{
                                                            $sender->sendMessage($this->usage("Please enter world names separated by commas, or §eclear §7/ §elist§7."));
                                                        }
                                                        return true;
                                                        break;
                                                    case "trackworld":
                                                    case "settrackworld":
                                                    case "track":
                                                        if(isset($args[2]))
                                                            {
                                                            $tw = trim($args[2]);
                                                            if($tw === "clear"
                                                            ||$tw === "none"
                                                            ||$tw === "remove")
                                                            {
                                                                $entity->namedtag->setTag(new StringTag("TrackWorld", ""));
                                                                $sender->sendMessage($this->ok("Tracking cleared. §f{c} §7now shows the total players online."));
                                                            }else{
                                                                $twLevel = $this->getServer()->getLevelByName($tw);
                                                                if($twLevel === null && !$this->getServer()->loadLevel($tw))
                                                                    {
                                                                    $sender->sendMessage($this->err("The world §f" . $tw . " §7was not found."));
                                                                    return true;
                                                                }
                                                                $entity->namedtag->setTag(new StringTag("TrackWorld", $tw));
                                                                $entity->namedtag->setTag(new ByteTag("HasPlaceholders", 1));
                                                                $sender->sendMessage($this->ok("§f{c} §7will now count only players in §f" . $tw . "§7."));
                                                            }
                                                        }else{
                                                            $current = $entity->namedtag->getString("TrackWorld", "");
                                                            if($current !== "")
                                                                {
                                                                $sender->sendMessage($this->info("Currently tracking §f" . $current . "§7. §f{c} §7counts only that world."));
                                                            }else{
                                                                $sender->sendMessage($this->info("No world is tracked. §f{c} §7shows players online across all worlds."));
                                                            }
                                                        }
                                                        return true;
                                                        break;
                                                    case "namevisible":
                                                    case "customnamevisible":
                                                    case "tagvisible":
                                                    case "name_visible":
                                                    case "custom_name_visible":
                                                    case "tag_visible":
                                                        if(isset($args[2]))
                                                            {
                                                            $entity->setDataProperty(3, 0, intval($args[2]));
                                                            $entity->respawnToAll();
                                                            $sender->sendMessage($this->ok("Name visibility updated."));
                                                        }else{
                                                            $sender->sendMessage($this->usage("Please enter §e1 §7(show the name) or §e0 §7(hide it)."));
                                                        }
                                                        return true;
                                                    case "addc":
                                                    case "addcmd":
                                                    case "addcommand":
                                                        if(isset($args[2]))
                                                            {
                                                            array_shift($args);
                                                            array_shift($args);
                                                            $input = trim(implode(" ", $args));
                                                            if(isset($entity->namedtag->getCompoundTag("Commands")[$input]))
                                                                {
                                                                $sender->sendMessage($this->err("That command has already been added to this NPC."));
                                                                return true;
                                                            }
                                                            $entity->namedtag->getCompoundTag("Commands")[$input] = new StringTag($input, $input);
                                                            $sender->sendMessage($this->ok("Command added. It runs when a player taps the NPC."));
                                                        }else{
                                                            $sender->sendMessage($this->usage("Please enter a command. Example: §e/vznpc edit <id> addcommand spawn"));
                                                        }
                                                        return true;
                                                    case "delc":
                                                    case "delcmd":
                                                    case "delcommand":
                                                    case "removecommand":
                                                        if(isset($args[2]))
                                                            {
                                                            array_shift($args);
                                                            array_shift($args);
                                                            $input = trim(implode(" ", $args));
                                                            unset($entity->namedtag->getCompoundTag("Commands")[$input]);
                                                            $sender->sendMessage($this->ok("Command removed."));
                                                        }else{
                                                            $sender->sendMessage($this->usage("Please enter the command to remove. Use §elistcommands §7to see them."));
                                                        }
                                                        return true;
                                                    case "listcommands":
                                                    case "listcmds":
                                                    case "listcs":
                                                        if($entity->namedtag->hasTag("Commands"))
                                                            {
                                                            $id = 0;
                                                            foreach($entity->namedtag->getCompoundTag("Commands") as $cmd)
                                                                {
                                                                $id++;
                                                                $sender->sendMessage(TextFormat::DARK_GRAY . " #" . TextFormat::YELLOW . $id . TextFormat::DARK_GRAY . " » " . TextFormat::WHITE . $cmd->getValue());
                                                            }
                                                        }else{
                                                            $sender->sendMessage($this->info("This NPC has no tap commands. Add one with §e/vznpc edit <id> addcommand <command>§7."));
                                                        }
                                                        return true;
                                                    case "update":
                                                    case "fix":
                                                    case "migrate":
                                                        if($this->getConfig()->get($entity->getName()) !== false)
                                                            {
                                                            foreach($this->getConfig()->get($entity->getName()) as $cmd)
                                                                {
                                                                $entity->namedtag->getCompoundTag("Commands")[$cmd] = new StringTag($cmd, $cmd);
                                                            }
                                                            $sender->sendMessage($this->ok("Old commands migrated to the new format."));
                                                        }else{
                                                            $sender->sendMessage($this->info("No old commands found. Nothing to migrate."));
                                                        }
                                                        return true;
                                                    case "teleporthere":
                                                    case "tphere":
                                                    case "movehere":
                                                    case "bringhere":
                                                        $entity->teleport($sender);
                                                        $sender->sendMessage($this->ok("NPC teleported to you."));
                                                        $entity->respawnToAll();
                                                        return true;
                                                        break;
                                                    case "teleportto":
                                                    case "tpto":
                                                    case "goto":
                                                    case "teleport":
                                                    case "tp":
                                                        $sender->teleport($entity);
                                                        $sender->sendMessage($this->ok("You were teleported to the NPC."));
                                                        return true;
                                                        break;
                                                    default:
                                                        $sender->sendMessage($this->err("Unknown edit option. Type §e/vznpc edit <id> §7to see all options."));
                                                        return true;
                                                }
                                            }else{
                                                $sender->sendMessage($this->helpHeader);
                                                foreach($this->editArgs as $msgArg)
                                                    {
                                                    $sender->sendMessage(str_ireplace("<eid>", $args[0], $msgArg));
                                                }
                                                return true;
                                            }
                                        }else{
                                            $sender->sendMessage($this->err("That is not a VZNPC NPC, so this command can't change it. Use §e/vznpc id §7to check an NPC's ID."));
                                        }
                                    }else{
                                        $sender->sendMessage($this->err("No NPC with that ID exists in your current world. Type §e/vznpc id §7and hit the NPC to find its ID."));
                                    }
                                    return true;
                                }else{
                                    $sender->sendMessage($this->helpHeader);
                                    foreach($this->editArgs as $msgArg)
                                        {
                                        $sender->sendMessage($msgArg);
                                    }
                                    return true;
                                }
                            }else{
                                $sender->sendMessage($this->noperm);
                            }
                            return true;
                            break;
                        case "help":
                        case "?":
                            $sender->sendMessage($this->helpHeader);
                            foreach($this->mainArgs as $msgArg)
                                {
                                $sender->sendMessage($msgArg);
                            }
                            return true;
                            break;
                        case "add":
                        case "make":
                        case "create":
                        case "spawn":
                        case "apawn":
                            $type = array_shift($args);
                            if($type === null
                            ||$type === ""
                            ||$type === " ")
                                {
                                $sender->sendMessage($this->usage("Usage: §e/vznpc spawn <type> [world] [name]"));
                                $sender->sendMessage($this->info("Types: §fhuman§7, §ffloatingtext§7. Example: §e/vznpc spawn floatingtext survival {color}aWelcome{line}{c} online"));
                                return true;
                            }
                            $trackWorldName = "";
                            if(isset($args[0]))
                                {
                                $possibleWorld = $args[0];
                                $testLevel = $this->getServer()->getLevelByName($possibleWorld);
                                if($testLevel !== null)
                                    {
                                    $trackWorldName = array_shift($args);
                                }else{
                                    if($this->getServer()->loadLevel($possibleWorld))
                                        {
                                        $trackWorldName = array_shift($args);
                                    }
                                }
                            }
                            $name = str_replace("{color}", "§", str_replace("{line}", "\n", trim(implode(" ", $args))));
                            if(empty($name))
                                {
                                $name = $sender->getDisplayName();
                            }
                            $playerX    = $sender->getX();
                            $playerY    = $sender->getY();
                            $playerZ    = $sender->getZ();
                            $spawnLevel = $sender->getLevel();
                            $inventory  = $sender->getInventory();
                            if(!isset(self::SPAWN_TYPES[strtolower($type)]))
                                {
                                $sender->sendMessage($this->err("§f" . $type . " §7is not a valid type. Available types: §fhuman§7, §ffloatingtext§7."));
                                return true;
                            }
                            list($theOne, $typeToUse) = self::SPAWN_TYPES[strtolower($type)];
                            $chunk = $spawnLevel->getChunk((int)$playerX >> 4, (int)$playerZ >> 4, true);
                            $nbt = $this->makeNBT($typeToUse, $sender->getSkin()->getSkinData(), $sender->getSkin()->getSkinId(), $name, $inventory, $sender->getYaw(), $sender->getPitch(), $playerX, $playerY, $playerZ);
                            $nbt->setTag(new StringTag("TrackWorld", $trackWorldName));
                            $vznpcEntity = Entity::createEntity($typeToUse, $spawnLevel, $nbt);
                            if($vznpcEntity === null)
                                {
                                $sender->sendMessage($this->err("Couldn't create the NPC. Please try again, and report it if it keeps happening."));
                                return true;
                            }
                            $trackMsg = $trackWorldName !== "" ? " §7{c} counts only §f" . $trackWorldName . "§7." : "";
                            $sender->sendMessage($this->ok("§f" . $theOne . " §7spawned with the name §f\"" . $name . "§f\" §8(ID §e" . $vznpcEntity->getId() . "§8)§7." . $trackMsg));
                            $sender->sendMessage($this->info("Edit it with §e/vznpc edit " . $vznpcEntity->getId() . " <option> §7or remove it with §e/vznpc remove " . $vznpcEntity->getId() . "§7."));
                            if($vznpcEntity instanceof VZNPCHuman
                                && !($vznpcEntity instanceof VZNPCFloatingText))
                            {
                                $armorInv = $vznpcEntity->getArmorInventory();
                                $senderArmorInv = $sender->getArmorInventory();
                                $armorInv->setHelmet($senderArmorInv->getHelmet());
                                $armorInv->setChestplate($senderArmorInv->getChestplate());
                                $armorInv->setLeggings($senderArmorInv->getLeggings());
                                $armorInv->setBoots($senderArmorInv->getBoots());
                                $inv = $vznpcEntity->getInventory();
                                $inv->setHeldItemSlot($inventory->getHeldItemSlot());
                                $inv->setItemInHand($inventory->getItemInHand());
                            }
                            $vznpcEntity->spawnToAll();
                            return true;
                        default:
                            $sender->sendMessage($this->err("Unknown command. Type §e/vznpc help §7for the list of commands."));
                            return true;
                    }
                }else{
                    $sender->sendMessage($this->err("This command can only be used in-game."));
                    return true;
                }
        }
        return true;
    }

    private function makeNBT($type, $skin, $skinId, $name, $inv, $yaw, $pitch, $x, $y, $z)
    {
        $nbt = new CompoundTag;
        $nbt->setTag(new ListTag("Pos", [
            new DoubleTag("", $x),
            new DoubleTag("", $y),
            new DoubleTag("", $z)
        ]));
        $nbt->setTag(new ListTag("Rotation", [
            new FloatTag("", $yaw),
            new FloatTag("", $pitch)
        ]));
        $nbt->setTag(new ShortTag("Health", 1));
        $nbt->setTag(new StringTag("CustomName", $name));
        $nbt->setTag(new CompoundTag("Commands", []));
        $nbt->setTag(new StringTag("MenuName", ""));
        $nbt->setTag(new StringTag("VZNPCVersion", "1.3.0.0"));
        $nbt->setTag(new ByteTag("CustomNameVisible", 1));
        $nbt->setTag(new ByteTag("HasPlaceholders", Placeholders::isDynamic($name) ? 1 : 0));
        $nbt->setTag(new StringTag("TrackWorld", ""));
        switch($type)
        {
            case "VZNPCHuman":
                $nbt->setTag(new ListTag("Inventory", []));
                $nbt->setTag(new CompoundTag("Skin", ["Data" => new StringTag("Data", $skin), "Name" => new StringTag("Name", $skinId)]));
                break;
            case "VZNPCFloatingText":
                $nbt->setTag(new ListTag("Inventory", []));
                $nbt->setTag(new CompoundTag("Skin", ["Data" => new StringTag("Data", $skin), "Name" => new StringTag("Name", $skinId)]));
                break;
        }
        return $nbt;
    }
    private function makeGroupKey()
    {
        return "grp" . date("His") . mt_rand(1000, 999999);
    }
    private function getGroupMembers($groupKey)
    {
        $members = [];
        foreach($this->getServer()->getLevels() as $level)
            {
            foreach($level->getEntities() as $entity)
                {
                if($entity instanceof VZNPCHuman)
                    {
                    if($entity->namedtag->hasTag("GroupKey")
                        && $entity->namedtag->getString("GroupKey", "") === $groupKey)
                    {
                        $members[] = $entity;
                    }
                }
            }
        }
        return $members;
    }
    private function setEntityWorlds($entity, array $worldNames)
    {
        if(!$entity->namedtag->hasTag("GroupKey")
            || $entity->namedtag->getString("GroupKey", "") === "")
            {
            $entity->namedtag->setTag(new StringTag("GroupKey", $this->makeGroupKey()));
        }
        $entity->namedtag->setTag(new ByteTag("IsMaster", 1));
        $worldTags = [];
        foreach($worldNames as $worldName)
            {
            $worldTags[] = new StringTag("", $worldName);
        }
        $entity->namedtag->setTag(new ListTag("Worlds", $worldTags));
        $this->syncEntityWorlds($entity);
    }
    private function clearEntityWorlds($entity)
    {
        if($entity->namedtag->hasTag("GroupKey"))
            {
            $groupKey = $entity->namedtag->getString("GroupKey", "");
            foreach($this->getGroupMembers($groupKey) as $member)
                {
                if($member === $entity)
                    {
                    continue;
                }
                if($member instanceof VZNPCHuman)
                    {
                    $member->getInventory()->clearAll();
                }
                $member->close();
            }
        }
        $entity->namedtag->setTag(new ListTag("Worlds", []));
        $entity->namedtag->setTag(new StringTag("GroupKey", ""));
        $entity->namedtag->setTag(new ByteTag("IsMaster", 0));
    }
    private function syncEntityWorlds($master)
    {
        if(!$master->namedtag->hasTag("Worlds")
            || !$master->namedtag->hasTag("GroupKey"))
        {
            return;
        }
        $groupKey = $master->namedtag->getString("GroupKey", "");
        $wantedWorlds = [];
        foreach($master->namedtag->getListTag("Worlds") as $w)
            {
            $name = (string)$w;
            if($name !== "")
                {
                $wantedWorlds[$name] = true;
            }
        }
        $masterLevel = $master->getLevel();
        $masterWorldName = $masterLevel !== null ? $masterLevel->getName() : null;
        $existingByWorld = [];
        foreach($this->getGroupMembers($groupKey) as $member)
            {
            $level = $member->getLevel();
            if($level === null) {
                continue;
            }
            $existingByWorld[$level->getName()] = $member;
        }
        foreach(array_keys($wantedWorlds) as $worldName)
            {
            if($worldName === $masterWorldName)
                {
                continue;
            }
            if(isset($existingByWorld[$worldName]))
                {
                continue;
            }
            $level = $this->getServer()->getLevelByName($worldName);
            if($level === null)
                {
                if(!$this->getServer()->loadLevel($worldName))
                    {
                    continue;
                }
                $level = $this->getServer()->getLevelByName($worldName);
                if($level === null)
                    {
                    continue;
                }
            }
            $shortName = $this->getShortEntityName($master);
            $cloneNbt = unserialize(serialize($master->namedtag));
            $cloneNbt->setTag(new ListTag("Pos", [
                new DoubleTag("", $master->x),
                new DoubleTag("", $master->y),
                new DoubleTag("", $master->z)
            ]));
            $cloneNbt->setTag(new ByteTag("IsMaster", 0));
            $level->getChunk((int)$master->x >> 4, (int)$master->z >> 4, true);
            $clone = Entity::createEntity($shortName, $level, $cloneNbt);
            if($clone !== null)
                {
                $clone->spawnToAll();
            }
        }
        foreach($existingByWorld as $worldName => $member)
            {
            if($worldName === $masterWorldName){
                continue;
            }
            if(!isset($wantedWorlds[$worldName]))
                {
                if($member instanceof VZNPCHuman)
                    {
                    $member->getInventory()->clearAll();
                }
                $member->close();
            }
        }
    }
    public function syncAllWorlds()
    {
        foreach($this->getServer()->getLevels() as $level)
            {
            foreach($level->getEntities() as $entity)
                {
                if($entity instanceof VZNPCHuman)
                    {
                    if($entity->namedtag->hasTag("IsMaster") && (int)$entity->namedtag->getTag("IsMaster")->getValue() === 1)
                        {
                        $this->syncEntityWorlds($entity);
                    }
                }
            }
        }
    }
    private function getShortEntityName($entity)
    {
        $class = get_class($entity);
        $pos = strrpos($class, "\\");
        return $pos === false ? $class : substr($class, $pos + 1);
    }
    public function onEntityDamage(EntityDamageEvent $event)
    {
        $taker = $event->getEntity();
        if($taker instanceof VZNPCHuman)
            {
            if(!($event instanceof EntityDamageByEntityEvent))
                $event->setCancelled(true);
            if($event instanceof EntityDamageByEntityEvent)
                {
                $hitter = $event->getDamager();
                if(!$hitter instanceof Player)
                    {
                    $event->setCancelled(true);
                }
                if($hitter instanceof Player)
                    {
                    $giverName = $hitter->getName();
                    if($hitter instanceof Player)
                        {
                        if($hitter->isOp() && $hitter->getInventory()->getItemInHand()->getId() === 388)
                            {
                            $event->setCancelled(true);
                            $id = $taker->getId();
                            $this->removeNpcDeferred($taker);
                            $hitter->sendMessage($this->ok("NPC §e#" . $id . " §7removed with the emerald."));
                            return;
                        }
                        if(isset($this->hitSessions[$giverName]))
                            {
                            $event->setCancelled(true);
                            $this->removeNpcDeferred($taker);
                            unset($this->hitSessions[$giverName]);
                            $hitter->sendMessage($this->ok("NPC removed."));
                            return;
                        }
                        if(isset($this->idSessions[$giverName])) {
                            $hitter->sendMessage($this->info("This NPC's ID is §e" . $taker->getId() . "§7. Use it like §e/vznpc edit " . $taker->getId() . " <option>§7."));
                            unset($this->idSessions[$giverName]);
                            $event->setCancelled();
                            return;
                        }
                        $event->setCancelled(true);
                        $now = microtime(true);
                        if (isset($this->npcCommandCooldown[$giverName]) && ($now - $this->npcCommandCooldown[$giverName]) < self::NPC_COMMAND_COOLDOWN_SECONDS)
                            {
                            return;
                        }
                        $this->npcCommandCooldown[$giverName] = $now;
                        if($taker->namedtag->hasTag("Commands"))
                            {
                            $server = $this->getServer();
                            foreach ($taker->namedtag->getCompoundTag("Commands") as $cmd)
                                {
                                $server->dispatchCommand(new ConsoleCommandSender(), str_ireplace("{player}", $giverName, $cmd->getValue()));
                            }
                        }else{
                            $this->getLogger()->warning("Outdated entity; adding blank commands compound. Please restore commands manually with '/vznpc edit " . $taker->getId() . " fix'");
                            $taker->namedtag->setTag(new CompoundTag("Commands", []));
                        }
                    }

                }
            }
        }

    }

    public function onEntitySpawn(EntitySpawnEvent $ev)
    {
        $entity = $ev->getEntity();
        if($entity instanceof VZNPCHuman)
        {
            $clearLagg = $this->getServer()->getPluginManager()->getPlugin("ClearLagg");
            if($clearLagg !== null)
                {
                $clearLagg->exemptEntity($entity);
            }
        }
    }
    private function removeNpcDeferred($npc)
    {
        $this->getScheduler()->scheduleDelayedTask(new ClosureTask(function (int $currentTick) use ($npc):void
        {
            if($npc->isClosed())
                {
                return;
            }
            if($npc instanceof VZNPCHuman)
                {
                $npc->getInventory()->clearAll();
            }
            $this->clearEntityWorlds($npc);
            $npc->close();
        }), 1);
    }
    public function onPlayerInteract(PlayerInteractEvent $event)
    {
        $player = $event->getPlayer();
        if(!$player->isOp())
            {
            return;
        }
        $item = $player->getInventory()->getItemInHand();
        if($item->getId() !== 388)
            {
            return;
        }
        $pos    = $player->asVector3();
        $yaw    = deg2rad($player->yaw);
        $pitch  = deg2rad($player->pitch);
        $dirX   = -sin($yaw) * cos($pitch);
        $dirY   = -sin($pitch);
        $dirZ   = cos($yaw) * cos($pitch);
        $level  = $player->getLevel();
        $target = null;
        $minDist = PHP_INT_MAX;
        foreach($level->getEntities() as $entity)
            {
            if(!($entity instanceof VZNPCHuman))
                {
                continue;
            }
            $aimYs = [$entity->y + $entity->getEyeHeight() * 0.5];
            if($entity instanceof VZNPCFloatingText)
                {
                $aimYs = [$entity->y + 0.3, $entity->y + 1.0, $entity->y + 2.0];
            }
            foreach($aimYs as $aimY)
                {
                $dx = $entity->x - $pos->x;
                $dy = $aimY - ($pos->y + $player->getEyeHeight());
                $dz = $entity->z - $pos->z;
                $dist = sqrt($dx * $dx + $dy * $dy + $dz * $dz);
                if($dist > 5)
                    {
                    continue;
                }
                $dot = ($dx * $dirX + $dy * $dirY + $dz * $dirZ) / max($dist, 0.001);
                if($dot < 0.7)
                    {
                    continue;
                }
                if($dist < $minDist)
                    {
                    $minDist = $dist;
                    $target = $entity;
                }
            }
        }
        if($target === null)
            {
            return;
        }
        $event->setCancelled(true);
        $id = $target->getId();
        $this->removeNpcDeferred($target);
        $player->sendMessage($this->ok("NPC §e#" . $id . " §7removed with the emerald."));
    }
}