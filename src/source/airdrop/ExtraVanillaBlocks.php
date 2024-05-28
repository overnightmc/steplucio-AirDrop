<?php

declare(strict_types=1);

namespace source\airdrop;

use pocketmine\block\Block;
use pocketmine\block\BlockBreakInfo;
use pocketmine\block\BlockIdentifier;
use pocketmine\block\BlockTypeIds;
use pocketmine\block\BlockTypeInfo;
use pocketmine\item\ToolTier;
use pocketmine\utils\CloningRegistryTrait;
use source\airdrop\block\Dispenser;
use source\airdrop\tile\Dispenser as TileDispenser;

/**
 * @method static Dispenser DISPENSER()
 */
final class ExtraVanillaBlocks{
	use CloningRegistryTrait;

	private function __construct(){}

	protected static function register(string $name, Block $block) : void{
		self::_registryRegister($name, $block);
	}

	/** @return Block[] */
	public static function getAll():array{
		$result = self::_registryGetAll();
		return $result;
	}

    protected static function setup():void{
		self::register("dispenser", new Dispenser(new BlockIdentifier(BlockTypeIds::newId(),TileDispenser::class),"Dispenser",new BlockTypeInfo(BlockBreakInfo::pickaxe(3.5,ToolTier::WOOD(),3.5))));
	}
}