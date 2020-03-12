<?php

/**
 * @name DamageTag
 * @main DamageTag\DamageTag
 * @author AvasKr
 * @version 1.0.0
 * @api 3.10.0
 */

namespace DamageTag;

use pocketmine\plugin\PluginBase;
use pocketmine\scheduler\Task;

use pocketmine\event\Listener;

use pocketmine\event\entity\EntityDamageEvent;
use pocketmine\event\entity\EntityDamageByEntityEvent;
use pocketmine\event\entity\EntityRegainHealthEvent;

use pocketmine\entity\Entity;
use pocketmine\level\Position;

use pocketmine\utils\UUID;

use pocketmine\item\Item;
use pocketmine\item\ItemFactory;

use pocketmine\network\mcpe\protocol\AddPlayerPacket;
use pocketmine\network\mcpe\protocol\RemoveActorPacket;

class DamageTag extends PluginBase
{

    public const DAMAGETAG_TYPE_ATTACK = 0;

    public const DAMAGETAG_TYPE_REGAIN = 1;

    public $color = [
        "§c",
        "§a"
    ];

    public $packetEntityId = [];


    public function onEnable (): void
    {
        $this->getServer ()->getPluginManager ()->registerEvents (new EventListener ($this), $this);
    }

    public function getPlayersInRadius (Position $pos, float $radius = 15.0): array
    {
        $arr = [];
        foreach ($pos->level->getPlayers () as $players) {
            if ($pos->distance ($players) <= $radius) {
                $arr [] = $players;
            }
        }
        return $arr;
    }

    public function placeTag (Position $pos, float $amount = 0.0, int $type = 0): void
    {
        $packet = new AddPlayerPacket ();
        $entityId = Entity::$entityCount ++;
        $packet->entityRuntimeId = $entityId;
        $packet->entityUniqueId = $entityId;
        $this->packetEntityId [$entityId] = true;
        $packet->position = $pos->add (0, 1);
        $uuid = UUID::fromRandom ();
        $packet->uuid = $uuid;
        $packet->item = ItemFactory::get (Item::AIR);
        $packet->username = $this->color [$type] ?? "§c{$amount}";
        $flags = (1 << Entity::DATA_FLAG_IMMOBILE);
        $packet->metadata = [
            Entity::DATA_FLAGS => [
                Entity::DATA_TYPE_LONG,
                $flags
            ],
            Entity::DATA_SCALE => [
                Entity::DATA_TYPE_FLOAT,
                0.01
            ]
        ];
        $around = $this->getPlayersInRadius ($pos, 15.5);
        foreach ($around as $players) {
            $players->sendDataPacket ($packet);
        }
        $this->getScheduler ()->scheduleDelayedTask (new class ($this, $entityId) extends Task{
            protected $plugin;
            protected $entityId;


            public function __construct (DamageTag $plugin, int $entityId)
            {
                $this->plugin = $plugin;
                $this->entityId = $entityId;
            }

            public function onRun (int $currentTick)
            {
                $this->plugin->deleteTag ($this->entityId);
            }
        }, 30);
    }

    public function deleteTag (int $entityId): void
    {
        if (isset ($this->packetEntityId [$entityId])) {
            $packet = new RemoveActorPacket ();
            $packet->entityUniqueId = $entityId;
            foreach ($this->getServer ()->getOnlinePlayers () as $players) {
                $players->sendDataPacket ($packet);
            }
        }
    }
}

class EventListener implements Listener
{

    /** @var null|DamageTag */
    protected $plugin = null;


    public function __construct (DamageTag $plugin)
    {
        $this->plugin = $plugin;
    }

    public function onAttack (EntityDamageEvent $event): void
    {
        if (!$event->isCancelled ())
            if ($event instanceof EntityDamageByEntityEvent) {
                if (($player = $event->getDamager ()) instanceof Player) {
                    $this->plugin->placeTag ($event->getEntity (), $event->getBaseDamage (), 0);
                }
            }
    }

    public function onRegainHealth (EntityRegainHealthEvent $event): void
    {
        if (!$event->isCancelled ())
            if (($player = $event->getDamager ()) instanceof Player) {
                $this->plugin->placeTag ($player, $event->getAmount (), 1);
            }
    }
}
