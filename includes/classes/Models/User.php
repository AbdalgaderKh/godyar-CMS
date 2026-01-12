<?php
namespace Godyar\Models;
class User extends BaseModel {
  public function findByEmail(string $email): ?array { $st=$this->db->prepare('SELECT * FROM users WHERE email=:email'); $st->execute([':email'=>$email]); $r=$st->fetch(); return $r?:null; }
}
