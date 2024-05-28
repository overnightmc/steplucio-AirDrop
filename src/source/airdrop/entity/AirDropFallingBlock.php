<?php

namespace source\airdrop\entity;

use pocketmine\block\Block;
use pocketmine\data\bedrock\item\SavedItemStackData;
use pocketmine\entity\Entity;
use pocketmine\entity\EntitySizeInfo;
use pocketmine\entity\Location;
use pocketmine\event\entity\EntityDamageEvent;
use pocketmine\item\Item;
use pocketmine\math\Facing;
use pocketmine\math\Vector3;
use pocketmine\nbt\NBT;
use pocketmine\nbt\tag\CompoundTag;
use pocketmine\nbt\tag\ListTag;
use pocketmine\network\mcpe\convert\TypeConverter;
use pocketmine\network\mcpe\protocol\types\entity\EntityIds;
use pocketmine\network\mcpe\protocol\types\entity\EntityMetadataCollection;
use pocketmine\network\mcpe\protocol\types\entity\EntityMetadataProperties;
use pocketmine\utils\TextFormat;
use pocketmine\utils\Utils;
use pocketmine\world\particle\HugeExplodeParticle;
use pocketmine\world\sound\ExplodeSound;
use source\airdrop\block\Dispenser as BlockDispenser;
use source\airdrop\ExtraVanillaBlocks;
use source\airdrop\Loader;
use source\airdrop\tile\Dispenser;

class AirDropFallingBlock extends \pocketmine\entity\Entity{

	public static function getNetworkTypeId() : string{ return EntityIds::FALLING_BLOCK; }

	protected Block|BlockDispenser $block;
    /** @var Item[] */
    protected array $loot=[];

	public function __construct(Location $location, ?CompoundTag $nbt = null){
		$this->block = ExtraVanillaBlocks::DISPENSER();
		parent::__construct($location, $nbt);
	}

	protected function getInitialSizeInfo() : EntitySizeInfo{ return new EntitySizeInfo(0.98, 0.98); }

	protected function getInitialDragMultiplier() : float{ return 0.02; }

	protected function getInitialGravity() : float{ return 0.04; }

    public function setLoot(array $loot):self{
        Utils::validateArrayValueType($loot,function(Item $item):void{});
        $this->loot=$loot;
        return $this;
    }

    public function getLoot():array{
        return $this->loot;
    }

	public function canCollideWith(Entity $entity) : bool{
		return false;
	}

	public function canBeMovedByCurrents() : bool{
		return false;
	}

	public function attack(EntityDamageEvent $source) : void{
		if($source->getCause() === EntityDamageEvent::CAUSE_VOID){
			parent::attack($source);
		}
	}

	protected function entityBaseTick(int $tickDiff = 1) : bool{
		if($this->closed)return false;
		$hasUpdate = parent::entityBaseTick($tickDiff);
		if(!$this->isFlaggedForDespawn()){
			$world = $this->getWorld();
			$pos = $this->location->add(-$this->size->getWidth() / 2, $this->size->getHeight(), -$this->size->getWidth() / 2)->floor();
			$this->block->position($world,$pos->x,$pos->y,$pos->z);
			if($this->onGround){
				$this->flagForDespawn();
				$world->setBlock($pos,$this->block->setFacing(Facing::DOWN));
				$world->addParticle($this->getPosition(),new HugeExplodeParticle());
				$world->addSound($this->getPosition(),new ExplodeSound());
				$tile=$world->getTile($pos);
                if($tile instanceof Dispenser){
					$tile->setName(TextFormat::colorize(Loader::getInstance()->getConfig()->getNested('block.inventory_display','')));
					$items=$this->getLoot();
					shuffle($items);
					shuffle($items);
					for($i=0;$i < $tile->getInventory()->getSize();$i++)$tile->getInventory()->setItem($i,$items[array_rand($items)]);
				}
				$hasUpdate = true;
			}
		}
		return $hasUpdate;
	}

	public function getBlock() : Block{
		return $this->block;
	}

	protected function initEntity(CompoundTag $nbt): void{
		parent::initEntity($nbt);
		$LootTag = $nbt->getTag('Loot');
		if($LootTag instanceof ListTag && $LootTag->getTagType() === NBT::TAG_Compound){
			$newContents = [];
			/** @var CompoundTag */
			foreach($LootTag as $itemNBT)$newContents[$itemNBT->getByte(SavedItemStackData::TAG_SLOT)]=Item::nbtDeserialize($itemNBT);
			$this->setLoot($newContents);
		}
	}

	public function saveNBT():CompoundTag{
		$nbt = parent::saveNBT();
		$loot = [];
        foreach($this->loot as $slot => $item)$loot[] = $item->nbtSerialize($slot);
        $nbt->setTag('Loot',new ListTag($loot, NBT::TAG_Compound));
		return $nbt;
	}

	protected function syncNetworkData(EntityMetadataCollection $properties) : void{
		parent::syncNetworkData($properties);

		$properties->setInt(EntityMetadataProperties::VARIANT, TypeConverter::getInstance()->getBlockTranslator()->internalIdToNetworkId($this->block->getStateId()));
	}

	public function getOffsetPosition(Vector3 $vector3) : Vector3{
		return $vector3->add(0, 0.49, 0); //TODO: check if height affects this
	}
}