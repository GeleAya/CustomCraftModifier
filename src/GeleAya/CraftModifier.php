<?php

namespace GeleAya;

use pocketmine\command\Command;
use pocketmine\command\CommandSender;
use pocketmine\event\Listener;
use pocketmine\event\player\PlayerJoinEvent;
use pocketmine\event\server\DataPacketReceiveEvent;
use pocketmine\inventory\CraftingManager;
use pocketmine\inventory\ShapedRecipe;
use pocketmine\item\Item;
use pocketmine\nbt\JsonNbtParser;
use pocketmine\nbt\tag\CompoundTag;
use pocketmine\network\mcpe\protocol\BatchPacket;
use pocketmine\network\mcpe\protocol\CraftingDataPacket;
use pocketmine\Player;
use pocketmine\plugin\PluginBase;
use pocketmine\Server;
use pocketmine\timings\Timings;
use pocketmine\utils\Config;

class CraftModifier extends PluginBase implements Listener
{
    public $cache;
	
    public function onEnable()
    {
        @mkdir($this->getDataFolder());

        if(!file_exists($this->getDataFolder()."config.yml")){

            $this->saveResource('config.yml');

        }

        $this->getServer()->getPluginManager()->registerEvents($this, $this);

        $this->craftingDataCache();
    }

    public function craftingDataCache()
    {
        $datas = $this->getServer()->getCraftingManager();
        $pk = new CraftingDataPacket();
        $pk->cleanRecipes = true;

        foreach ($datas->getShapelessRecipes() as $list) {

            foreach ($list as $recipe) {

                $pk->addShapelessRecipe($recipe);

            }

        }

        foreach ($datas->getShapedRecipes() as $list) {

            foreach ($list as $recipe) {

                $delete = false;

                $results = $recipe->getResults();

                foreach ($results as $result) {

                    if (in_array($result->getID(),$this->getAllDeleteIds())) {

                        if ($this->getDeleteDamageById($result->getID()) == $result->getDamage()) {

                            $this->getLogger()->info("§fL'item §e" . $result->getName() . "§r§f a été suprimmé");
                            $delete = true;

                        }

                    }

                }

                if (!$delete) $pk->addShapedRecipe($recipe);

            }

        }

        foreach($this->getAllAdd() as $craft) {

            $result = $this->getItem($craft["result"]);

            $recipes = $craft["recipe"];

            $recipe = new ShapedRecipe(
                [
                    "ABC",
                    "DEF",
                    "GHI",
                ],
                [
                    "A" => $this->getItem($recipes[0][0]),
                    "B" => $this->getItem($recipes[0][1]),
                    "C" => $this->getItem($recipes[0][2]),
                    "D" => $this->getItem($recipes[1][0]),
                    "E" => $this->getItem($recipes[1][1]),
                    "F" => $this->getItem($recipes[1][2]),
                    "G" => $this->getItem($recipes[2][0]),
                    "H" => $this->getItem($recipes[2][1]),
                    "I" => $this->getItem($recipes[2][2])
                ],
                [
                    $result
                ]
            );

            $pk->addShapedRecipe($recipe);
            $this->getServer()->getCraftingManager()->registerShapedRecipe($recipe);

        }

        foreach ($datas->getFurnaceRecipes() as $recipe) {

            $pk->addFurnaceRecipe($recipe);

        }

        $pk->encode();

        $batch = new BatchPacket();
        $batch->addPacket($pk);
        $batch->setCompressionLevel(Server::getInstance()->networkCompressionLevel);
        $batch->encode();

        $this->cache = $batch;
    }

    public function getAllAdd() : array
    {
        $config = new Config($this->getDataFolder() . "config.yml", Config::YAML);
        $all = $config->getAll()["add"];
        return $all;
    }

    public function getItem(array $item) : Item
    {
    	if (is_string($item[0])) {
    	
        	$data = Item::fromString($item[0]);
            $result = Item::get($data->getId(),$data->getDamage(),1);

        } else {
        	
            $result = Item::get($item[0],0,1);
            
        }
        
        if (isset($item[1])) {

            $result->setCount($item[1]);

        }

        if (isset($item[2])) {

            $tags = $exception = null;
            $data = $item[2];

            try {

                $tags = JsonNbtParser::parseJson($data);

            } catch (\Throwable $ex){

                $exception = $ex;

            }

            if (!($tags instanceof CompoundTag) or $exception !== null) {

                return $result;
            }

            $result->setNamedTag($tags);

        }

        return $result;
    }

    public function getAllDelete() : array
    {
        $config = new Config($this->getDataFolder() . "config.yml", Config::YAML);
        $all = $config->getAll()["delete"];
        return $all;
    }

    public function getAllDeleteIds() : array
    {
        $ids = [];

        foreach ($this->getAllDelete() as $item) {

            if (!is_null($item)) {

                $item = Item::fromString($item);
                $ids[] = $item->getID();

            }

        }

        return $ids;
    }

    public function getAllDeleteDamage() : array
    {
        $ids = [];

        foreach ($this->getAllDelete() as $item) {

            $item = Item::fromString($item);
            $ids[] = $item->getDamage();

        }

        return $ids;
    }

    public function getDeleteDamageById(int $id) : int
    {
        foreach ($this->getAllDeleteIds() as $number => $itemid) {

            if ($id === $itemid) {

                return $this->getAllDeleteDamage()[$number];

            }

        }

        return 0;
    }


    public function onJoin(PlayerJoinEvent $event)
    {
        $player = $event->getPlayer();
        $player->dataPacket($this->cache);
    }

}