<?php
namespace Godyar\Models;
class Author extends BaseModel {
  public function all(): array { return $this->db->query("SELECT * FROM authors ORDER BY id DESC")->fetchAll(); }
  public function create(array $d): bool {
    $st=$this->db->prepare("INSERT INTO authors(name,bio,avatar,user_id) VALUES(:n,:b,:a,:u)");
    return $st->execute([':n'=>$d['name'],':b'=>$d['bio']??'',':a'=>$d['avatar']??'',':u'=>$d['user_id']??null]);
  }
  public function delete(int $id): bool { $st=$this->db->prepare("DELETE FROM authors WHERE id=:id"); return $st->execute([':id'=>$id]); }
}
