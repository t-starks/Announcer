<?php

namespace TStark\Announcer;

use pocketmine\plugin\PluginBase;
use pocketmine\command\Command;
use pocketmine\command\CommandSender;
use pocketmine\player\Player;
use pocketmine\utils\Config;
use pocketmine\scheduler\Task;
use pocketmine\utils\TextFormat;

class Main extends PluginBase {

    private $ads;

    public function onEnable(): void {
        $this->getLogger()->info(TextFormat::AQUA . "Developed by T. Stark");
        $this->ads = new Config($this->getDataFolder() . "ads.yml", Config::YAML, []);
        foreach ($this->ads->getAll() as $id => $data) {
            $this->scheduleAd($id, $data['message'], $data['interval']);
        }
    }

    public function onDisable(): void {
        $this->getLogger()->info(TextFormat::RED . "System Closed");
        $this->getScheduler()->cancelAllTasks();
    }

    public function scheduleAd(string $id, string $message, int $interval): void {
        $this->getScheduler()->scheduleRepeatingTask(new class($this, $message) extends Task {
            private $plugin;
            private $message;

            public function __construct(Main $plugin, string $message) {
                $this->plugin = $plugin;
                $this->message = $message;
            }

            public function onRun(): void {
                foreach ($this->plugin->getServer()->getOnlinePlayers() as $player) {
                    $player->sendActionBarMessage($this->message);
                }
            }
        }, $interval * 20);
    }

    public function onCommand(CommandSender $sender, Command $command, string $label, array $args): bool {
        if (!$sender->hasPermission("announcer.admin")) {
            $sender->sendMessage(TextFormat::RED . "You do not have permission to use this command.");
            return false;
        }

        switch ($command->getName()) {
            case "newad":
                if (count($args) < 3) {
                    $sender->sendMessage("Usage: /newad <id> <message> <interval>");
                    return false;
                }

                $id = array_shift($args);
                $interval = array_pop($args);
                $message = implode(" ", $args);

                if ($this->ads->exists($id)) {
                    $sender->sendMessage(TextFormat::YELLOW . "An ad with ID $id already exists.");
                    return false;
                }

                $this->ads->set($id, ["message" => $message, "interval" => (int)$interval]);
                $this->ads->save();
                $this->scheduleAd($id, $message, (int)$interval);
                $sender->sendMessage(TextFormat::GREEN . "Ad $id created successfully.");
                break;

            case "deletad":
                if (count($args) != 1) {
                    $sender->sendMessage("Usage: /deletad <id>");
                    return false;
                }

                $id = $args[0];

                if (!$this->ads->exists($id)) {
                    $sender->sendMessage(TextFormat::RED . "No ad exists with ID $id.");
                    return false;
                }

                $this->ads->remove($id);
                $this->ads->save();
                $this->getScheduler()->cancelAllTasks();  // Cancel all tasks and reschedule existing ones
                foreach ($this->ads->getAll() as $adId => $data) {
                    $this->scheduleAd($adId, $data['message'], $data['interval']);
                }
                $sender->sendMessage(TextFormat::GREEN . "Ad $id deleted successfully.");
                break;

            case "ads":
                $adsList = $this->ads->getAll();
                if (empty($adsList)) {
                    $sender->sendMessage("§6There are no ads created.");
                } else {
                    $sender->sendMessage("§7ID §0| §3Message");
                    foreach ($adsList as $id => $data) {
                        $sender->sendMessage("$id | {$data['message']}");
                    }
                }
                break;

            default:
                return false;
        }

        return true;
    }
}
