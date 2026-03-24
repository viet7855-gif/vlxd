-- =========================================================
-- CƠ SỞ DỮ LIỆU: QUẢN LÝ KHO THEO LUỒNG CUNG ỨNG
-- Luồng nghiệp vụ:
-- Nhập nguyên vật liệu -> Sản xuất -> Lưu kho -> Điều chuyển kho
-- -> Xuất bán cho đại lý/khách hàng -> Thanh toán
-- =========================================================

CREATE DATABASE IF NOT EXISTS vlxd;
USE vlxd;

-- =========================================================
-- 1. BẢNG DANH MỤC CƠ BẢN
-- =========================================================

-- Bảng kho: tạo trước để các bảng khác có thể tham chiếu
CREATE TABLE Kho (
    Makho VARCHAR(50) PRIMARY KEY,
    Tenkho VARCHAR(100) NOT NULL,
    Diachi TEXT
) ENGINE=InnoDB;

-- Bảng nhà cung cấp
CREATE TABLE Nhacungcap (
    Mancc VARCHAR(50) PRIMARY KEY,
    Tenncc VARCHAR(255) NOT NULL,
    Sdtncc VARCHAR(15),
    Diachincc VARCHAR(255)
) ENGINE=InnoDB;

-- Bảng loại khách hàng
CREATE TABLE Loaikhachhang (
    Maloaikh INT PRIMARY KEY AUTO_INCREMENT,
    Tenloaikh VARCHAR(100) NOT NULL,
    Motaloaikh TEXT
) ENGINE=InnoDB;

-- Bảng khách hàng / đại lý
CREATE TABLE Khachhang (
    Makh VARCHAR(50) PRIMARY KEY,
    Tenkh VARCHAR(255) NOT NULL,
    Sdtkh VARCHAR(15),
    Diachikh VARCHAR(255),
    Maloaikh INT,
    FOREIGN KEY (Maloaikh) REFERENCES Loaikhachhang(Maloaikh)
        ON UPDATE CASCADE
        ON DELETE SET NULL
) ENGINE=InnoDB;

-- Bảng người dùng hệ thống
CREATE TABLE Nguoidung (
    Manv VARCHAR(50) PRIMARY KEY,
    Tendangnhap VARCHAR(100) NOT NULL UNIQUE,
    Matkhau VARCHAR(255) NOT NULL,
    Hovaten VARCHAR(255),
    Email VARCHAR(100),
    Vaitro VARCHAR(50) NOT NULL
) ENGINE=InnoDB;

-- Bảng danh mục sản phẩm
CREATE TABLE Danhmucsp (
    Madm INT PRIMARY KEY AUTO_INCREMENT,
    Tendm VARCHAR(100) NOT NULL UNIQUE,
    Mota VARCHAR(100)
) ENGINE=InnoDB;

-- =========================================================
-- 2. BẢNG HÀNG HÓA: NGUYÊN VẬT LIỆU VÀ THÀNH PHẨM
-- =========================================================

-- Bảng nguyên vật liệu: phục vụ cho khâu nhập và sản xuất
CREATE TABLE Nguyenvatlieu (
    Manvl VARCHAR(50) PRIMARY KEY,
    Tennvl VARCHAR(255) NOT NULL,
    Dvt VARCHAR(50) NOT NULL,
    Giavon DECIMAL(18, 2) DEFAULT 0
) ENGINE=InnoDB;

-- Bảng sản phẩm thành phẩm: phục vụ xuất bán cho đại lý/khách hàng
CREATE TABLE Sanpham (
    Masp VARCHAR(50) PRIMARY KEY,
    Tensp VARCHAR(255) NOT NULL,
    Madm INT,
    Dvt VARCHAR(50) NOT NULL,
    Giaban DECIMAL(18, 2) DEFAULT 0,
    FOREIGN KEY (Madm) REFERENCES Danhmucsp(Madm)
        ON UPDATE CASCADE
        ON DELETE SET NULL
) ENGINE=InnoDB;

-- Bảng định mức nguyên vật liệu cho từng sản phẩm (BOM)
-- Ví dụ: 1 sản phẩm cần bao nhiêu nguyên vật liệu
CREATE TABLE Congthucsanpham (
    Masp VARCHAR(50),
    Manvl VARCHAR(50),
    Soluong DECIMAL(10,2) NOT NULL,
    PRIMARY KEY (Masp, Manvl),
    FOREIGN KEY (Masp) REFERENCES Sanpham(Masp)
        ON UPDATE CASCADE
        ON DELETE CASCADE,
    FOREIGN KEY (Manvl) REFERENCES Nguyenvatlieu(Manvl)
        ON UPDATE CASCADE
        ON DELETE CASCADE
) ENGINE=InnoDB;

-- =========================================================
-- 3. BẢNG TỒN KHO
-- =========================================================

-- Tồn kho nguyên vật liệu theo từng kho
CREATE TABLE Tonkho_nvl (
    Makho VARCHAR(50),
    Manvl VARCHAR(50),
    Soluongton DECIMAL(18,2) DEFAULT 0,
    PRIMARY KEY (Makho, Manvl),
    FOREIGN KEY (Makho) REFERENCES Kho(Makho)
        ON UPDATE CASCADE
        ON DELETE CASCADE,
    FOREIGN KEY (Manvl) REFERENCES Nguyenvatlieu(Manvl)
        ON UPDATE CASCADE
        ON DELETE CASCADE
) ENGINE=InnoDB;

-- Tồn kho thành phẩm theo từng kho
CREATE TABLE Tonkho_sp (
    Makho VARCHAR(50),
    Masp VARCHAR(50),
    Soluongton DECIMAL(18,2) DEFAULT 0,
    PRIMARY KEY (Makho, Masp),
    FOREIGN KEY (Makho) REFERENCES Kho(Makho)
        ON UPDATE CASCADE
        ON DELETE CASCADE,
    FOREIGN KEY (Masp) REFERENCES Sanpham(Masp)
        ON UPDATE CASCADE
        ON DELETE CASCADE
) ENGINE=InnoDB;

-- =========================================================
-- 4. NHẬP NGUYÊN VẬT LIỆU
-- =========================================================

-- Phiếu nhập nguyên vật liệu từ nhà cung cấp vào kho
CREATE TABLE Phieunhap (
    Manhaphang VARCHAR(50) PRIMARY KEY,
    Mancc VARCHAR(50),
    Makho VARCHAR(50),
    Ngaynhaphang DATE NOT NULL,
    Tongtiennhap DECIMAL(18, 2) DEFAULT 0,
    Ghichu TEXT,
    FOREIGN KEY (Mancc) REFERENCES Nhacungcap(Mancc)
        ON UPDATE CASCADE
        ON DELETE SET NULL,
    FOREIGN KEY (Makho) REFERENCES Kho(Makho)
        ON UPDATE CASCADE
        ON DELETE SET NULL
) ENGINE=InnoDB;

-- Chi tiết phiếu nhập nguyên vật liệu
CREATE TABLE Chitiet_Phieunhap (
    Id INT PRIMARY KEY AUTO_INCREMENT,
    Manhaphang VARCHAR(50),
    Manvl VARCHAR(50),
    Soluong DECIMAL(18,2) NOT NULL,
    Dongianhap DECIMAL(18, 2) NOT NULL,
    Thanhtien DECIMAL(18, 2) AS (Soluong * Dongianhap) STORED,
    FOREIGN KEY (Manhaphang) REFERENCES Phieunhap(Manhaphang)
        ON UPDATE CASCADE
        ON DELETE CASCADE,
    FOREIGN KEY (Manvl) REFERENCES Nguyenvatlieu(Manvl)
        ON UPDATE CASCADE
        ON DELETE CASCADE
) ENGINE=InnoDB;

-- =========================================================
-- 5. SẢN XUẤT
-- =========================================================

-- Phiếu/lệnh sản xuất
CREATE TABLE Lenhsanxuat (
    Malenh VARCHAR(50) PRIMARY KEY,
    Masp VARCHAR(50) NOT NULL,
    Ngaysanxuat DATE NOT NULL,
    Soluongsanxuat DECIMAL(18,2) NOT NULL,
    Trangthai VARCHAR(50) DEFAULT N'Đang sản xuất',
    Ghichu TEXT,
    FOREIGN KEY (Masp) REFERENCES Sanpham(Masp)
        ON UPDATE CASCADE
        ON DELETE RESTRICT
) ENGINE=InnoDB;

-- Chi tiết nguyên vật liệu xuất cho sản xuất
CREATE TABLE Chitiet_XuatNVL_Sanxuat (
    Id INT PRIMARY KEY AUTO_INCREMENT,
    Malenh VARCHAR(50),
    Manvl VARCHAR(50),
    Soluong DECIMAL(18,2) NOT NULL,
    FOREIGN KEY (Malenh) REFERENCES Lenhsanxuat(Malenh)
        ON UPDATE CASCADE
        ON DELETE CASCADE,
    FOREIGN KEY (Manvl) REFERENCES Nguyenvatlieu(Manvl)
        ON UPDATE CASCADE
        ON DELETE RESTRICT
) ENGINE=InnoDB;

-- Chi tiết thành phẩm nhập kho sau sản xuất
-- Bảng này dùng để ghi nhận sản phẩm hoàn thành được nhập vào kho thành phẩm
CREATE TABLE Chitiet_Nhapsanpham_Sanxuat (
    Id INT PRIMARY KEY AUTO_INCREMENT,
    Malenh VARCHAR(50),
    Makho VARCHAR(50),
    Masp VARCHAR(50),
    Soluong DECIMAL(18,2) NOT NULL,
    FOREIGN KEY (Malenh) REFERENCES Lenhsanxuat(Malenh)
        ON UPDATE CASCADE
        ON DELETE CASCADE,
    FOREIGN KEY (Makho) REFERENCES Kho(Makho)
        ON UPDATE CASCADE
        ON DELETE RESTRICT,
    FOREIGN KEY (Masp) REFERENCES Sanpham(Masp)
        ON UPDATE CASCADE
        ON DELETE RESTRICT
) ENGINE=InnoDB;

-- =========================================================
-- 6. ĐIỀU CHUYỂN GIỮA CÁC KHO
-- =========================================================

-- Phiếu điều chuyển hàng hóa giữa hai kho
CREATE TABLE Phieudieuchuyen (
    Madieuchuyen VARCHAR(50) PRIMARY KEY,
    Khoxuat VARCHAR(50) NOT NULL,
    Khonhap VARCHAR(50) NOT NULL,
    Ngaydieuchuyen DATE NOT NULL,
    Ghichu TEXT,
    FOREIGN KEY (Khoxuat) REFERENCES Kho(Makho)
        ON UPDATE CASCADE
        ON DELETE RESTRICT,
    FOREIGN KEY (Khonhap) REFERENCES Kho(Makho)
        ON UPDATE CASCADE
        ON DELETE RESTRICT
) ENGINE=InnoDB;

-- Chi tiết phiếu điều chuyển thành phẩm
CREATE TABLE Chitiet_Phieudieuchuyen (
    Id INT PRIMARY KEY AUTO_INCREMENT,
    Madieuchuyen VARCHAR(50),
    Masp VARCHAR(50),
    Soluong DECIMAL(18,2) NOT NULL,
    FOREIGN KEY (Madieuchuyen) REFERENCES Phieudieuchuyen(Madieuchuyen)
        ON UPDATE CASCADE
        ON DELETE CASCADE,
    FOREIGN KEY (Masp) REFERENCES Sanpham(Masp)
        ON UPDATE CASCADE
        ON DELETE RESTRICT
) ENGINE=InnoDB;

-- =========================================================
-- 7. XUẤT BÁN CHO ĐẠI LÝ / KHÁCH HÀNG
-- =========================================================

-- Phiếu xuất bán
CREATE TABLE Phieuxuat (
    Maxuathang VARCHAR(50) PRIMARY KEY,
    Makh VARCHAR(50),
    Makho VARCHAR(50),
    Ngayxuat DATE NOT NULL,
    Tongtienxuat DECIMAL(18, 2) DEFAULT 0,
    Ghichu TEXT,
    FOREIGN KEY (Makh) REFERENCES Khachhang(Makh)
        ON UPDATE CASCADE
        ON DELETE SET NULL,
    FOREIGN KEY (Makho) REFERENCES Kho(Makho)
        ON UPDATE CASCADE
        ON DELETE SET NULL
) ENGINE=InnoDB;

-- Chi tiết phiếu xuất bán
CREATE TABLE Chitiet_Phieuxuat (
    Id INT PRIMARY KEY AUTO_INCREMENT,
    Maxuathang VARCHAR(50),
    Masp VARCHAR(50),
    Soluong DECIMAL(18,2) NOT NULL,
    Dongiaxuat DECIMAL(18, 2) NOT NULL,
    Thanhtien DECIMAL(18, 2) AS (Soluong * Dongiaxuat) STORED,
    FOREIGN KEY (Maxuathang) REFERENCES Phieuxuat(Maxuathang)
        ON UPDATE CASCADE
        ON DELETE CASCADE,
    FOREIGN KEY (Masp) REFERENCES Sanpham(Masp)
        ON UPDATE CASCADE
        ON DELETE RESTRICT
) ENGINE=InnoDB;

-- =========================================================
-- 8. THANH TOÁN
-- =========================================================

CREATE TABLE Thanhtoan (
    Matt INT AUTO_INCREMENT PRIMARY KEY,
    Maxuathang VARCHAR(50),
    Ngaythanhtoan DATE NOT NULL,
    Sotienthanhtoan DECIMAL(18,2) NOT NULL,
    Hinhthuc VARCHAR(50),
    Ghichu TEXT,
    FOREIGN KEY (Maxuathang) REFERENCES Phieuxuat(Maxuathang)
        ON UPDATE CASCADE
        ON DELETE SET NULL
) ENGINE=InnoDB;

-- =========================================================
-- GHI CHÚ THAY ĐỔI SO VỚI BẢN CŨ
-- =========================================================
-- 1. Đưa bảng Kho lên trước để không lỗi khóa ngoại khi tạo Phieunhap.
-- 2. Tách hàng hóa thành 2 nhóm:
--    - Nguyenvatlieu: dùng cho nhập và sản xuất.
--    - Sanpham: dùng cho thành phẩm xuất bán.
-- 3. Bổ sung bảng Congthucsanpham để mô tả định mức nguyên vật liệu.
-- 4. Tách tồn kho thành:
--    - Tonkho_nvl: tồn nguyên vật liệu.
--    - Tonkho_sp : tồn thành phẩm.
-- 5. Bổ sung các bảng phục vụ sản xuất:
--    - Lenhsanxuat
--    - Chitiet_XuatNVL_Sanxuat
--    - Chitiet_Nhapsanpham_Sanxuat
-- 6. Bổ sung bảng điều chuyển kho:
--    - Phieudieuchuyen
--    - Chitiet_Phieudieuchuyen
-- 7. Giữ lại các bảng xuất bán và thanh toán để hoàn chỉnh luồng phân phối đến đại lý.