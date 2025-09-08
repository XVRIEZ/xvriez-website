<?php
// Koneksi database
$servername = "localhost";
$username = "root";
$password = "";
$dbname = "xvriez";

$conn = new mysqli($servername, $username, $password, $dbname);

if ($conn->connect_error) {
    die("Koneksi gagal: " . $conn->connect_error);
}

// Menangani penambahan video dari kode embed
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST["embed_code"])) {
    $embed_code = $_POST['embed_code'];
    
    // Validasi sederhana untuk sumber yang diizinkan
    $allowed_sources = ['youtube.com', 'vimeo.com', 'tiktok.com', 'instagram.com', 'facebook.com'];
    $is_valid_source = false;
    foreach ($allowed_sources as $source) {
        if (strpos($embed_code, $source) !== false) {
            $is_valid_source = true;
            break;
        }
    }

    // Set nama default
    $custom_name = 'XVRIEZ';
    $is_favorite = isset($_POST['is_favorite']) ? (int)$_POST['is_favorite'] : 0;
    
    // Simpan jika kode embed tidak kosong dan berasal dari sumber yang valid
    if (!empty($embed_code) && $is_valid_source) {
        // Simpan seluruh kode embed
        $sql = "INSERT INTO videos (embed_code, custom_name, is_favorite) VALUES (?, ?, ?)";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("ssi", $embed_code, $custom_name, $is_favorite);
        $stmt->execute();
        $stmt->close();
    }
    
    header("Location: portfolio.php");
    exit();
}

// Menangani permintaan hapus video
if (isset($_POST['delete_id'])) {
    $id = $_POST['delete_id'];
    $sql_delete = "DELETE FROM videos WHERE id = ?";
    $stmt_delete = $conn->prepare($sql_delete);
    $stmt_delete->bind_param("i", $id);
    if ($stmt_delete->execute()) { echo json_encode(['status' => 'success']); } 
    else { echo json_encode(['status' => 'error', 'message' => 'Gagal menghapus data.']); }
    $conn->close(); exit();
}

// Menangani permintaan favorit video
if (isset($_POST['id'])) {
    $id = $_POST['id'];
    $sql_select = "SELECT is_favorite FROM videos WHERE id = ?";
    $stmt_select = $conn->prepare($sql_select);
    $stmt_select->bind_param("i", $id); $stmt_select->execute();
    $result_select = $stmt_select->get_result(); $row = $result_select->fetch_assoc();
    $is_favorite = $row['is_favorite'] == 1 ? 0 : 1;
    $sql_update = "UPDATE videos SET is_favorite = ? WHERE id = ?";
    $stmt_update = $conn->prepare($sql_update);
    $stmt_update->bind_param("ii", $is_favorite, $id);
    if ($stmt_update->execute()) { echo json_encode(['status' => 'success', 'is_favorite' => $is_favorite]); } 
    else { echo json_encode(['status' => 'error', 'message' => 'Gagal mengubah status favorit.']); }
    $conn->close(); exit();
}

// Menangani permintaan ubah nama video
if (isset($_POST['rename_id']) && isset($_POST['new_name'])) {
    $id = $_POST['rename_id']; $new_name = $_POST['new_name'];
    $sql = "UPDATE videos SET custom_name = ? WHERE id = ?";
    $stmt = $conn->prepare($sql); $stmt->bind_param("si", $new_name, $id);
    if ($stmt->execute()) { echo json_encode(['status' => 'success', 'new_name' => $new_name]); } 
    else { echo json_encode(['status' => 'error', 'message' => 'Gagal memperbarui nama.']); }
    $stmt->close(); $conn->close(); exit();
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Portofolio Video XVRIEZ</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700&display=swap" rel="stylesheet">
    <style>
        :root {
            --primary-color: #007bff; --primary-hover: #0056b3; --shadow: 0 4px 12px rgba(0, 0, 0, 0.08);
            --border-radius: 12px; --background-color: #f8f9fa; --sidebar-bg: #ffffff; --card-bg: #ffffff;
            --text-color: #343a40; --text-muted: #6c757d; --border-color: #dee2e6;
            --nav-hover-bg: #e9ecef; --nav-active-bg: #F1F5F9;
        }
        body.dark-mode {
            --background-color: #18181b; --sidebar-bg: #27272a; --card-bg: #27272a;
            --text-color: #e4e4e7; --text-muted: #a1a1aa; --border-color: #3f3f46;
            --shadow: 0 4px 12px rgba(0, 0, 0, 0.2); --nav-hover-bg: #3f3f46; --nav-active-bg: #3f3f46;
        }
        * { box-sizing: border-box; margin: 0; padding: 0; }
        body { font-family: 'Poppins', sans-serif; background-color: var(--background-color); color: var(--text-color); display: flex; min-height: 100vh; transition: background-color 0.3s, color 0.3s; }
        .sidebar { width: 260px; background-color: var(--sidebar-bg); padding: 24px; border-right: 1px solid var(--border-color); display: flex; flex-direction: column; flex-shrink: 0; transition: background-color 0.3s, border-color 0.3s; }
        .sidebar-header { margin-bottom: 32px; }
        .home-button { display: flex; align-items: center; gap: 10px; padding: 12px; width: 100%; border-radius: var(--border-radius); background-color: var(--background-color); border: 1px solid var(--border-color); font-size: 15px; font-weight: 500; color: var(--text-color); text-decoration: none; transition: all 0.2s; }
        .home-button:hover { background-color: var(--primary-color); color: white; border-color: var(--primary-color); }
        .home-button svg { transition: fill 0.2s; fill: var(--text-color); }
        .home-button:hover svg { fill: white; }
        .nav-section h3 { font-size: 12px; font-weight: 600; color: var(--text-muted); text-transform: uppercase; letter-spacing: 0.5px; margin-bottom: 12px; padding: 0 12px; }
        .nav-list { list-style-type: none; display: flex; flex-direction: column; gap: 4px; margin-bottom: 24px; }
        .nav-list a { display: flex; align-items: center; gap: 12px; padding: 12px; border-radius: 10px; text-decoration: none; color: var(--text-muted); font-weight: 500; font-size: 15px; transition: all 0.2s; }
        .nav-list a svg { fill: var(--text-muted); transition: fill 0.2s; }
        .nav-list a:hover, .nav-list a.active { background-color: var(--nav-hover-bg); color: var(--text-color); }
        .nav-list a:hover svg, .nav-list a.active svg { fill: var(--text-color); }
        .nav-list a.active { background-color: var(--nav-active-bg); font-weight: 600; }
        .main-content { flex-grow: 1; padding: 32px; overflow-y: auto; }
        .gallery-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 24px; }
        .gallery-header h1 { font-size: 28px; font-weight: 600; }
        .action-button { display: inline-flex; align-items: center; gap: 8px; padding: 12px 20px; border: none; background-color: var(--primary-color); color: white; border-radius: var(--border-radius); cursor: pointer; font-size: 15px; font-weight: 500; transition: all 0.2s; box-shadow: 0 2px 4px rgba(0,0,0,0.1); }
        .action-button:hover { background-color: var(--primary-hover); box-shadow: 0 4px 8px rgba(0,0,0,0.15); }
        .gallery { display: grid; grid-template-columns: repeat(auto-fill, minmax(320px, 1fr)); gap: 24px; align-items: start; }
        .video-container { 
            position: relative; 
            background-color: var(--card-bg); 
            border-radius: var(--border-radius); 
            box-shadow: var(--shadow); 
            overflow: hidden; 
            transition: all 0.3s ease; 
            display: flex;
            justify-content: center;
            align-items: center;
        }
        .video-container:hover { transform: translateY(-5px); box-shadow: 0 8px 20px rgba(0, 0, 0, 0.12); }
        .video-container > :first-child { max-width: 100%; border: none; }
        .video-container > blockquote { margin: 0 !important; }
        .video-header { position: absolute; top: 10px; right: 10px; display: flex; gap: 8px; z-index: 5; opacity: 0; transform: translateY(-10px); transition: all 0.3s ease; }
        .video-container:hover .video-header { opacity: 1; transform: translateY(0); }
        .favorite-button, .dropdown-toggle { background: rgba(255, 255, 255, 0.8); border: none; border-radius: 50%; width: 32px; height: 32px; display: flex; align-items: center; justify-content: center; cursor: pointer; transition: all 0.2s; box-shadow: 0 2px 4px rgba(0,0,0,0.2); }
        .favorite-button { font-size: 18px; color: #ccc; }
        .favorite-button.is-favorite { color: #ff4d4d; }
        .dropdown-menu { display: none; position: absolute; top: calc(100% + 5px); right: 0; background-color: var(--card-bg); box-shadow: var(--shadow); border-radius: 10px; padding: 8px; z-index: 10; min-width: 140px; }
        .dropdown-menu button { display: flex; gap: 8px; align-items: center; width: 100%; background: none; border: none; padding: 10px; text-align: left; cursor: pointer; color: var(--text-color); font-size: 14px; border-radius: 6px; }
        .dropdown-menu button:hover { background-color: var(--nav-hover-bg); }
        .sidebar-footer { margin-top: auto; padding-top: 24px; border-top: 1px solid var(--border-color); transition: border-color 0.3s; }
        .theme-toggle { width: 100%; padding: 10px; background-color: var(--background-color); border: 1px solid var(--border-color); border-radius: var(--border-radius); cursor: pointer; display: flex; align-items: center; justify-content: center; transition: all 0.3s; }
        .theme-toggle .icon { width: 24px; height: 24px; color: var(--text-muted); } .theme-toggle .moon { display: none; }
        body.dark-mode .theme-toggle .sun { display: none; } body.dark-mode .theme-toggle .moon { display: block; }
        .modal { display: none; position: fixed; z-index: 100; left: 0; top: 0; width: 100%; height: 100%; background-color: rgba(0,0,0,0.6); justify-content: center; align-items: center; }
        .modal-content { background-color: var(--card-bg); padding: 30px; border-radius: var(--border-radius); width: 90%; max-width: 550px; box-shadow: var(--shadow); position: relative; }
        .modal-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 25px; }
        .modal-header h2 { font-size: 24px; font-weight: 600; color: var(--text-color); margin: 0; }
        .close-button { color: var(--text-muted); font-size: 28px; font-weight: normal; cursor: pointer; line-height: 1; transition: color 0.2s; }
        .close-button:hover { color: var(--text-color); }
        .modal-form-item { text-align: left; margin-bottom: 15px; }
        .modal-form-item label { display: block; font-size: 14px; font-weight: 500; margin-bottom: 8px; color: var(--text-color); }
        .modal-form-item input[type="text"], .modal-form-item select, .modal-form-item textarea { width: 100%; padding: 12px; border: 1px solid var(--border-color); border-radius: 8px; background-color: var(--background-color); color: var(--text-color); font-size: 15px; transition: all 0.2s; font-family: 'Poppins', sans-serif; }
        .modal-form-item textarea { min-height: 120px; resize: vertical; }
        .modal-form-item input[type="text"]:focus, .modal-form-item select:focus, .modal-form-item textarea:focus { outline: none; border-color: var(--primary-color); box-shadow: 0 0 0 3px rgba(0, 123, 255, 0.25); }
        .modal-footer { padding-top: 15px; border-top: 1px solid var(--border-color); margin-top: 20px; }
    </style>
    <script> (function() { const theme = localStorage.getItem('theme'); if (theme === 'dark') { document.addEventListener('DOMContentLoaded', () => { document.body.classList.add('dark-mode'); }); } })(); </script>
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
                <li><a href="portfolio.php" class="<?php echo ($filter == '') ? 'active' : ''; ?>">
                    <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 256 256"><path d="M224,48H32A8,8,0,0,0,24,56V200a8,8,0,0,0,8,8H224a8,8,0,0,0,8-8V56A8,8,0,0,0,224,48ZM40,192V64H216V192Z"></path></svg>
                    <span>Semua</span>
                </a></li>
                <li><a href="portfolio.php?filter=favorite" class="<?php echo ($filter == 'favorite') ? 'active' : ''; ?>">
                    <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 256 256"><path d="M128,216S28,160,28,92A52,52,0,0,1,128,64a52,52,0,0,1,100,28C228,160,128,216,128,216ZM80,92a44,44,0,1,0,88,0c0-42.2-64.8-86.23-76.32-95.27a11.9,11.9,0,0,0-15.36,0C89,62,80,92,80,92Z"></path></svg>
                    <span>Favorit</span>
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
            <h1>Portofolio Video</h1>
            <button class="action-button" onclick="openAddModal()">
                <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" fill="currentColor" viewBox="0 0 256 256"><path d="M224,128a8,8,0,0,1-8,8H136v80a8,8,0,0,1-16,0V136H40a8,8,0,0,1,0-16h80V40a8,8,0,0,1,16,0v80h80A8,8,0,0,1,224,128Z"></path></svg>
                <span>Tambah Video</span>
            </button>
        </div>
        <div class="gallery">
            <?php
            // Updated SQL query to select embed_code
            $sql = "SELECT id, embed_code, custom_name, is_favorite FROM videos ORDER BY id DESC";
            $result = $conn->query($sql);

            if ($result->num_rows > 0) {
                while($row = $result->fetch_assoc()) {
                    $is_fav_class = $row["is_favorite"] == 1 ? 'is-favorite' : '';
                    
                    echo '<div class="video-container" title="' . htmlspecialchars($row["custom_name"]) . '">';
                    // Echo the full embed code directly
                    echo $row["embed_code"];
                    echo '  <div class="video-header">';
                    echo '      <button class="favorite-button ' . $is_fav_class . '" onclick="toggleFavorite(' . $row["id"] . ', this)">&#x2764;</button>';
                    echo '      <div class="dropdown">';
                    echo '          <button class="dropdown-toggle" onclick="toggleDropdown(this)">&#x22EE;</button>';
                    echo '          <div class="dropdown-menu">';
                    echo '              <button onclick="openRenameModal(' . $row["id"] . ', \'' . htmlspecialchars($row["custom_name"], ENT_QUOTES) . '\')">Ubah Nama</button>';
                    echo '              <button onclick="deleteVideo(' . $row["id"] . ', this)">Hapus</button>';
                    echo '          </div>';
                    echo '      </div>';
                    echo '  </div>';
                    echo '</div>';
                }
            } else {
                echo "<p style='color: var(--text-muted);'>Belum ada video yang ditambahkan.</p>";
            }
            $conn->close();
            ?>
        </div>
    </div>
    
    <div id="addModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h2>Tambah Video Baru</h2>
                <span class="close-button" onclick="closeModal('addModal')">&times;</span>
            </div>
            <form action="portfolio.php" method="POST">
                <div class="modal-form-item">
                    <label for="embed_code">Kode Embed:</label>
                    <textarea name="embed_code" id="embed_code" placeholder="Tempelkan seluruh kode embed dari YouTube, TikTok, Instagram, Facebook, dll." required></textarea>
                </div>
                <div class="modal-form-item">
                    <label for="is_favorite">Jadikan Favorit?</label>
                    <select name="is_favorite" id="is_favorite">
                        <option value="0">Tidak</option>
                        <option value="1">Ya</option>
                    </select>
                </div>
                <div class="modal-footer">
                    <button type="submit" class="action-button" style="width:100%;">Tambah Video</button>
                </div>
            </form>
        </div>
    </div>

    <div id="renameModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h2>Ubah Nama Video</h2>
                <span class="close-button" onclick="closeModal('renameModal')">&times;</span>
            </div>
            <form onsubmit="event.preventDefault(); submitRename();">
                <div class="modal-form-item">
                    <label for="newName">Nama Baru:</label>
                    <input type="text" id="newName" name="newName" required>
                    <input type="hidden" id="renameId" name="renameId">
                </div>
                <div class="modal-footer">
                    <button type="submit" class="action-button" style="width:100%;">Simpan Perubahan</button>
                </div>
            </form>
        </div>
    </div>

    <script>
        document.addEventListener('DOMContentLoaded', () => {
             const themeToggleButton = document.getElementById('theme-toggle');
             if(themeToggleButton) {
                themeToggleButton.addEventListener('click', () => {
                    document.body.classList.toggle('dark-mode');
                    let theme = document.body.classList.contains('dark-mode') ? 'dark' : 'light';
                    localStorage.setItem('theme', theme);
                });
             }
        });

        function toggleFavorite(id, button) {
            fetch('portfolio.php', { method: 'POST', headers: {'Content-Type': 'application/x-www-form-urlencoded'}, body: 'id=' + id })
            .then(res => res.json()).then(data => { if (data.status === 'success') { button.classList.toggle('is-favorite', data.is_favorite); } }).catch(console.error);
        }

        function toggleDropdown(button) {
            const menu = button.nextElementSibling;
            const container = button.closest('.video-container');
            const isOpen = menu.style.display === 'block';
            document.querySelectorAll('.dropdown-menu').forEach(m => m.style.display = 'none');
            document.querySelectorAll('.video-container').forEach(c => c.style.overflow = 'hidden');
            if (!isOpen) { menu.style.display = 'block'; container.style.overflow = 'visible'; }
        }
        
        window.onclick = function(event) {
            if (!event.target.matches('.dropdown-toggle, .dropdown-toggle *')) {
                document.querySelectorAll('.dropdown-menu').forEach(m => m.style.display = 'none');
                document.querySelectorAll('.video-container').forEach(c => c.style.overflow = 'hidden');
            }
            if (event.target.classList.contains('modal')) {
                event.target.style.display = 'none';
            }
        }

        function deleteVideo(id, button) {
            if (confirm("Apakah Anda yakin ingin menghapus video ini?")) {
                fetch('portfolio.php', { method: 'POST', headers: {'Content-Type': 'application/x-www-form-urlencoded'}, body: 'delete_id=' + id })
                .then(res => res.json())
                .then(data => {
                    if (data.status === 'success') { button.closest('.video-container').remove(); } 
                    else { alert("Gagal menghapus video: " . data.message); }
                }).catch(e => alert("Gagal menghapus video."));
            }
        }
        
        function openAddModal() { document.getElementById('addModal').style.display = 'flex'; }
        function openRenameModal(id, currentName) {
            document.getElementById('renameId').value = id;
            document.getElementById('newName').value = currentName;
            document.getElementById('renameModal').style.display = 'flex';
        }
        function submitRename() {
            const id = document.getElementById('renameId').value;
            const newName = document.getElementById('newName').value;
            fetch('portfolio.php', { method: 'POST', headers: {'Content-Type': 'application/x-www-form-urlencoded'}, body: `rename_id=${id}&new_name=${encodeURIComponent(newName)}`})
            .then(res => res.json())
            .then(data => {
                if (data.status === 'success') {
                    closeModal('renameModal');
                    location.reload(); 
                } else { alert("Gagal mengubah nama: " . data.message); }
            }).catch(e => alert("Gagal mengubah nama."));
        }
        function closeModal(modalId) { document.getElementById(modalId).style.display = 'none'; }
    </script>
</body>
</html>
