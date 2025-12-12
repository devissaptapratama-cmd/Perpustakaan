<?php
/**
 * GROCG Library - Authentication System
 * Handles Login, Register, Logout with Server-side Validation
 */

require_once 'config.php';

// ========================
// LOGIN HANDLER
// ========================
if (isset($_POST['login'])) {
    
    // Server-side Validation dengan filter_var()
    $email = trim($_POST['email']);
    $password = trim($_POST['password']);
    
    $errors = [];
    
    // Validasi Required
    if (!Validator::required($email)) {
        $errors[] = "Email atau NIM tidak boleh kosong";
    }
    
    if (!Validator::required($password)) {
        $errors[] = "Password tidak boleh kosong";
    }
    
    // Jika tidak ada error, proses login
    if (empty($errors)) {
        
        // Clean input untuk mencegah SQL Injection
        $email = DataManipulator::cleanInput($email, $conn);
        
        // Query user berdasarkan email atau NIM
        $query = "SELECT * FROM users WHERE email = '$email' OR nim = '$email' LIMIT 1";
        $result = mysqli_query($conn, $query);
        
        // Debug Query
        Debug::logQuery($query);
        
        if (mysqli_num_rows($result) > 0) {
            $user = mysqli_fetch_assoc($result);
            
            // Verify password dengan password_verify()
            if (DataManipulator::verifyPassword($password, $user['password'])) {
                
                // Set session data
                $_SESSION['user_id'] = $user['id'];
                $_SESSION['nama'] = $user['nama'];
                $_SESSION['email'] = $user['email'];
                $_SESSION['role'] = $user['role'];
                $_SESSION['login_time'] = time();
                
                // Set success message
                setMessage('success', 'Login berhasil! Selamat datang, ' . $user['nama']);
                
                // Redirect berdasarkan role
                if ($user['role'] === 'admin') {
                    redirect('admin/dashboard.php');
                } else {
                    redirect('index.php');
                }
                
            } else {
                setMessage('error', 'Password salah!');
                redirect('login.php');
            }
            
        } else {
            setMessage('error', 'Email atau NIM tidak ditemukan!');
            redirect('login.php');
        }
        
    } else {
        // Set error messages
        foreach ($errors as $error) {
            setMessage('error', $error);
        }
        redirect('login.php');
    }
}

// ========================
// REGISTER HANDLER
// ========================
if (isset($_POST['register'])) {
    
    // Ambil dan trim data input
    $nama = trim($_POST['nama']);
    $email = trim($_POST['email']);
    $nim = trim($_POST['nim']);
    $password = trim($_POST['password']);
    $confirm_password = trim($_POST['confirm_password']);
    $alamat = trim($_POST['alamat']);
    $no_hp = trim($_POST['no_hp']);
    
    $errors = [];
    
    // VALIDASI SERVER-SIDE dengan filter_var() dan custom validation
    
    // Validasi Nama
    if (!Validator::required($nama)) {
        $errors[] = "Nama tidak boleh kosong";
    } elseif (!Validator::minLength($nama, 3)) {
        $errors[] = "Nama minimal 3 karakter";
    }
    
    // Validasi Email
    if (!Validator::required($email)) {
        $errors[] = "Email tidak boleh kosong";
    } elseif (!Validator::email($email)) {
        $errors[] = "Format email tidak valid";
    }
    
    // Validasi NIM
    if (!Validator::required($nim)) {
        $errors[] = "NIM tidak boleh kosong";
    } elseif (!Validator::nim($nim)) {
        $errors[] = "Format NIM tidak valid (5-20 karakter alfanumerik)";
    }
    
    // Validasi Password
    if (!Validator::required($password)) {
        $errors[] = "Password tidak boleh kosong";
    } elseif (!Validator::minLength($password, 6)) {
        $errors[] = "Password minimal 6 karakter";
    }
    
    // Validasi Confirm Password
    if ($password !== $confirm_password) {
        $errors[] = "Password dan konfirmasi password tidak cocok";
    }
    
    // Validasi Alamat
    if (!Validator::required($alamat)) {
        $errors[] = "Alamat tidak boleh kosong";
    }
    
    // Validasi No HP
    if (!Validator::required($no_hp)) {
        $errors[] = "No HP tidak boleh kosong";
    } elseif (!Validator::phone($no_hp)) {
        $errors[] = "Format No HP tidak valid";
    }
    
    // Jika tidak ada error, lanjut ke pengecekan database
    if (empty($errors)) {
        
        // Clean semua input
        $nama = DataManipulator::cleanInput($nama, $conn);
        $email = DataManipulator::cleanInput($email, $conn);
        $nim = DataManipulator::cleanInput($nim, $conn);
        $alamat = DataManipulator::cleanInput($alamat, $conn);
        $no_hp = DataManipulator::cleanInput($no_hp, $conn);
        
        // Cek email sudah terdaftar atau belum
        $check_email = mysqli_query($conn, "SELECT id FROM users WHERE email = '$email'");
        if (mysqli_num_rows($check_email) > 0) {
            $errors[] = "Email sudah terdaftar";
        }
        
        // Cek NIM sudah terdaftar atau belum
        $check_nim = mysqli_query($conn, "SELECT id FROM users WHERE nim = '$nim'");
        if (mysqli_num_rows($check_nim) > 0) {
            $errors[] = "NIM sudah terdaftar";
        }
        
        // Jika masih tidak ada error, insert ke database
        if (empty($errors)) {
            
            // Hash password dengan bcrypt
            $hashed_password = DataManipulator::hashPassword($password);
            
            // Insert query
            $query = "INSERT INTO users (nama, email, nim, password, alamat, no_hp, role) 
                      VALUES ('$nama', '$email', '$nim', '$hashed_password', '$alamat', '$no_hp', 'user')";
            
            // Debug Query
            Debug::logQuery($query);
            
            if (mysqli_query($conn, $query)) {
                setMessage('success', 'Registrasi berhasil! Silakan login.');
                redirect('login.php');
            } else {
                Debug::logError('Register Error: ' . mysqli_error($conn));
                setMessage('error', 'Terjadi kesalahan saat registrasi. Silakan coba lagi.');
                redirect('register.php');
            }
            
        } else {
            // Ada error dari database check
            foreach ($errors as $error) {
                setMessage('error', $error);
            }
            redirect('register.php');
        }
        
    } else {
        // Ada error dari validasi input
        foreach ($errors as $error) {
            setMessage('error', $error);
        }
        redirect('register.php');
    }
}

// ========================
// LOGOUT HANDLER
// ========================
if (isset($_GET['logout'])) {
    
    // Destroy all session data
    session_unset();
    session_destroy();
    
    // Set success message (need to start new session for message)
    session_start();
    setMessage('success', 'Anda telah logout.');
    redirect('index.php');
}

// ========================
// UPDATE PROFILE HANDLER
// ========================
if (isset($_POST['update_profile'])) {
    
    if (!isLoggedIn()) {
        redirect('login.php');
    }
    
    $user_id = getUserId();
    $nama = trim($_POST['nama']);
    $email = trim($_POST['email']);
    $alamat = trim($_POST['alamat']);
    $no_hp = trim($_POST['no_hp']);
    
    $errors = [];
    
    // Validasi
    if (!Validator::required($nama) || !Validator::minLength($nama, 3)) {
        $errors[] = "Nama minimal 3 karakter";
    }
    
    if (!Validator::email($email)) {
        $errors[] = "Format email tidak valid";
    }
    
    if (!Validator::required($alamat)) {
        $errors[] = "Alamat tidak boleh kosong";
    }
    
    if (!Validator::phone($no_hp)) {
        $errors[] = "Format No HP tidak valid";
    }
    
    if (empty($errors)) {
        
        // Clean input
        $nama = DataManipulator::cleanInput($nama, $conn);
        $email = DataManipulator::cleanInput($email, $conn);
        $alamat = DataManipulator::cleanInput($alamat, $conn);
        $no_hp = DataManipulator::cleanInput($no_hp, $conn);
        
        // Check email sudah dipakai user lain atau belum
        $check_email = mysqli_query($conn, "SELECT id FROM users WHERE email = '$email' AND id != $user_id");
        if (mysqli_num_rows($check_email) > 0) {
            setMessage('error', 'Email sudah digunakan user lain');
            redirect('profil.php');
        }
        
        // Update query
        $query = "UPDATE users SET 
                  nama = '$nama', 
                  email = '$email', 
                  alamat = '$alamat', 
                  no_hp = '$no_hp' 
                  WHERE id = $user_id";
        
        Debug::logQuery($query);
        
        if (mysqli_query($conn, $query)) {
            // Update session data
            $_SESSION['nama'] = $nama;
            $_SESSION['email'] = $email;
            
            setMessage('success', 'Profil berhasil diupdate!');
            redirect('profil.php');
        } else {
            Debug::logError('Update Profile Error: ' . mysqli_error($conn));
            setMessage('error', 'Gagal update profil');
            redirect('profil.php');
        }
        
    } else {
        foreach ($errors as $error) {
            setMessage('error', $error);
        }
        redirect('profil.php');
    }
}

// ========================
// CHANGE PASSWORD HANDLER
// ========================
if (isset($_POST['change_password'])) {
    
    if (!isLoggedIn()) {
        redirect('login.php');
    }
    
    $user_id = getUserId();
    $old_password = trim($_POST['old_password']);
    $new_password = trim($_POST['new_password']);
    $confirm_password = trim($_POST['confirm_password']);
    
    $errors = [];
    
    // Validasi
    if (!Validator::required($old_password)) {
        $errors[] = "Password lama tidak boleh kosong";
    }
    
    if (!Validator::required($new_password) || !Validator::minLength($new_password, 6)) {
        $errors[] = "Password baru minimal 6 karakter";
    }
    
    if ($new_password !== $confirm_password) {
        $errors[] = "Password baru dan konfirmasi tidak cocok";
    }
    
    if (empty($errors)) {
        
        // Get current password from database
        $result = mysqli_query($conn, "SELECT password FROM users WHERE id = $user_id");
        $user = mysqli_fetch_assoc($result);
        
        // Verify old password
        if (DataManipulator::verifyPassword($old_password, $user['password'])) {
            
            // Hash new password
            $hashed_password = DataManipulator::hashPassword($new_password);
            
            // Update password
            $query = "UPDATE users SET password = '$hashed_password' WHERE id = $user_id";
            
            Debug::logQuery($query);
            
            if (mysqli_query($conn, $query)) {
                setMessage('success', 'Password berhasil diubah!');
                redirect('profil.php');
            } else {
                Debug::logError('Change Password Error: ' . mysqli_error($conn));
                setMessage('error', 'Gagal mengubah password');
                redirect('profil.php');
            }
            
        } else {
            setMessage('error', 'Password lama salah!');
            redirect('profil.php');
        }
        
    } else {
        foreach ($errors as $error) {
            setMessage('error', $error);
        }
        redirect('profil.php');
    }
}

// ========================
// UPLOAD AVATAR HANDLER (FILE HANDLING)
// ========================
if (isset($_POST['upload_avatar'])) {
    
    if (!isLoggedIn()) {
        redirect('login.php');
    }
    
    $user_id = getUserId();
    
    // Validasi file upload
    if (isset($_FILES['avatar']) && $_FILES['avatar']['error'] === UPLOAD_ERR_OK) {
        
        $allowedTypes = ['image/jpeg', 'image/png', 'image/gif'];
        $uploadResult = FileHandler::upload($_FILES['avatar'], UPLOAD_DIR . 'avatars/', $allowedTypes);
        
        if ($uploadResult['success']) {
            
            // Delete old avatar if exists
            $result = mysqli_query($conn, "SELECT avatar FROM users WHERE id = $user_id");
            $user = mysqli_fetch_assoc($result);
            
            if ($user['avatar']) {
                FileHandler::delete(UPLOAD_DIR . 'avatars/' . $user['avatar']);
            }
            
            // Update database
            $filename = $uploadResult['filename'];
            $query = "UPDATE users SET avatar = '$filename' WHERE id = $user_id";
            
            if (mysqli_query($conn, $query)) {
                setMessage('success', 'Avatar berhasil diupload!');
            } else {
                Debug::logError('Upload Avatar Error: ' . mysqli_error($conn));
                setMessage('error', 'Gagal menyimpan avatar');
            }
            
        } else {
            setMessage('error', $uploadResult['message']);
        }
        
    } else {
        setMessage('error', 'Tidak ada file yang diupload atau file error');
    }
    
    redirect('profil.php');
}

// If accessed directly, redirect to home
redirect('index.php');
?>