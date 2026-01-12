<?php
namespace Godyar\Models;
class Slide extends BaseModel {
  public function all(): array { return $this->db->query("SELECT * FROM slides ORDER BY sort_order, id DESC")->fetchAll(); }
  public function create(array $d): bool {
    $st=$this->db->prepare("INSERT INTO slides(title,image,link,sort_order,is_active) VALUES(:t,:i,:l,:s,:a)");
    return $st->execute([':t'=>$d['title'],':i'=>$d['image'],':l'=>$d['link']??'',':s'=>(int)($d['sort_order']??0),':a'=>isset($d['is_active'])?1:0]);
  }
  public function delete(int $id): bool { $st=$this->db->prepare("DELETE FROM slides WHERE id=:id"); return $st->execute([':id'=>$id]); }
}
