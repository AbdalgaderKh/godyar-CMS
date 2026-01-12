<?php
namespace Godyar\Models;
class Section extends BaseModel {
  public function all(): array { return $this->db->query("SELECT * FROM sections ORDER BY sort_order, name")->fetchAll(); }
  public function create(string $n,string $s,int $o,bool $a): bool {
    $st=$this->db->prepare("INSERT INTO sections(name,slug,sort_order,is_active) VALUES(:n,:s,:o,:a)");
    return $st->execute([':n'=>$n,':s'=>$s,':o'=>$o,':a'=>$a?1:0]);
  }
  public function delete(int $id): bool { $st=$this->db->prepare("DELETE FROM sections WHERE id=:id"); return $st->execute([':id'=>$id]); }
}
