<?php
require 'db.php';

$action = $_POST['action'] ?? '';

if ($action == 'register') {
    $name = $_POST['name'] ?? '';
    $username = $_POST['username'] ?? '';
    $password = $_POST['password'] ?? '';
    $class = $_POST['class'] ?? '';
    $phone = $_POST['phone'] ?? '';

    if (empty($name) || empty($username) || empty($password) || empty($class) || empty($phone)) {
        echo json_encode(["success" => false, "message" => "请填写所有字段"]);
        exit;
    }

    // Check if username exists
    $stmt = $conn->prepare("SELECT id FROM users WHERE username = ?");
    $stmt->bind_param("s", $username);
    $stmt->execute();
    if ($stmt->get_result()->num_rows > 0) {
        echo json_encode(["success" => false, "message" => "用户名已存在"]);
        exit;
    }

    $hashed_password = password_hash($password, PASSWORD_DEFAULT);
    $stmt = $conn->prepare("INSERT INTO users (name, username, password, class, phone) VALUES (?, ?, ?, ?, ?)");
    $stmt->bind_param("sssss", $name, $username, $hashed_password, $class, $phone);

    if ($stmt->execute()) {
        echo json_encode(["success" => true, "message" => "注册成功"]);
    } else {
        echo json_encode(["success" => false, "message" => "注册失败: " . $conn->error]);
    }
} elseif ($action == 'login') {
    $username = $_POST['username'] ?? '';
    $password = $_POST['password'] ?? '';

    if (empty($username) || empty($password)) {
        echo json_encode(["success" => false, "message" => "请输入用户名和密码"]);
        exit;
    }

    $stmt = $conn->prepare("SELECT id, password, name, class, phone FROM users WHERE username = ?");
    $stmt->bind_param("s", $username);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows > 0) {
        $user = $result->fetch_assoc();
        if (password_verify($password, $user['password'])) {
            $_SESSION['user_id'] = $user['id'];
            $_SESSION['user_name'] = $user['name'];
            echo json_encode(["success" => true, "message" => "登录成功", "user" => $user]);
        } else {
            echo json_encode(["success" => false, "message" => "密码错误"]);
        }
    } else {
        echo json_encode(["success" => false, "message" => "用户不存在"]);
    }
} elseif ($action == 'logout') {
    session_destroy();
    echo json_encode(["success" => true, "message" => "已退出登录"]);
} elseif ($action == 'check_session') {
    if (isset($_SESSION['user_id'])) {
        // Fetch fresh user info
        $user_id = $_SESSION['user_id'];
        $stmt = $conn->prepare("SELECT id, name, username, class, phone, avatar FROM users WHERE id = ?");
        $stmt->bind_param("i", $user_id);
        $stmt->execute();
        $res = $stmt->get_result();
        if ($res->num_rows > 0) {
            echo json_encode(["success" => true, "user" => $res->fetch_assoc()]);
        } else {
            session_destroy();
            echo json_encode(["success" => false]);
        }
    } else {
        echo json_encode(["success" => false]);
    }
} elseif ($action == 'recover') {
    $username = $_POST['username'] ?? '';
    $phone = $_POST['phone'] ?? '';
    $new_password = $_POST['new_password'] ?? '';

    if (empty($username) || empty($phone) || empty($new_password)) {
        echo json_encode(["success" => false, "message" => "请填写完整信息"]);
        exit;
    }

    $stmt = $conn->prepare("SELECT id FROM users WHERE username = ? AND phone = ?");
    $stmt->bind_param("ss", $username, $phone);
    $stmt->execute();
    if ($stmt->get_result()->num_rows > 0) {
        $hashed = password_hash($new_password, PASSWORD_DEFAULT);
        $update = $conn->prepare("UPDATE users SET password = ? WHERE username = ?");
        $update->bind_param("ss", $hashed, $username);
        if ($update->execute()) {
            echo json_encode(["success" => true, "message" => "密码重置成功，请登录"]);
        } else {
            echo json_encode(["success" => false, "message" => "重置失败"]);
        }
    } else {
        echo json_encode(["success" => false, "message" => "账号或手机号不匹配"]);
    }
} elseif ($action == 'update_profile') {
    if (!isset($_SESSION['user_id'])) {
        echo json_encode(["success" => false, "message" => "未登录"]);
        exit;
    }

    $user_id = $_SESSION['user_id'];
    $name = $_POST['name'] ?? '';
    
    // Start updating query construction
    $query = "UPDATE users SET ";
    $params = [];
    $types = "";
    
    if (!empty($name)) {
        $query .= "name = ?, ";
        $params[] = $name;
        $types .= "s";
    }
    
    // Handle avatar upload
    if (isset($_FILES['avatar']) && $_FILES['avatar']['error'] === UPLOAD_ERR_OK) {
        $target_dir = "../uploads/avatars/";
        if (!file_exists($target_dir)) {
            mkdir($target_dir, 0777, true);
        }
        
        $file_ext = strtolower(pathinfo($_FILES['avatar']['name'], PATHINFO_EXTENSION));
        $allowed = ['jpg', 'jpeg', 'png', 'gif'];
        
        if (in_array($file_ext, $allowed)) {
            // Use user_id + timestamp to avoid cache issues and collisions
            $new_filename = "avatar_" . $user_id . "_" . time() . "." . $file_ext;
            $target_file = $target_dir . $new_filename;
            
            if (move_uploaded_file($_FILES['avatar']['tmp_name'], $target_file)) {
                // Save relative path for frontend usage
                $db_path = "uploads/avatars/" . $new_filename;
                $query .= "avatar = ?, ";
                $params[] = $db_path;
                $types .= "s";
            } else {
                echo json_encode(["success" => false, "message" => "头像上传失败"]);
                exit;
            }
        } else {
            echo json_encode(["success" => false, "message" => "仅支持 JPG, PNG, GIF 格式"]);
            exit;
        }
    }
    
    // Remove trailing comma and space
    $query = rtrim($query, ", ");
    
    if (empty($params)) {
        echo json_encode(["success" => false, "message" => "没有需要更新的信息"]);
        exit;
    }
    
    $query .= " WHERE id = ?";
    $params[] = $user_id;
    $types .= "i";
    
    $stmt = $conn->prepare($query);
    $stmt->bind_param($types, ...$params);
    
    if ($stmt->execute()) {
        // Update session name if changed
        if (!empty($name)) {
            $_SESSION['user_name'] = $name;
        }
        
        // Fetch updated user data to return
        $stmt = $conn->prepare("SELECT id, name, username, class, phone, avatar FROM users WHERE id = ?");
        $stmt->bind_param("i", $user_id);
        $stmt->execute();
        $user = $stmt->get_result()->fetch_assoc();
        
        echo json_encode(["success" => true, "message" => "个人资料已更新", "user" => $user]);
    } else {
        echo json_encode(["success" => false, "message" => "更新失败: " . $conn->error]);
    }

} else {
    echo json_encode(["success" => false, "message" => "Invalid action"]);
}
?>
