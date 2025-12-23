<?php
require 'db.php';

$action = $_POST['action'] ?? $_GET['action'] ?? '';

if ($action == 'create') {
    if (!isset($_SESSION['user_id'])) {
        echo json_encode(["success" => false, "message" => "请先登录"]);
        exit;
    }

    $content = $_POST['content'] ?? '';
    $topic_name = trim($_POST['topic_name'] ?? '');
    $user_id = $_SESSION['user_id'];
    
    if (empty($content) && empty($_FILES['images']['name'][0])) {
        echo json_encode(["success" => false, "message" => "内容不能为空"]);
        exit;
    }

    // Handle topic
    $topic_id = null;
    if (!empty($topic_name)) {
        // Check if topic exists
        $stmt = $conn->prepare("SELECT id FROM topics WHERE name = ?");
        $stmt->bind_param("s", $topic_name);
        $stmt->execute();
        $res = $stmt->get_result();
        if ($res->num_rows > 0) {
            $topic_id = $res->fetch_assoc()['id'];
        } else {
            // Create new topic
            $stmt = $conn->prepare("INSERT INTO topics (name) VALUES (?)");
            $stmt->bind_param("s", $topic_name);
            if ($stmt->execute()) {
                $topic_id = $stmt->insert_id;
            }
        }
    }

    $image_paths = [];
    if (!empty($_FILES['images']['name'][0])) {
        $total = count($_FILES['images']['name']);
        for ($i = 0; $i < $total; $i++) {
            $tmpFilePath = $_FILES['images']['tmp_name'][$i];
            if ($tmpFilePath != "") {
                $filename = uniqid() . "_" . $_FILES['images']['name'][$i];
                $newFilePath = "../uploads/" . $filename;
                if (move_uploaded_file($tmpFilePath, $newFilePath)) {
                    $image_paths[] = "uploads/" . $filename;
                }
            }
        }
    }

    $images_json = json_encode($image_paths);

    $stmt = $conn->prepare("INSERT INTO memoirs (user_id, content, images, topic_id) VALUES (?, ?, ?, ?)");
    $stmt->bind_param("issi", $user_id, $content, $images_json, $topic_id);

    if ($stmt->execute()) {
        echo json_encode(["success" => true, "message" => "发布成功"]);
    } else {
        echo json_encode(["success" => false, "message" => "发布失败: " . $conn->error]);
    }

} elseif ($action == 'list') {
    $search = $_GET['search'] ?? '';
    $filter_user_id = $_GET['user_id'] ?? 0;
    $filter_topic_id = $_GET['topic_id'] ?? 0;
    $current_user_id = $_SESSION['user_id'] ?? 0;
    
    // Pagination params
    $page = isset($_GET['page']) ? max(1, intval($_GET['page'])) : 1;
    $limit = isset($_GET['limit']) ? max(1, intval($_GET['limit'])) : 5; // Default 5 per page
    $offset = ($page - 1) * $limit;

    // Base conditions
    $where = "WHERE 1=1";
    $types = "";
    $params = [];
    
    if ($filter_user_id > 0) {
        $where .= " AND m.user_id = ?";
        $types .= "i";
        $params[] = $filter_user_id;
    }

    if ($filter_topic_id > 0) {
        $where .= " AND m.topic_id = ?";
        $types .= "i";
        $params[] = $filter_topic_id;
    }

    if (!empty($search)) {
        $where .= " AND (m.content LIKE ? OR u.name LIKE ?)";
        $search_term = "%" . $search . "%";
        $types .= "ss";
        $params[] = $search_term;
        $params[] = $search_term;
    }

    // 1. Get total count
    $count_sql = "SELECT COUNT(*) as total FROM memoirs m JOIN users u ON m.user_id = u.id $where";
    $stmt = $conn->prepare($count_sql);
    if (!empty($params)) {
        $stmt->bind_param($types, ...$params);
    }
    $stmt->execute();
    $total_result = $stmt->get_result();
    $total_rows = $total_result->fetch_assoc()['total'];
    $total_pages = ceil($total_rows / $limit);

    // 2. Get data
    $sql = "SELECT m.*, u.name as author_name, u.class as author_class, u.avatar as author_avatar, t.name as topic_name,
            (SELECT COUNT(*) FROM likes l WHERE l.memoir_id = m.id) as likes_count,
            (SELECT COUNT(*) FROM comments c WHERE c.memoir_id = m.id) as comments_count,
            (SELECT COUNT(*) FROM likes l2 WHERE l2.memoir_id = m.id AND l2.user_id = ?) as is_liked
            FROM memoirs m 
            JOIN users u ON m.user_id = u.id 
            LEFT JOIN topics t ON m.topic_id = t.id
            $where
            ORDER BY m.created_at DESC
            LIMIT ? OFFSET ?";
    
    // Add params for is_liked subquery (first param) and limit/offset (last params)
    // The is_liked param needs to be prepended, but our $params array is for the WHERE clause.
    // The structure is: SELECT ... (subquery param) ... FROM ... WHERE (where params) ... LIMIT (limit params)
    
    // Reconstruct params array
    $final_params = [$current_user_id]; // for is_liked
    $final_types = "i";
    
    // Add where params
    if (!empty($params)) {
        $final_params = array_merge($final_params, $params);
        $final_types .= $types;
    }
    
    // Add limit/offset params
    $final_params[] = $limit;
    $final_params[] = $offset;
    $final_types .= "ii";

    $stmt = $conn->prepare($sql);
    if (!$stmt) {
        echo json_encode(["success" => false, "message" => "SQL准备失败: " . $conn->error]);
        exit;
    }
    $stmt->bind_param($final_types, ...$final_params);
    $stmt->execute();
    $result = $stmt->get_result();
    
    $memoirs = [];
    while ($row = $result->fetch_assoc()) {
        $row['images'] = json_decode($row['images']);
        $memoirs[] = $row;
    }
    
    echo json_encode([
        "success" => true, 
        "memoirs" => $memoirs,
        "pagination" => [
            "page" => $page,
            "limit" => $limit,
            "total" => $total_rows,
            "total_pages" => $total_pages
        ]
    ]);

} elseif ($action == 'popular') {
    $sql = "SELECT m.*, u.name as author_name, 
            (SELECT COUNT(*) FROM likes l WHERE l.memoir_id = m.id) as likes_count
            FROM memoirs m 
            JOIN users u ON m.user_id = u.id 
            ORDER BY likes_count DESC LIMIT 10";
    
    $result = $conn->query($sql);
    
    if (!$result) {
        echo json_encode(["success" => false, "message" => "查询失败: " . $conn->error]);
        exit;
    }

    $memoirs = [];
    while ($row = $result->fetch_assoc()) {
        // Handle string truncation safely (fallback if mb_substr is missing)
        $content = $row['content'];
        if (function_exists('mb_substr')) {
            $preview = mb_substr($content, 0, 20);
        } else {
            // Fallback for environments without mbstring
            $preview = substr($content, 0, 60); // 20 chars * 3 bytes approx
        }
        
        // Only minimal info for sidebar
        $memoirs[] = [
            'id' => $row['id'],
            'content' => $preview . '...',
            'author_name' => $row['author_name'],
            'likes_count' => $row['likes_count']
        ];
    }
    echo json_encode(["success" => true, "memoirs" => $memoirs]);

} elseif ($action == 'delete') {
    if (!isset($_SESSION['user_id'])) {
        echo json_encode(["success" => false, "message" => "请先登录"]);
        exit;
    }
    
    $memoir_id = $_POST['memoir_id'] ?? 0;
    $user_id = $_SESSION['user_id'];
    
    // Check ownership or admin (admin is handled via separate flow usually, but let's allow admin check here if needed, 
    // though admin has no DB session usually. Wait, admin is fixed account. Admin needs a way to delete.)
    // For now, check ownership. Admin delete will be in admin.php or handled by a special flag.
    
    $stmt = $conn->prepare("DELETE FROM memoirs WHERE id = ? AND user_id = ?");
    $stmt->bind_param("ii", $memoir_id, $user_id);
    
    if ($stmt->execute() && $stmt->affected_rows > 0) {
        echo json_encode(["success" => true, "message" => "删除成功"]);
    } else {
        echo json_encode(["success" => false, "message" => "删除失败或无权限"]);
    }
} else {
    echo json_encode(["success" => false, "message" => "Invalid action: " . $action]);
}
?>
