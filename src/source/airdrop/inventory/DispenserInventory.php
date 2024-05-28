<?php

namespace source\airdrop\inventory;

use Closure;
use pocketmine\block\inventory\BlockInventory;
use pocketmine\block\inventory\BlockInventoryTrait;
use pocketmine\inventory\SimpleInventory;
use pocketmine\world\Position;

class DispenserInventory extends SimpleInventory implements BlockInventory{
    use BlockInventoryTrait;

    protected Closure $open_callback;

    public function __construct(Position $position){
        $this->holder=$position;
        parent::__construct(9);
    }
}