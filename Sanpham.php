
    <?php
$conn = mysqli_connect("localhost", "root", "", "quanlykho");
require_once 'Classes/PHPExcel.php';  // đường dẫn tới thư viện
require_once 'Classes/PHPExcel/IOFactory.php';

// ===============================
// 3. HÀM INSERT DỮ LIỆU
// ===============================
function tao_ins($Masp, $Tensp, $Madm, $dvt, $giaban, $conn) {
    $sql = "INSERT INTO Sanpham
            VALUES ('$Masp', '$Tensp', $Madm, '$dvt', $giaban)";
    return mysqli_query($conn, $sql);
}

function kiemtra_masp($Masp, $conn) {
    $sql = "SELECT 1 FROM Sanpham WHERE Masp = '$Masp' LIMIT 1";
    $rs = mysqli_query($conn, $sql);
    return mysqli_num_rows($rs) > 0;
}


// ===============================
// 4. XỬ LÝ NHẬP EXCEL
// ===============================
if (isset($_POST['btnUpload'])) {

    $file = $_FILES['txtTenfile']['tmp_name'];

    // Đọc file Excel
    $objReader = PHPExcel_IOFactory::createReaderForFile($file);
    $objExcel  = $objReader->load($file);

    // Lấy sheet đầu tiên
    $sheet = $objExcel->getSheet(0);
    $sheetData = $sheet->toArray(null, true, true, true);

    // Duyệt dữ liệu (bỏ dòng tiêu đề)
    for ($i = 2; $i <= count($sheetData); $i++) {

        $Masp = $sheetData[$i]['A'];
        $Tensp = $sheetData[$i]['B'];
        $Madm = $sheetData[$i]['C'];
        $dvt   = $sheetData[$i]['D'];
      $giaban   = $sheetData[$i]['E'];

        // Không insert dòng trống
        if ($Masp != "") {

            // Nếu mã đã tồn tại → bỏ qua
            if (kiemtra_masp($Masp, $conn)) {
                continue; // nhảy sang dòng tiếp theo
            }

            // Nếu chưa tồn tại → insert
            if (!tao_ins($Masp, $Tensp, $Madm, $dvt, $giaban, $conn)) {
                echo "Lỗi insert dòng $i: " . mysqli_error($conn);
                exit;
            }
        }


    }

    echo "<script>alert('Nhập dữ liệu từ Excel thành công!'); window.location.href='Sanpham.php';</script>";
}
// ===============================
// 5. XỬ LÝ XUẤT EXCEL
if (isset($_GET['export'])) {

    require_once 'Classes/PHPExcel.php';

    // ===== CODE XUẤT EXCEL =====
    $objExcel = new PHPExcel();
    $objExcel->setActiveSheetIndex(0);
    $sheet = $objExcel->getActiveSheet()->setTitle('Danh sách sản phẩm');

    $rowCount = 1;

    // ===== TẠO TIÊU ĐỀ CỘT =====
    $sheet->setCellValue('A'.$rowCount, 'Mã');
    $sheet->setCellValue('B'.$rowCount, 'Tên');
    $sheet->setCellValue('C'.$rowCount, 'Tên danh mục');

    $sheet->setCellValue('D'.$rowCount, 'Đơn vị tính');
    $sheet->setCellValue('E'.$rowCount, 'Giá bán');



    // ===== ĐỊNH DẠNG CỘT =====
    $sheet->getColumnDimension('A')->setAutoSize(true);
    $sheet->getColumnDimension('B')->setAutoSize(true);
    $sheet->getColumnDimension('C')->setAutoSize(true);
    $sheet->getColumnDimension('D')->setAutoSize(true);
    $sheet->getColumnDimension('E')->setAutoSize(true);


    // ===== GÁN MÀU NỀN =====
    $sheet->getStyle('A1:E1')
          ->getFill()
          ->setFillType(PHPExcel_Style_Fill::FILL_SOLID)
          ->getStartColor()->setRGB('00FF00');

    // ===== CĂN GIỮA =====
    $sheet->getStyle('A1:E1')
          ->getAlignment()
          ->setHorizontal(PHPExcel_Style_Alignment::HORIZONTAL_CENTER);

    // ===== LẤY DỮ LIỆU TỪ FORM TÌM KIẾM =====
    $ma  = $_GET['tkma']  ?? '';
    $ten = $_GET['tkten'] ?? '';

    $where = " WHERE 1=1 ";
    if ($ma != '')  $where .= " AND t.Masp LIKE '%$ma%'";
    if ($ten != '') $where .= " AND t.Tensp LIKE '%$ten%'";

    $sql = "
        SELECT t.Masp, t.Tensp, dm.Tendm,  t.Dvt,t.Giaban 
        FROM Sanpham t
        LEFT JOIN Danhmucsp dm ON t.Madm = dm.Madm
        $where
        ORDER BY t.Masp ASC
    ";

    $data = mysqli_query($conn, $sql);

    // ===== ĐIỀN DỮ LIỆU =====
    while ($row = mysqli_fetch_assoc($data)) {
        $rowCount++;

        $sheet->setCellValue('A'.$rowCount, $row['Masp']);
        $sheet->setCellValue('B'.$rowCount, $row['Tensp']);
        $sheet->setCellValue('C'.$rowCount, $row['Tendm']);

        $sheet->setCellValue('E'.$rowCount, $row['Giaban']);
        $sheet->setCellValue('D'.$rowCount, $row['Dvt']);
       
    }

    // ===== KẺ BẢNG =====
    $styleArray = array(
        'borders' => array(
            'allborders' => array(
                'style' => PHPExcel_Style_Border::BORDER_THIN
            )
        )
    );
    $sheet->getStyle('A1:E'.$rowCount)->applyFromArray($styleArray);

    // ===== XUẤT FILE =====
    $filename = "ExportExcel.xlsx";

    // Xóa buffer tránh lỗi file hỏng
    if (ob_get_length()) {
        ob_end_clean();
    }

    header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
    header('Content-Disposition: attachment; filename="'.$filename.'"');

    $writer = PHPExcel_IOFactory::createWriter($objExcel, 'Excel2007');
    $writer->save('php://output');
    exit;
}
// ===============================
$ma = "";
$ten = "";
    // Lấy danh sách sản phẩm từ bảng 'tao'
$where = "WHERE 1=1";
if (isset($_GET['timkiem'])) {
    if (!empty($_GET['tkma'])) {
        $ma = mysqli_real_escape_string($conn, $_GET['tkma']);
        $where .= " AND Masp LIKE '%$ma%'";
    }
    if (!empty($_GET['tkten'])) {
        $ten = mysqli_real_escape_string($conn, $_GET['tkten']);
        $where .= " AND Tensp LIKE '%$ten%'";
    }
}
$limit = 10; // 10 sản phẩm / trang
$page = isset($_GET['page']) ? max(1, intval($_GET['page'])) : 1;
$offset = ($page - 1) * $limit;
$sqlCount = "SELECT COUNT(*) as total FROM Sanpham t 
LEFT JOIN Danhmucsp dm ON t.Madm = dm.Madm $where";
$totalRow = mysqli_fetch_assoc(mysqli_query($conn, $sqlCount));
$totalPage = ceil($totalRow['total'] / $limit);

$sql = "SELECT t.Masp, t.Tensp, dm.Tendm, t.Dvt, t.Giaban 
        FROM Sanpham t
        LEFT JOIN Danhmucsp dm ON t.Madm = dm.Madm
        $where
        LIMIT $limit OFFSET $offset";
$list = mysqli_query($conn, $sql);



if (!$list) {
    die("Lỗi truy vấn: " . mysqli_error($conn));
}

?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Danh Sách Sản Phẩm - Slick</title>
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
        
       
        
        
         /* 4. DESIGN BẢNG GIỐNG MẪU BẠN GỬI */
       /* =========================
   HEADER + NHÓM NÚT
   ========================= */

/* Khung header phía trên bảng */
.header-table {
    display: flex;                 /* Dùng flexbox để sắp xếp con theo hàng ngang */
    justify-content: space-between;/* Đẩy 2 bên: tiêu đề bên trái – nút bên phải */
    align-items: center;           /* Canh giữa theo chiều dọc */
    margin-bottom: 20px;           /* Tạo khoảng cách với phần bên dưới */
}

/* Nhóm các nút nằm cạnh nhau */
.nhom-nut {
    display: flex;                 /* Các nút nằm trên 1 hàng */
    gap: 10px;                     /* Khoảng cách giữa các nút */
}

/* Style chung cho các nút tự tạo */
.nut {
    padding: 8px 15px;             /* Đệm trong: trên-dưới | trái-phải */
    border-radius: 4px;            /* Bo tròn góc nút */
    text-decoration: none;         /* Bỏ gạch chân thẻ <a> */
    font-size: 13px;               /* Cỡ chữ */
    font-weight: bold;             /* Chữ đậm */
    border: none;                  /* Bỏ viền */
    cursor: pointer;               /* Hover hiện bàn tay */
}

/* Nút tạo mới */
.nut-tao {
    background: #27ae60;           /* Màu nền xanh lá */
    color: white;                  /* Màu chữ trắng */
}

/* Nút xuất */
.nut-xuat {
    background: #eee;              /* Nền xám nhạt */
    color: #333;                   /* Màu chữ xám đậm */
}

/* =========================
   THANH TÌM KIẾM
   ========================= */

/* Khung tìm kiếm */
.thanh-tim-kiem {
    display: flex;                 /* Sắp xếp input + nút theo hàng */
    gap: 20px;                     /* Khoảng cách giữa các thành phần */
    background: #f9f9f9;           /* Màu nền xám rất nhạt */
    padding: 10px;                 /* Khoảng đệm trong */
    border-radius: 4px;            /* Bo tròn góc */
    margin-bottom: 15px;           /* Cách phần bảng bên dưới */
    align-items: center;           /* Canh giữa theo chiều dọc */
    border: 1px solid #ddd;        /* Viền xám nhạt */
}

/* Input bên trong thanh tìm kiếm */
.thanh-tim-kiem input {
    border: none;                  /* Bỏ viền mặc định */
    background: transparent;       /* Nền trong suốt */
    outline: none;                 /* Bỏ viền xanh khi focus */
    padding-left: 10px;            /* Cách lề trái cho chữ */
    width: 100%;                   /* Chiếm hết chiều ngang còn lại */
}

/* =========================
   TABLE
   ========================= */

table {
    width: 100%;                   /* Bảng rộng full khung */
    border-collapse: collapse;     /* Gộp viền lại (không bị đôi) */
    font-size: 14px;               /* Cỡ chữ trong bảng */
}

table thead {
    background: #f8f9fa;           /* Nền header bảng */
    border-bottom: 2px solid #dee2e6; /* Viền dưới header */
}

table th {
    padding: 12px;                 /* Khoảng cách chữ với ô */
    text-align: left;              /* Canh trái chữ */
    color: #495057;                /* Màu chữ header */
}

table td {
    padding: 12px;                 /* Khoảng cách trong ô */
    border-bottom: 1px solid #eee; /* Đường kẻ giữa các dòng */
    vertical-align: middle;        /* Canh giữa nội dung theo chiều dọc */
}
.canh-trai {
    text-align: left;
}
.canh-giua {
    text-align: center;
}
.canh-phai {
    text-align: right;
}

/* =========================
   CHIP – TRẠNG THÁI
   ========================= */

.chip {
    padding: 4px 10px;             /* Đệm trong */
    border-radius: 20px;           /* Bo tròn dạng viên thuốc */
    font-size: 11px;               /* Cỡ chữ nhỏ */
    font-weight: bold;             /* Chữ đậm */
    background: #e8f0fe;           /* Nền xanh nhạt */
    color: #1967d2;                /* Chữ xanh đậm */
}

/* =========================
   NÚT HÀNH ĐỘNG (SỬA / XOÁ)
   ========================= */

.nut-hanh-dong {
    color: #888;                   /* Màu icon mặc định */
    margin: 0 5px;                 /* Khoảng cách giữa các icon */
    cursor: pointer;               /* Hover hiện tay */
    text-decoration: none;         /* Bỏ gạch chân */
}

.nut-hanh-dong:hover {
    color: #ff4d4d;                /* Hover chuyển sang đỏ */
}

/* =========================
   PHÂN TRANG CỐ ĐỊNH DƯỚI
   ========================= */

.pagination-fixed {
    position: fixed;               /* Cố định ở màn hình */
    bottom: 0;                     /* Dính sát đáy */
    left: 250px;                   /* Chừa chỗ cho sidebar */
    right: 0;                      /* Kéo rộng hết bên phải */
    background: #fff;              /* Nền trắng */
    padding: 10px 20px;            /* Đệm trong */
    border-top: 1px solid #ddd;    /* Viền trên */
    z-index: 999;                  /* Luôn nằm trên các phần khác */
}

/* Khung pagination */
.pagination {
    display: flex;                /* Dùng flexbox */
    justify-content: center;       /* Canh giữa các nút trang */
    gap: 8px;                      /* Khoảng cách giữa các nút */
  /*  justify-content: flex-end;  👉 CANH PHẢI */ 
/*justify-content: flex-start;  canh trái */

}

/* Nút số trang */
.pagination a {
    padding: 6px 12px;             /* Kích thước nút */
    border-radius: 4px;            /* Bo góc */
    background: #f1f1f1;           /* Nền xám */
    text-decoration: none;         /* Bỏ gạch chân */
    color: #333;                   /* Màu chữ */
    font-size: 13px;               /* Cỡ chữ */
}

/* Trang đang chọn */
.pagination a.active {
    background: #007bff;           /* Nền xanh */
    color: #fff;                   /* Chữ trắng */
}

/* Hover nút trang */
.pagination a:hover {
    background: #0056b3;           /* Xanh đậm hơn */
    color: #fff;                   /* Chữ trắng */
}

/* =========================
   HEADER DANH SÁCH
   ========================= */

.header-danh-sach {
    display: flex;                 /* Sắp xếp ngang */
    justify-content: space-between;/* Đẩy 2 bên */
    align-items: center;           /* Canh giữa */
    margin-bottom: 20px;           /* Cách phần dưới */
}

/* Tiêu đề chính */
.tieu-de-chinh {
    font-weight: 700;              /* Chữ rất đậm */
    color: #333;                   /* Màu chữ */
}
.chu {
    font-weight: 700;              /* Chữ rất đậm */
    color: #d30b0b;                   /* Màu chữ */
}

/* =========================
   FORM TÌM KIẾM 2 CỘT
   ========================= */

.chia2cot {
    display: grid;                 /* Dùng grid layout */
    grid-template-columns: 1fr 1fr auto; 
                                   /* 2 ô input + 1 ô nút */
    gap: 20px;                     /* Khoảng cách giữa các cột */
    background: #fff;              /* Nền trắng */
    padding: 15px;                 /* Đệm trong */
    border-radius: 8px;            /* Bo góc */
    box-shadow: 0 4px 12px rgba(0,0,0,0.05); 
                                   /* Đổ bóng nhẹ */
    margin-bottom: 15px;           /* Cách phần dưới */
}

/* Input tìm kiếm */
.input-tim-kiem {
    padding: 10px 12px;            /* Đệm trong */
    border: 1px solid #ddd;        /* Viền xám */
    border-radius: 6px;            /* Bo góc */
    width: 100%;                   /* Full chiều ngang */
}

/* =========================
   KHUNG BẢNG
   ========================= */

.khung-bang-bao-quanh {
    background: #fff;              /* Nền trắng */
    border-radius: 10px;           /* Bo góc */
    box-shadow: 0 6px 18px rgba(0,0,0,0.06); 
                                   /* Đổ bóng */
    overflow: hidden;              /* Không cho tràn góc */
}

/* Bảng sản phẩm */
.bang-san-pham {
    width: 100%;                   /* Full chiều ngang */
    border-collapse: collapse;     /* Gộp viền */
}

/* Header bảng */
.bang-san-pham th {
    background: #f1f3f5;           /* Nền header */
    text-transform: uppercase;     /* Viết hoa chữ */
    font-size: 13px;               /* Cỡ chữ nhỏ */
}

/* Ô bảng */
.bang-san-pham td,
.bang-san-pham th {
    padding: 14px;                 /* Đệm trong */
    border-bottom: 1px solid #eee; /* Đường kẻ dưới */
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

    <!-- HEADER -->
    <div class="header-danh-sach">
        <h2 class="tieu-de-chinh">Danh sách sản phẩm</h2>
    </div>

    <!-- TÌM KIẾM + NÚT -->
    <form method="GET">
        <div class="chia2cot">
            <input type="text" name="tkma" value="<?= htmlspecialchars($ma) ?>"
                   class="input-tim-kiem" placeholder="Tìm theo mã sản phẩm">

            <input type="text" name="tkten" value="<?= htmlspecialchars($ten) ?>"
                   class="input-tim-kiem" placeholder="Tìm theo tên sản phẩm">

            <div class="nhom-nut">
                <button class="btn btn-primary" name="timkiem">
                    <i class="fas fa-search"></i> Tìm
                </button>
            </div>
        </div>
    </form>

    <!-- NHÓM NÚT CHỨC NĂNG -->
    <div class="d-flex gap-2 mb-3">
        <form method="POST" enctype="multipart/form-data">
            <input type="file" name="txtTenfile" accept=".xls,.xlsx" required>
            <button type="submit" name="btnUpload" class="btn btn-secondary">
                <i class="fas fa-file-import"></i> Nhập Excel
            </button>
        </form>

        <a href="Sanpham.php?export=1&tkma=<?= urlencode($ma) ?>&tkten=<?= urlencode($ten) ?>"
           class="btn btn-secondary">
            <i class="fas fa-file-export"></i> Xuất Excel
        </a>

        <a href="taosanpham.php" class="btn btn-success">
            <i class="fas fa-plus"></i> Thêm sản phẩm
        </a>
    </div>

    <!-- BẢNG -->
    <div class="khung-bang-bao-quanh">
        <table class="bang-san-pham">
            <thead>
                <tr>
                    <th>Mã SP</th>
                    <th>Tên sản phẩm</th>
                    <th>Danh mục</th>
                    <th>Đơn vị</th>
                    <th>Giá bán</th>
                    <th>Thao tác</th>
                </tr>
            </thead>
            <tbody>
            <?php while($row = mysqli_fetch_assoc($list)): ?>
                <tr>
                    <td><?= $row['Masp'] ?></td>
                    <td class="chu"><?= $row['Tensp'] ?></td>
                    <td><span class="chip"><?= $row['Tendm'] ?></span></td>
                    <td><?= $row['Dvt'] ?></td>
                    <td><?= number_format($row['Giaban']) ?></td>
                    <td>
                        <a class="nut-hanh-dong nut-sua"
                           href="suasp.php?Masp=<?= $row['Masp'] ?>">
                           <i class="fas fa-edit"></i>
                        </a>
                        <a class="nut-hanh-dong nut-xoa"
                           onclick="return confirm('Bạn có chắc muốn xóa?');"
                           href="xoasp.php?Masp=<?= $row['Masp'] ?>">
                           <i class="fas fa-trash"></i>
                        </a>
                    </td>
                </tr>
            <?php endwhile; ?>
            </tbody>
        </table>
    </div>

</div>


           <div class="pagination-fixed">
                <div class="pagination">
                    <?php for ($i = 1; $i <= $totalPage; $i++): ?>
                        <a class="<?= ($i == $page) ? 'active' : '' ?>"
                        href="?page=<?= $i ?>&tkma=<?= urlencode($ma) ?>&tkten=<?= urlencode($ten) ?>">
                            <?= $i ?>
                        </a>
                    <?php endfor; ?>
                </div>
            </div>



        
    </div>
<script>
document.addEventListener("DOMContentLoaded", function () {

    // ===== TOGGLE MENU KHI CLICK =====
    document.getElementById("btnSanPham")?.addEventListener("click", function () {
        document.getElementById("submenuSanPham")?.classList.toggle("d-none");
    });

    document.getElementById("btnPhieuNhap")?.addEventListener("click", function () {
        document.getElementById("submenuPhieuNhap")?.classList.toggle("d-none");
    });

    document.getElementById("btnPhieuXuat")?.addEventListener("click", function () {
        document.getElementById("submenuPhieuXuat")?.classList.toggle("d-none");
    });

    document.getElementById("btnBaoCao")?.addEventListener("click", function () {
        document.getElementById("submenuBaoCao")?.classList.toggle("d-none");
    });

    document.getElementById("btnKhachHang")?.addEventListener("click", function () {
        document.getElementById("submenuKhachHang")?.classList.toggle("d-none");
    });

    // ===== TỰ ĐỘNG MỞ MENU QUẢN LÝ SẢN PHẨM KHI Ở TRANG CON =====
    const path = window.location.pathname;

    const sanPhamPages = [
        "Sanpham.php",
        "dmsp.php",
        "Nhacungcap.php",
        "taosanpham.php",
        "taodmsp.php",
        "suasp.php",
        "suadmsp.php",
        "taoncc.php",
        "suancc.php"
    ];

    sanPhamPages.forEach(page => {
        if (path.includes(page)) {
            document.getElementById("submenuSanPham")?.classList.remove("d-none");
        }
    });

});
</script>


</body>
</html>