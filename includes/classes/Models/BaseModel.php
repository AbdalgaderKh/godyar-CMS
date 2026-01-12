<?php
namespace Godyar\Models;
use Godyar\DB; use PDO;
abstract class BaseModel {
  protected PDO $db;
  public function __construct(?PDO $pdo = null){
    $this->db = $pdo ?: DB::pdo();
  }
}
