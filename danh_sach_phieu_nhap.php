<?php
session_start();
if (!isset($_SESSION['user'])) {
    header('Location: dangnhap.php');
    exit;
}
require_once __DIR__ . '/db.php';

// =========================
// Xử lý xóa phiếu nhập
// =========================
if (isset($_GET['xoa']) && !empty($_GET['xoa'])) {
    $manhap = trim($_GET['xoa']);
    try {
        $pdo->beginTransaction();
        
        // Lấy thông tin phiếu nhập để lấy Makho
        $phieuNhap = $pdo->prepare("SELECT Makho FROM Phieunhap WHERE Manhaphang = ?");
        $phieuNhap->execute([$manhap]);
        $phieuNhap = $phieuNhap->fetch();
        
        if ($phieuNhap && $phieuNhap['Makho']) {
            $makho = $phieuNhap['Makho'];
            
            // Lấy chi tiết phiếu nhập để cập nhật lại tồn kho
            $chiTiet = $pdo->prepare("SELECT Manvl, Soluong FROM Chitiet_Phieunhap WHERE Manhaphang = ?");
            $chiTiet->execute([$manhap]);
            $chiTietRows = $chiTiet->fetchAll();
            
            // Giảm số lượng tồn kho
            foreach ($chiTietRows as $ct) {
                $stmtTonkho = $pdo->prepare("
                    UPDATE Tonkho_nvl 
                    SET Soluongton = Soluongton - :sl
                    WHERE Makho = :makho AND Manvl = :manvl AND Soluongton >= :sl_check
                ");
                $stmtTonkho->execute([
                    ':makho' => $makho,
                    ':manvl' => $ct['Manvl'],
                    ':sl' => $ct['Soluong'],
                    ':sl_check' => $ct['Soluong'],
                ]);
            }
        }
        
        // Xóa chi tiết phiếu nhập
        $pdo->prepare("DELETE FROM Chitiet_Phieunhap WHERE Manhaphang = ?")->execute([$manhap]);
        // Xóa phiếu nhập
        $pdo->prepare("DELETE FROM Phieunhap WHERE Manhaphang = ?")->execute([$manhap]);
        
        $pdo->commit();
        header("Location: danh_sach_phieu_nhap.php?success=xoa");
        exit;
    } catch (Exception $e) {
        $pdo->rollBack();
        header("Location: danh_sach_phieu_nhap.php?error=" . urlencode($e->getMessage()));
        exit;
    }
}

// =========================
// Bộ lọc tìm kiếm
// =========================
$maSearch     = trim($_GET['ma'] ?? '');
$nccSearch    = trim($_GET['mancc'] ?? '');
$khoSearch    = trim($_GET['makho'] ?? '');
$dateFrom     = trim($_GET['from'] ?? '');
$dateTo       = trim($_GET['to'] ?? '');

// Lấy danh sách NCC & Kho cho dropdown lọc
$nhacungcaps = $pdo->query("SELECT Mancc, Tenncc FROM Nhacungcap ORDER BY Tenncc")->fetchAll();
$khos        = $pdo->query("SELECT Makho, Tenkho FROM Kho ORDER BY Tenkho")->fetchAll();

// Xây dựng SQL với điều kiện lọc
$sql = "SELECT pn.*, ncc.Tenncc, k.Tenkho,
        (SELECT COUNT(*) FROM Chitiet_Phieunhap WHERE Manhaphang = pn.Manhaphang) as SoMatHang
        FROM Phieunhap pn
        LEFT JOIN Nhacungcap ncc ON pn.Mancc = ncc.Mancc
        LEFT JOIN Kho k ON pn.Makho = k.Makho
        WHERE 1=1";

$params = [];

if ($maSearch !== '') {
    $sql .= " AND pn.Manhaphang LIKE :ma";
    $params[':ma'] = '%' . $maSearch . '%';
}

if ($nccSearch !== '') {
    $sql .= " AND pn.Mancc = :mancc";
    $params[':mancc'] = $nccSearch;
}

if ($khoSearch !== '') {
    $sql .= " AND pn.Makho = :makho";
    $params[':makho'] = $khoSearch;
}

if ($dateFrom !== '') {
    $sql .= " AND pn.Ngaynhaphang >= :from";
    $params[':from'] = $dateFrom;
}

if ($dateTo !== '') {
    $sql .= " AND pn.Ngaynhaphang <= :to";
    $params[':to'] = $dateTo;
}

$sql .= " ORDER BY pn.Ngaynhaphang DESC, pn.Manhaphang DESC";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$phieuNhaps = $stmt->fetchAll();

$success = $_GET['success'] ?? '';
$error = $_GET['error'] ?? '';
?>
<!doctype html>
<html lang="vi">
<head>
  <meta charset="utf-8" />
  <meta name="viewport" content="width=device-width,initial-scale=1" />
  <title>Danh sách phiếu nhập</title>
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
        /* ===== DARK -> LIGHT (NỀN TRẮNG, CHỮ ĐEN) ===== */

/* Nền tổng */
body {
    background-color: #f8f9fa !important;
}

/* Nội dung chính */
.main-content {
    background-color: #f8f9fa !important;
    color: #212529 !important;
}

/* Card / form / box */
.bg-slate-800,
.bg-slate-900 {
    background-color: #ffffff !important;
}

/* Border */
.border-slate-700,
.border-slate-800 {
    border-color: #dee2e6 !important;
}

/* Text Tailwind */
.text-slate-200,
.text-slate-300,
.text-slate-400 {
    color: #495057 !important;
}

/* Tiêu đề */
h1, h2, h3, h4, h5 {
    color: #212529 !important;
}

/* Input / select / textarea */
input,
select,
textarea {
    background-color: #ffffff !important;
    color: #212529 !important;
}

/* Placeholder */
input::placeholder,
textarea::placeholder {
    color: #6c757d !important;
}

/* Table */
thead.bg-slate-900 {
    background-color: #f1f3f5 !important;
    color: #212529 !important;
}

tbody tr {
    color: #212529 !important;
}

tbody tr:hover {
    background-color: #f8f9fa !important;
}

/* Ghi chú trong bảng */
td.text-slate-400 {
    color: #6c757d !important;
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
                    <li class="nav-item">
                        <a class="nav-link" href="Sanpham.php">
                            <i class="fas fa-cube"></i> Sản phẩm
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="dmsp.php">
                            <i class="fas fa-tags"></i> Danh mục sản phẩm
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="Nhacungcap.php">
                            <i class="fas fa-truck"></i> Nhà cung cấp
                        </a>
                    </li>
                </ul>
            </li>


            <li class="nav-item">
              <a class="nav-link" href="javascript:void(0)" id="btnPhieuNhap">
                  <i class="fas fa-file-import"></i> Phiếu nhập kho
                  <i class="fas fa-chevron-down float-end"></i>
              </a>

              <ul class="nav flex-column ms-3 d-none" id="submenuPhieuNhap">
                  <li class="nav-item">
                      <a class="nav-link" href="danh_sach_phieu_nhap.php">
                          <i class="fas fa-list"></i> Danh sách phiếu nhập
                      </a>
                  </li>
                  <li class="nav-item">
                      <a class="nav-link" href="phieu_nhap.php">
                          <i class="fas fa-plus-circle"></i> Tạo phiếu nhập
                      </a>
                  </li>
              </ul>
          </li>
          <li class="nav-item">
              <a class="nav-link" href="javascript:void(0)" id="btnPhieuXuat">
                  <i class="fas fa-file-import"></i> Phiếu xuất
                  <i class="fas fa-chevron-down float-end"></i>
              </a>

              <ul class="nav flex-column ms-3 d-none" id="submenuPhieuXuat">
                  <li class="nav-item">
                      <a class="nav-link" href="danh_sach_phieu_xuat.php">
                          <i class="fas fa-list"></i> Danh sách phiếu xuất
                      </a>
                  </li>
                  <li class="nav-item">
                      <a class="nav-link" href="phieu_xuat.php">
                          <i class="fas fa-plus-circle"></i> Tạo phiếu xuất
                      </a>
                  </li>
              </ul>
          </li>
            <li class="nav-item">
                <a class="nav-link" href="javascript:void(0)" id="btnBaoCao">
                    <i class="fas fa-chart-bar"></i> Báo cáo & Thống kê
                    <i class="fas fa-chevron-down float-end"></i>
                </a>

            
                    <li class="nav-item">
                        <a class="nav-link" href="tonkho.php">
                            <i class="fas fa-warehouse"></i> Báo cáo tồn kho
                        </a>
                    </li>
                  
                </ul>
            </li>

            <li class="nav-item">
                <a class="nav-link" href="khachhang.php"><i class="fas fa-users"></i> Khách hàng</a>
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
                <a class="nav-link text-danger" href="logout.php"><i class="fas fa-sign-out-alt"></i> Đăng xuất</a>
            </li>
        </ul>
    </nav>

    <div class="main-content">
  <div class="max-w-7xl mx-auto p-6 space-y-6">
    <div class="flex items-center justify-between">
      <div>
        <h1 class="text-2xl font-bold">Danh sách phiếu nhập</h1>
        <p class="text-slate-400 text-sm mt-1">Quản lý các phiếu nhập kho</p>
      </div>
      <div class="flex gap-2 text-sm">
        <a href="phieu_nhap.php" class="px-4 py-2 rounded bg-sky-600 hover:bg-sky-700 font-semibold">+ Tạo phiếu nhập</a>
        <a href="danh_sach_phieu_nhap.php" class="px-3 py-2 rounded bg-slate-800 hover:bg-slate-700">← Dashboard</a>
        <a href="logout.php" class="px-3 py-2 rounded bg-red-600 hover:bg-red-700">Đăng xuất</a>
      </div>
    </div>

    <?php if ($success === 'xoa'): ?>
      <div class="bg-emerald-900/60 border border-emerald-700 text-emerald-100 px-4 py-3 rounded">
        Đã xóa phiếu nhập thành công.
      </div>
    <?php endif; ?>

    <?php if ($error): ?>
      <div class="bg-red-900/60 border border-red-700 text-red-200 px-4 py-3 rounded">
        Lỗi: <?= htmlspecialchars($error) ?>
      </div>
    <?php endif; ?>

    <!-- Form tìm kiếm -->
    <form method="get" class="bg-slate-800 border border-slate-700 rounded-lg p-4 space-y-3 text-sm">
      <div class="grid md:grid-cols-5 gap-3">
        <div>
          <label class="block text-slate-300 mb-1">Mã phiếu</label>
          <input name="ma" value="<?= htmlspecialchars($maSearch) ?>" class="w-full px-3 py-2 rounded bg-slate-900 border border-slate-700" placeholder="Nhập mã phiếu..." />
        </div>
        <div>
          <label class="block text-slate-300 mb-1">Nhà cung cấp</label>
          <select name="mancc" class="w-full px-3 py-2 rounded bg-slate-900 border border-slate-700">
            <option value="">-- Tất cả --</option>
            <?php foreach ($nhacungcaps as $ncc): ?>
              <option value="<?= htmlspecialchars($ncc['Mancc']) ?>" <?= $nccSearch === $ncc['Mancc'] ? 'selected' : '' ?>>
                <?= htmlspecialchars($ncc['Tenncc']) ?>
              </option>
            <?php endforeach; ?>
          </select>
        </div>
        <div>
          <label class="block text-slate-300 mb-1">Kho</label>
          <select name="makho" class="w-full px-3 py-2 rounded bg-slate-900 border border-slate-700">
            <option value="">-- Tất cả --</option>
            <?php foreach ($khos as $kho): ?>
              <option value="<?= htmlspecialchars($kho['Makho']) ?>" <?= $khoSearch === $kho['Makho'] ? 'selected' : '' ?>>
                <?= htmlspecialchars($kho['Tenkho']) ?> [<?= htmlspecialchars($kho['Makho']) ?>]
              </option>
            <?php endforeach; ?>
          </select>
        </div>
        <div>
          <label class="block text-slate-300 mb-1">Từ ngày</label>
          <input type="date" name="from" value="<?= htmlspecialchars($dateFrom) ?>" class="w-full px-3 py-2 rounded bg-slate-900 border border-slate-700" />
        </div>
        <div>
          <label class="block text-slate-300 mb-1">Đến ngày</label>
          <input type="date" name="to" value="<?= htmlspecialchars($dateTo) ?>" class="w-full px-3 py-2 rounded bg-slate-900 border border-slate-700" />
        </div>
      </div>
      <div class="flex items-center gap-2 pt-1">
        <button type="submit" class="px-4 py-2 rounded bg-emerald-600 hover:bg-emerald-700 text-slate-900 font-semibold">
          Tìm kiếm
        </button>
        <a href="danh_sach_phieu_nhap.php" class="px-3 py-2 rounded bg-slate-700 hover:bg-slate-600 text-slate-100">
          Xóa lọc
        </a>
      </div>
    </form>

    <div class="bg-slate-800 rounded-lg border border-slate-700 overflow-auto">
      <table class="min-w-full text-sm">
        <thead class="bg-slate-900 text-slate-300">
          <tr>
            <th class="px-4 py-3 text-left">Mã phiếu</th>
            <th class="px-4 py-3 text-left">Nhà cung cấp</th>
            <th class="px-4 py-3 text-left">Kho</th>
            <th class="px-4 py-3 text-left">Ngày nhập</th>
            <th class="px-4 py-3 text-right">Số mặt hàng</th>
            <th class="px-4 py-3 text-right">Tổng tiền</th>
            <th class="px-4 py-3 text-left">Ghi chú</th>
            <th class="px-4 py-3 text-center">Thao tác</th>
          </tr>
        </thead>
        <tbody>
          <?php if (empty($phieuNhaps)): ?>
            <tr><td colspan="8" class="px-4 py-4 text-center text-slate-400">Chưa có phiếu nhập nào.</td></tr>
          <?php else: ?>
            <?php foreach ($phieuNhaps as $pn): ?>
              <tr class="border-t border-slate-800 hover:bg-slate-700/50">
                <td class="px-4 py-2 font-semibold"><?= htmlspecialchars($pn['Manhaphang']) ?></td>
                <td class="px-4 py-2"><?= htmlspecialchars($pn['Tenncc'] ?? 'N/A') ?></td>
                <td class="px-4 py-2"><?= htmlspecialchars($pn['Tenkho'] ?? 'N/A') ?></td>
                <td class="px-4 py-2"><?= date('d/m/Y', strtotime($pn['Ngaynhaphang'])) ?></td>
                <td class="px-4 py-2 text-right"><?= number_format($pn['SoMatHang']) ?></td>
                <td class="px-4 py-2 text-right font-semibold"><?= number_format($pn['Tongtiennhap'], 0, ',', '.') ?> đ</td>
                <td class="px-4 py-2 text-slate-400"><?= htmlspecialchars(mb_substr($pn['Ghichu'] ?? '', 0, 50)) ?><?= mb_strlen($pn['Ghichu'] ?? '') > 50 ? '...' : '' ?></td>
                <td class="px-4 py-2">
                  <div class="flex items-center justify-center gap-2">
                    <a href="sua_phieu_nhap.php?id=<?= urlencode($pn['Manhaphang']) ?>" class="px-3 py-1 rounded bg-blue-600 hover:bg-blue-700 text-xs font-semibold">Sửa</a>
                    <a href="chi_tiet_phieu_nhap.php?id=<?= urlencode($pn['Manhaphang']) ?>" class="px-3 py-1 rounded bg-emerald-600 hover:bg-emerald-700 text-xs font-semibold">Chi tiết</a>
                    <a href="?xoa=<?= urlencode($pn['Manhaphang']) ?>" 
                       onclick="return confirm('Bạn có chắc chắn muốn xóa phiếu nhập này? Hành động này sẽ giảm số lượng tồn kho.')" 
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
    const menu = document.getElementById("submenuSanPham");
    menu.classList.toggle("d-none");
    
});
document.getElementById("btnBaoCao").addEventListener("click", function () {
    document.getElementById("submenuBaoCao").classList.toggle("d-none");
});
const btnPhieuNhap = document.getElementById("btnPhieuNhap");
const submenuPhieuNhap = document.getElementById("submenuPhieuNhap");

if (btnPhieuNhap) {
    btnPhieuNhap.addEventListener("click", function () {
        submenuPhieuNhap.classList.toggle("d-none");
    });
}
const btnPhieuXuat = document.getElementById("btnPhieuXuat");
const submenuPhieuXuat = document.getElementById("submenuPhieuXuat");

if (btnPhieuXuat) {
    btnPhieuXuat.addEventListener("click", function () {
        submenuPhieuXuat.classList.toggle("d-none");
    });
}
</script>
</body>
</html>