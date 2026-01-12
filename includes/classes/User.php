<?php
// permissive bootstrap: allow direct access by bootstrapping app if needed
if (!defined('APP_BOOT')) {
    define('APP_BOOT', true);
    $__d = __DIR__;
    $__root = $__d;
    for ($__i=0; $__i<6; $__i++) {
        if (file_exists($__root . '/vendor/autoload.php') || file_exists($__root . '/index.php')) break;
        $__root = dirname($__root);
    }
    if (file_exists($__root . '/vendor/autoload.php')) {
        require $__root . '/vendor/autoload.php';
        if (class_exists('App\\Core\\App')) { App\Core\App::boot($__root); }
    }
    if (!defined('BASE_PATH')) define('BASE_PATH', $__root);
}

class User {
    private ?bool $hasLastLoginAt = null;
    private ?bool $hasLastLogin   = null;

    private function columnExists(string $column): bool {
        $col = Security::cleanInput($column);
        try {
            $stmt = $this->db->query("SHOW COLUMNS FROM {$this->table} LIKE ?", [$col]);
            return $stmt && ($stmt->fetch() !== false);
        } catch (\Throwable $e) {
            return false;
        }
    }

    private $db;
    private $table = 'users';
    
    public function __construct() {
        global $database;
        $this->db = $database;
    }
    
    // تسجيل مستخدم جديد
    public function register($userData) {
        $required = ['username', 'email', 'password'];
        foreach ($required as $field) {
            if (empty($userData[$field])) {
                throw new Exception("حقل {$field} مطلوب");
            }
        }
        
        if (!$this->validateEmail($userData['email'])) {
            throw new Exception('البريد الإلكتروني غير صالح');
        }
        
        if ($this->emailExists($userData['email'])) {
            throw new Exception('البريد الإلكتروني مسجل مسبقاً');
        }
        
        $hashedPassword = password_hash($userData['password'], PASSWORD_DEFAULT);
        $verificationCode = bin2hex(random_bytes(16));
        
        $sql = "INSERT INTO {$this->table} (username, email, password_hash, verification_code, created_at) VALUES (?, ?, ?, ?, NOW())";
        $params = [
            Security::cleanInput($userData['username']),
            Security::cleanInput($userData['email']),
            $hashedPassword,
            $verificationCode
        ];
        
        $result = $this->db->query($sql, $params);
        
        if ($result) {
            // إرسال بريد التحقق
            $this->sendVerificationEmail($userData['email'], $verificationCode);
            return $this->db->lastInsertId();
        }
        
        return false;
    }
    
    // تسجيل الدخول
    public function login($email, $password) {
        $sql = "SELECT * FROM {$this->table} WHERE email = ? AND status = 'active'";
        $stmt = $this->db->query($sql, [Security::cleanInput($email)]);
        $user = $stmt->fetch();
        
        if ($user && password_verify($password, $user['password_hash'])) {
            // تحديث آخر دخول
            $this->updateLastLogin($user['id']);
            
            // تعيين بيانات الجلسة (مفاتيح قديمة)
            $_SESSION['user_id'] = $user['id'];
            $_SESSION['user_email'] = $user['email'];
            $_SESSION['user_role'] = $user['role'];
            $_SESSION['user_name'] = $user['username'];

            // الشكل الموحد المستخدم في الهيدر والفرونت
            if (function_exists('auth_set_user_session')) {
                auth_set_user_session([
                    'id'       => (int)$user['id'],
                    'username' => $user['username'] ?? null,
                    'email'    => $user['email'] ?? null,
                    'role'     => $user['role'] ?? 'user',
                    'status'   => $user['status'] ?? 'active',
                    'avatar'   => $user['avatar'] ?? null,
                ]);
            } else {
                $_SESSION['user'] = [
                    'id'       => (int)$user['id'],
                    'username' => $user['username'] ?? null,
                    'email'    => $user['email'] ?? null,
                    'role'     => $user['role'] ?? 'user',
                    'status'   => $user['status'] ?? 'active',
                    'avatar'   => $user['avatar'] ?? null,
                    'login_at' => date('Y-m-d H:i:s'),
                ];
                $_SESSION['is_member_logged'] = 1;
            }
            
            Security::logSecurityEvent('USER_LOGIN_SUCCESS', $user['id']);
            return true;
        }
        
        Security::logSecurityEvent('USER_LOGIN_FAILED');
        return false;
    }
    
    // تحديث الملف الشخصي
    public function updateProfile($userId, $data) {
        $allowedFields = ['username', 'full_name', 'bio', 'avatar'];
        $updates = [];
        $params = [];
        
        foreach ($allowedFields as $field) {
            if (isset($data[$field])) {
                $updates[] = "{$field} = ?";
                $params[] = Security::cleanInput($data[$field]);
            }
        }
        
        if (empty($updates)) {
            throw new Exception('لا توجد بيانات لتحديثها');
        }
        
        $params[] = $userId;
        $sql = "UPDATE {$this->table} SET " . implode(', ', $updates) . " WHERE id = ?";
        
        return $this->db->query($sql, $params);
    }
    
    // التحقق من وجود البريد الإلكتروني
    private function emailExists($email) {
        $sql = "SELECT id FROM {$this->table} WHERE email = ?";
        $stmt = $this->db->query($sql, [Security::cleanInput($email)]);
        return $stmt->fetch() !== false;
    }
    
    // إرسال بريد التحقق
    private function sendVerificationEmail($email, $code) {
        $verificationLink = BASE_URL . "verify.php?code=" . $code;
        $subject = "تفعيل حسابك في Godyar";
        $message = "اضغط على الرابط التالي لتفعيل حسابك: " . $verificationLink;
        
        // هنا يمكنك استخدام PHPMailer أو دالة mail()
        return mail($email, $subject, $message);
    }
    
    // تحديث آخر دخول
    private function updateLastLogin($userId) {
        if ($this->hasLastLoginAt === null) {
            $this->hasLastLoginAt = $this->columnExists('last_login_at');
            $this->hasLastLogin   = $this->columnExists('last_login');
        }

        if ($this->hasLastLoginAt) {
            return $this->db->query("UPDATE {$this->table} SET last_login_at = NOW() WHERE id = ?", [$userId]);
        }
        if ($this->hasLastLogin) {
            return $this->db->query("UPDATE {$this->table} SET last_login = NOW() WHERE id = ?", [$userId]);
        }

        // لا يوجد عمود مناسب في بعض قواعد البيانات القديمة
        return true;
    }
    
    // الحصول على بيانات المستخدم
    public function getUser($userId) {
        if ($this->hasLastLoginAt === null) {
            $this->hasLastLoginAt = $this->columnExists('last_login_at');
            $this->hasLastLogin   = $this->columnExists('last_login');
        }

        $lastLoginSelect = 'NULL AS last_login';
        if ($this->hasLastLoginAt) {
            $lastLoginSelect = 'last_login_at AS last_login';
        } elseif ($this->hasLastLogin) {
            $lastLoginSelect = 'last_login';
        }

        $sql = "SELECT id, username, email, role, created_at, {$lastLoginSelect} FROM {$this->table} WHERE id = ?";
        $stmt = $this->db->query($sql, [$userId]);
        return $stmt->fetch();
    }
}
?>