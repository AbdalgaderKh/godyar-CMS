<?php
namespace Godyar\Models;
class Category extends BaseModel { public function all(): array { return $this->db->query('SELECT * FROM categories ORDER BY name')->fetchAll(); } }
