-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Generation Time: Feb 12, 2026 at 04:53 AM
-- Server version: 10.4.32-MariaDB
-- PHP Version: 8.2.12

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Database: `inventory_db`
--

-- --------------------------------------------------------

--
-- Table structure for table `activity_log`
--

CREATE TABLE `activity_log` (
  `id` int(11) NOT NULL,
  `user_id` int(11) DEFAULT NULL,
  `action` varchar(50) NOT NULL,
  `item_id` int(11) DEFAULT NULL,
  `details` text DEFAULT NULL,
  `date_created` datetime NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `activity_log`
--

INSERT INTO `activity_log` (`id`, `user_id`, `action`, `item_id`, `details`, `date_created`) VALUES
(1, 2, 'add', 48, 'Batch created item: TRA-0003', '2026-02-12 11:42:10'),
(2, 2, 'add', 49, 'Batch created item: TRA-0004', '2026-02-12 11:42:10');

-- --------------------------------------------------------

--
-- Table structure for table `buildings`
--

CREATE TABLE `buildings` (
  `id` int(11) NOT NULL,
  `name` varchar(255) NOT NULL,
  `floor` int(11) NOT NULL DEFAULT 1
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `buildings`
--

INSERT INTO `buildings` (`id`, `name`, `floor`) VALUES
(1, 'Administrative', 1),
(2, 'Front Line', 1),
(3, 'Out Patient', 1),
(4, 'Ward', 1),
(5, 'Ward', 2),
(6, 'Ward', 3);

-- --------------------------------------------------------

--
-- Table structure for table `departments`
--

CREATE TABLE `departments` (
  `id` int(11) NOT NULL,
  `building_id` int(11) DEFAULT NULL,
  `name` varchar(255) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `departments`
--

INSERT INTO `departments` (`id`, `building_id`, `name`) VALUES
(1, 1, 'HOPSS'),
(2, 6, 'Transition Ward');

-- --------------------------------------------------------

--
-- Table structure for table `employees`
--

CREATE TABLE `employees` (
  `id` int(11) NOT NULL,
  `firstname` varchar(100) NOT NULL,
  `lastname` varchar(100) NOT NULL,
  `middlename` varchar(100) DEFAULT NULL,
  `email` varchar(150) DEFAULT NULL,
  `contact` varchar(50) DEFAULT NULL,
  `department_id` int(11) DEFAULT NULL,
  `section_id` int(11) DEFAULT NULL,
  `position` varchar(100) DEFAULT NULL,
  `date_hired` date DEFAULT NULL,
  `status` enum('Active','Inactive') DEFAULT 'Active',
  `date_created` timestamp NOT NULL DEFAULT current_timestamp(),
  `date_updated` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `employees`
--

INSERT INTO `employees` (`id`, `firstname`, `lastname`, `middlename`, `email`, `contact`, `department_id`, `section_id`, `position`, `date_hired`, `status`, `date_created`, `date_updated`) VALUES
(1, 'Alvin', 'Lopez', 'Mabasa', NULL, NULL, 1, 1, 'Information Systems Analyst III', NULL, 'Active', '2025-11-20 01:22:09', '2025-11-20 01:26:46'),
(2, 'Christian Jane', 'Ramos', 'Gutierrez', NULL, NULL, 1, NULL, 'Training Specialist II', NULL, 'Active', '2025-11-20 01:24:43', '2025-11-20 01:26:32');

-- --------------------------------------------------------

--
-- Table structure for table `equipment`
--

CREATE TABLE `equipment` (
  `id` int(11) NOT NULL,
  `name` varchar(100) NOT NULL,
  `category` varchar(50) NOT NULL,
  `description` varchar(255) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `equipment`
--

INSERT INTO `equipment` (`id`, `name`, `category`, `description`) VALUES
(1, 'Dummy Equipment', 'GENERAL', 'Placeholder for orphaned inventory records'),
(2, 'Laptop', 'ICT', 'Office Laptop'),
(3, 'Desktop PC', 'ICT', 'Computer for office use'),
(4, 'Medical Bed', 'MEDICAL', 'Hospital Bed'),
(5, 'Projector', 'OFFICE', 'Conference room projector'),
(6, 'Radio Set', 'Communication', 'DRRM communication device'),
(7, 'Mechanical Ventilator', 'DRRM', 'Heart Monitoring');

-- --------------------------------------------------------

--
-- Table structure for table `inventory`
--

CREATE TABLE `inventory` (
  `id` int(11) NOT NULL,
  `article_name` varchar(255) NOT NULL,
  `description` text DEFAULT NULL,
  `property_no` varchar(120) DEFAULT NULL,
  `uom` varchar(50) DEFAULT NULL,
  `qty_property_card` decimal(12,2) DEFAULT 0.00,
  `qty_physical_count` decimal(12,2) DEFAULT 0.00,
  `location_id` int(11) DEFAULT NULL,
  `condition_text` varchar(100) DEFAULT NULL,
  `remarks` text DEFAULT NULL,
  `certified_correct` text DEFAULT NULL,
  `approved_by` int(11) DEFAULT NULL,
  `verified_by` int(11) DEFAULT NULL,
  `section_id` int(11) DEFAULT NULL,
  `date_added` timestamp NOT NULL DEFAULT current_timestamp(),
  `date_updated` timestamp NULL DEFAULT NULL,
  `fund_cluster` varchar(50) DEFAULT NULL,
  `unit_value` decimal(12,2) DEFAULT 0.00,
  `equipment_id` int(11) DEFAULT 1,
  `type_equipment` varchar(50) NOT NULL DEFAULT '',
  `category` varchar(50) NOT NULL DEFAULT '',
  `allocate_to` int(11) DEFAULT NULL,
  `barcode_data` varchar(255) DEFAULT NULL,
  `barcode_image` longblob DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `inventory`
--

INSERT INTO `inventory` (`id`, `article_name`, `description`, `property_no`, `uom`, `qty_property_card`, `qty_physical_count`, `location_id`, `condition_text`, `remarks`, `certified_correct`, `approved_by`, `verified_by`, `section_id`, `date_added`, `date_updated`, `fund_cluster`, `unit_value`, `equipment_id`, `type_equipment`, `category`, `allocate_to`, `barcode_data`, `barcode_image`) VALUES
(3, '', 'BOTTLE', '321', 'Unit', 3.00, 5.00, 1, 'Serviceable', 'fgfghfgn', '[1]', 2, 1, 1, '2025-11-18 04:20:51', '2025-11-20 04:50:50', 'IGF', 1000.00, 3, 'Property Plant Equipment (50K Above)', '', 1, '321', NULL),
(4, '', 'VM Backup to Synology NAS', '365', 'Per PC', 32.00, 115.00, 1, 'Serviceable', 'KASFGAK RAEMAK', '[1,2]', 2, 1, 1, '2025-11-18 04:24:32', '2026-02-06 06:02:43', 'TF', 5000.00, 2, 'Property Plant Equipment (50K Above)', 'ICT', 2, '365', NULL),
(5, '', 'VM Backup to Synology NAS', 'Sample2', 'Unit', 3.00, 10.00, 2, 'Serviceable', 'Remarks', '[2]', 1, 2, 1, '2025-11-18 05:17:01', '2025-11-20 04:19:52', 'TF', 3000.00, 6, 'Semi-expendable Equipment', '', 1, 'Sample2', NULL),
(8, '', 'Amoxicillin', '2602-8745', 'Lot', 2.00, 2.00, 2, 'Serviceable', 'remarks', '[2]', 2, 2, 2, '2025-11-19 23:58:24', '2026-02-06 06:43:54', 'HI', 12.00, 4, 'Property Plant Equipment (50K Above)', 'MEDICAL', 2, '2602-8745', NULL),
(14, '', 'Amoxicillin', '2602-2436', 'Lot', 1.00, 1.00, 2, 'Serviceable', '123413SDGSDG', '[1,2]', 1, 1, 2, '2025-11-20 02:27:39', '2026-02-06 06:03:19', 'RAF', 1.00, 7, 'Semi-expendable Equipment', 'DRRM', 1, '2602-2436', NULL),
(16, '', 'VM Backup to Synology NAS', '89768', 'Unit', 1.00, 2.00, 2, 'Serviceable', 'remarks sample', '[1,2]', 1, 1, 3, '2025-11-25 08:46:34', '2025-11-25 08:46:34', 'IGF', 1.00, 3, 'Property Plant Equipment (50K Above)', '', 2, '89768', NULL),
(17, '', 'Vancomycin', 'Sample765', 'Unit', 1.00, 1.00, 1, 'Serviceable', 'Sample Remarks lang', '[1,2]', 1, 2, 3, '2025-11-25 08:47:27', '2025-11-25 08:47:27', 'RAF', 1.00, 7, 'Property Plant Equipment (50K Above)', '', 2, 'Sample765', 0x646174613a696d6167652f706e673b6261736536342c6956424f5277304b47676f414141414e5355684555674141415177414141416541514d414141446a4b374c3041414141426c424d5645582f2f2f38414141425677744e2b4141414141585253546c4d41514f62595a67414141416c7753466c7a4141414f78414141447351426c53734f477741414144644a524546554f49316a2b4d7a446650344d6a2f45422b2f4e6e7a746766506d7a41382b4741766332664433384f6e7a2f4d38352f5a68762f4d42345a524a614e4b5270574d4e435541632f67796e35703049464141414141415355564f524b35435949493d),
(18, 'Face Mask', 'Heng De face mask', 'MEDI-HOP-1-982', 'Box', 1.00, 1.00, 1, 'Serviceable', 'dtgdrtgdrg', '[1]', 1, 1, 2, '2026-02-06 05:12:47', '2026-02-12 01:44:45', 'HI', 11.00, 4, 'Semi-expendable Equipment', 'MEDICAL', 1, 'MEDI-HOP-1-982', 0x646174613a696d6167652f706e673b6261736536342c6956424f5277304b47676f414141414e535568455567414141586f414141416541514d41414141596645637241414141426c424d5645582f2f2f38414141425677744e2b4141414141585253546c4d41514f62595a67414141416c7753466c7a4141414f78414141447351426c53734f4777414141454a4a524546554f4933747937454a41454549424d43445334567452544156746e586847684a4d4462364d543362794f574f586166436f61673851313532354f364152507233784273436a5a5450714b43676f4b436a38487a36304c43466c514d38694e4141414141424a52553545726b4a6767673d3d),
(20, 'Lappy', 'lappy1', 'DESK-HOP-1-954', 'Per PC', 1.00, 1.00, 1, 'Serviceable', 'wow', '[1]', 1, 1, 3, '2026-02-12 01:36:20', '2026-02-12 01:36:20', 'TR', 1.00, 3, 'Semi-expendable Equipment', 'ICT', 1, 'DESK-HOP-1-954', 0x646174613a696d6167652f706e673b6261736536342c6956424f5277304b47676f414141414e535568455567414141586f414141416541514d41414141596645637241414141426c424d5645582f2f2f38414141425677744e2b4141414141585253546c4d41514f62595a67414141416c7753466c7a4141414f78414141447351426c53734f477741414145644a524546554f4933747937454a7744414d4245434457734776496e41722b4e554e62674e657861425752624a466d752b7575564675365937495873634b68455577757a3836455856373772506870443258637730464251554668662f44432f332b516232417a564d534141414141456c46546b5375516d4343),
(37, 'tablet - Unit 1', 'tablet - Unit 1', 'DESK-HOP-1-585', 'Lot', 1.00, 1.00, 1, 'Serviceable', 'Batch generated', '[1]', 1, 1, 3, '2026-02-12 02:47:57', '2026-02-12 02:51:04', 'RAF', 0.00, 3, 'Semi-expendable Equipment', 'ICT', 1, 'DESK-HOP-1-585', 0x646174613a696d6167652f706e673b6261736536342c6956424f5277304b47676f414141414e535568455567414141586f414141416541514d41414141596645637241414141426c424d5645582f2f2f38414141425677744e2b4141414141585253546c4d41514f62595a67414141416c7753466c7a4141414f78414141447351426c53734f4777414141455a4a524546554f49316a2b4d7a44624d504477323967382b6641656562502f5062387a415947396a5a2f2f67435a505062384270382f665035772b444d2f6b47504d384f474476664542686c454e6f7870474e597871474e557738426f41435131496178796c7a316341414141415355564f524b35435949493d),
(43, 'tablet', 'tablet', 'DESK-HOP-1-539', 'Set', 1.00, 1.00, 1, 'Serviceable', 'tablet', '[1]', 1, 1, 3, '2026-02-12 03:32:08', '2026-02-12 03:32:08', 'TR', 1.00, 3, 'Semi-expendable Equipment', 'ICT', 1, 'DESK-HOP-1-539', 0x646174613a696d6167652f706e673b6261736536342c6956424f5277304b47676f414141414e535568455567414141586f414141416541514d41414141596645637241414141426c424d5645582f2f2f38414141425677744e2b4141414141585253546c4d41514f62595a67414141416c7753466c7a4141414f78414141447351426c53734f4777414141455a4a524546554f4933747937454a51444549426343417265417151746f48622f5741412f78564246754c5a4976663246317a71315367616f342b6e355452784a336f666c536156315a4730414a304a50645a45795a4d6d4444682f3341424171565063306b794e364541414141415355564f524b35435949493d),
(48, 'phones', 'phones', 'TRA-0003', 'Set', 1.00, 1.00, 2, 'Serviceable', 'sssss', NULL, NULL, NULL, NULL, '2026-02-12 03:42:10', '2026-02-12 03:42:10', 'IGF', 0.00, NULL, 'Semi-expendable Equipment', 'Batch Generated', NULL, 'TRA-0003', 0x646174613a696d6167652f706e673b6261736536342c6956424f5277304b47676f414141414e5355684555674141414f41414141416541514d41414141506534444c41414141426c424d5645582f2f2f38414141425677744e2b4141414141585253546c4d41514f62595a67414141416c7753466c7a4141414f78414141447351426c53734f477741414144424a524546554b4a466a2b4d7a446650374165575a37592b5944683838667472632f62324e6a63506a412b664f66442f77357a7a41714f536f354b6b6d354a414253454e457546746d53524141414141424a52553545726b4a6767673d3d),
(49, 'phones', 'phones', 'TRA-0004', 'Set', 1.00, 1.00, 2, 'Serviceable', 'sssss', NULL, NULL, NULL, NULL, '2026-02-12 03:42:10', '2026-02-12 03:42:10', 'IGF', 0.00, NULL, 'Semi-expendable Equipment', 'Batch Generated', NULL, 'TRA-0004', 0x646174613a696d6167652f706e673b6261736536342c6956424f5277304b47676f414141414e5355684555674141414f41414141416541514d41414141506534444c41414141426c424d5645582f2f2f38414141425677744e2b4141414141585253546c4d41514f62595a67414141416c7753466c7a4141414f78414141447351426c53734f477741414144424a524546554b4a466a2b4d7a446650374165575a37592b5944683838667472632f62324e6a634f44445958376d41332f4f4d34784b6a6b714f536c49754351433639614a73633663666b4141414141424a52553545726b4a6767673d3d);

-- --------------------------------------------------------

--
-- Table structure for table `sections`
--

CREATE TABLE `sections` (
  `id` int(11) NOT NULL,
  `department_id` int(11) DEFAULT NULL,
  `name` varchar(255) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `sections`
--

INSERT INTO `sections` (`id`, `department_id`, `name`) VALUES
(1, 1, 'Material Management'),
(2, 2, 'Surgery'),
(3, 1, 'IMISS');

-- --------------------------------------------------------

--
-- Table structure for table `users`
--

CREATE TABLE `users` (
  `id` int(11) NOT NULL,
  `firstname` varchar(100) DEFAULT NULL,
  `lastname` varchar(100) DEFAULT NULL,
  `username` varchar(80) DEFAULT NULL,
  `password` varchar(255) DEFAULT NULL,
  `avatar` varchar(255) DEFAULT NULL,
  `role` enum('admin','staff') DEFAULT 'staff',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `users`
--

INSERT INTO `users` (`id`, `firstname`, `lastname`, `username`, `password`, `avatar`, `role`, `created_at`) VALUES
(1, 'System', 'Admin', 'admin', '$2y$10$Q71fOmIqNorlGCZcteBv5uKMySuLxTvw9qevnNey2OIISNQoGyJqW', NULL, 'admin', '2025-11-18 00:13:11'),
(2, 'Alvin', 'Lopez', 'alvin', '$2y$10$CKNC2x9bRZvEPeEFrpUso.GFXVrn19fDrW/w7qOP56hmE7KiIKGFC', 'avatar_2.ico', 'admin', '2025-11-18 00:20:44'),
(3, 'Linette', 'Lagustan', 'linette', '$2y$10$lQZFZdWLOBrX/Xeqo4rOeOxbsCTLiJKMzA7xd4UpKKOJZvXbu7h2u', NULL, 'admin', '2025-11-20 07:46:43');

--
-- Indexes for dumped tables
--

--
-- Indexes for table `activity_log`
--
ALTER TABLE `activity_log`
  ADD PRIMARY KEY (`id`),
  ADD KEY `user_id` (`user_id`),
  ADD KEY `item_id` (`item_id`);

--
-- Indexes for table `buildings`
--
ALTER TABLE `buildings`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `departments`
--
ALTER TABLE `departments`
  ADD PRIMARY KEY (`id`),
  ADD KEY `building_id` (`building_id`);

--
-- Indexes for table `employees`
--
ALTER TABLE `employees`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `email` (`email`),
  ADD KEY `department_id` (`department_id`),
  ADD KEY `section_id` (`section_id`);

--
-- Indexes for table `equipment`
--
ALTER TABLE `equipment`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `inventory`
--
ALTER TABLE `inventory`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `property_no` (`property_no`),
  ADD KEY `section_id` (`section_id`),
  ADD KEY `approved_by` (`approved_by`),
  ADD KEY `verified_by` (`verified_by`),
  ADD KEY `equipment_id` (`equipment_id`),
  ADD KEY `location_id` (`location_id`),
  ADD KEY `idx_barcode_data` (`barcode_data`);

--
-- Indexes for table `sections`
--
ALTER TABLE `sections`
  ADD PRIMARY KEY (`id`),
  ADD KEY `department_id` (`department_id`);

--
-- Indexes for table `users`
--
ALTER TABLE `users`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `username` (`username`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `activity_log`
--
ALTER TABLE `activity_log`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT for table `buildings`
--
ALTER TABLE `buildings`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=7;

--
-- AUTO_INCREMENT for table `departments`
--
ALTER TABLE `departments`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT for table `employees`
--
ALTER TABLE `employees`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT for table `equipment`
--
ALTER TABLE `equipment`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=9;

--
-- AUTO_INCREMENT for table `inventory`
--
ALTER TABLE `inventory`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=50;

--
-- AUTO_INCREMENT for table `sections`
--
ALTER TABLE `sections`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT for table `users`
--
ALTER TABLE `users`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=9;

--
-- Constraints for dumped tables
--

--
-- Constraints for table `departments`
--
ALTER TABLE `departments`
  ADD CONSTRAINT `departments_ibfk_1` FOREIGN KEY (`building_id`) REFERENCES `buildings` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `employees`
--
ALTER TABLE `employees`
  ADD CONSTRAINT `employees_ibfk_1` FOREIGN KEY (`department_id`) REFERENCES `departments` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `employees_ibfk_2` FOREIGN KEY (`section_id`) REFERENCES `sections` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `inventory`
--
ALTER TABLE `inventory`
  ADD CONSTRAINT `fk_inventory_equipment` FOREIGN KEY (`equipment_id`) REFERENCES `equipment` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `inventory_ibfk_1` FOREIGN KEY (`section_id`) REFERENCES `sections` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `inventory_ibfk_2` FOREIGN KEY (`approved_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `inventory_ibfk_3` FOREIGN KEY (`verified_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `inventory_ibfk_4` FOREIGN KEY (`equipment_id`) REFERENCES `equipment` (`id`),
  ADD CONSTRAINT `inventory_ibfk_5` FOREIGN KEY (`location_id`) REFERENCES `departments` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `sections`
--
ALTER TABLE `sections`
  ADD CONSTRAINT `sections_ibfk_1` FOREIGN KEY (`department_id`) REFERENCES `departments` (`id`) ON DELETE SET NULL;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
