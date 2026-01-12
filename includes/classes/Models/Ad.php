<?php
namespace Godyar\Models;
class Ad extends BaseModel {
  public function all(): array { return $this->db->query("SELECT * FROM ads ORDER BY id DESC")->fetchAll(); }
  public function create(array $d): bool {
    $st=$this->db->prepare("INSERT INTO ads(title,image,code,placement,is_active) VALUES(:t,:i,:c,:p,:a)");
    return $st->execute([':t'=>$d['title'],':i'=>$d['image']??null,':c'=>$d['code']??null,':p'=>$d['placement']??'sidebar',':a'=>isset($d['is_active'])?1:0]);
  }
  public function delete(int $id): bool { $st=$this->db->prepare("DELETE FROM ads WHERE id=:id"); return $st->execute([':id'=>$id]); }
}
