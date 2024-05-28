<?php

namespace source;

use Exception;
use muqsit\invmenu\InvMenu;
use muqsit\invmenu\InvMenuHandler;
use muqsit\invmenu\type\util\InvMenuTypeBuilders;
use pocketmine\block\RuntimeBlockStateRegistry;
use pocketmine\block\tile\TileFactory;
use pocketmine\command\CommandSender;
use pocketmine\command\Command;
use pocketmine\data\bedrock\block\BlockStateNames;
use pocketmine\data\bedrock\block\BlockTypeNames;
use pocketmine\data\bedrock\block\convert\BlockStateReader;
use pocketmine\data\bedrock\block\convert\BlockStateWriter;
use pocketmine\entity\EntityDataHelper;
use pocketmine\entity\EntityFactory;
use pocketmine\entity\Location;
use pocketmine\errorhandler\ErrorToExceptionHandler;
use pocketmine\event\block\BlockPlaceEvent;
use pocketmine\event\EventPriority;
use pocketmine\event\server\DataPacketSendEvent;
use pocketmine\inventory\CreativeInventory;
use pocketmine\inventory\Inventory;
use pocketmine\item\Item;
use pocketmine\item\StringToItemParser;
use pocketmine\math\Vector3;
use pocketmine\nbt\BigEndianNbtSerializer;
use pocketmine\nbt\NBT;
use pocketmine\nbt\NbtDataException;
use pocketmine\nbt\tag\CompoundTag;
use pocketmine\nbt\tag\ListTag;
use pocketmine\nbt\TreeRoot;
use pocketmine\network\mcpe\protocol\AvailableCommandsPacket;
use pocketmine\network\mcpe\protocol\types\command\CommandEnum;
use pocketmine\network\mcpe\protocol\types\command\CommandOverload;
use pocketmine\network\mcpe\protocol\types\command\CommandParameter;
use pocketmine\network\mcpe\protocol\types\inventory\WindowTypes;
use pocketmine\player\Player;
use pocketmine\plugin\PluginBase;
use pocketmine\scheduler\AsyncTask;
use pocketmine\utils\Config;
use pocketmine\utils\Filesystem;
use pocketmine\utils\SingletonTrait;
use pocketmine\utils\TextFormat;
use pocketmine\utils\Utils;
use pocketmine\world\format\io\GlobalBlockStateHandlers;
use pocketmine\world\World;
use source\block\Dispenser;
use source\entity\AirDropFallingBlock;
use source\tile\Dispenser as TileDispenser;
use Symfony\Component\Filesystem\Path;

class Loader extends PluginBase{
    use SingletonTrait;
    const TYPE_DISPENSER="joshet18:dispenser";

    public static function getInstance(): Loader{
        return self::$instance;
    }

    public function onEnable():void{
        self::setInstance($this);
        if(!InvMenuHandler::isRegistered())InvMenuHandler::register($this);
        EntityFactory::getInstance()->register(AirDropFallingBlock::class,fn(World $world,CompoundTag $nbt):AirDropFallingBlock=>new AirDropFallingBlock(EntityDataHelper::parseLocation($nbt, $world),$nbt),['AirdropFallingBlock']);
        InvMenuHandler::getTypeRegistry()->register(self::TYPE_DISPENSER,InvMenuTypeBuilders::BLOCK_ACTOR_FIXED()->setBlock(ExtraVanillaBlocks::DISPENSER())->setBlockActorId("Dispenser")->setSize(9)->setNetworkWindowType(WindowTypes::DISPENSER)->build());
        TileFactory::getInstance()->register(TileDispenser::class,[BlockTypeNames::DISPENSER]);
		self::register_dispenser();
		$this->getServer()->getAsyncPool()->addWorkerStartHook(function(int $worker) : void{
			$this->getServer()->getAsyncPool()->submitTaskToWorker(new class extends AsyncTask{
				public function onRun():void{
					Loader::register_dispenser();
				}
			}, $worker);
		});
        $register_event=$this->getServer()->getPluginManager()->registerEvent(...);
        $register_event(BlockPlaceEvent::class,$this->onBlockPlace(...),EventPriority::NORMAL,$this);
        $register_event(DataPacketSendEvent::class,$this->onDataPacketSend(...),EventPriority::NORMAL,$this);
	}

    public function getConfig():Config{
        return new Config($this->getDataFolder()."config.json",Config::JSON,[
            'block'=>[
                'inventory_display'=>''
            ],
            'item'=>[
                'custom_name'=>'&r&3AirDrop&r',
                'lore'=>[]
            ]
        ]);
    }

	private function getDataPath(string $file):string{
		return Path::join($this->getDataFolder(),strtolower($file).'.dat');
	}

	private function handleCorruptedData(string $file):void{
		$path = $this->getDataPath($file);
		rename($path,$path.'.bak');
	}

	public function has(string $file):bool{
		return file_exists($this->getDataPath($file));
	}

	public function load(string $file):?CompoundTag{
		$file = strtolower($file);
		$path = $this->getDataPath($file);
		if(!file_exists($path))return null;
		try{
			$contents = Filesystem::fileGetContents($path);
		}catch(\RuntimeException $e){
			throw new Exception("Failed to read data file \"$path\": " . $e->getMessage(), 0, $e);
		}
		try{
			$decompressed = ErrorToExceptionHandler::trapAndRemoveFalse(fn() => zlib_decode($contents));
		}catch(\ErrorException $e){
			$this->handleCorruptedData($file);
			throw new Exception("Failed to decompress raw data for \"$file\": " . $e->getMessage(), 0, $e);
		}

		try{
			return (new BigEndianNbtSerializer())->read($decompressed)->mustGetCompoundTag();
		}catch(NbtDataException $e){
			$this->handleCorruptedData($file);
			throw new Exception("Failed to decode NBT data for \"$file\": " . $e->getMessage(), 0, $e);
		}
	}

	public function save(string $file,CompoundTag $data):void{
		$nbt = new BigEndianNbtSerializer();
		$contents = Utils::assumeNotFalse(zlib_encode($nbt->write(new TreeRoot($data)), ZLIB_ENCODING_GZIP), "zlib_encode() failed unexpectedly");
		try{
			Filesystem::safeFilePutContents($this->getDataPath($file), $contents);
		}catch(\RuntimeException $e){
			throw new Exception("Failed to write data file: " . $e->getMessage(), 0, $e);
		}
	}

	public static function register_dispenser():void{
        $block = ExtraVanillaBlocks::DISPENSER();
        RuntimeBlockStateRegistry::getInstance()->register($block);
        StringToItemParser::getInstance()->registerBlock(BlockTypeNames::DISPENSER,fn()=>clone $block);
        CreativeInventory::getInstance()->add($block->asItem());
        GlobalBlockStateHandlers::getDeserializer()->map(BlockTypeNames::DISPENSER,fn(BlockStateReader $reader):Dispenser=>(clone $block)->setFacing($reader->readFacingDirection())->setTriggered($reader->readBool(BlockStateNames::TRIGGERED_BIT)));
        GlobalBlockStateHandlers::getSerializer()->map($block,fn(Dispenser $block)=>BlockStateWriter::create(BlockTypeNames::DISPENSER)->writeFacingDirection($block->getFacing())->writeBool(BlockStateNames::TRIGGERED_BIT,$block->isTriggered()));
	}

    /** @return Item[] */
    public function getAirDropItems():array{
        $contents=[];
        try{
            $itemsTag=$this->load('airdrop_loot');
            /** @var ListTag $items */
            if($itemsTag !== null && ($items=$itemsTag->getTag('items')) instanceof ListTag && $items->getTagType()===NBT::TAG_Compound){
                foreach($items as $itemNBT)$contents[]=Item::nbtDeserialize($itemNBT);
            }
        }catch(\Throwable $e){
            \GlobalLogger::get()->logException($e);
            return [];
        }
        return $contents;
    }

    public function onDataPacketSend(DataPacketSendEvent $ev):void{
        foreach($ev->getPackets() as $packet){
            if($packet instanceof AvailableCommandsPacket){
                if(isset($packet->commandData['airdrop']))$packet->commandData['airdrop']->overloads=[
                    new CommandOverload(false,[
                        CommandParameter::enum('give',new CommandEnum('give',['give']),0,false),
                        CommandParameter::standard('player',AvailableCommandsPacket::ARG_TYPE_TARGET,0,false),
                        CommandParameter::standard('count',AvailableCommandsPacket::ARG_TYPE_INT,0,true)
                    ]),
                    new CommandOverload(false,[
                        CommandParameter::enum('contents',new CommandEnum('contents',['contents']),0,false),
                    ])
                ];
            }
        }
    }

    public function onBlockPlace(BlockPlaceEvent $ev):void{
        $player=$ev->getPlayer();
        $item=$ev->getItem();
        if($ev->isCancelled())return;
        if($item->getNamedTag()->getTag('airdrop')!==null){
            $ev->cancel();
            $player->getInventory()->setItemInHand($item->setCount($item->getCount()-1));
            foreach($ev->getTransaction()->getBlocks() as [$x,$y,$z,$block]){
                if($block instanceof Dispenser){
                    (new AirDropFallingBlock(Location::fromObject(new Vector3($x+0.5,$y+5,$z+0.5),$player->getWorld())))->setLoot($this->getAirDropItems())->spawnToAll();
                }
            }
        }
    }

    private function edit_menu(Player $player):void{
        ($menu=InvMenu::create(InvMenu::TYPE_DOUBLE_CHEST)->setName('§6AirDrop Edit Menu')->setInventoryCloseListener(function(Player $player,Inventory $inventory):void{
            $player->sendMessage("§aAirdrop content has been updated");
            $contents=[];
            foreach($inventory->getContents() as $item)$contents[]=$item->nbtSerialize();
            ($CompoundTag=CompoundTag::create())->setString('last_change',date('d/m/Y H:i:s a T'))->setString('change_by',$player->getName())->setTag('items',new ListTag($contents,NBT::TAG_Compound));
            $this->save('airdrop_loot',$CompoundTag);
        }))->getInventory()->setContents($this->getAirDropItems());
        $menu->send($player);
    }

    public function onCommand(CommandSender $sender, Command $command, string $label, array $args):bool{
        if($command->getName()==='airdrop'){
            if(!isset($args[0])){
                $sender->sendMessage("§cUsage: /{$label} <action> <args...>");
                return true;
            }
            switch($args[0]){
                case 'contents':
                    if(!$command->testPermission($sender,'airdrop.edit.command'))return true;
                    if(!$sender instanceof Player){
                        $sender->sendMessage("§cThis command only in-game");
                        return true;
                    }
                    $this->edit_menu($sender);
                break;
                case "give":
                    if(!$command->testPermission($sender,'airdrop.give.command'))return true;
                    $count=1;
                    if(isset($args[2]) && is_numeric($args[2]))$count=(int)$args[2];
                    if(!isset($args[1])){
                        $sender->sendMessage("§cUsage: /{$label} give <player> [count]");
                        return true;
                    }
                    $target=$this->getServer()->getPlayerByPrefix($args[1]);
                    if(!$target instanceof Player){
                        $sender->sendMessage("§c{$args[1]} is offline");
                        return true;
                    }
                    $item=ExtraVanillaBlocks::DISPENSER()->asItem()->setCustomName(TextFormat::colorize($this->getConfig()->getNested('item.custom_name','')))->setLore(array_map(fn(string $string):string=>TextFormat::colorize($string),$this->getConfig()->getNested('item.lore',[])))->setCount($count);
                    $item->getNamedTag()->setString("airdrop","no se XD");
                    $target->sendMessage("§aYou've received {$item->getName()}§6x§e{$item->getCount()}");
                    foreach($target->getInventory()->addItem($item) as $i)$target->dropItem($i);
                break;
                default:
                    $sender->sendMessage("§a/{$label} §egive <player> [count]");
                    $sender->sendMessage("§a/{$label} §econtents");
                break;
            }
        }
        return true;
    }
}