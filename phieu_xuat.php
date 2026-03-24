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
$khachhangs = $pdo->query("
    SELECT Makh, Tenkh 
    FROM Khachhang 
    ORDER BY Tenkh
")->fetchAll();

$sanphams = $pdo->query("
    SELECT Masp, Tensp, Dvt 
    FROM Sanpham 
    ORDER BY Tensp
")->fetchAll();

$khos = $pdo->query("SELECT Makho, Tenkho FROM Kho ORDER BY Tenkho")->fetchAll();



if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $maxuat = trim($_POST['maxuathang'] ?? '');
    $makh = trim($_POST['makh'] ?? '');
    $makho = trim($_POST['makho'] ?? '');
    $ngayxuat = $_POST['ngayxuat'] ?? '';
    $ghichu = trim($_POST['ghichu'] ?? '');

    $maspArr = $_POST['masp'] ?? [];
    $soluongArr = $_POST['soluong'] ?? [];
    $dongiaArr = $_POST['dongia'] ?? [];

    // Kiểm tra dữ liệu chính
    if ($maxuat === '' || $makh === '' || $makho === '' || $ngayxuat === '') {
        $errors[] = 'Vui lòng nhập đầy đủ Mã xuất, Khách hàng, Kho, Ngày xuất.';
    }

    // Chuẩn hóa chi tiết sản phẩm
     $items = [];
    for ($i = 0; $i < count($maspArr); $i++) {
        $masp = trim($maspArr[$i] ?? '');
        $soluong = (int)($soluongArr[$i] ?? 0);
        $dongia = (float)($dongiaArr[$i] ?? 0);

        if ($masp === '' || $soluong <= 0 || $dongia <= 0) {
            continue;
        }

        $items[] = [
            'masp' => $masp,
            'soluong' => $soluong,
            'dongia' => $dongia,
        ];
    }

    if (empty($items)) {
        $errors[] = 'Cần ít nhất một dòng chi tiết hợp lệ.';
    }


    if (empty($items)) {
        $errors[] = 'Cần ít nhất một dòng chi tiết sản phẩm hợp lệ.';
    }


    if (!$errors) {
    try {
        $pdo->beginTransaction();

        // Tính tổng tiền xuất
        $tong = 0;
        foreach ($items as $it) {
            $tong += $it['soluong'] * $it['dongia'];
        }

        // Lưu phiếu xuất
        $stmtPhieu = $pdo->prepare("
            INSERT INTO Phieuxuat 
            (Maxuathang, Makh, Makho, Ngayxuat, Tongtienxuat, Ghichu)
            VALUES (:ma, :makh, :makho, :ngay, :tong, :ghichu)
        ");
        $stmtPhieu->execute([
            ':ma'    => $maxuat,
            ':makh'  => $makh,
            ':makho' => $makho,
            ':ngay'  => $ngayxuat,
            ':tong'  => $tong,
            ':ghichu'=> $ghichu,
        ]);

        // Lưu chi tiết phiếu xuất + trừ tồn kho
        $stmtCt = $pdo->prepare("
            INSERT INTO Chitiet_Phieuxuat 
            (Maxuathang, Masp, Soluong, Dongiaxuat, Thanhtien)
            VALUES (:ma, :masp, :sl, :dg, :tt)
        ");

       $stmtTonkho = $pdo->prepare("
    UPDATE Tonkho_sp
    SET Soluongton = Soluongton - :sl
    WHERE Makho = :makho AND Masp = :masp
");


        foreach ($items as $it) {
            // Chi tiết phiếu xuất
            $stmtCt->execute([
                ':ma'   => $maxuat,
                ':masp' => $it['masp'],
                ':sl'   => $it['soluong'],
                ':dg'   => $it['dongia'],
                ':tt'   => $it['soluong'] * $it['dongia'],
            ]);

            // Trừ tồn kho
            $stmtTonkho->execute([
                ':makho' => $makho,
                ':masp'  => $it['masp'],
                ':sl'    => $it['soluong'],
            ]);
        }

        $pdo->commit();
        $success = 'Tạo phiếu xuất thành công và đã cập nhật tồn kho.';
    } catch (Exception $e) {
        $pdo->rollBack();
        $errors[] = 'Lỗi khi lưu phiếu: ' . htmlspecialchars($e->getMessage());
    }
}

}
?>
<!doctype html>
<html lang="vi">
<head>
  <meta charset="utf-8" />
  <meta name="viewport" content="width=device-width,initial-scale=1" />
  <title>Phiếu xuất kho</title>
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
        /* ===== PHIẾU NHẬP NỀN TRẮNG – CHỮ ĐEN ===== */

/* Khung form */
.main-content .bg-slate-800 {
    background-color: #ffffff !important;
    color: #000000 !important;
}

/* Tiêu đề, chữ */
.main-content h1,
.main-content label,
.main-content p,
.main-content .text-slate-200,
.main-content .text-slate-300,
.main-content .text-slate-400 {
    color: #000000 !important;
}

/* Input, select, textarea */
.main-content input,
.main-content select,
.main-content textarea {
    background-color: #ffffff !important;
    color: #000000 !important;
    border: 1px solid #ced4da !important;
}

/* Table */
.main-content table {
    background-color: #ffffff !important;
    color: #000000 !important;
}

.main-content thead {
    background-color: #f1f3f5 !important;
    color: #000000 !important;
}

.main-content tbody tr {
    background-color: #ffffff !important;
}

.main-content tbody tr:hover {
    background-color: #f8f9fa !important;
}

/* Nút xóa */
.main-content button.text-red-400 {
    color: #dc3545 !important;
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
  <div class="max-w-5xl mx-auto p-6 space-y-6">
    <div class="flex items-center justify-between">
      <div>
        <h1 class="text-2xl font-bold">Phiếu xuất kho</h1>
        <p class="text-slate-400 text-sm mt-1">Ghi nhận hàng xuất ra</p>
      </div>
      <div class="flex gap-2 text-sm">
        
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
      <div class="grid md:grid-cols-3 gap-4">
        <div>
  <label class="block text-sm text-slate-300 mb-2">Khách hàng *</label>
  <select name="makh" required class="w-full px-3 py-2 rounded bg-slate-900 border border-slate-700">
    <option value="">-- Chọn khách hàng --</option>
    <?php foreach ($khachhangs as $kh): ?>
      <option value="<?= htmlspecialchars($kh['Makh']) ?>"
        <?= (($_POST['makh'] ?? '') === $kh['Makh']) ? 'selected' : '' ?>>
        <?= htmlspecialchars($kh['Tenkh']) ?>
      </option>
    <?php endforeach; ?>
  </select>
</div>

        <div>
          <label class="block text-sm text-slate-300 mb-2">Kho *</label>
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
      <div class="grid md:grid-cols-1 gap-4">
          <div>
          <label class="block text-sm text-slate-300 mb-2">Mã xuất hàng *</label>
          <input name="maxuathang" required class="w-full px-3 py-2 rounded bg-slate-900 border border-slate-700" value="<?= htmlspecialchars($_POST['maxuathang'] ?? '') ?>" />
        </div>
        <div>
          <label class="block text-sm text-slate-300 mb-2">Ngày xuất *</label>
            <input type="date" name="ngayxuat" required
            class="w-full px-3 py-2 rounded bg-slate-900 border border-slate-700"
             value="<?= htmlspecialchars($_POST['ngayxuat'] ?? date('Y-m-d')) ?>" />

        </div>
      </div>

      <div>
        <label class="block text-sm text-slate-300 mb-2">Ghi chú</label>
        <textarea name="ghichu" rows="3" class="w-full px-3 py-2 rounded bg-slate-900 border border-slate-700"><?= htmlspecialchars($_POST['ghichu'] ?? '') ?></textarea>
      </div>

      <div class="space-y-2">
        <div class="flex items-center justify-between">
          <div class="text-slate-200 font-semibold">Chi tiết sản phẩm</div>
          <button type="button" onclick="addRow()" class="px-3 py-1 rounded bg-sky-600 hover:bg-sky-700 text-sm font-semibold">+ Thêm dòng</button>
        </div>
        <div class="overflow-auto border border-slate-700 rounded">
          <table class="min-w-full text-sm">
            <thead class="bg-slate-900 text-slate-300">
              <tr>
                <th class="px-3 py-2 text-left">Sản phẩm</th>
                <th class="px-3 py-2 text-left">Số lượng</th>
                <th class="px-3 py-2 text-left">Đơn giá</th>
                <th class="px-3 py-2"></th>
              </tr>
            </thead>
            <tbody id="detail-rows">
              <?php
              $posted = isset($_POST['masp']) ? count($_POST['masp']) : 0;
              $rowCount = max($posted, 1);
              for ($i = 0; $i < $rowCount; $i++):
                  $maspVal = $_POST['masp'][$i] ?? '';
                  $slVal = $_POST['soluong'][$i] ?? '';
                  $dgVal = $_POST['dongia'][$i] ?? '';
              ?>
              <tr class="border-t border-slate-800">
                <td class="px-3 py-2">
                  <select name="masp[]" class="w-full px-3 py-2 rounded bg-slate-900 border border-slate-700">
                    <option value="">-- Chọn --</option>
                    <?php foreach ($sanphams as $sp): ?>
                      <option value="<?= htmlspecialchars($sp['Masp']) ?>" <?= ($maspVal === $sp['Masp']) ? 'selected' : '' ?>>
                        <?= htmlspecialchars($sp['Tensp']) ?> (<?= htmlspecialchars($sp['Dvt']) ?>)
                      </option>
                    <?php endforeach; ?>
                  </select>
                </td>
                <td class="px-3 py-2"><input name="soluong[]" type="number" min="1" class="w-full px-3 py-2 rounded bg-slate-900 border border-slate-700" value="<?= htmlspecialchars($slVal) ?>" /></td>
                <td class="px-3 py-2"><input name="dongia[]" type="number" min="0" step="0.01" class="w-full px-3 py-2 rounded bg-slate-900 border border-slate-700" value="<?= htmlspecialchars($dgVal) ?>" /></td>
                <td class="px-3 py-2 text-right"><button type="button" onclick="removeRow(this)" class="text-red-400 hover:text-red-200">Xóa</button></td>
              </tr>
              <?php endfor; ?>
            </tbody>
          </table>
        </div>
      </div>

      <div class="pt-2">
        <button type="submit" class="w-full md:w-auto inline-flex items-center justify-center gap-2 bg-emerald-600 hover:bg-emerald-700 text-slate-900 font-semibold px-5 py-3 rounded">
          Lưu phiếu xuất
        </button>
      </div>
    </form>
  </div>
</div>

<script>
  const optionTemplate = <?php
    $options = '';
    foreach ($sanphams as $sp) {
      $label = htmlspecialchars($sp['Tensp'] . ' (' . $sp['Dvt'] . ')', ENT_QUOTES);
      $val = htmlspecialchars($sp['Masp'], ENT_QUOTES);
      $options .= "<option value=\\\"{$val}\\\">{$label}</option>";
    }
    echo json_encode($options);
  ?>;

  function addRow() {
    const tbody = document.getElementById('detail-rows');
    const tr = document.createElement('tr');
    tr.className = 'border-t border-slate-800';
    tr.innerHTML = `
      <td class="px-3 py-2">
        <select name="masp[]" class="w-full px-3 py-2 rounded bg-slate-900 border border-slate-700">
          <option value="">-- Chọn --</option>
          ${optionTemplate}
        </select>
      </td>
      <td class="px-3 py-2"><input name="soluong[]" type="number" min="1" class="w-full px-3 py-2 rounded bg-slate-900 border border-slate-700" /></td>
      <td class="px-3 py-2"><input name="dongia[]" type="number" min="0" step="0.01" class="w-full px-3 py-2 rounded bg-slate-900 border border-slate-700" /></td>
      <td class="px-3 py-2 text-right"><button type="button" onclick="removeRow(this)" class="text-red-400 hover:text-red-200">Xóa</button></td>
    `;
    tbody.appendChild(tr);
  }

  function removeRow(btn) {
    const tr = btn.closest('tr');
    const tbody = tr.parentElement;
    tbody.removeChild(tr);
    if (tbody.children.length === 0) {
      addRow();
    }
  }
  
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
