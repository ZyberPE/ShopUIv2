<?php

declare(strict_types=1);

namespace ShopUI;

use jojoe77777\FormAPI\CustomForm;
use jojoe77777\FormAPI\SimpleForm;

use onebone\economyapi\EconomyAPI;

use pocketmine\command\Command;
use pocketmine\command\CommandSender;

use pocketmine\item\StringToItemParser;

use pocketmine\player\Player;

use pocketmine\plugin\PluginBase;

class Main extends PluginBase{

    public function onEnable() : void{
        $this->saveDefaultConfig();
    }

    public function onCommand(CommandSender $sender, Command $command, string $label, array $args) : bool{

        if(!$sender instanceof Player){
            return true;
        }

        switch(strtolower($command->getName())){

            case "shop":
            case "shopui":
                $this->openShop($sender);
                return true;
        }

        return false;
    }

    public function openShop(Player $player) : void{

        $player->sendMessage(
            $this->getConfig()->get("messages")["shop-open"]
        );

        $form = new SimpleForm(function(Player $player, ?int $data){

            if($data === null){

                $player->sendMessage(
                    $this->getConfig()->get("messages")["shop-close"]
                );

                return;
            }

            $categories = array_values(
                $this->getConfig()->get("categories")
            );

            if(!isset($categories[$data])){
                return;
            }

            $this->openCategoryItems($player, $categories[$data]);
        });

        $form->setTitle(
            $this->getConfig()->get("category-menu-title")
        );

        foreach($this->getConfig()->get("categories") as $category){

            $form->addButton($category["name"]);
        }

        $player->sendForm($form);
    }

    public function openCategoryItems(Player $player, array $category) : void{

        $form = new SimpleForm(function(Player $player, ?int $data) use ($category){

            if($data === null){
                $this->openShop($player);
                return;
            }

            $items = array_values($category["items"]);

            if(!isset($items[$data])){
                return;
            }

            $this->openQuantityForm($player, $items[$data]);
        });

        $form->setTitle(
            $this->getConfig()->get("item-menu-title")
        );

        foreach($category["items"] as $item){

            $form->addButton(
                $item["custom-name"] .
                "\n§a$" . $item["price"]
            );
        }

        $player->sendForm($form);
    }

    public function openQuantityForm(Player $player, array $itemData) : void{

        $form = new CustomForm(function(Player $player, ?array $data) use ($itemData){

            if($data === null){

                $player->sendMessage(
                    $this->getConfig()->get("messages")["purchase-cancelled"]
                );

                return;
            }

            $amount = (int)$data[1];

            if($amount <= 0){
                $amount = 1;
            }

            $this->openConfirmForm($player, $itemData, $amount);
        });

        $form->setTitle("§l§8Select Quantity");

        $form->addLabel("§7Enter amount to purchase");

        $form->addInput("Quantity", "1");

        $player->sendForm($form);
    }

    public function openConfirmForm(Player $player, array $itemData, int $amount) : void{

        $price = $itemData["price"] * $amount;

        $form = new SimpleForm(function(Player $player, ?int $data) use ($itemData, $amount, $price){

            if($data === null){

                $player->sendMessage(
                    $this->getConfig()->get("messages")["purchase-cancelled"]
                );

                return;
            }

            switch($data){

                case 0:
                    $this->buyItem($player, $itemData, $amount, $price);
                    break;

                case 1:
                    $player->sendMessage(
                        $this->getConfig()->get("messages")["purchase-denied"]
                    );

                    $this->openShop($player);
                    break;

                case 2:
                    $player->sendMessage(
                        $this->getConfig()->get("messages")["purchase-cancelled"]
                    );
                    break;
            }
        });

        $messages = $this->getConfig()->get("messages");

        $content = str_replace(
            ["{item}", "{amount}", "{price}"],
            [
                $itemData["custom-name"],
                $amount,
                $price
            ],
            $messages["confirm-content"]
        );

        $form->setTitle($messages["confirm-title"]);
        $form->setContent($content);

        $form->addButton("§aYes");
        $form->addButton("§cNo");
        $form->addButton("§7Cancel");

        $player->sendForm($form);
    }

    public function buyItem(Player $player, array $itemData, int $amount, int $price) : void{

        $economy = EconomyAPI::getInstance();

        if($economy->myMoney($player) < $price){

            $player->sendMessage(
                $this->getConfig()->get("messages")["purchase-failed"]
            );

            return;
        }

        $item = StringToItemParser::getInstance()->parse(
            (string)$itemData["id"]
        );

        if($item === null){
            return;
        }

        $item->setCount($amount);

        $item->setCustomName($itemData["custom-name"]);

        if(!$player->getInventory()->canAddItem($item)){

            $player->sendMessage(
                $this->getConfig()->get("messages")["inventory-full"]
            );

            return;
        }

        $economy->reduceMoney($player, $price);

        $player->getInventory()->addItem($item);

        $message = str_replace(
            ["{item}", "{amount}"],
            [
                $itemData["custom-name"],
                $amount
            ],
            $this->getConfig()->get("messages")["purchase-success"]
        );

        $player->sendMessage($message);
    }
}
