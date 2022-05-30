<?php
declare(strict_types=1);

namespace TradeNPC;

use pocketmine\entity\Human;
use pocketmine\entity\NPC as PMNPC;
use pocketmine\event\entity\EntityDamageEvent;
use pocketmine\item\Item;
use pocketmine\nbt\LittleEndianNbtSerializer;
use pocketmine\nbt\TreeRoot;
use pocketmine\nbt\tag\ByteTag;
use pocketmine\nbt\tag\CompoundTag;
use pocketmine\nbt\tag\IntTag;
use pocketmine\nbt\tag\StringTag;
use pocketmine\nbt\tag\ListTag;
use pocketmine\nbt\tag\NameTag;
use pocketmine\entity\Ageable;
use pocketmine\entity\EntitySizeInfo;

class TradeNPC extends Human implements Ageable
{
    
	/** @var CompoundTag|null */
	protected $shop = null;
	
	private $baby = false;
	
	protected function getInitialSizeInfo() : EntitySizeInfo{
		return new EntitySizeInfo(1.8, 0.6); //TODO: eye height??
	}

	public function getName() : string{
		return "TradeNPC";
	}
    
    public function isBaby() :bool {
    	return $this->baby;
    }

	public function makeRecipe(Item $buyA, Item $buyB, Item $sell): CompoundTag
	{
		$tag = CompoundTag::create()
			->setTag("buyA", $buyA->nbtSerialize())
			->setTag("buyB", $buyB->nbtSerialize())
			->setTag("sell", $sell->nbtSerialize())
			->setInt("maxUses", 32767)
			->setByte("rewardExp", 0)
			->setInt("uses", 0)
			->setString("label", "");
		return $tag;
	}

	public function addTradeItem(Item $buyA,Item $buyB, Item $sell): void
	{
		$this->getTagFunction($this->shop)->getListTag("Recipes")->push($this->makeRecipe($buyA, $buyB, $sell));
	}
    
    public function getTagFunction($tag) {
    	if($tag instanceof CompoundTag) {
    	    $tag1 = $tag;
        } elseif($tag instanceof TreeRoot){
			$tag1 = $tag->getTag();
		}
        return $tag1;
    }
    
	public function getShopCompoundTag()
	{
		if($this->shop instanceof CompoundTag){
			return $this->shop;
		} elseif($this->shop instanceof TreeRoot){
			return $this->shop->mustGetCompoundTag();
		}
	}

	public function saveNBT(): CompoundTag
	{
		$nbt = parent::saveNBT();
		Main::getInstance()->saveData($this);
		return $nbt;
	}

	public function getSaveNBT(): string
	{
		return (new LittleEndianNbtSerializer)->write($this->getTreeRoot($this->shop));
	}

	public function loadData($nbt): void
	{
		$this->shop = $nbt;
	}
	public function getTreeRoot($tag) {
		if($tag instanceof CompoundTag) {
			return new TreeRoot($tag);
		} elseif($tag instanceof TreeRoot) {
			return $tag;
		}
	}

	public function initEntity(CompoundTag $nbt): void
	{
		parent::initEntity($nbt);
		if ($this->shop === null) {
			Main::getInstance()->loadData($this);
		}
		$this->setNameTagAlwaysVisible(true);
	}

	public function getTradeInventory(): TradeInventory
	{
		return new TradeInventory($this);
	}

	public function attack(EntityDamageEvent $source): void
	{
		$source->cancel();
	}
}