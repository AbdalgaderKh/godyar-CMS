<?php
namespace Godyar\Models;
class TeamMember extends BaseModel {
  public function all(): array { return $this->db->query("SELECT * FROM team_members ORDER BY sort_order, id DESC")->fetchAll(); }
  public function create(array $d): bool {
    $st=$this->db->prepare("INSERT INTO team_members(name,role_title,photo,sort_order,is_active) VALUES(:n,:r,:p,:o,:a)");
    return $st->execute([':n'=>$d['name'],':r'=>$d['role_title']??'',':p'=>$d['photo']??'',':o'=>(int)($d['sort_order']??0),':a'=>isset($d['is_active'])?1:0]);
  }
  public function delete(int $id): bool { $st=$this->db->prepare("DELETE FROM team_members WHERE id=:id"); return $st->execute([':id'=>$id]); }
}
