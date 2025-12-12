<?php
require_once 'config.php';

// Get book ID
$book_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if ($book_id == 0) {
    redirect('index.php');
}

// Get book details
$query = "SELECT b.*, k.nama_kategori 
          FROM buku b 
          LEFT JOIN kategori k ON b.kategori_id = k.id 
          WHERE b.id = $book_id";
$result = mysqli_query($conn, $query);

if (mysqli_num_rows($result) == 0) {
    setMessage('error', 'Buku tidak ditemukan!');
    redirect('index.php');
}

$book = mysqli_fetch_assoc($result);

// Check if book is in user's collection
$in_collection = false;
if (isLoggedIn()) {
    $user_id = getUserId();
    $check_koleksi = mysqli_query($conn, "SELECT * FROM koleksi WHERE user_id = $user_id AND buku_id = $book_id");
    $in_collection = mysqli_num_rows($check_koleksi) > 0;
}

// Get similar books (same category)
$query_similar = "SELECT * FROM buku 
                  WHERE kategori_id = {$book['kategori_id']} 
                  AND id != $book_id 
                  LIMIT 4";
$similar_books = mysqli_query($conn, $query_similar);

// Handle Add to Collection
if (isset($_POST['add_to_collection'])) {
    if (!isLoggedIn()) {
        setMessage('error', 'Silakan login terlebih dahulu!');
        redirect('login.php');
    }
    
    $user_id = getUserId();
    $query_insert = "INSERT INTO koleksi (user_id, buku_id) VALUES ($user_id, $book_id)";
    
    if (mysqli_query($conn, $query_insert)) {
        setMessage('success', 'Buku berhasil ditambahkan ke koleksi!');
    } else {
        setMessage('error', 'Buku sudah ada di koleksi Anda!');
    }
    redirect("detail.php?id=$book_id");
}

// Handle Borrow Book
if (isset($_POST['borrow_book'])) {
    if (!isLoggedIn()) {
        setMessage('error', 'Silakan login terlebih dahulu!');
        redirect('login.php');
    }
    
    if ($book['stok'] <= 0) {
        setMessage('error', 'Stok buku habis!');
        redirect("detail.php?id=$book_id");
    }
    
    $user_id = getUserId();
    $tanggal_pinjam = date('Y-m-d');
    $tanggal_harus_kembali = date('Y-m-d', strtotime('+7 days'));
    
    // Check if user already borrowed this book
    $check_pinjam = mysqli_query($conn, 
        "SELECT * FROM peminjaman 
         WHERE user_id = $user_id 
         AND buku_id = $book_id 
         AND status = 'dipinjam'");
    
    if (mysqli_num_rows($check_pinjam) > 0) {
        setMessage('error', 'Anda sudah meminjam buku ini!');
        redirect("detail.php?id=$book_id");
    }
    
    // Insert peminjaman
    $query_pinjam = "INSERT INTO peminjaman (user_id, buku_id, tanggal_pinjam, tanggal_harus_kembali, status) 
                     VALUES ($user_id, $book_id, '$tanggal_pinjam', '$tanggal_harus_kembali', 'dipinjam')";
    
    // Update stok
    $query_update_stok = "UPDATE buku SET stok = stok - 1 WHERE id = $book_id";
    
    if (mysqli_query($conn, $query_pinjam) && mysqli_query($conn, $query_update_stok)) {
        setMessage('success', 'Buku berhasil dipinjam! Harap kembalikan sebelum ' . formatTanggal($tanggal_harus_kembali));
    } else {
        setMessage('error', 'Gagal meminjam buku!');
    }
    redirect("detail.php?id=$book_id");
}

$message = getMessage();
?>
<!doctype html>
<html lang="id">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <link rel="stylesheet" href="style.css">
  <title><?= htmlspecialchars($book['judul']) ?> - GROCG Library</title>
</head>
<body>

<?php if ($message): ?>
<div class="alert alert-<?= $message['type'] ?>">
  <?= $message['text'] ?>
</div>
<?php endif; ?>

<header class="site-header">
  <div class="header-container">
    <div class="header-left">
      <div class="logo">G</div>
      <span class="site-title">GROCG LIBRARY</span>
    </div>

    <nav class="header-nav">
      <a href="index.php" class="nav-link">Beranda</a>
      <a href="kategori.php" class="nav-link">Kategori</a>
      <?php if (isLoggedIn()): ?>
        <a href="profil.php" class="nav-link">Profil</a>
      <?php endif; ?>
    </nav>

    <div class="header-right">
      <?php if (isLoggedIn()): ?>
        <span style="color: var(--beige); margin-right: 16px;">Halo, <?= $_SESSION['nama'] ?></span>
        <a href="auth.php?logout=1" class="btn-login">Logout</a>
      <?php else: ?>
        <a href="login.php" class="btn-login">Login</a>
      <?php endif; ?>
      <button class="btn-menu" id="menuBtn">
        <span></span>
        <span></span>
        <span></span>
      </button>
    </div>
  </div>

  <nav class="sidebar" id="sidebar">
    <div class="sidebar-header">
      <h3>Menu</h3>
      <button class="close-btn" id="closeBtn">&times;</button>
    </div>
    <a href="index.php">Beranda</a>
    <a href="kategori.php">Kategori</a>
    <?php if (isLoggedIn()): ?>
      <a href="profil.php">Profil</a>
      <a href="auth.php?logout=1">Logout</a>
    <?php else: ?>
      <a href="login.php">Login</a>
    <?php endif; ?>
  </nav>
  <div class="sidebar-overlay" id="sidebarOverlay"></div>
</header>

<main class="container">
  <div class="book-detail">
    <div class="book-cover"></div>

    <div class="book-info">
      <h3><?= htmlspecialchars($book['judul']) ?></h3>
      
      <p><strong>Penulis:</strong> <?= htmlspecialchars($book['penulis']) ?></p>
      <p><strong>Penerbit:</strong> <?= htmlspecialchars($book['penerbit']) ?></p>
      <p><strong>Tahun Terbit:</strong> <?= $book['tahun_terbit'] ?></p>
      <p><strong>Kategori:</strong> <?= htmlspecialchars($book['nama_kategori']) ?></p>
      <p><strong>ISBN:</strong> <?= htmlspecialchars($book['isbn']) ?></p>
      <p><strong>Harga:</strong> <?= formatRupiah($book['harga']) ?></p>
      <p><strong>Stok:</strong> <?= $book['stok'] ?> tersedia</p>

      <div style="margin-top: 24px; margin-bottom: 16px;">
        <h4 style="font-size: 20px; color: var(--navy); margin-bottom: 12px;">Deskripsi Buku</h4>
        <p style="line-height: 1.8; color: var(--text-light);">
          <?= nl2br(htmlspecialchars($book['deskripsi'])) ?>
        </p>
      </div>

      <div>
        <?php if (isLoggedIn()): ?>
          <form method="POST" style="display: inline;">
            <?php if (!$in_collection): ?>
              <button type="submit" name="add_to_collection" class="btn">Tambahkan ke Koleksi</button>
            <?php else: ?>
              <button type="button" class="btn" disabled>Sudah di Koleksi ✓</button>
            <?php endif; ?>
          </form>
          
          <form method="POST" style="display: inline;">
            <button type="submit" name="borrow_book" class="btn btn-secondary" 
                    <?= $book['stok'] <= 0 ? 'disabled' : '' ?>>
              <?= $book['stok'] <= 0 ? 'Stok Habis' : 'Pinjam Buku' ?>
            </button>
          </form>
        <?php else: ?>
          <a href="login.php" class="btn">Login untuk Meminjam</a>
        <?php endif; ?>
      </div>
    </div>
  </div>

  <!-- Similar Books -->
  <?php if (mysqli_num_rows($similar_books) > 0): ?>
  <section class="section" style="margin-top: 64px;">
    <div class="section-header">
      <h2 class="section-title">Buku Serupa</h2>
    </div>

    <div class="grid">
      <?php while ($similar = mysqli_fetch_assoc($similar_books)): ?>
      <a href="detail.php?id=<?= $similar['id'] ?>" class="card">
        <div class="card-image"></div>
        <div class="card-content">
          <h3 class="card-title"><?= htmlspecialchars($similar['judul']) ?></h3>
          <p class="card-author"><?= htmlspecialchars($similar['penulis']) ?></p>
          <p class="card-year"><?= $similar['tahun_terbit'] ?></p>
        </div>
      </a>
      <?php endwhile; ?>
    </div>
  </section>
  <?php endif; ?>
</main>

<footer class="site-footer">
  <div class="footer-content">
    <div class="footer-left">
      <div class="footer-logo">
        <div class="logo">G</div>
        <span>GROCG LIBRARY</span>
      </div>
      <p>Platform perpustakaan digital terbaik untuk Anda</p>
    </div>
    
    <div class="footer-center">
      <h4>Menu</h4>
      <a href="index.php">Beranda</a>
      <a href="kategori.php">Kategori</a>
      <a href="profil.php">Profil</a>
    </div>
    
    <div class="footer-right">
      <h4>Ikuti Kami</h4>
      <div class="socials">
        <div class="social">Y</div>
        <div class="social">I</div>
        <div class="social">F</div>
      </div>
    </div>
  </div>
  
  <div class="footer-bottom">
    <p>© 2024 GROCG Library. All rights reserved.</p>
  </div>
</footer>

<script>
  const menuBtn = document.getElementById('menuBtn');
  const closeBtn = document.getElementById('closeBtn');
  const sidebar = document.getElementById('sidebar');
  const overlay = document.getElementById('sidebarOverlay');

  menuBtn.addEventListener('click', () => {
    sidebar.classList.add('open');
    overlay.classList.add('active');
  });

  closeBtn.addEventListener('click', () => {
    sidebar.classList.remove('open');
    overlay.classList.remove('active');
  });

  overlay.addEventListener('click', () => {
    sidebar.classList.remove('open');
    overlay.classList.remove('active');
  });

  setTimeout(() => {
    const alert = document.querySelector('.alert');
    if (alert) alert.style.display = 'none';
  }, 5000);
</script>

<style>
.alert {
  position: fixed;
  top: 90px;
  right: 32px;
  padding: 16px 24px;
  border-radius: 12px;
  box-shadow: var(--shadow-lg);
  z-index: 9999;
  animation: slideInRight 0.3s ease-out;
  font-weight: 600;
}

.alert-success {
  background: #10b981;
  color: white;
}

.alert-error {
  background: #ef4444;
  color: white;
}

@keyframes slideInRight {
  from {
    transform: translateX(100%);
    opacity: 0;
  }
  to {
    transform: translateX(0);
    opacity: 1;
  }
}
</style>

</body>
</html>