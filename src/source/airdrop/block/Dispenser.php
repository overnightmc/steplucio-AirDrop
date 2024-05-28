<?php

namespace source\airdrop\block;

use muqsit\invmenu\InvMenu;
use pocketmine\block\Block;
use pocketmine\block\Opaque;
use pocketmine\block\utils\AnyFacingTrait;
use pocketmine\data\runtime\RuntimeDataDescriber;
use pocketmine\item\Item;
use pocketmine\math\Vector3;
use pocketmine\player\Player;
use pocketmine\world\BlockTransaction;
use source\airdrop\Loader;
use source\airdrop\tile\Dispenser as TileDispenser;

class Dispenser extends Opaque{
    use AnyFacingTrait;

    protected bool $triggered=false;

    protected function describeBlockOnlyState(RuntimeDataDescriber $w):void{
        $w->facing($this->facing);
        $w->bool($this->triggered);
    }

    public function isTriggered():bool{
        return $this->triggered;
    }

    public function setTriggered(bool $triggered):self{
        $this->triggered = $triggered;
        return $this;
    }

    public function onInteract(Item $item,int $face,Vector3 $clickVector,?Player $player=null,array &$returnedItems=[]) : bool{
		if($player instanceof Player){
			$tile = $this->position->getWorld()->getTile($this->position);
            if($tile instanceof TileDispenser){
                ($menu=InvMenu::create(Loader::TYPE_DISPENSER))->setInventory($tile->getInventory());
                $menu->send($player,$tile->getName());
            }
		}
		return true;
	}

    public function place(BlockTransaction $tx,Item $item,Block $blockReplace,Block $blockClicked,int $face,Vector3 $clickVector,?Player $player=null):bool{
        $tx->addBlock($blockReplace->position,$this->setFacing($face));
        return true;
    }
}