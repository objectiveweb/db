<?php
namespace Bravado;

use PDO;

class DB {
	var $pdo;

	function __construct($uri, $username, $password) {
		$this->pdo = new PDO($uri, $username, $password);
		$this->pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
		$this->pdo->setAttribute(PDO::ATTR_EMULATE_PREPARES, false);
	}

	function query($query) {
		$stmt = $this->pdo->query($query);
		return $stmt->fetchAll(PDO::FETCH_ASSOC);
	}
}
