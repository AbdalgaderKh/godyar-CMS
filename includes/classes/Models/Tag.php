<?php
namespace Godyar\Models;
class Tag extends BaseModel {
  public function all(): array { return $this->db->query("SELECT * FROM tags ORDER BY name")->fetchAll(); }
  public function create(string $name,string $slug): bool { $st=$this->db->prepare("INSERT INTO tags(name,slug) VALUES(:n,:s)"); return $st->execute([':n'=>$name,':s'=>$slug]); }
  public function delete(int $id): bool { $st=$this->db->prepare("DELETE FROM tags WHERE id=:id"); return $st->execute([':id'=>$id]); }
}
