<?php
// Database connection
$servername = "localhost";
$username = "root";
$password = "";
$dbname = "xvriez";

$conn = new mysqli($servername, $username, $password, $dbname);

if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// --- NEW: Handle bulk category change request from AJAX ---
if (isset($_POST['bulk_photo_ids']) && isset($_POST['new_category_id'])) {
    $photo_ids = $_POST['bulk_photo_ids']; // This is an array
    $category_id = $_POST['new_category_id'];

    if (!is_array($photo_ids) || empty($photo_ids)) {
        echo json_encode(['status' => 'error', 'message' => 'No photos selected.']);
        $conn->close();
        exit();
    }

    $sql_category_id = ($category_id == '0' || $category_id == '') ? NULL : (int)$category_id;
    
    // Create placeholders for the IN clause, e.g., (?, ?, ?)
    $placeholders = implode(',', array_fill(0, count($photo_ids), '?'));
    $sql = "UPDATE photos SET category_id = ? WHERE id IN ($placeholders)";
    
    // Create the types string, e.g., 'sii' for 1 string/null and 2 integers
    $types = ($sql_category_id === NULL ? 's' : 'i') . str_repeat('i', count($photo_ids));
    
    // Merge the category_id with the photo_ids for binding
    $params = array_merge([$sql_category_id], $photo_ids);
    
    $stmt = $conn->prepare($sql);
    // Use the splat operator (...) to bind params dynamically
    $stmt->bind_param($types, ...$params);

    if ($stmt->execute()) {
        echo json_encode(['status' => 'success', 'message' => 'Categories updated successfully.']);
    } else {
        echo json_encode(['status' => 'error', 'message' => 'Failed to update categories in the database.']);
    }
    $stmt->close();
    $conn->close();
    exit();
}


// Handle rename request from AJAX
if (isset($_POST['rename_id']) && isset($_POST['new_name'])) {
    $id = $_POST['rename_id'];
    $new_name = $_POST['new_name'];

    $sql = "UPDATE photos SET custom_name = ? WHERE id = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("si", $new_name, $id);

    if ($stmt->execute()) {
        echo json_encode(['status' => 'success', 'new_name' => $new_name]);
    } else {
        echo json_encode(['status' => 'error', 'message' => 'Failed to update name in the database.']);
    }
    $stmt->close();
    $conn->close();
    exit();
}

// Handle multi-file upload
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_FILES["fileToUpload"])) {
    $target_dir = "assets/";
    $file_count = count($_FILES['fileToUpload']['name']);
    
    $category_id = isset($_POST['category']) ? (int)$_POST['category'] : 0;
    $sql_category_id = ($category_id === 0) ? NULL : $category_id;

    $sql = "INSERT INTO photos (filename, custom_name, category_id) VALUES (?, ?, ?)";
    $stmt = $conn->prepare($sql);

    for ($i = 0; $i < $file_count; $i++) {
        if ($_FILES['fileToUpload']['error'][$i] === UPLOAD_ERR_OK) {
            $original_filename = basename($_FILES["fileToUpload"]["name"][$i]);
            $file_extension = pathinfo($original_filename, PATHINFO_EXTENSION);
            $unique_filename = uniqid() . '_' . time() . '_' . $i . '.' . $file_extension;
            $target_file = $target_dir . $unique_filename;

            if (getimagesize($_FILES["fileToUpload"]["tmp_name"][$i])) {
                if (move_uploaded_file($_FILES["fileToUpload"]["tmp_name"][$i], $target_file)) {
                    $custom_name = pathinfo($original_filename, PATHINFO_FILENAME);
                    $stmt->bind_param("ssi", $unique_filename, $custom_name, $sql_category_id);
                    $stmt->execute();
                }
            }
        }
    }
    $stmt->close();
    header("Location: " . $_SERVER['PHP_SELF']);
    exit();
}


// Handle delete request
if (isset($_POST['delete_id'])) {
    $id = $_POST['delete_id'];
    $sql_select = "SELECT filename FROM photos WHERE id = ?";
    $stmt_select = $conn->prepare($sql_select);
    $stmt_select->bind_param("i", $id);
    $stmt_select->execute();
    $result_select = $stmt_select->get_result();
    $row = $result_select->fetch_assoc();
    $filename = $row['filename'];
    $sql_delete = "DELETE FROM photos WHERE id = ?";
    $stmt_delete = $conn->prepare($sql_delete);
    $stmt_delete->bind_param("i", $id);
    if ($stmt_delete->execute()) {
        if (file_exists("assets/" . $filename)) {
            unlink("assets/" . $filename);
        }
        echo json_encode(['status' => 'success']);
    } else {
        echo json_encode(['status' => 'error', 'message' => 'Failed to delete data from the database.']);
    }
    $conn->close();
    exit();
}

// Handle favorite request from AJAX
if (isset($_POST['id'])) {
    $id = $_POST['id'];
    $sql_select = "SELECT is_favorite FROM photos WHERE id = ?";
    $stmt_select = $conn->prepare($sql_select);
    $stmt_select->bind_param("i", $id);
    $stmt_select->execute();
    $result_select = $stmt_select->get_result();
    $row = $result_select->fetch_assoc();
    $is_favorite = $row['is_favorite'] == 1 ? 0 : 1;
    $sql_update = "UPDATE photos SET is_favorite = ? WHERE id = ?";
    $stmt_update = $conn->prepare($sql_update);
    $stmt_update->bind_param("ii", $is_favorite, $id);
    if ($stmt_update->execute()) {
        echo json_encode(['status' => 'success', 'is_favorite' => $is_favorite]);
    } else {
        echo json_encode(['status' => 'error', 'message' => 'Failed to change favorite status.']);
    }
    $conn->close();
    exit();
}

// Fetch all categories from the database
$categories_sql = "SELECT id, name FROM categories ORDER BY name ASC";
$categories_result = $conn->query($categories_sql);
$categories = [];
if ($categories_result->num_rows > 0) {
    while($row = $categories_result->fetch_assoc()) {
        $categories[] = $row;
    }
}

?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Galeri Aset XVRIEZ</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700&display=swap" rel="stylesheet">
    <style>
        :root {
            --primary-color: #007bff;
            --primary-hover: #0056b3;
            --secondary-color: #6c757d;
            --secondary-hover: #5a6268;
            --shadow: 0 4px 12px rgba(0, 0, 0, 0.08);
            --border-radius: 12px;
            --background-color: #f8f9fa;
            --sidebar-bg: #ffffff;
            --card-bg: #ffffff;
            --text-color: #343a40;
            --text-muted: #6c757d;
            --border-color: #dee2e6;
            --nav-hover-bg: #e9ecef;
            --nav-active-bg: #F1F5F9;
        }
        body.dark-mode {
            --background-color: #18181b;
            --sidebar-bg: #27272a;
            --card-bg: #27272a;
            --text-color: #e4e4e7;
            --text-muted: #a1a1aa;
            --border-color: #3f3f46;
            --shadow: 0 4px 12px rgba(0, 0, 0, 0.2);
            --nav-hover-bg: #3f3f46;
            --nav-active-bg: #3f3f46;
        }

        * { box-sizing: border-box; margin: 0; padding: 0; }
        body {
            font-family: 'Poppins', sans-serif;
            background-color: var(--background-color);
            color: var(--text-color);
            display: flex;
            min-height: 100vh;
            transition: background-color 0.3s, color 0.3s;
        }
        .sidebar{width:260px;background-color:var(--sidebar-bg);padding:24px;border-right:1px solid var(--border-color);display:flex;flex-direction:column;flex-shrink:0;transition:background-color .3s,border-color .3s}.sidebar-header{margin-bottom:32px}.home-button{display:flex;align-items:center;gap:10px;padding:12px;width:100%;border-radius:var(--border-radius);background-color:var(--background-color);border:1px solid var(--border-color);font-size:15px;font-weight:500;color:var(--text-color);text-decoration:none;transition:background-color .2s,color .2s,border-color .2s}.home-button:hover{background-color:var(--primary-color);color:#fff;border-color:var(--primary-color)}.home-button svg{transition:fill .2s;fill:var(--text-color)}.home-button:hover svg{fill:#fff}.nav-section h3{font-size:12px;font-weight:600;color:var(--text-muted);text-transform:uppercase;letter-spacing:.5px;margin-bottom:12px;padding:0 12px}.nav-list{list-style-type:none;display:flex;flex-direction:column;gap:4px;margin-bottom:24px}.nav-list a{display:flex;align-items:center;gap:12px;padding:12px;border-radius:10px;text-decoration:none;color:var(--text-muted);font-weight:500;font-size:15px;transition:background-color .2s,color .2s}.nav-list a svg{fill:var(--text-muted);transition:fill .2s}.nav-list a.active,.nav-list a:hover{background-color:var(--nav-hover-bg);color:var(--text-color)}.nav-list a.active svg,.nav-list a:hover svg{fill:var(--text-color)}.nav-list a.active{background-color:var(--nav-active-bg);font-weight:600}.category-dropdown-toggle{display:flex;justify-content:flex-start;align-items:center;width:100%;cursor:pointer;padding:0;border-radius:10px;background-color:transparent;border:none;color:var(--text-muted)}.category-dropdown-toggle:hover{color:var(--text-color)}.category-dropdown-toggle h3{padding:12px;margin:0;pointer-events:none}.category-submenu{padding-left:20px;max-height:0;overflow:hidden;transition:max-height .3s ease-out;margin-bottom:24px}#texture-submenu{margin-bottom:0}
        .main-content { flex-grow: 1; padding: 32px; overflow-y: auto; }
        .gallery-header{display:flex;justify-content:space-between;align-items:center;margin-bottom:24px;gap:16px}.gallery-header h1{font-size:28px;font-weight:600;flex-shrink:0}.header-buttons{display:flex;gap:12px}.upload-button{display:inline-flex;align-items:center;gap:8px;padding:12px 20px;border:none;background-color:var(--primary-color);color:#fff;border-radius:var(--border-radius);cursor:pointer;font-size:15px;font-weight:500;transition:background-color .2s,box-shadow .2s;box-shadow:0 2px 4px rgba(0,0,0,.1)}.upload-button:hover{background-color:var(--primary-hover);box-shadow:0 4px 8px rgba(0,0,0,.15)}.secondary-button{background-color:var(--secondary-color);}.secondary-button:hover{background-color:var(--secondary-hover);}
        .gallery{display:grid;grid-template-columns:repeat(auto-fill,minmax(250px,1fr));gap:24px}
        .photo-container{position:relative;aspect-ratio:1 / 1;background-color:var(--card-bg);border-radius:var(--border-radius);box-shadow:var(--shadow);overflow:hidden;transition:transform .3s ease,box-shadow .3s ease;border:2px solid transparent}
        
        /* --- NEW: Styles for Bulk Edit --- */
        body.selection-mode .photo-container {
            cursor: pointer;
        }
        .photo-container.is-selected {
            border-color: var(--primary-color);
            box-shadow: 0 0 0 3px var(--primary-color), var(--shadow);
        }
        .selection-overlay {
            position: absolute;
            top: 0; left: 0;
            width: 100%; height: 100%;
            background-color: rgba(0, 123, 255, 0.3);
            display: flex;
            align-items: center;
            justify-content: center;
            opacity: 0;
            transition: opacity 0.2s ease;
            pointer-events: none; /* Allows clicking through */
        }
        .photo-container.is-selected .selection-overlay {
            opacity: 1;
        }
        .selection-overlay svg {
            width: 50px;
            height: 50px;
            fill: rgba(255, 255, 255, 0.9);
            filter: drop-shadow(0 2px 4px rgba(0,0,0,0.5));
        }
        
        .photo-container:hover{transform:translateY(-5px);box-shadow:0 8px 20px rgba(0,0,0,.12)}.photo-container img{width:100%;height:100%;object-fit:cover}.photo-overlay{position:absolute;bottom:0;left:0;right:0;display:flex;align-items:flex-end;padding:12px;background:linear-gradient(to top,rgba(0,0,0,.7) 0%,transparent 100%);transition:opacity .3s ease,transform .3s ease;opacity:0;transform:translateY(10px);height:50%}.photo-container:hover .photo-overlay{opacity:1;transform:translateY(0)}.photo-info{display:flex;align-items:center;gap:8px}.title{color:#fff;font-weight:600;text-shadow:1px 1px 3px rgba(0,0,0,.5)}.photo-header{position:absolute;top:10px;right:10px;display:flex;gap:8px;z-index:5;opacity:0;transform:translateY(-10px);transition:opacity .3s ease,transform .3s ease}.photo-container:hover .photo-header{opacity:1;transform:translateY(0)}.favorite-button{background:rgba(255,255,255,.8);border:none;border-radius:50%;width:32px;height:32px;display:flex;align-items:center;justify-content:center;cursor:pointer;font-size:18px;color:#ccc;transition:background .2s,color .2s;box-shadow:0 2px 4px rgba(0,0,0,.2)}.favorite-button.is-favorite{color:#ff4d4d}.favorite-button:hover{background:rgba(255,255,255,.95);color:red}body.dark-mode .favorite-button{background:rgba(0,0,0,.6);color:#777}body.dark-mode .favorite-button.is-favorite{color:#ff4d4d}body.dark-mode .favorite-button:hover{background:rgba(0,0,0,.8);color:red}.dropdown-toggle{background:rgba(255,255,255,.8);border:none;border-radius:50%;width:32px;height:32px;display:flex;align-items:center;justify-content:center;color:var(--text-color);cursor:pointer;box-shadow:0 2px 4px rgba(0,0,0,.2);transition:background .2s}.dropdown-toggle:hover{background:rgba(255,255,255,.95)}body.dark-mode .dropdown-toggle{background:rgba(0,0,0,.6);color:var(--text-color)}body.dark-mode .dropdown-toggle:hover{background:rgba(0,0,0,.8)}
        .dropdown-menu{display:none;position:absolute;top:calc(100% + 5px);right:0;background-color:var(--card-bg);box-shadow:var(--shadow);border-radius:10px;padding:8px;z-index:10;min-width:200px}.dropdown-menu button,.dropdown-menu a{display:flex;gap:8px;align-items:center;width:100%;background:none;border:none;padding:10px;text-align:left;cursor:pointer;color:var(--text-color);font-size:14px;border-radius:6px;text-decoration:none}.dropdown-menu svg{fill:var(--text-color)}.dropdown-menu button:hover,.dropdown-menu a:hover{background-color:var(--nav-hover-bg)}
        hr.dropdown-divider{border:0;height:1px;background-color:var(--border-color);margin:8px 0}
        .sidebar-footer{margin-top:auto;padding-top:24px;border-top:1px solid var(--border-color);transition:border-color .3s}.theme-toggle{width:100%;padding:10px;background-color:var(--background-color);border:1px solid var(--border-color);border-radius:var(--border-radius);cursor:pointer;display:flex;align-items:center;justify-content:center;transition:background-color .3s,border-color .3s}.theme-toggle .icon{width:24px;height:24px;color:var(--text-muted)}.theme-toggle .moon{display:none}body.dark-mode .theme-toggle .sun{display:none}body.dark-mode .theme-toggle .moon{display:block}
        .modal{display:none;position:fixed;z-index:100;left:0;top:0;width:100%;height:100%;overflow:auto;background-color:rgba(0,0,0,.6);justify-content:center;align-items:center}.modal-content{background-color:var(--card-bg);padding:30px;border-radius:var(--border-radius);width:90%;max-width:550px;box-shadow:var(--shadow);text-align:center;position:relative;color:var(--text-color);transition:background-color .3s,color .3s;display:flex;flex-direction:column;gap:20px}.modal-header{display:flex;justify-content:space-between;align-items:center;margin-bottom:15px}.modal-header h2{font-size:24px;font-weight:600;color:var(--text-color);margin:0}.close-button{color:var(--text-muted);font-size:28px;font-weight:400;cursor:pointer;line-height:1;transition:color .2s}.close-button:hover{color:var(--text-color)}.modal-form-item{text-align:left;margin-bottom:15px}.modal-form-item label{display:block;font-size:14px;font-weight:500;margin-bottom:8px;color:var(--text-color)}.modal-form-item input[type=text],.modal-form-item select{width:100%;padding:12px;border:1px solid var(--border-color);border-radius:8px;background-color:var(--background-color);color:var(--text-color);font-size:15px;transition:border-color .2s,background-color .2s,box-shadow .2s}.modal-form-item input[type=text]:focus,.modal-form-item select:focus{outline:0;border-color:var(--primary-color);box-shadow:0 0 0 3px rgba(0,123,255,.25)}.file-input-wrapper{position:relative;overflow:hidden;display:flex;align-items:center;width:100%;border:1px solid var(--border-color);border-radius:8px;background-color:var(--background-color);padding:8px 12px;cursor:pointer;transition:border-color .2s,background-color .2s,box-shadow .2s}.file-input-wrapper:hover{border-color:var(--primary-color)}.file-input-wrapper input[type=file]{position:absolute;left:0;top:0;opacity:0;width:100%;height:100%;cursor:pointer}.file-input-display{flex-grow:1;color:var(--text-muted);font-size:15px;text-align:left;white-space:nowrap;overflow:hidden;text-overflow:ellipsis}.file-input-button{background-color:var(--primary-color);color:#fff;padding:8px 15px;border-radius:6px;font-size:14px;font-weight:500;cursor:pointer;transition:background-color .2s;flex-shrink:0}.file-input-button:hover{background-color:var(--primary-hover)}.modal-footer{padding-top:15px;border-top:1px solid var(--border-color);margin-top:10px}.modal-footer .upload-button{width:100%;padding:12px 20px;border-radius:8px;font-size:16px;font-weight:600;display:flex;justify-content:center;align-items:center;gap:8px}
    </style>
    <script>
        (function(){const e=localStorage.getItem("theme");e==="dark"&&document.addEventListener("DOMContentLoaded",()=>{document.body.classList.add("dark-mode")})})();
    </script>
</head>
<body>
    <div class="sidebar">
        <div class="sidebar-header">
             <a href="main.html" class="home-button">
                <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 256 256"><path d="M240,117.22l-80-75.49V16a8,8,0,0,0-16,0V53.53L130.63,42.2A8,8,0,0,0,120,48.15v0l-96,90.58A8,8,0,0,0,24,144H40v72a8,8,0,0,0,8,8H208a8,8,0,0,0,8-8V144h16a8,8,0,0,0,8-8A8,8,0,0,0,240,117.22ZM200,216H56V144H200ZM224,128H32L128,37.48,224,128Z"></path></svg>
                <span>Back to Home</span>
            </a>
        </div>

        <?php 
            $filter = isset($_GET['filter']) ? $_GET['filter'] : '';
            $category_id = isset($_GET['category_id']) ? $_GET['category_id'] : ''; 
        ?>

        <nav class="nav-section">
            <h3>Library</h3>
            <ul class="nav-list">
                <li><a href="my_assets.php" class="<?php echo ($filter == '') ? 'active' : ''; ?>">
                    <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 256 256"><path d="M224,48H32A8,8,0,0,0,24,56V200a8,8,0,0,0,8,8H224a8,8,0,0,0,8-8V56A8,8,0,0,0,224,48ZM40,192V64H216V192Z"></path></svg>
                    <span>All</span>
                </a></li>
                <li><a href="my_assets.php?filter=favorite" class="<?php echo ($filter == 'favorite') ? 'active' : ''; ?>">
                    <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 256 256"><path d="M128,216S28,160,28,92A52,52,0,0,1,128,64a52,52,0,0,1,100,28C228,160,128,216,128,216ZM80,92a44,44,0,1,0,88,0c0-42.2-64.8-86.23-76.32-95.27a11.9,11.9,0,0,0-15.36,0C89,62,80,92,80,92Z"></path></svg>
                    <span>Favorites</span>
                </a></li>
            </ul>
        </nav>

        <div class="nav-section">
            <button class="category-dropdown-toggle" id="texture-dropdown-toggle">
                <h3>Texture</h3>
            </button>
            <ul class="nav-list category-submenu" id="texture-submenu">
                <?php foreach ($categories as $cat): ?>
                    <li><a href="my_assets.php?filter=category&category_id=<?php echo $cat['id']; ?>" 
                           class="<?php echo ($filter == 'category' && $category_id == $cat['id']) ? 'active' : ''; ?>">
                        <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 256 256"><path d="M208,32H48A16,16,0,0,0,32,48V208a16,16,0,0,0,16,16H208a16,16,0,0,0,16-16V48A16,16,0,0,0,208,32Zm0,176H48V48H208V208ZM120,96a8,8,0,0,1-8,8H88a8,8,0,0,1-8-8V72a8,8,0,0,1,16,0V88h16A8,8,0,0,1,120,96Zm48,32H80a8,8,0,0,0-8,8v40a8,8,0,0,0,16,0V144h64v24a8,8,0,0,0,16,0V136A8,8,0,0,0,168,128Z"></path></svg>
                        <span><?php echo htmlspecialchars($cat['name']); ?></span>
                    </a></li>
                <?php endforeach; ?>
            </ul>
        </div>
        
        <div class="sidebar-footer">
            <button id="theme-toggle" class="theme-toggle" title="Change Theme">
                <svg class="icon sun" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="5"></circle><line x1="12" y1="1" x2="12" y2="3"></line><line x1="12" y1="21" x2="12" y2="23"></line><line x1="4.22" y1="4.22" x2="5.64" y2="5.64"></line><line x1="18.36" y1="18.36" x2="19.78" y2="19.78"></line><line x1="1" y1="12" x2="3" y2="12"></line><line x1="21" y1="12" x2="23" y2="12"></line><line x1="4.22" y1="19.78" x2="5.64" y2="18.36"></line><line x1="18.36" y1="5.64" x2="19.78" y2="4.22"></line></svg>
                <svg class="icon moon" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 12.79A9 9 0 1 1 11.21 3 7 7 0 0 0 21 12.79z"></path></svg>
            </button>
        </div>
    </div>

    <div class="main-content">
        <div class="gallery-header">
            <h1>Your Photo Gallery</h1>
            <div class="header-buttons">
                <button class="upload-button secondary-button" id="bulk-edit-button">Ubah Kategori</button>
                <button class="upload-button" onclick="openModal('uploadModal')">
                    <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" fill="currentColor" viewBox="0 0 256 256"><path d="M224,128a8,8,0,0,1-8,8H136v80a8,8,0,0,1-16,0V136H40a8,8,0,0,1,0-16h80V40a8,8,0,0,1,16,0v80h80A8,8,0,0,1,224,128Z"></path></svg>
                    <span>Upload File</span>
                </button>
            </div>
        </div>
        <div class="gallery">
            <?php
            $sql = "SELECT id, filename, custom_name, is_favorite FROM photos";
            $where_clauses = [];
            $params = [];
            $types = '';

            if ($filter == 'favorite') {
                $where_clauses[] = "is_favorite = 1";
            } elseif ($filter == 'category' && !empty($category_id)) {
                $where_clauses[] = "category_id = ?";
                $params[] = $category_id;
                $types .= 'i';
            }

            if (!empty($where_clauses)) {
                $sql .= " WHERE " . implode(" AND ", $where_clauses);
            }
            $sql .= " ORDER BY id DESC";
            
            $stmt = $conn->prepare($sql);
            if (!empty($params)) {
                $stmt->bind_param($types, ...$params);
            }
            $stmt->execute();
            $result = $stmt->get_result();

            if ($result->num_rows > 0) {
                while($row = $result->fetch_assoc()) {
                    $file_path = "assets/" . $row["filename"];
                    $is_fav_class = $row["is_favorite"] == 1 ? 'is-favorite' : '';

                    echo '<div class="photo-container" data-id="' . $row["id"] . '">'; // Add data-id attribute
                    echo '  <img src="' . $file_path . '" alt="' . htmlspecialchars($row["custom_name"]) . '">';
                    // NEW: Selection overlay
                    echo '  <div class="selection-overlay"><svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor"><path d="M9 16.2L4.8 12l-1.4 1.4L9 19 21 7l-1.4-1.4L9 16.2z"/></svg></div>';
                    echo '  <div class="photo-header">';
                    echo '      <button class="favorite-button ' . $is_fav_class . '" onclick="toggleFavorite(' . $row["id"] . ', this)">&#x2764;</button>';
                    echo '      <div class="dropdown">';
                    echo '          <button class="dropdown-toggle" onclick="toggleDropdown(this)">&#x22EE;</button>';
                    echo '          <div class="dropdown-menu">';
                    echo '              <a href="' . $file_path . '" download><svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 256 256"><path d="M208,128a8,8,0,0,1-8,8H136v56a8,8,0,0,1-16,0V136H56a8,8,0,0,1,0-16h64V64a8,8,0,0,1,16,0v56h64A8,8,0,0,1,208,128Zm24,80H24a8,8,0,0,1,0-16H232a8,8,0,0,1,0,16Z"></path></svg><span>Download</span></a>';
                    echo '              <button onclick="openRenameModal(' . $row["id"] . ', \'' . htmlspecialchars($row["custom_name"], ENT_QUOTES) . '\')"><svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 256 256"><path d="M227.31,73.37,182.63,28.68a16,16,0,0,0-22.63,0L36.69,152A15.86,15.86,0,0,0,32,163.31V208a16,16,0,0,0,16,16H92.69A15.86,15.86,0,0,0,104,219.31L227.31,96a16,16,0,0,0,0-22.63ZM92.69,208H48V163.31l88-88L180.69,120ZM192,108.68,147.31,64l24-24L216,84.68Z"></path></svg><span>Rename</span></button>';
                    
                    // --- REMOVED: Category Change Submenu is now deleted ---
                    
                    echo '              <hr class="dropdown-divider">';
                    echo '              <button onclick="deletePhoto(' . $row["id"] . ', this)"><svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 256 256"><path d="M216,48H176V40a24,24,0,0,0-24-24H104A24,24,0,0,0,80,40v8H40a8,8,0,0,0,0,16h8V208a16,16,0,0,0,16,16H192a16,16,0,0,0,16-16V64h8a8,8,0,0,0,0-16ZM96,40a8,8,0,0,1,8-8h48a8,8,0,0,1,8,8v8H96ZM192,208H64V64H192Z"></path></svg><span>Delete</span></button>';
                    echo '          </div>';
                    echo '      </div>';
                    echo '  </div>';
                    echo '  <div class="photo-overlay">';
                    echo '      <div class="photo-info">';
                    echo '          <span class="title">' . htmlspecialchars($row["custom_name"]) . '</span>';
                    echo '      </div>';
                    echo '  </div>';
                    echo '</div>';
                }
            } else {
                echo "<p style='color: var(--text-muted);'>No photos found.</p>";
            }
            $conn->close();
            ?>
        </div>
    </div>
    
    <div id="uploadModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h2>Upload File</h2>
                <span class="close-button" onclick="closeModal('uploadModal')">&times;</span>
            </div>
            <form action="my_assets.php" method="POST" enctype="multipart/form-data" id="uploadForm">
                <div class="modal-form-item">
                    <label for="category">Category:</label>
                    <select name="category" id="category">
                        <option value="0">General</option>
                        <?php foreach ($categories as $cat): ?>
                            <option value="<?php echo $cat['id']; ?>"><?php echo htmlspecialchars($cat['name']); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="modal-form-item">
                    <label for="fileToUpload">Select File (Multiple files allowed):</label>
                    <div class="file-input-wrapper">
                        <span id="file-name-display" class="file-input-display">No file chosen</span>
                        <label for="fileToUpload" class="file-input-button">Choose File</label>
                        <input type="file" name="fileToUpload[]" id="fileToUpload" required onchange="updateFileName(this)" multiple>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="submit" class="upload-button">
                        <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" fill="currentColor" viewBox="0 0 256 256"><path d="M224,128a8,8,0,0,1-8,8H136v80a8,8,0,0,1-16,0V136H40a8,8,0,0,1,0-16h80V40a8,8,0,0,1,16,0v80h80A8,8,0,0,1,224,128Z"></path></svg>
                        <span>Upload</span>
                    </button>
                </div>
            </form>
        </div>
    </div>

    <div id="renameModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h2>Rename File</h2>
                <span class="close-button" onclick="closeModal('renameModal')">&times;</span>
            </div>
            <form onsubmit="event.preventDefault(); submitRename();">
                <div class="modal-form-item">
                    <label for="newName">New Name:</label>
                    <input type="text" id="newName" name="newName" required>
                    <input type="hidden" id="renameId" name="renameId">
                </div>
                <div class="modal-footer">
                    <button type="submit" class="upload-button">
                        <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" fill="currentColor" viewBox="0 0 256 256"><path d="M216,40H40A16,16,0,0,0,24,56V200a16,16,0,0,0,16,16H216a16,16,0,0,0,16-16V56A16,16,0,0,0,216,40Zm-8,168H48V56H208V208ZM176,88a8,8,0,0,1-8,8H88a8,8,0,0,1,0-16h80A8,8,0,0,1,176,88Zm0,40a8,8,0,0,1-8,8H88a8,8,0,0,1,0-16h80A8,8,0,0,1,176,128Zm0,40a8,8,0,0,1-8,8H88a8,8,0,0,1,0-16h80A8,8,0,0,1,176,168Z"></path></svg>
                        <span>Save Changes</span>
                    </button>
                </div>
            </form>
        </div>
    </div>
    
    <div id="bulkChangeCategoryModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h2>Ubah Kategori Massal</h2>
                <span class="close-button" onclick="closeModal('bulkChangeCategoryModal')">&times;</span>
            </div>
            <form onsubmit="event.preventDefault(); submitBulkChange();">
                <div class="modal-form-item">
                    <label for="bulkNewCategory">Pindahkan foto yang dipilih ke kategori:</label>
                    <select id="bulkNewCategory" name="bulkNewCategory">
                        <option value="0">General</option>
                        <?php foreach ($categories as $cat): ?>
                            <option value="<?php echo $cat['id']; ?>"><?php echo htmlspecialchars($cat['name']); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="modal-footer">
                    <button type="submit" class="upload-button">
                        <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" fill="currentColor" viewBox="0 0 256 256"><path d="M216,40H40A16,16,0,0,0,24,56V200a16,16,0,0,0,16,16H216a16,16,0,0,0,16-16V56A16,16,0,0,0,216,40Zm-8,168H48V56H208V208ZM176,88a8,8,0,0,1-8,8H88a8,8,0,0,1,0-16h80A8,8,0,0,1,176,88Zm0,40a8,8,0,0,1-8,8H88a8,8,0,0,1,0-16h80A8,8,0,0,1,176,128Zm0,40a8,8,0,0,1-8,8H88a8,8,0,0,1,0-16h80A8,8,0,0,1,176,168Z"></path></svg>
                        <span>Simpan Perubahan</span>
                    </button>
                </div>
            </form>
        </div>
    </div>


    <script>
        // --- Theme Functions ---
        if (localStorage.getItem('theme') === 'dark') { document.body.classList.add('dark-mode'); }
        document.getElementById('theme-toggle').addEventListener('click', () => {
            document.body.classList.toggle('dark-mode');
            localStorage.setItem('theme', document.body.classList.contains('dark-mode') ? 'dark' : 'light');
        });
        
        // Sidebar Dropdown
        document.addEventListener('DOMContentLoaded', function() {
            const dropdownToggle = document.getElementById('texture-dropdown-toggle');
            const submenu = document.getElementById('texture-submenu');
            const storageKey = 'textureDropdownState';
            const isCategoryPage = submenu.querySelector('a.active') !== null;
            const openMenu = () => {
                dropdownToggle.classList.add('open');
                setTimeout(() => { submenu.style.maxHeight = submenu.scrollHeight + "px"; }, 10);
            };
            const closeMenu = () => {
                dropdownToggle.classList.remove('open');
                submenu.style.maxHeight = null;
            };
            if (isCategoryPage || localStorage.getItem(storageKey) === 'open') {
                openMenu();
                if (isCategoryPage) { localStorage.setItem(storageKey, 'open'); }
            }
            dropdownToggle.addEventListener('click', function() {
                if (this.classList.contains('open')) {
                    closeMenu();
                    localStorage.setItem(storageKey, 'closed');
                } else {
                    openMenu();
                    localStorage.setItem(storageKey, 'open');
                }
            });
        });
        
        // --- NEW: Global State and Functions for Bulk Edit ---
        let selectionMode = false;
        const selectedPhotos = new Set();
        const bulkEditButton = document.getElementById('bulk-edit-button');
        const gallery = document.querySelector('.gallery');

        function toggleSelectionMode() {
            selectionMode = !selectionMode;
            document.body.classList.toggle('selection-mode', selectionMode);

            if (selectionMode) {
                bulkEditButton.textContent = 'Batalkan';
                if (!document.getElementById('apply-bulk-edit-button')) {
                    const applyButton = document.createElement('button');
                    applyButton.id = 'apply-bulk-edit-button';
                    applyButton.className = 'upload-button';
                    applyButton.textContent = 'Terapkan ke 0 foto';
                    applyButton.disabled = true;
                    applyButton.onclick = () => {
                        if (selectedPhotos.size > 0) {
                            openModal('bulkChangeCategoryModal');
                        }
                    };
                    bulkEditButton.insertAdjacentElement('beforebegin', applyButton);
                }
            } else {
                bulkEditButton.textContent = 'Ubah Kategori';
                document.getElementById('apply-bulk-edit-button')?.remove();
                
                selectedPhotos.clear();
                document.querySelectorAll('.photo-container.is-selected').forEach(el => {
                    el.classList.remove('is-selected');
                });
            }
        }

        function updateApplyButton() {
            const applyButton = document.getElementById('apply-bulk-edit-button');
            if (applyButton) {
                const count = selectedPhotos.size;
                applyButton.textContent = `Terapkan ke ${count} foto`;
                applyButton.disabled = count === 0;
            }
        }

        bulkEditButton.addEventListener('click', toggleSelectionMode);

        gallery.addEventListener('click', (event) => {
            if (event.target.closest('.dropdown') || event.target.closest('.favorite-button')) {
                return;
            }
            if (!selectionMode) return;
            
            const photoContainer = event.target.closest('.photo-container');
            if (photoContainer) {
                const photoId = photoContainer.dataset.id;
                photoContainer.classList.toggle('is-selected');

                if (selectedPhotos.has(photoId)) {
                    selectedPhotos.delete(photoId);
                } else {
                    selectedPhotos.add(photoId);
                }
                updateApplyButton();
            }
        });

        function submitBulkChange() {
            const newCategoryId = document.getElementById('bulkNewCategory').value;
            if (selectedPhotos.size === 0) {
                alert("Tidak ada foto yang dipilih.");
                return;
            }

            const formData = new FormData();
            Array.from(selectedPhotos).forEach(id => {
                formData.append('bulk_photo_ids[]', id);
            });
            formData.append('new_category_id', newCategoryId);

            fetch('my_assets.php', { method: 'POST', body: formData })
            .then(response => response.json())
            .then(data => {
                if (data.status === 'success') {
                    alert('Kategori berhasil diperbarui!');
                    location.reload();
                } else {
                    alert('Error: ' + data.message);
                }
            })
            .catch(error => {
                console.error('Error:', error);
                alert('Terjadi kesalahan saat memperbarui.');
            });
        }


        // --- Gallery Functions ---
        function toggleFavorite(id, button) {
            var xhr=new XMLHttpRequest;xhr.open("POST","my_assets.php",!0),xhr.setRequestHeader("Content-Type","application/x-www-form-urlencoded"),xhr.onreadystatechange=function(){if(this.readyState===4&&this.status===200)try{var e=JSON.parse(this.responseText);if(e.status==="success"){button.classList.toggle("is-favorite");const t=new URLSearchParams(window.location.search);t.get("filter")==="favorite"&&!button.classList.contains("is-favorite")&&location.reload()}}catch(e){console.error("Error parsing JSON:",e)}},xhr.send("id="+id)
        }

        function toggleDropdown(button) {
            const dropdownMenu = button.nextElementSibling;
            const isCurrentlyOpen = dropdownMenu.style.display === 'block';

            document.querySelectorAll('.dropdown-menu').forEach(menu => {
                menu.style.display = 'none';
            });
            
            if (!isCurrentlyOpen) {
                dropdownMenu.style.display = 'block';
            }
        }

        window.onclick = function(event) {
            if (!event.target.closest('.dropdown')) {
                document.querySelectorAll('.dropdown-menu').forEach(menu => {
                    menu.style.display = 'none';
                });
            }
            if (event.target.classList.contains('modal')) {
                closeModal(event.target.id);
            }
        }

        function deletePhoto(id, button) {
            if(confirm("Apakah Anda yakin ingin menghapus foto ini?")){var xhr=new XMLHttpRequest;xhr.open("POST","my_assets.php",!0),xhr.setRequestHeader("Content-Type","application/x-www-form-urlencoded"),xhr.onreadystatechange=function(){if(this.readyState===4&&this.status===200)try{var e=JSON.parse(this.responseText);e.status==="success"?button.closest(".photo-container").remove():alert("Gagal menghapus foto: "+e.message)}catch(e){alert("Gagal menghapus foto. Silakan coba lagi.")}},xhr.send("delete_id="+id)}
        }
        
        // --- Modal Functions ---
        function openModal(modalId) { document.getElementById(modalId).style.display = 'flex'; }
        function closeModal(modalId) { document.getElementById(modalId).style.display = 'none'; }
        
        let currentRenameTitleElement = null;
        function openRenameModal(id, currentName) {
            document.getElementById('renameId').value = id;
            document.getElementById('newName').value = currentName;
            openModal('renameModal');
            const photoContainer = document.querySelector(`.photo-container[data-id="${id}"]`);
            currentRenameTitleElement = photoContainer.querySelector('.title');
        }

        function submitRename() {
            const id = document.getElementById('renameId').value;
            const newName = document.getElementById('newName').value;
            var xhr=new XMLHttpRequest;xhr.open("POST","my_assets.php",!0),xhr.setRequestHeader("Content-Type","application/x-www-form-urlencoded"),xhr.onreadystatechange=function(){if(this.readyState===4&&this.status===200)try{var e=JSON.parse(this.responseText);if(e.status==="success"){currentRenameTitleElement&&(currentRenameTitleElement.textContent=e.new_name);const t=document.querySelector(`.photo-container[data-id="${id}"]`),o=t.querySelector('.dropdown-menu button[onclick^="openRenameModal"]');if(o){const t=e.new_name.replace(/'/g,"\\'");o.setAttribute("onclick",`openRenameModal(${id}, '${t}')`)}closeModal("renameModal")}else alert("Gagal mengganti nama: "+(e.message||"Error tidak diketahui"))}catch(e){alert("Terjadi error. Silakan coba lagi."),console.error("Error parsing JSON:",this.responseText)}},xhr.send("rename_id="+id+"&new_name="+encodeURIComponent(newName))
        }

        function updateFileName(input) {
            const numFiles = input.files.length;
            let fileNameText = numFiles === 1 ? input.files[0].name : (numFiles > 1 ? numFiles + ' file dipilih' : 'Tidak ada file dipilih');
            document.getElementById('file-name-display').textContent = fileNameText;
        }
    </script>
</body>
</html>
