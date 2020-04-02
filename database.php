<?php

class User {
    public $id;
    public $balance;
    public $cooldown;

    function __construct(string $id, float $balance, int $cooldown) {
        $this->id = $id;
        $this->balance = $balance;
        $this->cooldown = $cooldown;
    }
}

class Database {
    private $db;
    private $table;

    function __construct(string $database, string $table, string $user, string $password) {
        $this->db = new mysqli('localhost', $user, $password, $database);
        if($this->db->connect_error) {
            throw new Exception($this->db->error);
        }

        $this->table = $table;
    }

    private function prepare_statement(string $statement) {
        $stmt = $this->db->prepare($statement);
        if($stmt === FALSE) {
            throw new Exception($this->db->error);
        }
        return $stmt;
    }

    function add_user_if_not_exists(string $user) {
        $stmt = $this->prepare_statement("INSERT INTO ".$this->table." (`id`) VALUES (?) ON DUPLICATE KEY UPDATE `id`=VALUES(`id`)");
        $stmt->bind_param("s", $user);
        $stmt->execute();
        $stmt->close();
    }

    function get_user(string $user) {
        $stmt = $this->prepare_statement("SELECT `id`, `balance`, `cooldown` FROM ".$this->table." WHERE `id`=?");
        $stmt->bind_param("s", $user);
        $stmt->execute();
        $stmt->bind_result($id, $balance, $cooldown);
        
        $out = null;
        if($stmt->fetch()) {
            $out = new User($id, $balance, $cooldown);
        }
        $stmt->close();
        return $out;
    }

    function save_users(array $users) {
        $stmt = $this->prepare_statement("UPDATE ".$this->table." SET `balance`=?, `cooldown`=? WHERE `id`=?");
        $stmt->bind_param("dds", $balance, $cooldown, $id);
        foreach($users as $user) {
            $balance = $user->balance;
            $cooldown = $user->cooldown;
            $id = $user->id;
            $stmt->execute();
        }
        $stmt->close();
    }

    function save_user(User $user) {
        $this->save_users(array($user));
    }

    function get_scoreboard(int $limit) {
        $stmt = $this->prepare_statement("SELECT `id`, `$this->table`.`balance`, `cooldown` FROM `$this->table` JOIN ( SELECT DISTINCT `balance` FROM `$this->table` ORDER BY `balance` DESC LIMIT ? ) tlim ON `$this->table`.`balance` = tlim.`balance` ORDER BY `$this->table`.`balance` DESC");
        $stmt->bind_param("d", $limit);
        $stmt->execute();
        $stmt->bind_result($id, $balance, $cooldown);

        $users = array();
        while($stmt->fetch()) {
            $users[] = new User($id, $balance, $cooldown);
        }

        $stmt->close();
        return $users;
    }

    function get_user_rank(float $score) {
        $stmt = $this->prepare_statement("SELECT COUNT(DISTINCT `balance`) FROM `$this->table` WHERE `balance` >= ?");
        $stmt->bind_param("d", $score);
        $stmt->execute();
        $stmt->bind_result($location);
        $stmt->fetch();
        $stmt->close();
        return $location;
    }
}

?>
