<?php
session_start();
if (!isset($_SESSION['user'])) {
    header('Location: dangnhap.php');
    exit;
}
require_once __DIR__ . '/db.php';

$errors = [];
$success = '';

// Lấy dữ liệu dropdown
$sanphams = $pdo->query("SELECT Masp, Tensp FROM Sanpham ORDER BY Tensp")->fetchAll();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $malenh = trim($_POST['malenh'] ?? '');
    $masp = trim($_POST['masp'] ?? '');
    $ngaysanxuat = $_POST['ngaysanxuat'] ?? '';
    $soluongsanxuat = (int)($_POST['soluongsanxuat'] ?? 0);
    $ghichu = trim($_POST['ghichu'] ?? '');

    if ($malenh === '' || $masp === '' || $ngaysanxuat === '' || $soluongsanxuat <= 0) {
        $errors[] = 'Vui lòng nhập đầy đủ Mã lệnh, Sản phẩm, Ngày sản xuất, Số lượng.';
    }

    if (!$errors) {
        try {
            $pdo->beginTransaction();

            // Lưu lệnh sản xuất
            $stmtLenh = $pdo->prepare("
                INSERT INTO Lenhsanxuat (Malenh, Masp, Ngaysanxuat, Soluongsanxuat, Ghichu)
                VALUES (:malenh, :masp, :ngay, :sl, :ghichu)
            ");
            $stmtLenh->execute([
                ':malenh' => $malenh,
                ':masp' => $masp,
                ':ngay' => $ngaysanxuat,
                ':sl' => $soluongsanxuat,
                ':ghichu' => $ghichu,
            ]);

            $pdo->commit();
            $success = 'Tạo lệnh sản xuất thành công.';
        } catch (Exception $e) {
            $pdo->rollBack();
            $errors[] = 'Lỗi khi lưu lệnh: ' . htmlspecialchars($e->getMessage());
        }
    }
}
?>
<!doctype html>
<html lang="vi">
<head>
  <meta charset="utf-8" />
  <meta name="viewport" content="width=device-width,initial-scale=1" />
  <title>Lệnh sản xuất</title>
  <script src="https://cdn.tailwindcss.com"></script>
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
       <style>
        body { 
            background-color: #f8f9fa; 
            font-family: 'Segoe UI', sans-serif; 
        }
        
        /* Sidebar */
        .sidebar { 
            background-color: #007bff; 
            height: 100vh; 
            position: fixed; 
            width: 250px; 
            color: white; 
            padding-top: 20px; 
            top: 0;
            left: 0;
            overflow-y: auto;
        }
        
        .sidebar .nav-link {
            color: white !important;
            padding: 12px 20px;
            border-radius: 5px;
            margin: 4px 10px;
            transition: all 0.3s ease;
            font-weight: normal; /* Chữ bình thường mặc định */
        }
        
        /* CHỈ hover mới in đậm và nổi bật */
        .sidebar .nav-link:hover {
            background-color: #0069d9;    /* Nền xanh đậm hơn một chút */
            font-weight: bold;            /* Chữ in đậm */
            transform: translateX(8px);   /* Dịch nhẹ sang phải cho đẹp */
        }
        
        /* Bỏ hoàn toàn style active - tất cả đều giống nhau */
        .sidebar .nav-link.active {
            background-color: transparent;
            font-weight: normal;
            transform: none;
        }
        
        .main-content { 
            margin-left: 250px; 
            padding: 20px; 
        }
        @media (max-width: 768px) { 
            .sidebar { 
                width: 100%; 
                height: auto; 
                position: relative; 
            } 
            .main-content { 
                margin-left: 0; 
            } 
        }
         /* tránh ghi đè */
        .d-none {
            display: none !important;
        }
        #submenuSanPham {
            transition: all 0.3s ease;
        }
    </style>
</head>
<body>
 <nav class="sidebar">
    <div class="text-center mb-4">
        <h4><i class="fas fa-warehouse"></i> Quản Lý Kho</h4>
    </div>
    <ul class="nav flex-column">
        <li class="nav-item">
            <a class="nav-link" href="trangchu.php"><i class="fas fa-home"></i> Trang Chủ</a>
        </li>

        <li class="nav-item">
            <a class="nav-link" href="javascript:void(0)" id="btnSanPham">
                <i class="fas fa-box"></i> Quản lý sản phẩm
                <i class="fas fa-chevron-down float-end"></i>
            </a>
            <ul class="nav flex-column ms-3 d-none" id="submenuSanPham">
                <li class="nav-item"><a class="nav-link" href="Sanpham.php"><i class="fas fa-cube"></i> Sản phẩm</a></li>
                <li class="nav-item"><a class="nav-link" href="dmsp.php"><i class="fas fa-tags"></i> Danh mục sản phẩm</a></li>
                <li class="nav-item"><a class="nav-link" href="Nhacungcap.php"><i class="fas fa-truck"></i> Nhà cung cấp</a></li>
            </ul>
        </li>

        <li class="nav-item">
            <a class="nav-link" href="javascript:void(0)" id="btnPhieuNhap">
                <i class="fas fa-file-import"></i> Phiếu nhập kho
                <i class="fas fa-chevron-down float-end"></i>
            </a>
            <ul class="nav flex-column ms-3 d-none" id="submenuPhieuNhap">
                <li class="nav-item"><a class="nav-link" href="danh_sach_phieu_nhap.php"><i class="fas fa-list"></i> Danh sách phiếu nhập</a></li>
                <li class="nav-item"><a class="nav-link" href="phieu_nhap.php"><i class="fas fa-plus-circle"></i> Tạo phiếu nhập</a></li>
            </ul>
        </li>

        <li class="nav-item">
            <a class="nav-link" href="javascript:void(0)" id="btnPhieuXuat">
                <i class="fas fa-file-export"></i> Phiếu xuất <!-- Đã sửa icon đúng -->
                <i class="fas fa-chevron-down float-end"></i>
            </a>
            <ul class="nav flex-column ms-3 d-none" id="submenuPhieuXuat">
                <li class="nav-item"><a class="nav-link" href="danh_sach_phieu_xuat.php"><i class="fas fa-list"></i> Danh sách phiếu xuất</a></li>
                <li class="nav-item"><a class="nav-link" href="phieu_xuat.php"><i class="fas fa-plus-circle"></i> Tạo phiếu xuất</a></li>
            </ul>
        </li>

        <li class="nav-item">
            <a class="nav-link" href="javascript:void(0)" id="btnBaoCao">
                <i class="fas fa-chart-bar"></i> Báo cáo & Thống kê
                <i class="fas fa-chevron-down float-end"></i>
            </a>
            <ul class="nav flex-column ms-3 d-none" id="submenuBaoCao"> <!-- ĐÃ SỬA: thêm ul đúng id -->
                <li class="nav-item"><a class="nav-link" href="tonkho.php"><i class="fas fa-warehouse"></i> Báo cáo tồn kho</a></li>
            </ul>
        </li>

        <li class="nav-item">
            <a class="nav-link" href="javascript:void(0)" id="btnKhachHang">
                <i class="fas fa-users"></i> Quản lý khách hàng <!-- Đã sửa icon đúng -->
                <i class="fas fa-chevron-down float-end"></i>
            </a>
            <ul class="nav flex-column ms-3 d-none" id="submenuKhachHang">
                <li class="nav-item"><a class="nav-link" href="khachhang.php"><i class="fas fa-user"></i> Khách hàng</a></li>
                <li class="nav-item"><a class="nav-link" href="loaikhachhang.php"><i class="fas fa-users-cog"></i> Loại khách hàng</a></li>
            </ul>
        </li>

        <li class="nav-item">
            <a class="nav-link text-danger" href="logout.php"><i class="fas fa-sign-out-alt"></i> Đăng xuất</a>
        </li>
    </ul>
</nav>

    <div class="main-content">
  <div class="max-w-5xl mx-auto p-6 space-y-6">
    <div class="flex items-center justify-between">
      <div>
        <h1 class="text-2xl font-bold">Tạo lệnh sản xuất</h1>
        <p class="text-slate-400 text-sm mt-1">Tạo lệnh sản xuất mới</p>
      </div>
      <div class="flex gap-2 text-sm">
        <a href="danh_sach_lenh_san_xuat.php" class="px-3 py-2 rounded bg-white-800 hover:bg-white-700"></a>
      </div>
    </div>

    <?php if ($errors): ?>
    <div class="bg-red-900/60 border border-red-700 text-red-200 px-4 py-3 rounded">
      <ul class="list-disc list-inside space-y-1">
        <?php foreach ($errors as $er): ?>
          <li><?= htmlspecialchars($er) ?></li>
        <?php endforeach; ?>
      </ul>
    </div>
    <?php endif; ?>

    <?php if ($success): ?>
    <div class="bg-emerald-900/60 border border-emerald-700 text-emerald-100 px-4 py-3 rounded">
      <?= htmlspecialchars($success) ?>
    </div>
    <?php endif; ?>

    <form method="post" class="bg-slate-800 rounded-lg p-5 space-y-4">
      <div class="grid md:grid-cols-2 gap-4">
        <div>
          <label class="block text-sm text-slate-300 mb-2">Mã lệnh sản xuất *</label>
          <input name="malenh" required class="w-full px-3 py-2 rounded bg-slate-900 border border-slate-700" value="<?= htmlspecialchars($_POST['malenh'] ?? '') ?>" />
        </div>
        <div>
          <label class="block text-sm text-slate-300 mb-2">Sản phẩm *</label>
          <select name="masp" required class="w-full px-3 py-2 rounded bg-slate-900 border border-slate-700">
            <option value="">-- Chọn sản phẩm --</option>
            <?php foreach ($sanphams as $sp): ?>
              <option value="<?= htmlspecialchars($sp['Masp']) ?>"
                <?= (($_POST['masp'] ?? '') === $sp['Masp']) ? 'selected' : '' ?>>
                <?= htmlspecialchars($sp['Tensp']) ?>
              </option>
            <?php endforeach; ?>
          </select>
        </div>
        <div>
          <label class="block text-sm text-slate-300 mb-2">Ngày sản xuất *</label>
          <input type="date" name="ngaysanxuat" required
            class="w-full px-3 py-2 rounded bg-slate-900 border border-slate-700"
             value="<?= htmlspecialchars($_POST['ngaysanxuat'] ?? date('Y-m-d')) ?>" />
        </div>
        <div>
          <label class="block text-sm text-slate-300 mb-2">Số lượng sản xuất *</label>
          <input type="number" name="soluongsanxuat" required min="1"
            class="w-full px-3 py-2 rounded bg-slate-900 border border-slate-700"
             value="<?= htmlspecialchars($_POST['soluongsanxuat'] ?? '') ?>" />
        </div>
      </div>

      <div>
        <label class="block text-sm text-slate-300 mb-2">Ghi chú</label>
        <textarea name="ghichu" rows="3" class="w-full px-3 py-2 rounded bg-slate-900 border border-slate-700"><?= htmlspecialchars($_POST['ghichu'] ?? '') ?></textarea>
      </div>

      <div class="pt-2">
        <button type="submit" class="px-6 py-2 rounded bg-blue-600 hover:bg-blue-700 text-white font-semibold">
          Tạo lệnh sản xuất
        </button>
      </div>
    </form>
  </div>
  <script>
document.getElementById("btnSanPham").addEventListener("click", function () {
        document.getElementById("submenuSanPham").classList.toggle("d-none");
    });

    // Phiếu nhập kho
    document.getElementById("btnPhieuNhap").addEventListener("click", function () {
        document.getElementById("submenuPhieuNhap").classList.toggle("d-none");
    });

    // Phiếu xuất
    document.getElementById("btnPhieuXuat").addEventListener("click", function () {
        document.getElementById("submenuPhieuXuat").classList.toggle("d-none");
    });

    // Báo cáo & Thống kê (giờ hoạt động)
    document.getElementById("btnBaoCao").addEventListener("click", function () {
        document.getElementById("submenuBaoCao").classList.toggle("d-none");
    });

    // QUẢN LÝ KHÁCH HÀNG (đã thêm đầy đủ toggle)
    document.getElementById("btnKhachHang").addEventListener("click", function () {
        document.getElementById("submenuKhachHang").classList.toggle("d-none");
    });
</script>
</body>
</html>