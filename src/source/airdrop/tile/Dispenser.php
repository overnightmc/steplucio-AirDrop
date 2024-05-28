<?php

namespace source\airdrop\tile;

use pocketmine\block\tile\Spawnable;
use pocketmine\block\tile\Container;
use pocketmine\block\tile\ContainerTrait;
use pocketmine\block\tile\Nameable;
use pocketmine\block\tile\NameableTrait;
use pocketmine\math\Vector3;
use pocketmine\nbt\tag\CompoundTag;
use pocketmine\world\World;
use source\airdrop\inventory\DispenserInventory;

class Dispenser extends Spawnable implements Container, Nameable{
	use NameableTrait;
	use ContainerTrait;

    protected DispenserInventory $inventory;

    public function __construct(World $world,Vector3 $pos){
        parent::__construct($world,$pos);
        $this->inventory=new DispenserInventory($this->position);
    }

    public function getDefaultName():string{
        return "Dispenser";
    }

    public function getInventory():DispenserInventory{
        return $this->inventory;
    }

    public function getRealInventory():DispenserInventory{
        return $this->inventory;
    }

    public function readSaveData(CompoundTag $nbt):void{
        $this->loadName($nbt);
		$this->loadItems($nbt);
    }
    
    protected function writeSaveData(CompoundTag $nbt):void{
        $this->saveName($nbt);
		$this->saveItems($nbt);
    }
}