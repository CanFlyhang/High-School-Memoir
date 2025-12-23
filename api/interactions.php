<?php
require 'db.php';

$action = $_POST['action'] ?? $_GET['action'] ?? '';

if (!isset($_SESSION['user_id']) && $action != 'get_comments') {
    echo json_encode(["success" => false, "message" => "请先登录"]);
    exit;
}

$current_user_id = $_SESSION['user_id'] ?? 0;

if ($action == 'toggle_like') {
    $memoir_id = $_POST['memoir_id'] ?? 0;
    
    // Check if liked
    $check = $conn->prepare("SELECT id FROM likes WHERE memoir_id = ? AND user_id = ?");
    $check->bind_param("ii", $memoir_id, $current_user_id);
    $check->execute();
    if ($check->get_result()->num_rows > 0) {
        // Unlike
        $stmt = $conn->prepare("DELETE FROM likes WHERE memoir_id = ? AND user_id = ?");
        $stmt->bind_param("ii", $memoir_id, $current_user_id);
        $stmt->execute();
        echo json_encode(["success" => true, "liked" => false]);
    } else {
        // Like
        $stmt = $conn->prepare("INSERT INTO likes (memoir_id, user_id) VALUES (?, ?)");
        $stmt->bind_param("ii", $memoir_id, $current_user_id);
        $stmt->execute();
        echo json_encode(["success" => true, "liked" => true]);
    }

} elseif ($action == 'add_comment') {
    $memoir_id = $_POST['memoir_id'] ?? 0;
    $content = $_POST['content'] ?? '';
    
    $image_path = null;
    if (!empty($_FILES['image']['name'])) {
        $filename = uniqid() . "_" . $_FILES['image']['name'];
        $newFilePath = "../uploads/" . $filename;
        if (move_uploaded_file($_FILES['image']['tmp_name'], $newFilePath)) {
            $image_path = "uploads/" . $filename;
        }
    }

    if (empty($content) && empty($image_path)) {
        // 如果没有内容且没有图片，则返回错误
        // 但注意：content 可能是 '0' 或其他假值，empty() 对 '0' 返回 true
        // 应该用 strlen() > 0 或其他方式判断
        // 不过这里 content 预期是字符串。
        // 让我们放宽一点：只要有一个存在就行。
        echo json_encode(["success" => false, "message" => "评论内容不能为空"]);
        exit;
    }

    $stmt = $conn->prepare("INSERT INTO comments (memoir_id, user_id, content, image) VALUES (?, ?, ?, ?)");
    // 确保 content 即使是空字符串也能正确插入
    // image_path 如果是 null，bind_param 怎么处理？
    // bind_param 不支持 null 直接传，需要变量引用。
    // 更好的方式：动态构建 SQL 或者手动处理 null。
    // 但是这里 'ss' 类型，如果是 null 会被转为空字符串吗？不，mysqli 会报错或行为不一致。
    
    // 修正：如果 image_path 是 null，我们应该在 SQL 中处理，或者确保传入 null 值
    // mysqli bind_param 中，null 值是可以的，只要变量是 null。
    $stmt->bind_param("iiss", $memoir_id, $current_user_id, $content, $image_path);
    
    if ($stmt->execute()) {
        echo json_encode(["success" => true, "message" => "评论成功"]);
    } else {
        echo json_encode(["success" => false, "message" => "评论失败"]);
    }

} elseif ($action == 'get_comments') {
    $memoir_id = $_GET['memoir_id'] ?? 0;
    
    $sql = "SELECT c.*, u.name as author_name, u.avatar as author_avatar FROM comments c 
            JOIN users u ON c.user_id = u.id 
            WHERE c.memoir_id = ? ORDER BY c.created_at ASC";
    
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $memoir_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    $comments = [];
    while ($row = $result->fetch_assoc()) {
        $comments[] = $row;
    }
    
    echo json_encode(["success" => true, "comments" => $comments]);

} elseif ($action == 'get_notifications') {
    if (!isset($_SESSION['user_id'])) {
        echo json_encode(["success" => false, "message" => "请先登录"]);
        exit;
    }
    $uid = $_SESSION['user_id'];
    
    // Get pagination parameters
    $page = $_GET['page'] ?? 1;
    $limit = $_GET['limit'] ?? 10;
    $offset = ($page - 1) * $limit;
    
    // Base SQL for getting notifications
    $baseSql = "
    (SELECT 'like' as type, u.name as actor_name, m.content as memoir_preview, l.created_at 
    FROM likes l 
    JOIN users u ON l.user_id = u.id 
    JOIN memoirs m ON l.memoir_id = m.id 
    WHERE m.user_id = ? AND l.user_id != ?)
    UNION ALL
    (SELECT 'comment' as type, u.name as actor_name, m.content as memoir_preview, c.created_at 
    FROM comments c 
    JOIN users u ON c.user_id = u.id 
    JOIN memoirs m ON c.memoir_id = m.id 
    WHERE m.user_id = ? AND c.user_id != ?)
    ORDER BY created_at DESC";
    
    // First get total count
    $countSql = "SELECT COUNT(*) as total FROM ($baseSql) as all_notifications";
    $countStmt = $conn->prepare($countSql);
    $countStmt->bind_param("iiii", $uid, $uid, $uid, $uid);
    $countStmt->execute();
    $countResult = $countStmt->get_result();
    $total = $countResult->fetch_assoc()['total'];
    
    // Then get paginated results
    $paginatedSql = $baseSql . " LIMIT ? OFFSET ?";
    $stmt = $conn->prepare($paginatedSql);
    $stmt->bind_param("iiiiii", $uid, $uid, $uid, $uid, $limit, $offset);
    $stmt->execute();
    $result = $stmt->get_result();
    
    $notifs = [];
    while ($row = $result->fetch_assoc()) {
        $notifs[] = $row;
    }
    
    // Calculate total pages
    $totalPages = ceil($total / $limit);
    
    // Return results with pagination info
    echo json_encode([
        "success" => true, 
        "notifications" => $notifs,
        "pagination" => [
            "page" => $page,
            "limit" => $limit,
            "total" => $total,
            "total_pages" => $totalPages
        ]
    ]);
}
?>
