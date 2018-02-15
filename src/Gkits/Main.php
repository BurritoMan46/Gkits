<?php

namespace Gkits;

use Gkits\economy\EconomyManager;
use Gkits\lang\LangManager;
use Gkits\tasks\CoolDownTask;
use pocketmine\command\Command;
use pocketmine\command\CommandSender;
use pocketmine\Player;
use pocketmine\plugin\PluginBase;
use pocketmine\utils\TextFormat;

use PiggyCustomEnchants;

class Main extends PluginBase{

    /**@var gkit[] */
    public $gkits = [];
    /**@var kit[] */
    public $hasGkit = [];
    /**@var EconomyManager */
    public $economy;
    public $permManager = false;
    /**@var LangManager */
    public $langManager;
    /** @var null|PiggyCustomEnchants\Main */
    public $piggyEnchants;
    
    public function onEnable(){
        @mkdir($this->getDataFolder() . "cooldowns/");
        $this->saveDefaultConfig();
        $this->loadKits();
        $this->economy = new EconomyManager($this);
        $this->langManager = new LangManager($this);
        if($this->getServer()->getPluginManager()->getPlugin("PurePerms") !== null and !$this->getConfig()->get("force-builtin-permissions")){
            $this->permManager = true;
        }
        $this->getServer()->getScheduler()->scheduleDelayedRepeatingTask(new CoolDownTask($this), 1200, 1200);
        $this->getServer()->getPluginManager()->registerEvents(new EventListener($this), $this);
        
        $this->piggyEnchants = $this->getServer()->getPluginManager()->getPlugin("PiggyCustomEnchants");
        if($this->piggyEnchants !== null){
            $this->getServer()->getLogger()->info(TextFormat::GREEN . "[Gkits] Using PiggyCustomEnchants!");
        }
    }
    public function onDisable(){
        foreach($this->gkits as $gkit){
            $gkit->save();
        }
    }
    public function onCommand(CommandSender $sender, Command $command, string $label, array $args) : bool{
        switch(strtolower($command->getName())){
            case "gkit":
                if(!($sender instanceof Player)){
                    $sender->sendMessage($this->langManager->getTranslation("in-game"));
                    return true;
                }
                if(!isset($args[0])){
                    $sender->sendMessage($this->langManager->getTranslation("av-gkits", implode(", ", array_keys($this->kits))));
                    return true;
                }
                $gkit = $this->getKit($args[0]);
                if($gkit === null){
                    $sender->sendMessage($this->langManager->getTranslation("no-gkit", $args[0]));
                    return true;
                }
                $gkit->handleRequest($sender);
                return true;
                break;
            case "gkreload":
                foreach($this->kits as $gkit){
                    $gkit->save();
                }
                $this->gkits = [];
                $this->loadGkits();
                $sender->sendMessage($this->langManager->getTranslation("reload"));
                return true;
                break;
        }
        return true;
    }
    private function loadGkits(){
        $this->saveResource("gkits.yml");
        $gkitsData = yaml_parse_file($this->getDataFolder() . "gkits.yml");
        $this->fixConfig($kitsData);
        foreach($gkitsData as $gkitName => $gkitData){
            $this->gkits[$gkitName] = new Kit($this, $gkitData, $gkitName);
        }
    }
    private function fixConfig(&$config){
        foreach($config as $name => $gkit){
            if(isset($gkit["users"])){
                $users = array_map("strtolower", $gkit["users"]);
                $config[$name]["users"] = $users;
            }
            if(isset($gkit["worlds"])){
                $worlds = array_map("strtolower", $gkit["worlds"]);
                $config[$name]["worlds"] = $worlds;
            }
        }
    }
    /**
     * @param string $gkit
     * @return Gkit|null
     */
    public function getGkit(string $gkit){
        /**@var Gkit[] $lowerKeys */
        $lowerKeys = array_change_key_case($this->gkits, CASE_LOWER);
        if(isset($lowerKeys[strtolower($kit)])){
            return $lowerKeys[strtolower($kit)];
        }
        return null;
    }
    /**
     * @param      $player
     * @param bool $object whether to return the gkit object or the gkit name
     * @return gkit|null
     */
    public function getPlayerKit($player, $object = false){
        if($player instanceof Player) $player = $player->getName();
        return isset($this->hasGkit[strtolower($player)]) ? ($object ? $this->hasGkit[strtolower($player)] : $this->hasGkit[strtolower($player)]->getName()) : null;
    }
}
