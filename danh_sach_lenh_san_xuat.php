<?php
session_start();
if (!isset($_SESSION['user'])) {
    header('Location: dangnhap.php');
    exit;
}
require_once __DIR__ . '/db.php';

// =========================
// Xử lý xóa lệnh sản xuất
// =========================
if (isset($_GET['xoa']) && !empty($_GET['xoa'])) {
    $malenh = trim($_GET['xoa']);
    try {
        $pdo->beginTransaction();
        
        // Xóa chi tiết xuất NVL (nếu có)
        $pdo->prepare("DELETE FROM Chitiet_XuatNVL_Sanxuat WHERE Malenh = ?")->execute([$malenh]);
        // Xóa chi tiết nhập sản phẩm (nếu có)
        $pdo->prepare("DELETE FROM Chitiet_Nhapsanpham_Sanxuat WHERE Malenh = ?")->execute([$malenh]);
        // Xóa lệnh sản xuất
        $pdo->prepare("DELETE FROM Lenhsanxuat WHERE Malenh = ?")->execute([$malenh]);
        
        $pdo->commit();
        header("Location: danh_sach_lenh_san_xuat.php?success=xoa");
        exit;
    } catch (Exception $e) {
        $pdo->rollBack();
        header("Location: danh_sach_lenh_san_xuat.php?error=" . urlencode($e->getMessage()));
        exit;
    }
}

// Bộ lọc tìm kiếm
$maSearch   = trim($_GET['ma'] ?? '');
$spSearch   = trim($_GET['masp'] ?? '');
$dateFrom  = trim($_GET['from'] ?? '');
$dateTo    = trim($_GET['to'] ?? '');

// Lấy danh sách sản phẩm cho dropdown lọc
$sanphams = $pdo->query("SELECT Masp, Tensp FROM Sanpham ORDER BY Tensp")->fetchAll();

// Xây dựng SQL với điều kiện lọc
$sql = "
SELECT l.*, sp.Tensp
FROM Lenhsanxuat l
LEFT JOIN Sanpham sp ON l.Masp = sp.Masp
WHERE 1=1
";

// Tìm theo mã lệnh
if ($maSearch !== '') {
    $sql .= " AND l.Malenh LIKE :ma";
    $params[':ma'] = '%' . $maSearch . '%';
}
// Tìm theo mã sản phẩm
if ($spSearch !== '') {
    $sql .= " AND l.Masp = :masp";
    $params[':masp'] = $spSearch;
}

// Từ ngày sản xuất
if ($dateFrom !== '') {
    $sql .= " AND l.Ngaysanxuat >= :from";
    $params[':from'] = $dateFrom;
}

// Đến ngày sản xuất
if ($dateTo !== '') {
    $sql .= " AND l.Ngaysanxuat <= :to";
    $params[':to'] = $dateTo;
}

$sql .= " ORDER BY l.Ngaysanxuat DESC, l.Malenh DESC";

$stmt = $pdo->prepare($sql);
$stmt->execute($params ?? []);
$lenhs = $stmt->fetchAll();

$success = $_GET['success'] ?? '';
$error = $_GET['error'] ?? '';
?>
<!doctype html>
<html lang="vi">
<head>
  <meta charset="utf-8" />
  <meta name="viewport" content="width=device-width,initial-scale=1" />
  <title>Danh sách lệnh sản xuất</title>
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
            font-weight: normal;
        }
        
        .sidebar .nav-link:hover {
            background-color: #0069d9;
            font-weight: bold;
            transform: translateX(8px);
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
        .d-none {
            display: none !important;
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
                <i class="fas fa-file-export"></i> Phiếu xuất
                <i class="fas fa-chevron-down float-end"></i>
            </a>
            <ul class="nav flex-column ms-3 d-none" id="submenuPhieuXuat">
                <li class="nav-item"><a class="nav-link" href="danh_sach_phieu_xuat.php"><i class="fas fa-list"></i> Danh sách phiếu xuất</a></li>
                <li class="nav-item"><a class="nav-link" href="phieu_xuat.php"><i class="fas fa-plus-circle"></i> Tạo phiếu xuất</a></li>
            </ul>
        </li>

        <li class="nav-item">
            <a class="nav-link" href="javascript:void(0)" id="btnSanXuat">
                <i class="fas fa-cogs"></i> Sản xuất
                <i class="fas fa-chevron-down float-end"></i>
            </a>
            <ul class="nav flex-column ms-3 d-none" id="submenuSanXuat">
                <li class="nav-item"><a class="nav-link" href="danh_sach_lenh_san_xuat.php"><i class="fas fa-list"></i> Danh sách lệnh sản xuất</a></li>
                <li class="nav-item"><a class="nav-link" href="lenh_san_xuat.php"><i class="fas fa-plus-circle"></i> Tạo lệnh sản xuất</a></li>
            </ul>
        </li>

        <li class="nav-item">
            <a class="nav-link" href="javascript:void(0)" id="btnBaoCao">
                <i class="fas fa-chart-bar"></i> Báo cáo & Thống kê
                <i class="fas fa-chevron-down float-end"></i>
            </a>
            <ul class="nav flex-column ms-3 d-none" id="submenuBaoCao">
                <li class="nav-item"><a class="nav-link" href="tonkho.php"><i class="fas fa-warehouse"></i> Báo cáo tồn kho</a></li>
            </ul>
        </li>

        <li class="nav-item">
            <a class="nav-link" href="javascript:void(0)" id="btnKhachHang">
                <i class="fas fa-users"></i> Quản lý khách hàng
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
  <div class="max-w-7xl mx-auto p-6 space-y-6">
    <div class="flex items-center justify-between">
      <div>
        <h1 class="text-2xl font-bold">Danh sách lệnh sản xuất</h1>
        <p class="text-slate-400 text-sm mt-1">Quản lý các lệnh sản xuất</p>
      </div>
      <div class="flex gap-2 text-sm">
        <a href="lenh_san_xuat.php" class="px-3 py-2 rounded bg-blue-600 hover:bg-blue-700 text-white font-semibold">Tạo lệnh mới</a>
      </div>
    </div>

    <?php if ($success): ?>
    <div class="bg-emerald-900/60 border border-emerald-700 text-emerald-100 px-4 py-3 rounded">
      Xóa lệnh sản xuất thành công.
    </div>
    <?php endif; ?>

    <?php if ($error): ?>
    <div class="bg-red-900/60 border border-red-700 text-red-200 px-4 py-3 rounded">
      <?= htmlspecialchars($error) ?>
    </div>
    <?php endif; ?>

    <!-- Bộ lọc -->
    <form method="GET" class="bg-slate-800 rounded-lg p-5 space-y-4">
      <div class="grid md:grid-cols-4 gap-4">
        <div>
          <label class="block text-sm text-slate-300 mb-2">Mã lệnh</label>
          <input name="ma" class="w-full px-3 py-2 rounded bg-slate-900 border border-slate-700" value="<?= htmlspecialchars($maSearch) ?>" />
        </div>
        <div>
          <label class="block text-sm text-slate-300 mb-2">Sản phẩm</label>
          <select name="masp" class="w-full px-3 py-2 rounded bg-slate-900 border border-slate-700">
            <option value="">-- Tất cả --</option>
            <?php foreach ($sanphams as $sp): ?>
              <option value="<?= htmlspecialchars($sp['Masp']) ?>" <?= ($spSearch === $sp['Masp']) ? 'selected' : '' ?>>
                <?= htmlspecialchars($sp['Tensp']) ?>
              </option>
            <?php endforeach; ?>
          </select>
        </div>
        <div>
          <label class="block text-sm text-slate-300 mb-2">Từ ngày</label>
          <input type="date" name="from" class="w-full px-3 py-2 rounded bg-slate-900 border border-slate-700" value="<?= htmlspecialchars($dateFrom) ?>" />
        </div>
        <div>
          <label class="block text-sm text-slate-300 mb-2">Đến ngày</label>
          <input type="date" name="to" class="w-full px-3 py-2 rounded bg-slate-900 border border-slate-700" value="<?= htmlspecialchars($dateTo) ?>" />
        </div>
      </div>
      <div class="pt-2">
        <button type="submit" class="px-6 py-2 rounded bg-blue-600 hover:bg-blue-700 text-white font-semibold">
          Tìm kiếm
        </button>
      </div>
    </form>

    <div class="bg-white-800 rounded-lg border border-slate-700 overflow-auto">
      <table class="min-w-full text-sm">
        <thead class="bg-slate-900 text-slate-300">
          <tr>
            <th class="px-4 py-3 text-left">Mã lệnh</th>
            <th class="px-4 py-3 text-left">Sản phẩm</th>
            <th class="px-4 py-3 text-left">Ngày sản xuất</th>
            <th class="px-4 py-3 text-right">Số lượng</th>
            <th class="px-4 py-3 text-left">Trạng thái</th>
            <th class="px-4 py-3 text-left">Ghi chú</th>
            <th class="px-4 py-3 text-center">Thao tác</th>
          </tr>
        </thead>
        <tbody>
          <?php if (empty($lenhs)): ?>
            <tr><td colspan="7" class="px-4 py-4 text-center text-slate-400">Chưa có lệnh sản xuất nào.</td></tr>
          <?php else: ?>
            <?php foreach ($lenhs as $l): ?>
              <tr class="border-t border-slate-800 hover:bg-slate-700/50">
                <td class="px-4 py-2 font-semibold"><?= htmlspecialchars($l['Malenh']) ?></td>
                <td class="px-4 py-2"><?= htmlspecialchars($l['Tensp'] ?? 'N/A') ?></td>
                <td class="px-4 py-2"><?= date('d/m/Y', strtotime($l['Ngaysanxuat'])) ?></td>
                <td class="px-4 py-2 text-right"><?= number_format($l['Soluongsanxuat']) ?></td>
                <td class="px-4 py-2"><?= htmlspecialchars($l['Trangthai']) ?></td>
                <td class="px-4 py-2 text-slate-400"><?= htmlspecialchars(mb_substr($l['Ghichu'] ?? '', 0, 50)) ?><?= mb_strlen($l['Ghichu'] ?? '') > 50 ? '...' : '' ?></td>
                <td class="px-4 py-2">
                  <div class="flex items-center justify-center gap-2">
                    <a href="hoan_thanh_san_xuat.php?id=<?= urlencode($l['Malenh']) ?>" class="px-3 py-1 rounded bg-green-600 hover:bg-green-700 text-xs font-semibold">Hoàn thành</a>
                    <a href="danh_sach_lenh_san_xuat.php?xoa=<?= urlencode($l['Malenh']) ?>" onclick="return confirm('Xóa lệnh sản xuất này?')" class="px-3 py-1 rounded bg-red-600 hover:bg-red-700 text-xs font-semibold">Xóa</a>
                  </div>
                </td>
              </tr>
            <?php endforeach; ?>
          <?php endif; ?>
        </tbody>
      </table>
    </div>
  </div>
  <script>
document.getElementById("btnSanPham").addEventListener("click", function () {
        document.getElementById("submenuSanPham").classList.toggle("d-none");
    });

    document.getElementById("btnPhieuNhap").addEventListener("click", function () {
        document.getElementById("submenuPhieuNhap").classList.toggle("d-none");
    });

    document.getElementById("btnPhieuXuat").addEventListener("click", function () {
        document.getElementById("submenuPhieuXuat").classList.toggle("d-none");
    });

    document.getElementById("btnSanXuat").addEventListener("click", function () {
        document.getElementById("submenuSanXuat").classList.toggle("d-none");
    });

    document.getElementById("btnBaoCao").addEventListener("click", function () {
        document.getElementById("submenuBaoCao").classList.toggle("d-none");
    });

    document.getElementById("btnKhachHang").addEventListener("click", function () {
        document.getElementById("submenuKhachHang").classList.toggle("d-none");
    });
</script>
</body>
</html>