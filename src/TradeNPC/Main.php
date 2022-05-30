<?php
declare(strict_types=1);

namespace TradeNPC;

use pocketmine\player\Player;
use pocketmine\entity\{Entity, EntityFactory, EntityDataHelper};
use pocketmine\event\Listener;
use pocketmine\utils\TextFormat;
use pocketmine\plugin\PluginBase;
use pocketmine\world\World;
use pocketmine\math\Vector2;
use pocketmine\entity\Location;
use pocketmine\entity\Human;
use pocketmine\utils\BinaryDataException;
use pocketmine\item\{Item, ItemFactory};
use pocketmine\nbt\LittleEndianNbtSerializer;
use pocketmine\nbt\ReaderTracker;
use pocketmine\event\inventory\InventoryOpenEvent;
use muqsit\invmenu\{InvMenu, InvMenuHandler};
use pocketmine\command\{CommandSender, Command};
use pocketmine\network\mcpe\NetworkBinaryStream;
use pocketmine\event\server\DataPacketReceiveEvent;
use pocketmine\inventory\transaction\action\SlotChangeAction;
use pocketmine\network\mcpe\protocol\types\NetworkInventoryAction;
use pocketmine\item\enchantment\{Enchantment, EnchantmentInstance};
use pocketmine\nbt\tag\{ByteArrayTag, CompoundTag, ListTag, StringTag, NameTag};
use pocketmine\network\mcpe\protocol\{LevelEventPacket, LevelSoundEventPacket, types\LevelSoundEvent, types\LevelEvent};
use pocketmine\event\player\{PlayerMoveEvent, PlayerQuitEvent, PlayerChatEvent};
use muqsit\invmenu\transaction\{InvMenuTransaction as Transaction, InvMenuTransactionResult as TransactionResult};
use pocketmine\network\mcpe\protocol\{ActorEventPacket, ContainerClosePacket, InventoryTransactionPacket, LoginPacket, MovePlayerPacket, types\ActorEvent};
use pocketmine\network\mcpe\protocol\types\inventory\{UseItemOnEntityTransactionData, NormalTransactionData, TransactionData};
use pocketmine\network\mcpe\JwtUtils;
use pocketmine\nbt\NBT;
use slapper\entities\SlapperHuman;

class Main extends PluginBase implements Listener
{
    
    public $currentWindow = null;
    
	public const CHEST = [
		0, 1, 2, 3, 4, 5, 6, 7, 8, 9, 10, 11, 12, 13, 14, 15, 16, 17, 18, 19, 20, 21, 22, 23, 24, 25, 26
	];

	public const ITEM_FORMAT = [
        "id" => 1,
        "damage" => 0,
        "count" => 1,
        "display_name" => "",
        "lore" => [

        ],
        "enchants" => [

        ],
    ];

	protected $deviceOSData = [];

	public $fullItem = [];
	public $name = null;
	public $start = false;

	public $turn = false;

	public $enti = null;

	private static $instance = null;

	public $itemList = [];

	public function onLoad() :void
	{
		self::$instance = $this;
	}

	public static function getInstance(): Main
	{
		return self::$instance;
	}

	public function onEnable() :void
	{
		$this->saveResource("config.yml");
		/*EntityFactory::getInstance()->register(TradeNPC::class, static function(World $world, CompoundTag $nbt): TradeNPC{
            return new TradeNPC(EntityDataHelper::parseLocation($nbt, $world), $nbt);
        }, ['tradenpc']);*/
        EntityFactory::getInstance()->register(TradeNPC::class, function (World $world, CompoundTag $nbt): TradeNPC {
            return new TradeNPC(EntityDataHelper::parseLocation($nbt, $world), Human::parseSkinNBT($nbt), $nbt);
        }, ['TradeNPC', 'Trade']);
		//Entity::registerEntity(TradeNPC::class, true, ["tradenpc"]);
		$this->getServer()->getPluginManager()->registerEvents($this, $this);
		if(!InvMenuHandler::isRegistered()){
            InvMenuHandler::register($this);
        }
		$this->menu = InvMenu::create(InvMenu::TYPE_CHEST);
	}

	public function onChat(PlayerChatEvent $ev){
		$p = $ev->getPlayer();
		$chat = $ev->getMessage();
		if($this->turn and $this->name == $p->getName()){
        	$entity = $this->getEntityName($chat);
            if($entity === null) {
            	return;
            }
        	for($i=0;$i <= $this->fullItem;$i++){
        		$item1 = $this->fullItem[$i];
        		$item2 = $this->fullItem[$i+9];
        		$item3 = $this->fullItem[$i+18];
        		if($item1->isNull() or $item2->isNull() or $item3->isNull()){
        			unset(TradeDataPool::$editNPCData[$p->getName()]);
        			break;
        		}
        		TradeDataPool::$editNPCData[$p->getName()]["buyA"] = $item1;
				TradeDataPool::$editNPCData[$p->getName()]["buyB"] = $item2;
				TradeDataPool::$editNPCData[$p->getName()]["sell"] = $item3;
				$buya = TradeDataPool::$editNPCData[$p->getName()]["buyA"];
				$buyb = TradeDataPool::$editNPCData[$p->getName()]["buyB"];
				$sell = TradeDataPool::$editNPCData[$p->getName()]["sell"];
				$entity->addTradeItem($buya, $buyb, $sell);
				unset(TradeDataPool::$editNPCData[$p->getName()]);
				$p->sendMessage("Đã thêm item vào trade!");
				$this->saveall();
        	}
        	$this->fullItem = [];
        	$this->turn = false;
        	$this->name = null;
        	unset(TradeDataPool::$editNPCData[$p->getName()]);
			$ev->cancel();
        }
	}

	public function getEntityName(string $chat){
		foreach ($this->getServer()->getWorldManager()->getWorlds() as $level) {
			foreach ($level->getEntities() as $entity) {
				if ($entity instanceof TradeNPC) {
					if ($entity->getNameTag() === $chat) {
						$this->turn = false;
						return $entity;
						break;
					}
				}
			}
		}
	}

	public function setItemss($p){
		$this->menu->setName("§eTradeNPC");
		$this->menu->setListener(function(Transaction $transaction) : TransactionResult{
			$inv = $this->menu->getInventory();
            $player = $transaction->getPlayer();
            if($transaction->getItemClicked()->getId() == 160){
                foreach(self::CHEST as $slot){
                	$item = $this->menu->getInventory()->getItem($slot);
                	if($item->getId() == 160){
                		continue;
                	}
                	$this->fullItem[] = $item;
                }
                $this->turn = true;
                $this->name = $player->getName();
                $player->sendMessage("Nhập Tên NPC");
               # $player->removeCurrentWindow($this->menu->getInventory());
                return $transaction->discard();
            }
            return $transaction->continue();
		});
		$xacnhan = ItemFactory::getInstance()->get(160,7,1);
		$xacnhan->setCustomName("Xác nhận\nKhi bấm rồi nhập tên NPC");
		$thoat = ItemFactory::getInstance()->get(331,0,1);
		$thoat->setCustomName("Thoát");
		$inv = $this->menu->getInventory();
		$inv->setItem(26, $xacnhan);
		$this->menu->send($p);
	}

	public function loadData(TradeNPC $npc)
	{
		if (file_exists($this->getDataFolder() . $npc->getNameTag() . ".dat")) {
			$nbt = (new LittleEndianNbtSerializer)->read(file_get_contents($this->getDataFolder() . $npc->getNameTag() . ".dat"));
		} else {
			$nbt = CompoundTag::create()
			->setTag("Recipes", new ListTag([]), NBT::TAG_Compound
			);
		}
		$npc->loadData($nbt);
	}
	
	public function saveall(){
		foreach($this->getServer()->getOnlinePlayers() as $player){
			$player->save();
		}

		foreach($this->getServer()->getWorldManager()->getWorlds() as $world){
			$world->save(true);
		}
	}
	
	public function distance($posx, $posz, $x, $z) : float{
		return sqrt($this->distanceSquared($posx, $posz, $x, $z));
	}

	public function distanceSquared($posx, $posz, $x, $z) : float{
		return (($x - $posx) ** 2) + (($z - $posz) ** 2);
	}
	
	public function onCommand(CommandSender $sender, Command $command, string $label, array $args): bool
	{
		if (!$sender instanceof Player) {
			return true;
		}
		if (!isset($args[0])) {
			$args[0] = "x";
		}
		if($this->getServer()->isOp($sender->getName())){
		switch ($args[0]) {
			case "create":
				array_shift($args);
				$name = implode(" ", $args);
				if (!isset($name)) {
					$sender->sendMessage("Hãy sử dụng: /npc create (tên)");
					break;
				}
				$nbt = CompoundTag::create();
				$nbt->setTag("Name", new StringTag($sender->getSkin()->getSkinId()));
				$nbt->setTag("Data", new ByteArrayTag($sender->getSkin()->getSkinData()));
				$nbt->setTag("CapeData", new ByteArrayTag($sender->getSkin()->getCapeData()));
				$nbt->setTag("GeometryName", new StringTag($sender->getSkin()->getGeometryName()));
				$nbt->setTag("GeometryData", new ByteArrayTag($sender->getSkin()->getGeometryData()));
				/** @var TradeNPC $entity */
				//$entity = Entity::createEntity("tradenpc", $sender->getWorld(), $nbt);
				$entity = new TradeNPC(Location::fromObject($sender->getPosition()->add(0.5, 0, 0.5), $sender->getPosition()->getWorld(), $sender->getLocation()->getYaw() ?? 0, $sender->getLocation()->getPitch() ?? 0), $sender->getSkin(), $nbt);
                $entity->setNameTag($name);
				$entity->setNameTagAlwaysVisible(true);
				$entity->spawnToAll();
				break;
			case "setitem":
				$this->setItemss($sender);
				break;
			case "remove":
				array_shift($args);
				$name = implode(" ", $args);
				if (!isset($name)) {
					$sender->sendMessage("Hãy sử dụng: /npc remove (tên)");
					break;
				}
				if (!file_exists($this->getDataFolder() . $name . ".dat")) {
					$sender->sendMessage("Tên NPC này hiện không tồn tại!");
					break;
				}
				unlink($this->getDataFolder() . $name . ".dat");
				$sender->sendMessage("Đã xóa NPC!");
				$this->saveall();
				foreach ($this->getServer()->getWorldManager()->getWorlds() as $level) {
					foreach ($level->getEntities() as $entity) {
						if ($entity instanceof TradeNPC) {
							if ($entity->getNameTag() === $name) {
								$entity->close();
								break;
							}
						}
					}
				}
				break;
			default:
				foreach ([
							 ["/npc create (tên)", "Tạo ra npc trade"],
							 ["/npc setitem", "Add item vào trade"],
							 ["/npc remove", "Xóa npc"]
						 ] as $usage) {
					$sender->sendMessage($usage[0] . " - " . $usage[1]);
				}
		}
	}
		return true;
	}

	/**
	 * @param DataPacketReceiveEvent $event
	 *
	 * @author
	 */
	public function handleDataPacket(DataPacketReceiveEvent $event)
	{
		$player = $event->getOrigin()->getPlayer();
		$packet = $event->getPacket();
		if ($packet instanceof ActorEventPacket) {
			if ($packet->eventId === ActorEvent::COMPLETE_TRADE) {
				if (!isset(TradeDataPool::$interactNPCData[$player->getName()])) {
					return;
				}
				$data = TradeDataPool::$interactNPCData[$player->getName()]->getShopCompoundTag()->getListTag("Recipes")->get($packet->eventData);
				if ($data instanceof CompoundTag) {
					$buya = Item::nbtDeserialize($data->getCompoundTag("buyA"));
					$buyb = Item::nbtDeserialize($data->getCompoundTag("buyB"));
					$sell = Item::nbtDeserialize($data->getCompoundTag("sell"));
					if ($player->getInventory()->contains($buya) and $player->getInventory()->contains($buyb)) {// Prevents https://github.com/alvin0319/TradeNPC/issues/3
						$player->getInventory()->removeItem($buya);
						$player->getInventory()->removeItem($buyb);
						$player->getInventory()->addItem($sell);
						$volume = mt_rand();
					} else {
						$volume = mt_rand();
					}
				}
				// unset(TradeDataPool::$interactNPCData[$player->getName()]);
			}
		}
		if ($packet instanceof InventoryTransactionPacket) {
			//7: PC
			if($packet->trData instanceof NormalTransactionData){
				foreach ($packet->trData->getActions() as $action) {
					if ($action instanceof NetworkInventoryAction) {
						if (isset(TradeDataPool::$windowIdData[$player->getName()]) and $action->windowId === TradeDataPool::$windowIdData[$player->getName()]) {
							$player->getInventory()->addItem($action->oldItem);
							$player->getInventory()->removeItem($action->newItem);
						}
					}
				}
			} elseif($packet->trData instanceof UseItemOnEntityTransactionData) {
				$entity = $player->getWorld()->getEntity($packet->trData->getActorRuntimeId());
				if ($entity instanceof TradeNPC) {
					$this->setCWindow($entity->getTradeInventory(), $player);
				}
			}
		}
		if ($packet instanceof LoginPacket) {
			$data = JwtUtils::parse($packet->clientDataJwt);
            $device = (int)$data[1]["DeviceOS"];
			//$device = (int)$packet->clientData["DeviceOS"];
			//foreach($data as $datas) {
			   //var_dump($data);
			//}
			$this->deviceOSData[strtolower($data[1]["ThirdPartyName"])] = $device;
		}
		if ($packet instanceof ContainerClosePacket) {
			if (isset(TradeDataPool::$windowIdData[$player->getName()])) {
				$pk = new ContainerClosePacket();
				$pk->windowId = 255; // ??
				$player->getNetworkSession()->sendDataPacket($pk);
			}
		}
	}
	
	public function setCWindow(TradeInventory $inventory, $player) : bool{
		//$player->currentWindow = $inventory;
		if($inventory === $this->currentWindow){
			return true;
		}
		$ev = new InventoryOpenEvent($inventory, $player);
		$ev->call();
		if($ev->isCancelled()){
			return false;
		}

		//TODO: client side race condition here makes the opening work incorrectly
		$player->removeCurrentWindow();

		if(($inventoryManager = $player->getNetworkSession()->getInvManager()) === null){
			throw new \InvalidArgumentException("Player cannot open inventories in this state");
		}
		//$player->logger->debug("Opening inventory " . get_class($inventory) . "#" . spl_object_id($inventory));
		//$inventoryManager->onCurrentWindowChange($inventory);
		$inventory->onOpen($player);
		$this->currentWindow = $inventory;
		return true;
	}

	public function onQuit(PlayerQuitEvent $event)
	{
		$player = $event->getPlayer();
		if (isset($this->deviceOSData[strtolower($player->getName())])) unset($this->deviceOSData[strtolower($player->getName())]);
	}

	public function saveData(TradeNPC $npc)
	{
		if(!file_exists($this->getDataFolder() . $npc->getNameTag() . ".dat")) {
			fopen($this->getDataFolder() . $npc->getNameTag() . ".dat", "w");
		}
		file_put_contents($this->getDataFolder() . $npc->getNameTag() . ".dat", $npc->getSaveNBT());
		//file_put_contents($this->getDataFolder() . $npc->getNameTag() . ".dat", $npc->getSaveNBT());
	}

	public function onDisable() :void
	{
		foreach ($this->getServer()->getWorldManager()->getWorlds() as $level) {
			foreach ($level->getEntities() as $entity) {
				if ($entity instanceof TradeNPC) {
					file_put_contents($this->getDataFolder() . $entity->getNameTag() . ".dat", $entity->getSaveNBT());
				}
			}
		}
	}
}