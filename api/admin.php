<?php
require 'db.php';

$ADMIN_USER = 'admin';
$ADMIN_PASS = '123456';

$action = $_POST['action'] ?? $_GET['action'] ?? '';

if ($action == 'login') {
    $username = $_POST['username'] ?? '';
    $password = $_POST['password'] ?? '';

    if ($username === $ADMIN_USER && $password === $ADMIN_PASS) {
        $_SESSION['admin_logged_in'] = true;
        echo json_encode(["success" => true, "message" => "管理员登录成功"]);
    } else {
        echo json_encode(["success" => false, "message" => "账号或密码错误"]);
    }
    exit;
}

if ($action == 'logout') {
    unset($_SESSION['admin_logged_in']);
    echo json_encode(["success" => true]);
    exit;
}

// Middleware check
if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    echo json_encode(["success" => false, "message" => "无管理员权限"]);
    exit;
}

if ($action == 'list_users') {
    $result = $conn->query("SELECT * FROM users ORDER BY created_at DESC");
    $users = [];
    while ($row = $result->fetch_assoc()) {
        $users[] = $row;
    }
    echo json_encode(["success" => true, "users" => $users]);

} elseif ($action == 'delete_user') {
    $user_id = $_POST['user_id'] ?? 0;
    $stmt = $conn->prepare("DELETE FROM users WHERE id = ?");
    $stmt->bind_param("i", $user_id);
    if ($stmt->execute()) {
        echo json_encode(["success" => true, "message" => "用户删除成功"]);
    } else {
        echo json_encode(["success" => false, "message" => "删除失败"]);
    }

} elseif ($action == 'list_memoirs') {
    // Join with users table to get author name
    $result = $conn->query("SELECT m.*, u.name as author_name FROM memoirs m JOIN users u ON m.user_id = u.id ORDER BY m.created_at DESC");
    $memoirs = [];
    while ($row = $result->fetch_assoc()) {
        $memoirs[] = $row;
    }
    echo json_encode(["success" => true, "memoirs" => $memoirs]);

} elseif ($action == 'delete_memoir') {
    $memoir_id = $_POST['memoir_id'] ?? 0;
    $stmt = $conn->prepare("DELETE FROM memoirs WHERE id = ?");
    $stmt->bind_param("i", $memoir_id);
    if ($stmt->execute()) {
        echo json_encode(["success" => true, "message" => "回忆删除成功"]);
    } else {
        echo json_encode(["success" => false, "message" => "删除失败"]);
    }

} elseif ($action == 'add_announcement') {
    $content = $_POST['content'] ?? '';
    if (empty($content)) {
        echo json_encode(["success" => false, "message" => "内容不能为空"]);
        exit;
    }
    
    $stmt = $conn->prepare("INSERT INTO announcements (content) VALUES (?)");
    $stmt->bind_param("s", $content);
    if ($stmt->execute()) {
        echo json_encode(["success" => true, "message" => "公告发布成功"]);
    } else {
        echo json_encode(["success" => false, "message" => "发布失败: " . $conn->error]);
    }

} elseif ($action == 'delete_announcement') {
    $id = $_POST['id'] ?? 0;
    $stmt = $conn->prepare("DELETE FROM announcements WHERE id = ?");
    $stmt->bind_param("i", $id);
    if ($stmt->execute()) {
        echo json_encode(["success" => true, "message" => "公告删除成功"]);
    } else {
        echo json_encode(["success" => false, "message" => "删除失败"]);
    }

} elseif ($action == 'list_announcements') {
    $result = $conn->query("SELECT * FROM announcements ORDER BY created_at DESC");
    $list = [];
    while ($row = $result->fetch_assoc()) {
        $list[] = $row;
    }
    echo json_encode(["success" => true, "announcements" => $list]);

} elseif ($action == 'add_topic') {
    $name = $_POST['name'] ?? '';
    if (empty($name)) {
        echo json_encode(["success" => false, "message" => "话题名称不能为空"]);
        exit;
    }
    
    // Check duplication
    $check = $conn->prepare("SELECT id FROM topics WHERE name = ?");
    $check->bind_param("s", $name);
    $check->execute();
    if ($check->get_result()->num_rows > 0) {
        echo json_encode(["success" => false, "message" => "话题已存在"]);
        exit;
    }

    $stmt = $conn->prepare("INSERT INTO topics (name) VALUES (?)");
    $stmt->bind_param("s", $name);
    if ($stmt->execute()) {
        echo json_encode(["success" => true, "message" => "话题创建成功"]);
    } else {
        echo json_encode(["success" => false, "message" => "创建失败: " . $conn->error]);
    }

} elseif ($action == 'delete_topic') {
    $id = $_POST['id'] ?? 0;
    $stmt = $conn->prepare("DELETE FROM topics WHERE id = ?");
    $stmt->bind_param("i", $id);
    if ($stmt->execute()) {
        echo json_encode(["success" => true, "message" => "话题删除成功"]);
    } else {
        echo json_encode(["success" => false, "message" => "删除失败"]);
    }

} elseif ($action == 'list_topics') {
    $result = $conn->query("SELECT * FROM topics ORDER BY created_at DESC");
    $list = [];
    while ($row = $result->fetch_assoc()) {
        $list[] = $row;
    }
    echo json_encode(["success" => true, "topics" => $list]);

} else {
    echo json_encode(["success" => false, "message" => "Invalid action"]);
}
?>
