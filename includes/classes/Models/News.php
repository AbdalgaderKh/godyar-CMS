<?php
namespace Godyar\Models;
class News extends BaseModel {
  public function all(int $limit=20,int $offset=0): array {
    $st = $this->db->prepare('SELECT * FROM news ORDER BY created_at DESC LIMIT :l OFFSET :o');
    $st->bindValue(':l',$limit,\PDO::PARAM_INT); $st->bindValue(':o',$offset,\PDO::PARAM_INT); $st->execute(); return $st->fetchAll();
  }
  public function find(int $id): ?array { $st=$this->db->prepare('SELECT * FROM news WHERE id=:id'); $st->execute([':id'=>$id]); $r=$st->fetch(); return $r?:null; }
  public function create(array $d): int {
    $st=$this->db->prepare('INSERT INTO news (title, slug, content, category_id, author_id, featured_image, status, created_at) VALUES (:title,:slug,:content,:category_id,:author_id,:featured_image,:status,NOW())');
    $st->execute($d); return (int)$this->db->lastInsertId();
  }
  public function update(int $id, array $d): bool {
    $d[':id']=$id;
    $st=$this->db->prepare('UPDATE news SET title=:title, slug=:slug, content=:content, category_id=:category_id, author_id=:author_id, featured_image=:featured_image, status=:status WHERE id=:id');
    return $st->execute($d);
  }
  public function delete(int $id): bool { $st=$this->db->prepare('DELETE FROM news WHERE id=:id'); return $st->execute([':id'=>$id]); }
}
