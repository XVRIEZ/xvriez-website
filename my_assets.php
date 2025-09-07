<?php
// Blok PHP untuk fungsionalitas backend
// Koneksi database
$servername = "localhost";
$username = "root";
$password = "";
$dbname = "xvriez";

$conn = new mysqli($servername, $username, $password, $dbname);

if ($conn->connect_error) {
    die("Koneksi gagal: " . $conn->connect_error);
}

// Menangani permintaan unggahan
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_FILES["fileToUpload"])) {
    $target_dir = "assets/";
    $original_filename = basename($_FILES["fileToUpload"]["name"]);
    $file_extension = pathinfo($original_filename, PATHINFO_EXTENSION);
    $unique_filename = uniqid() . '_' . time() . '.' . $file_extension;
    $target_file = $target_dir . $unique_filename;
    $uploadOk = 1;
    if (getimagesize($_FILES["fileToUpload"]["tmp_name"]) === false) {
        echo "<script>alert('File bukan gambar.');</script>";
        $uploadOk = 0;
    }
    if ($uploadOk == 1) {
        if (move_uploaded_file($_FILES["fileToUpload"]["tmp_name"], $target_file)) {
            $custom_name = $_POST["custom_name"];
            $sql = "INSERT INTO photos (filename, custom_name) VALUES (?, ?)";
            $stmt = $conn->prepare($sql);
            $stmt->bind_param("ss", $unique_filename, $custom_name);
            $stmt->execute();
            header("Location: my_assets.php");
            exit();
        } else {
            echo "<script>alert('Maaf, terjadi kesalahan saat mengunggah file.');</script>";
        }
    }
}

// Menangani permintaan hapus
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
        echo json_encode(['status' => 'error', 'message' => 'Gagal menghapus data dari database.']);
    }
    $conn->close();
    exit();
}

// Menangani permintaan favorit dari AJAX
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
        echo json_encode(['status' => 'error', 'message' => 'Gagal mengubah status favorit.']);
    }
    $conn->close();
    exit();
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
            --shadow: 0 4px 12px rgba(0, 0, 0, 0.08);
            --border-radius: 12px;

            /* Light Theme (Default) */
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
            /* Dark Theme Variables */
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

        * {
            box-sizing: border-box;
            margin: 0;
            padding: 0;
        }

        body {
            font-family: 'Poppins', sans-serif;
            background-color: var(--background-color);
            color: var(--text-color);
            display: flex;
            min-height: 100vh;
            transition: background-color 0.3s, color 0.3s;
        }

        .sidebar {
            width: 260px;
            background-color: var(--sidebar-bg);
            padding: 24px;
            border-right: 1px solid var(--border-color);
            display: flex;
            flex-direction: column;
            flex-shrink: 0;
            transition: background-color 0.3s, border-color 0.3s;
        }

        .sidebar-header {
            margin-bottom: 32px;
        }

        .home-button {
            display: flex;
            align-items: center;
            gap: 10px;
            padding: 12px;
            width: 100%;
            border-radius: var(--border-radius);
            background-color: var(--background-color);
            border: 1px solid var(--border-color);
            font-size: 15px;
            font-weight: 500;
            color: var(--text-color);
            text-decoration: none;
            transition: background-color 0.2s, color 0.2s, border-color 0.2s;
        }

        .home-button:hover {
            background-color: var(--primary-color);
            color: white;
            border-color: var(--primary-color);
        }
        
        .home-button svg {
            transition: fill 0.2s;
            fill: var(--text-color);
        }
        
        .home-button:hover svg {
            fill: white;
        }

        .nav-section h3 {
            font-size: 12px;
            font-weight: 600;
            color: var(--text-muted);
            text-transform: uppercase;
            letter-spacing: 0.5px;
            margin-bottom: 12px;
            padding: 0 12px;
        }

        .nav-list {
            list-style-type: none;
            display: flex;
            flex-direction: column;
            gap: 4px;
            margin-bottom: 24px;
        }

        .nav-list a {
            display: flex;
            align-items: center;
            gap: 12px;
            padding: 12px;
            border-radius: 10px;
            text-decoration: none;
            color: var(--text-muted);
            font-weight: 500;
            font-size: 15px;
            transition: background-color 0.2s, color 0.2s;
        }
        
        .nav-list a svg {
             fill: var(--text-muted);
             transition: fill 0.2s;
        }

        .nav-list a:hover, .nav-list a.active {
            background-color: var(--nav-hover-bg);
            color: var(--text-color);
        }
        
        .nav-list a:hover svg, .nav-list a.active svg {
            fill: var(--text-color);
        }

        .nav-list a.active {
            background-color: var(--nav-active-bg);
            font-weight: 600;
        }

        .main-content {
            flex-grow: 1;
            padding: 32px;
            overflow-y: auto;
        }

        .gallery-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 24px;
        }

        .gallery-header h1 {
            font-size: 28px;
            font-weight: 600;
        }

        .upload-button {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            padding: 12px 20px;
            border: none;
            background-color: var(--primary-color);
            color: white;
            border-radius: var(--border-radius);
            cursor: pointer;
            font-size: 15px;
            font-weight: 500;
            transition: background-color 0.2s, box-shadow 0.2s;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }

        .upload-button:hover {
            background-color: var(--primary-hover);
            box-shadow: 0 4px 8px rgba(0,0,0,0.15);
        }
        
        .gallery {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(250px, 1fr));
            gap: 24px;
        }

        .photo-container {
            position: relative;
            aspect-ratio: 1 / 1;
            background-color: var(--card-bg);
            border-radius: var(--border-radius);
            box-shadow: var(--shadow);
            overflow: hidden;
            transition: transform 0.3s ease, box-shadow 0.3s ease, background-color 0.3s;
        }

        .photo-container:hover {
            transform: translateY(-5px);
            box-shadow: 0 8px 20px rgba(0, 0, 0, 0.12);
        }

        .photo-container img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }
        
        .photo-overlay {
            position: absolute;
            top: 0; left: 0; right: 0; bottom: 0;
            background: linear-gradient(to top, rgba(0,0,0,0.8) 0%, transparent 60%);
            display: flex;
            flex-direction: column;
            justify-content: space-between;
            padding: 12px;
            opacity: 0;
            pointer-events: none; /* PERBAIKAN: Membuat overlay tidak bisa di-klik saat transparan */
            transition: opacity 0.3s ease;
        }

        .photo-container:hover .photo-overlay {
            opacity: 1;
            pointer-events: auto; /* PERBAIKAN: Membuat overlay bisa di-klik saat terlihat */
        }

        .photo-header {
            display: flex;
            justify-content: flex-end;
        }

        .photo-info {
            display: flex;
            justify-content: space-between;
            align-items: flex-end;
        }

        .title {
            color: white;
            font-weight: 600;
            text-shadow: 1px 1px 3px rgba(0,0,0,0.5);
        }

        .photo-actions button {
            background: none; border: none; cursor: pointer;
            font-size: 22px; color: white;
            filter: drop-shadow(0 1px 2px rgba(0,0,0,0.5));
        }

        .dropdown-toggle {
            background: rgba(255, 255, 255, 0.8);
            border: none; border-radius: 50%;
            width: 32px; height: 32px;
            display: flex; align-items: center; justify-content: center;
            color: var(--text-color);
        }

        .dropdown-menu {
            display: none; position: absolute; top: calc(100% + 5px); right: 0;
            background-color: var(--card-bg); box-shadow: var(--shadow); border-radius: 10px;
            padding: 8px; z-index: 10; min-width: 140px;
        }

        .dropdown-menu button, .dropdown-menu a {
            display: flex; gap: 8px; align-items: center; width: 100%; background: none; border: none;
            padding: 10px; text-align: left; cursor: pointer; color: var(--text-color); font-size: 14px;
            border-radius: 6px; text-decoration: none;
        }
        
        .dropdown-menu svg {
            fill: var(--text-color);
        }

        .dropdown-menu button:hover, .dropdown-menu a:hover {
            background-color: var(--nav-hover-bg);
        }
        
        .sidebar-footer {
            margin-top: auto;
            padding-top: 24px;
            border-top: 1px solid var(--border-color);
            transition: border-color 0.3s;
        }

        .theme-toggle {
            width: 100%;
            padding: 10px;
            background-color: var(--background-color);
            border: 1px solid var(--border-color);
            border-radius: var(--border-radius);
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: center;
            transition: background-color 0.3s, border-color 0.3s;
        }

        .theme-toggle .icon {
            width: 24px;
            height: 24px;
            color: var(--text-muted);
        }

        .theme-toggle .moon { display: none; }
        body.dark-mode .theme-toggle .sun { display: none; }
        body.dark-mode .theme-toggle .moon { display: block; }
    </style>
    <script>
        (function() {
            const theme = localStorage.getItem('theme');
            if (theme === 'dark') {
                document.addEventListener('DOMContentLoaded', () => {
                    document.body.classList.add('dark-mode');
                });
            }
        })();
    </script>
</head>
<body>
    <div class="sidebar">
        <div class="sidebar-header">
             <a href="main.html" class="home-button">
                <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 256 256"><path d="M240,117.22l-80-75.49V16a8,8,0,0,0-16,0V53.53L130.63,42.2A8,8,0,0,0,120,48.15v0l-96,90.58A8,8,0,0,0,24,144H40v72a8,8,0,0,0,8,8H208a8,8,0,0,0,8-8V144h16a8,8,0,0,0,8-8A8,8,0,0,0,240,117.22ZM200,216H56V144H200ZM224,128H32L128,37.48,224,128Z"></path></svg>
                <span>Kembali ke Beranda</span>
            </a>
        </div>

        <nav class="nav-section">
            <h3>Kategori</h3>
            <ul class="nav-list">
                <?php $filter = isset($_GET['filter']) ? $_GET['filter'] : ''; ?>
                <li><a href="my_assets.php" class="<?php echo ($filter == '') ? 'active' : ''; ?>">
                    <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 256 256"><path d="M224,48H32A8,8,0,0,0,24,56V200a8,8,0,0,0,8,8H224a8,8,0,0,0,8-8V56A8,8,0,0,0,224,48ZM40,192V64H216V192Z"></path></svg>
                    <span>Semua</span>
                </a></li>
                <li><a href="my_assets.php?filter=favorite" class="<?php echo ($filter == 'favorite') ? 'active' : ''; ?>">
                    <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 256 256"><path d="M128,216S28,160,28,92A52,52,0,0,1,128,64a52,52,0,0,1,100,28C228,160,128,216,128,216ZM80,92a44,44,0,1,0,88,0c0-42.2-64.8-86.23-76.32-95.27a11.9,11.9,0,0,0-15.36,0C89,62,80,92,80,92Z"></path></svg>
                    <span>Favorit</span>
                </a></li>
            </ul>
        </nav>
        <nav class="nav-section">
            <h3>Filter</h3>
            <ul class="nav-list">
                 <li><a href="#">
                    <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 256 256"><path d="M208,32H48A16,16,0,0,0,32,48V208a16,16,0,0,0,16,16H208a16,16,0,0,0,16-16V48A16,16,0,0,0,208,32Zm0,176H48V48H208V208ZM120,96a8,8,0,0,1-8,8H88a8,8,0,0,1-8-8V72a8,8,0,0,1,16,0V88h16A8,8,0,0,1,120,96Zm48,32H80a8,8,0,0,0-8,8v40a8,8,0,0,0,16,0V144h64v24a8,8,0,0,0,16,0V136A8,8,0,0,0,168,128Z"></path></svg>
                    <span>Terbaru</span>
                </a></li>
                 <li><a href="#">
                    <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 256 256"><path d="M232,94.2c-2.4-7.2-9.6-12-17.6-11.2l-44.8,4.8-20-41.6a16.2,16.2,0,0,0-29.6-1.6L100,88,56.8,80.8c-8-1.6-15.2,4-17.6,11.2s0,16,7.2,20l122.4,56.8c1.6.8,3.2,1.6,4.8,1.6a14.9,14.9,0,0,0,12.8-8l45.6-88.8C233.6,109.4,234.4,101.4,232,94.2Z"></path></svg>
                    <span>Paling Disukai</span>
                </a></li>
            </ul>
        </nav>
        <div class="sidebar-footer">
            <button id="theme-toggle" class="theme-toggle" title="Ganti Tema">
                <svg class="icon sun" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="5"></circle><line x1="12" y1="1" x2="12" y2="3"></line><line x1="12" y1="21" x2="12" y2="23"></line><line x1="4.22" y1="4.22" x2="5.64" y2="5.64"></line><line x1="18.36" y1="18.36" x2="19.78" y2="19.78"></line><line x1="1" y1="12" x2="3" y2="12"></line><line x1="21" y1="12" x2="23" y2="12"></line><line x1="4.22" y1="19.78" x2="5.64" y2="18.36"></line><line x1="18.36" y1="5.64" x2="19.78" y2="4.22"></line></svg>
                <svg class="icon moon" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 12.79A9 9 0 1 1 11.21 3 7 7 0 0 0 21 12.79z"></path></svg>
            </button>
        </div>
    </div>

    <div class="main-content">
        <div class="gallery-header">
            <h1>Galeri Foto Anda</h1>
            <button class="upload-button" onclick="openModal()">
                <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" fill="currentColor" viewBox="0 0 256 256"><path d="M224,128a8,8,0,0,1-8,8H136v80a8,8,0,0,1-16,0V136H40a8,8,0,0,1,0-16h80V40a8,8,0,0,1,16,0v80h80A8,8,0,0,1,224,128Z"></path></svg>
                <span>Unggah File</span>
            </button>
        </div>
        
        <div class="gallery">
            <?php
            $sql = "SELECT id, filename, custom_name, is_favorite FROM photos";
            $where_clause = "";
            if (isset($_GET['filter']) && $_GET['filter'] == 'favorite') {
                $where_clause = " WHERE is_favorite = 1";
            }
            $sql .= $where_clause . " ORDER BY id DESC";

            $result = $conn->query($sql);

            if ($result->num_rows > 0) {
                while($row = $result->fetch_assoc()) {
                    $file_path = "assets/" . $row["filename"];
                    $is_fav_style = $row["is_favorite"] == 1 ? 'color: #ff4d4d;' : 'color: white;';

                    echo '<div class="photo-container">';
                    echo '  <img src="' . $file_path . '" alt="' . htmlspecialchars($row["custom_name"]) . '">';
                    echo '  <div class="photo-overlay">';
                    echo '      <div class="photo-header">';
                    echo '          <div class="dropdown">';
                    echo '              <button class="dropdown-toggle" onclick="toggleDropdown(this)">&#x22EE;</button>';
                    echo '              <div class="dropdown-menu">';
                    echo '                  <a href="' . $file_path . '" download><svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 256 256"><path d="M208,128a8,8,0,0,1-8,8H136v56a8,8,0,0,1-16,0V136H56a8,8,0,0,1,0-16h64V64a8,8,0,0,1,16,0v56h64A8,8,0,0,1,208,128Zm24,80H24a8,8,0,0,1,0-16H232a8,8,0,0,1,0,16Z"></path></svg><span>Download</span></a>';
                    echo '                  <button onclick="deletePhoto(' . $row["id"] . ', this)"><svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 256 256"><path d="M216,48H176V40a24,24,0,0,0-24-24H104A24,24,0,0,0,80,40v8H40a8,8,0,0,0,0,16h8V208a16,16,0,0,0,16,16H192a16,16,0,0,0,16-16V64h8a8,8,0,0,0,0-16ZM96,40a8,8,0,0,1,8-8h48a8,8,0,0,1,8,8v8H96ZM192,208H64V64H192Z"></path></svg><span>Hapus</span></button>';
                    echo '              </div>';
                    echo '          </div>';
                    echo '      </div>';
                    echo '      <div class="photo-info">';
                    echo '          <span class="title">' . htmlspecialchars($row["custom_name"]) . '</span>';
                    echo '          <div class="photo-actions">';
                    echo '              <button onclick="toggleFavorite(' . $row["id"] . ', this)" style="' . $is_fav_style . '">&#x2764;</button>';
                    echo '          </div>';
                    echo '      </div>';
                    echo '  </div>';
                    echo '</div>';
                }
            } else {
                echo "<p style='color: var(--text-muted);'>Belum ada foto yang diunggah.</p>";
            }
            $conn->close();
            ?>
        </div>
    </div>
    
    <div id="uploadModal" class="modal">
        </div>

    <script>
        if (localStorage.getItem('theme') === 'dark') {
            document.body.classList.add('dark-mode');
        }
        
        const themeToggleButton = document.getElementById('theme-toggle');
        
        themeToggleButton.addEventListener('click', () => {
            document.body.classList.toggle('dark-mode');
            
            let theme = 'light';
            if (document.body.classList.contains('dark-mode')) {
                theme = 'dark';
            }
            localStorage.setItem('theme', theme);
        });

        function toggleFavorite(id, button) {
            var xhr = new XMLHttpRequest();
            xhr.open("POST", "my_assets.php", true);
            xhr.setRequestHeader("Content-Type", "application/x-www-form-urlencoded");
            xhr.onreadystatechange = function() {
                if (this.readyState === 4 && this.status === 200) {
                    try {
                        var response = JSON.parse(this.responseText);
                        if (response.status === 'success') {
                            button.style.color = response.is_favorite == 1 ? '#ff4d4d' : 'white';
                        }
                    } catch (e) { console.error("Error parsing JSON:", e); }
                }
            };
            xhr.send("id=" + id);
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
            if (!event.target.matches('.dropdown-toggle')) {
                document.querySelectorAll('.dropdown-menu').forEach(menu => {
                    menu.style.display = 'none';
                });
            }
            var modal = document.getElementById('uploadModal');
            if (event.target == modal) { closeModal(); }
        }

        function deletePhoto(id, button) {
            if (confirm("Apakah Anda yakin ingin menghapus foto ini?")) {
                var xhr = new XMLHttpRequest();
                xhr.open("POST", "my_assets.php", true);
                xhr.setRequestHeader("Content-Type", "application/x-www-form-urlencoded");
                xhr.onreadystatechange = function() {
                    if (this.readyState === 4 && this.status === 200) {
                        try {
                            var response = JSON.parse(this.responseText);
                            if (response.status === 'success') {
                                button.closest('.photo-container').remove();
                            } else {
                                alert("Gagal menghapus foto: " . response.message);
                            }
                        } catch (e) { alert("Gagal menghapus foto. Coba lagi."); }
                    }
                };
                xhr.send("delete_id=" = id);
            }
        }
        
        function openModal() { /* Implementasi fungsi modal Anda */ }
        function closeModal() { /* Implementasi fungsi modal Anda */ }
    </script>
</body>
</html>