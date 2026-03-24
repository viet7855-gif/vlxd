<?php
session_start();
if (!isset($_SESSION['user'])) {
    header('Location: dangnhap.php');
    exit;
}
require_once __DIR__ . '/db.php';

// =========================
// Xử lý xóa phiếu xuất
// =========================
if (isset($_GET['xoa']) && !empty($_GET['xoa'])) {
    $maxuat = trim($_GET['xoa']);
    try {
        $pdo->beginTransaction();
        
        // Lấy thông tin phiếu xuất để lấy Makh và Makho
        $phieuXuat = $pdo->prepare("SELECT Makh, Makho FROM Phieuxuat WHERE Maxuathang = ?");
        $phieuXuat->execute([$maxuat]);
        $phieuXuat = $phieuXuat->fetch();

        
     // Lấy chi tiết phiếu xuất
$chiTiet = $pdo->prepare("
    SELECT Masp, Soluong 
    FROM Chitiet_Phieuxuat 
    WHERE Maxuathang = ?
");
$chiTiet->execute([$maxuat]);
$chiTietRows = $chiTiet->fetchAll();

// CỘNG LẠI số lượng tồn kho
foreach ($chiTietRows as $ct) {
    $stmtTonkho = $pdo->prepare("
        UPDATE Tonkho_sp 
        SET Soluongton = Soluongton + :sl
        WHERE Makho = :makho AND Masp = :masp
    ");
    $stmtTonkho->execute([
        ':makho' => $phieuXuat['Makho'],
        ':masp' => $ct['Masp'],
        ':sl'   => $ct['Soluong'],
    ]);
}

        
        // Xóa chi tiết phiếu xuất
        $pdo->prepare("DELETE FROM Chitiet_Phieuxuat WHERE Maxuathang = ?")->execute([$maxuat]);
        // Xóa phiếu xuất
        $pdo->prepare("DELETE FROM Phieuxuat WHERE Maxuathang = ?")->execute([$maxuat]);
        
        $pdo->commit();
        header("Location: danh_sach_phieu_xuat.php?success=xoa");
        exit;
    } catch (Exception $e) {
        $pdo->rollBack();
        header("Location: danh_sach_phieu_xoa.php?error=" . urlencode($e->getMessage()));
        exit;
    }
}


// Bộ lọc tìm kiếm
// =========================
$maSearch   = trim($_GET['ma'] ?? '');      // Mã phiếu xuất
$khSearch   = trim($_GET['makh'] ?? '');    // Mã khách hàng
$spSearch   = trim($_GET['masp'] ?? '');    //  Mã sản phẩm (MỚI)
$dateFrom  = trim($_GET['from'] ?? '');
$dateTo    = trim($_GET['to'] ?? '');


// Lấy danh sách Khách hàng cho dropdown lọc 
$khachhangs = $pdo->query("
    SELECT Makh, Tenkh 
    FROM Khachhang 
    ORDER BY Tenkh
")->fetchAll();

// Xây dựng SQL với điều kiện lọc
$sql = "
SELECT DISTINCT px.*, kh.Tenkh, k.Tenkho,
    (SELECT COUNT(*)
     FROM Chitiet_Phieuxuat ct2
     WHERE ct2.Maxuathang = px.Maxuathang) AS SoMatHang
FROM Phieuxuat px
LEFT JOIN Khachhang kh ON px.Makh = kh.Makh
LEFT JOIN Kho k ON px.Makho = k.Makho
LEFT JOIN Chitiet_Phieuxuat ct ON ct.Maxuathang = px.Maxuathang
WHERE 1=1
";


$params = [];

// Tìm theo mã phiếu xuất
if ($maSearch !== '') {
    $sql .= " AND px.Maxuathang LIKE :ma";
    $params[':ma'] = '%' . $maSearch . '%';
}
// Tìm theo mã sản phẩm
if ($spSearch !== '') {
    $sql .= " AND ct.Masp LIKE :masp";
    $params[':masp'] = '%' . $spSearch . '%';
}
// Tìm theo khách hàng
if ($khSearch !== '') {
    $sql .= " AND px.Makh = :makh";
    $params[':makh'] = $khSearch;
}

// Từ ngày xuất
if ($dateFrom !== '') {
    $sql .= " AND px.Ngayxuat >= :from";
    $params[':from'] = $dateFrom;
}

// Đến ngày xuất
if ($dateTo !== '') {
    $sql .= " AND px.Ngayxuat <= :to";
    $params[':to'] = $dateTo;
}


$sql .= " ORDER BY px.Ngayxuat DESC, px.Maxuathang DESC";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$phieuXuats = $stmt->fetchAll();

$success = $_GET['success'] ?? '';
$error = $_GET['error'] ?? '';
?>
<!doctype html>
<html lang="vi">
    
<head>
  <meta charset="utf-8" />
  <meta name="viewport" content="width=device-width,initial-scale=1" />
  <title>Danh sách phiếu xuất</title>
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
  <div class="max-w-7xl mx-auto p-6 space-y-6">
    <div class="flex items-center justify-between">
      <div>
        <h1 class="text-2xl font-bold">Danh sách phiếu xuất</h1>
        <p class="text-slate-400 text-sm mt-1">Quản lý các phiếu xuất kho</p>
      </div>
      <div class="flex gap-2 text-sm">
        <a href="phieu_xuat.php" class="px-4 py-2 rounded bg-white-600 hover:bg-white-700 font-semibold"></a>
        <a href="dashboard.php" class="px-3 py-2 rounded bg-white-800 hover:bg-white-700"></a>
        <a href="logout.php" class="px-3 py-2 rounded bg-white-600 hover:bg-white-700"></a>
      </div>
    </div>

    <?php if ($success === 'xoa'): ?>
      <div class="bg-emerald-900/60 border border-emerald-700 text-emerald-100 px-4 py-3 rounded">
        Đã xóa phiếu xuất thành công.
      </div>
    <?php endif; ?>

    <?php if ($error): ?>
      <div class="bg-red-900/60 border border-red-700 text-red-200 px-4 py-3 rounded">
        Lỗi: <?= htmlspecialchars($error) ?>
      </div>
    <?php endif; ?>

    <!-- Form tìm kiếm -->
<form method="get" class="bg-white-800 border border-white-700 rounded-lg p-4 space-y-3 text-sm">
  <div class="grid md:grid-cols-5 gap-3">

    <!-- Mã phiếu xuất -->
    <div>
      <label class="block text-black-300 mb-1">Mã phiếu xuất</label>
      <input name="ma"
             value="<?= htmlspecialchars($maSearch) ?>"
             class="w-full px-3 py-2 rounded bg-white-900 border border-white-700"
             placeholder="Nhập mã phiếu xuất..." />
    </div>

    <!-- Khách hàng -->
    <div>
      <label class="block text-black-300 mb-1">Khách hàng</label>
      <select name="makh" class="w-full px-3 py-2 rounded bg-white-900 border border-white-700">
        <option value="">-- Tất cả --</option>
        <?php foreach ($khachhangs as $kh): ?>
          <option value="<?= $kh['Makh'] ?>"
            <?= ($khSearch === $kh['Makh']) ? 'selected' : '' ?>>
            <?= htmlspecialchars($kh['Tenkh']) ?>
          </option>
        <?php endforeach; ?>
      </select>
    </div>

    <!-- Mã sản phẩm -->
    <div>
      <label class="block text-black-300 mb-1">Mã sản phẩm</label>
      <input name="masp"
             value="<?= htmlspecialchars($spSearch) ?>"
             class="w-full px-3 py-2 rounded bg-white-900 border border-white-700"
             placeholder="Nhập mã sản phẩm..." />
    </div>

    <!-- Từ ngày -->
    <div>
      <label class="block text-black-300 mb-1">Từ ngày</label>
      <input type="date" name="from"
             value="<?= htmlspecialchars($dateFrom) ?>"
             class="w-full px-3 py-2 rounded bg-white-900 border border-white-700" />
    </div>

    <!-- Đến ngày -->
    <div>
      <label class="block text-black-300 mb-1">Đến ngày</label>
      <input type="date" name="to"
             value="<?= htmlspecialchars($dateTo) ?>"
             class="w-full px-3 py-2 rounded bg-white-900 border border-white-700" />
    </div>

  </div>

  <div class="flex items-center gap-2 pt-1">
    <button type="submit"
            class="px-4 py-2 rounded bg-blue-600 hover:bg-blue-700 text-white-900 font-semibold">
      Tìm kiếm
    </button>
    <a href="danh_sach_phieu_xuat.php"
       class="px-3 py-2 rounded bg-red-700 hover:bg-red-600 text-white-100">
      Xóa lọc
    </a>
  </div>
</form>


    <div class="bg-white-800 rounded-lg border border-slate-700 overflow-auto">
      <table class="min-w-full text-sm">
        <thead class="bg-black-900 text-black-300">
          <tr>
            <th class="px-4 py-3 text-left">Mã phiếu</th>
            <th class="px-4 py-3 text-left">Khách hàng</th>
            <th class="px-4 py-3 text-left">Kho</th>
            <th class="px-4 py-3 text-left">Ngày xuất</th>
            <th class="px-4 py-3 text-right">Số mặt hàng</th>
            <th class="px-4 py-3 text-right">Tổng tiền</th>
            <th class="px-4 py-3 text-left">Ghi chú</th>
            <th class="px-4 py-3 text-center">Thao tác</th>
          </tr>
        </thead>
        <tbody>
          <?php if (empty($phieuXuats)): ?>
            <tr><td colspan="9" class="px-4 py-4 text-center text-black-400">Chưa có phiếu xuất nào.</td></tr>
          <?php else: ?>
            <?php foreach ($phieuXuats as $px): ?>
              <tr class="border-t border-slate-800 hover:bg-slate-700/50">
                <td class="px-4 py-2 font-semibold"><?= htmlspecialchars($px['Maxuathang']) ?></td>
                <td class="px-4 py-2"><?= htmlspecialchars($px['Tenkh'] ?? 'N/A') ?></td>
                <td class="px-4 py-2"><?= htmlspecialchars($px['Tenkho'] ?? 'N/A') ?></td>
                <td class="px-4 py-2"><?= date('d/m/Y', strtotime($px['Ngayxuat'])) ?></td>
                <td class="px-4 py-2 text-right"><?= number_format($px['SoMatHang']) ?></td>
                <td class="px-4 py-2 text-right font-semibold"><?= number_format($px['Tongtienxuat'], 0, ',', '.') ?> đ</td>
                <td class="px-4 py-2 text-slate-400"><?= htmlspecialchars(mb_substr($px['Ghichu'] ?? '', 0, 50)) ?><?= mb_strlen($px['Ghichu'] ?? '') > 50 ? '...' : '' ?></td>
                <td class="px-4 py-2">
                  <div class="flex items-center justify-center gap-2">
                    <a href="sua_phieu_xuat.php?id=<?= urlencode($px['Maxuathang']) ?>" class="px-3 py-1 rounded bg-blue-600 hover:bg-blue-700 text-xs font-semibold">Sửa</a>
                    <a href="chi_tiet_phieu_xuat.php?id=<?= urlencode($px['Maxuathang']) ?>" class="px-3 py-1 rounded bg-emerald-600 hover:bg-emerald-700 text-xs font-semibold">Chi tiết</a>
                    <a href="?xoa=<?= urlencode($px['Maxuathang']) ?>" 
                       onclick="return confirm('Bạn có chắc chắn muốn xóa phiếu xuất này? Hành động này sẽ tăng số lượng tồn kho.')" 
                       class="px-3 py-1 rounded bg-red-600 hover:bg-red-700 text-xs font-semibold">Xóa</a>
                  </div>
                </td>
              </tr>
            <?php endforeach; ?>
          <?php endif; ?>
        </tbody>
      </table>
    </div>
  </div>
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