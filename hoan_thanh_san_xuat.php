<?php
session_start();
if (!isset($_SESSION['user'])) {
    header('Location: dangnhap.php');
    exit;
}
require_once __DIR__ . '/db.php';

$errors = [];
$success = '';
$malenh = $_GET['id'] ?? '';

if (empty($malenh)) {
    header('Location: danh_sach_lenh_san_xuat.php');
    exit;
}

// Lấy thông tin lệnh sản xuất
$stmtLenh = $pdo->prepare("SELECT * FROM Lenhsanxuat WHERE Malenh = ?");
$stmtLenh->execute([$malenh]);
$lenh = $stmtLenh->fetch();

if (!$lenh) {
    header('Location: danh_sach_lenh_san_xuat.php?error=Lệnh không tồn tại');
    exit;
}

// Lấy công thức sản phẩm
$congthuc = $pdo->query("SELECT ct.*, nvl.Tennvl, nvl.Dvt FROM Congthucsanpham ct JOIN Nguyenvatlieu nvl ON ct.Manvl = nvl.Manvl WHERE ct.Masp = '{$lenh['Masp']}'")->fetchAll();

// Lấy danh sách kho
$khos = $pdo->query("SELECT Makho, Tenkho FROM Kho ORDER BY Tenkho")->fetchAll();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $makho = trim($_POST['makho'] ?? '');

    if ($makho === '') {
        $errors[] = 'Vui lòng chọn kho.';
    }

    if (!$errors) {
        try {
            $pdo->beginTransaction();

            // Xuất nguyên vật liệu
            foreach ($congthuc as $ct) {
                $soluong_can = $ct['Soluong'] * $lenh['Soluongsanxuat'];
                // Giả sử xuất từ kho sản xuất, nhưng để đơn giản, từ kho đã chọn
                $stmtXuat = $pdo->prepare("
                    UPDATE Tonkho_nvl 
                    SET Soluongton = Soluongton - :sl 
                    WHERE Makho = :makho AND Manvl = :manvl AND Soluongton >= :sl
                ");
                $stmtXuat->execute([
                    ':makho' => $makho,
                    ':manvl' => $ct['Manvl'],
                    ':sl' => $soluong_can,
                ]);
                if ($stmtXuat->rowCount() === 0) {
                    throw new Exception("Không đủ nguyên vật liệu {$ct['Tennvl']} trong kho.");
                }

                // Ghi chi tiết xuất NVL
                $pdo->prepare("INSERT INTO Chitiet_XuatNVL_Sanxuat (Malenh, Manvl, Soluong) VALUES (?, ?, ?)")->execute([$malenh, $ct['Manvl'], $soluong_can]);
            }

            // Nhập sản phẩm vào kho
            $stmtNhap = $pdo->prepare("
                INSERT INTO Tonkho_sp (Makho, Masp, Soluongton) 
                VALUES (:makho, :masp, :sl)
                ON DUPLICATE KEY UPDATE Soluongton = Soluongton + :sl
            ");
            $stmtNhap->execute([
                ':makho' => $makho,
                ':masp' => $lenh['Masp'],
                ':sl' => $lenh['Soluongsanxuat'],
            ]);

            // Ghi chi tiết nhập sản phẩm
            $pdo->prepare("INSERT INTO Chitiet_Nhapsanpham_Sanxuat (Malenh, Makho, Masp, Soluong) VALUES (?, ?, ?, ?)")->execute([$malenh, $makho, $lenh['Masp'], $lenh['Soluongsanxuat']]);

            // Cập nhật trạng thái lệnh
            $pdo->prepare("UPDATE Lenhsanxuat SET Trangthai = 'Hoàn thành' WHERE Malenh = ?")->execute([$malenh]);

            $pdo->commit();
            $success = 'Hoàn thành lệnh sản xuất thành công.';
        } catch (Exception $e) {
            $pdo->rollBack();
            $errors[] = 'Lỗi: ' . htmlspecialchars($e->getMessage());
        }
    }
}
?>
<!doctype html>
<html lang="vi">
<head>
  <meta charset="utf-8" />
  <meta name="viewport" content="width=device-width,initial-scale=1" />
  <title>Hoàn thành lệnh sản xuất</title>
  <script src="https://cdn.tailwindcss.com"></script>
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
       <style>
        body { 
            background-color: #f8f9fa; 
            font-family: 'Segoe UI', sans-serif; 
        }
        
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
  <div class="max-w-5xl mx-auto p-6 space-y-6">
    <div class="flex items-center justify-between">
      <div>
        <h1 class="text-2xl font-bold">Hoàn thành lệnh sản xuất</h1>
        <p class="text-slate-400 text-sm mt-1">Mã lệnh: <?= htmlspecialchars($lenh['Malenh']) ?></p>
      </div>
      <div class="flex gap-2 text-sm">
        <a href="danh_sach_lenh_san_xuat.php" class="px-3 py-2 rounded bg-slate-800 hover:bg-slate-700">Quay lại</a>
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

    <div class="bg-slate-800 rounded-lg p-5 space-y-4">
      <h2 class="text-lg font-semibold">Thông tin lệnh sản xuất</h2>
      <div class="grid md:grid-cols-2 gap-4">
        <div>
          <label class="block text-sm text-slate-400 mb-1">Mã lệnh</label>
          <div class="text-lg font-semibold"><?= htmlspecialchars($lenh['Malenh']) ?></div>
        </div>
        <div>
          <label class="block text-sm text-slate-400 mb-1">Sản phẩm</label>
          <div class="text-lg font-semibold">
            <?php
            $sp = $pdo->query("SELECT Tensp FROM Sanpham WHERE Masp = '{$lenh['Masp']}'")->fetch();
            echo htmlspecialchars($sp['Tensp'] ?? 'N/A');
            ?>
          </div>
        </div>
        <div>
          <label class="block text-sm text-slate-400 mb-1">Số lượng</label>
          <div class="text-lg font-semibold"><?= number_format($lenh['Soluongsanxuat']) ?></div>
        </div>
        <div>
          <label class="block text-sm text-slate-400 mb-1">Trạng thái</label>
          <div class="text-lg font-semibold"><?= htmlspecialchars($lenh['Trangthai']) ?></div>
        </div>
      </div>
    </div>

    <div class="bg-slate-800 rounded-lg p-5 space-y-4">
      <h2 class="text-lg font-semibold">Công thức nguyên vật liệu</h2>
      <table class="min-w-full text-sm">
        <thead class="bg-slate-900 text-slate-300">
          <tr>
            <th class="px-4 py-3 text-left">Nguyên vật liệu</th>
            <th class="px-4 py-3 text-left">ĐVT</th>
            <th class="px-4 py-3 text-right">Số lượng cần</th>
          </tr>
        </thead>
        <tbody>
          <?php if (empty($congthuc)): ?>
            <tr><td colspan="3" class="px-4 py-4 text-center text-slate-400">Chưa có công thức.</td></tr>
          <?php else: ?>
            <?php foreach ($congthuc as $ct): ?>
              <tr class="border-t border-slate-800">
                <td class="px-4 py-2"><?= htmlspecialchars($ct['Tennvl']) ?></td>
                <td class="px-4 py-2"><?= htmlspecialchars($ct['Dvt']) ?></td>
                <td class="px-4 py-2 text-right"><?= number_format($ct['Soluong'] * $lenh['Soluongsanxuat']) ?></td>
              </tr>
            <?php endforeach; ?>
          <?php endif; ?>
        </tbody>
      </table>
    </div>

    <?php if ($lenh['Trangthai'] !== 'Hoàn thành'): ?>
    <form method="post" class="bg-slate-800 rounded-lg p-5 space-y-4">
      <div class="grid md:grid-cols-1 gap-4">
        <div>
          <label class="block text-sm text-slate-300 mb-2">Chọn kho để thực hiện sản xuất *</label>
          <select name="makho" required class="w-full px-3 py-2 rounded bg-slate-900 border border-slate-700">
            <option value="">-- Chọn kho --</option>
            <?php foreach ($khos as $k): ?>
              <option value="<?= htmlspecialchars($k['Makho']) ?>"
                <?= (($_POST['makho'] ?? '') === $k['Makho']) ? 'selected' : '' ?>>
                <?= htmlspecialchars($k['Tenkho']) ?>
              </option>
            <?php endforeach; ?>
          </select>
        </div>
      </div>

      <div class="pt-2">
        <button type="submit" class="px-6 py-2 rounded bg-green-600 hover:bg-green-700 text-white font-semibold">
          Hoàn thành sản xuất
        </button>
      </div>
    </form>
    <?php endif; ?>
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