-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Хост: 127.0.0.1
-- Час створення: Лис 24 2026 р., 20:27
-- Версія сервера: 10.4.32-MariaDB
-- Версія PHP: 8.2.12

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- База даних: `cyberfix_db`
--

-- --------------------------------------------------------

--
-- Структура таблиці `admins`
--

CREATE TABLE `admins` (
  `admin_id` int(11) NOT NULL,
  `employee_id` int(11) NOT NULL,
  `username` varchar(50) NOT NULL,
  `password` varchar(255) NOT NULL,
  `role` enum('Operator','Engineer','Superadmin','Accountant') NOT NULL,
  `created_at` datetime DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Дамп даних таблиці `admins`
--

INSERT INTO `admins` (`admin_id`, `employee_id`, `username`, `password`, `role`, `created_at`) VALUES
(1, 8, 'alex.admin', '$2y$10$KMNBhPOaCFe4BjXqR1JJ7OT2YxVjYZhNyFEpcU2Snu.lMVOGXY/Ea', 'Superadmin', '2026-11-11 11:17:34'),
(2, 6, 'olena.op', '$2y$10$/Sv4Bkc6ql9tbnKDIIZcE.HxxOBdgs5ixgJ1su8/PLRJ/8JwgGna2', 'Operator', '2026-11-14 10:57:50'),
(3, 9, 'tarasM.eng', '$2y$10$rlHPMcQMcGG55ZDRo2K.hO9LAolERfobesufxtksGcevkHCB.HqtS', 'Engineer', '2026-11-14 10:57:50'),
(4, 2, 'Mark.eng', '$2y$10$1mhniPUZgSgK22bf5AFC0uk5Z4QDV/qWUDOAIOVGV2nIuiZTFK69a', 'Engineer', '2026-11-14 15:51:45'),
(6, 11, 'sofiia.eng', '$2y$10$ak9/7x7HHHDrcIAT6Vd47uT3ZLFetqJ2AL9EUXNPmPuKQvx.VFYgK', 'Engineer', '2026-11-14 15:58:19'),
(7, 13, 'iryna.op', '$2y$10$HMMiRGhsCVtnTYnxFn1kQ..bx.HDMcj1G.1FFrIxQi0WUPY9vPMPu', 'Operator', '2026-11-14 15:58:19'),
(8, 15, 'Natali.acc', '$2y$10$Y3BdjTpuXOY3OEQUmDsMtuzaRwtboxj9/sbYE09pylPbmumAvMHkK', 'Accountant', '2026-11-24 19:48:53');

-- --------------------------------------------------------

--
-- Структура таблиці `applied_services`
--

CREATE TABLE `applied_services` (
  `applied_service_id` int(11) NOT NULL,
  `device_id` int(11) NOT NULL,
  `issue_id` int(11) NOT NULL,
  `service_id` int(11) NOT NULL,
  `start_date` datetime DEFAULT current_timestamp(),
  `result_status` enum('Successful','Failed_Needs_Another_Service','In_Testing') NOT NULL DEFAULT 'In_Testing',
  `completion_date` datetime DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Дамп даних таблиці `applied_services`
--

INSERT INTO `applied_services` (`applied_service_id`, `device_id`, `issue_id`, `service_id`, `start_date`, `result_status`, `completion_date`) VALUES
(1, 1, 1, 1, '2026-11-10 10:59:49', 'In_Testing', NULL),
(2, 2, 2, 2, '2026-11-10 10:59:49', 'In_Testing', NULL),
(3, 3, 3, 3, '2026-11-10 10:59:49', 'In_Testing', NULL),
(4, 4, 4, 6, '2026-11-10 10:59:49', 'Successful', NULL),
(5, 5, 5, 4, '2026-11-10 10:59:49', 'Successful', NULL),
(7, 1, 8, 2, '2026-11-14 12:55:25', 'In_Testing', NULL),
(8, 14, 9, 10, '2026-10-04 13:00:00', 'Successful', '2026-10-04 15:00:00'),
(9, 15, 10, 14, '2026-10-07 09:00:00', 'Successful', '2026-10-08 17:30:00'),
(10, 17, 11, 13, '2026-10-20 10:00:00', 'Successful', '2026-10-22 16:00:00'),
(11, 18, 12, 15, '2026-11-03 11:00:00', 'Successful', '2026-11-03 14:00:00'),
(12, 20, 13, 6, '2026-11-11 14:00:00', 'In_Testing', NULL),
(13, 22, 14, 14, '2026-08-05 09:00:00', 'Successful', '2026-08-07 16:00:00'),
(14, 22, 14, 13, '2026-08-07 10:00:00', 'Successful', '2026-08-07 16:00:00'),
(15, 23, 15, 11, '2026-08-21 11:00:00', 'Successful', '2026-08-21 15:30:00'),
(16, 24, 16, 13, '2026-09-12 10:00:00', 'Successful', '2026-09-14 15:00:00'),
(17, 24, 16, 10, '2026-09-14 11:00:00', 'Successful', '2026-09-14 15:00:00'),
(18, 28, 17, 10, '2026-09-16 11:00:00', 'Successful', '2026-09-16 13:30:00'),
(19, 25, 18, 15, '2026-09-26 12:00:00', 'Successful', '2026-09-27 17:30:00'),
(20, 25, 18, 10, '2026-09-27 14:00:00', 'Successful', '2026-09-27 17:30:00'),
(21, 26, 19, 12, '2026-11-20 12:00:00', 'Successful', '2026-11-20 16:00:00'),
(22, 27, 20, 13, '2026-11-22 10:00:00', 'In_Testing', NULL);

-- --------------------------------------------------------

--
-- Структура таблиці `clients`
--

CREATE TABLE `clients` (
  `client_id` int(11) NOT NULL,
  `client_type` enum('Individual','Corporate') NOT NULL DEFAULT 'Individual',
  `name` varchar(255) NOT NULL,
  `phone_number` varchar(30) NOT NULL,
  `email` varchar(100) NOT NULL,
  `registration_date` datetime DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Дамп даних таблиці `clients`
--

INSERT INTO `clients` (`client_id`, `client_type`, `name`, `phone_number`, `email`, `registration_date`) VALUES
(1, 'Individual', 'Alex Morgan', '+380971112233', 'alex.morgan@gmail.com', '2026-11-10 10:58:16'),
(2, 'Corporate', 'CyberSoft LLC', '+380322223344', 'contact@cybersoft.com', '2026-11-10 10:58:16'),
(3, 'Individual', 'Emily Stone', '+380633334455', 'emily.stone@outlook.com', '2026-11-10 10:58:16'),
(4, 'Corporate', 'IT Academy Lviv', '+380504445566', 'info@it-lviv.com', '2026-11-10 10:58:16'),
(5, 'Individual', 'Michael Brown', '+380985556677', 'misha.brown@gmail.com', '2026-11-10 10:58:16'),
(10, 'Individual', 'Sara Maile', '+ 380 98 6543 432', 'Sara@gmail.com', '2026-11-11 12:21:52'),
(11, 'Individual', 'Mia Salma', '+380967632976', 'vvkte98n.20@gmail.com', '2026-11-14 15:20:22'),
(12, 'Individual', 'Olena Kovalenko', '+380972345601', 'olena.kovalenko@gmail.com', '2026-10-02 10:15:00'),
(13, 'Corporate', 'SoftServe Lviv Hub LLC', '+380322409002', 'it-support@softserve-demo.ua', '2026-10-05 14:20:00'),
(14, 'Individual', 'Dmytro Shevchenko', '+380632345603', 'd.shevchenko@ukr.net', '2026-10-11 09:40:00'),
(15, 'Corporate', 'PixelCraft Design Studio', '+380502345604', 'office@pixelcraft.io', '2026-10-18 16:05:00'),
(16, 'Individual', 'Kateryna Boyko', '+380982345605', 'katya.boyko@gmail.com', '2026-10-25 11:30:00'),
(17, 'Corporate', 'NovaTech Logistics Group', '+380672345606', 'sysadmin@novatech.com.ua', '2026-11-01 13:10:00'),
(18, 'Individual', 'Bohdan Lysenko', '+380932345607', 'bohdan.lysenko@icloud.com', '2026-11-06 17:45:00'),
(19, 'Individual', 'Marta Kravchuk', '+380662345608', 'marta.kravchuk@gmail.com', '2026-11-10 12:00:00'),
(20, 'Corporate', 'Genesis Tech Lviv LLC', '+380322901101', 'admin@genesistech.ua', '2026-08-03 10:00:00'),
(21, 'Individual', 'Andriy Savchuk', '+380673001102', 'andriy.savchuk@gmail.com', '2026-08-19 14:30:00'),
(22, 'Corporate', 'MedCenter West Clinic', '+380503001103', 'it@medcenter-west.ua', '2026-09-10 09:15:00'),
(23, 'Individual', 'Viktoriia Moroz', '+380933001104', 'v.moroz@ukr.net', '2026-09-24 16:40:00'),
(24, 'Individual', 'Roman Petrenko', '+380973001105', 'roman.petrenko@gmail.com', '2026-11-19 11:20:00'),
(25, 'Corporate', 'Aether Game Studio', '+380633001106', 'ops@aethergames.io', '2026-11-21 15:50:00');

-- --------------------------------------------------------

--
-- Структура таблиці `devices`
--

CREATE TABLE `devices` (
  `device_id` int(11) NOT NULL,
  `client_id` int(11) NOT NULL,
  `type_id` int(11) NOT NULL,
  `brand` varchar(50) NOT NULL,
  `model` varchar(100) NOT NULL,
  `serial_number` varchar(100) NOT NULL,
  `first_service_date` datetime DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Дамп даних таблиці `devices`
--

INSERT INTO `devices` (`device_id`, `client_id`, `type_id`, `brand`, `model`, `serial_number`, `first_service_date`) VALUES
(1, 1, 1, 'ASUS', 'ROG Strix G15', 'SN-ASUS-2023-001', '2026-11-10 10:59:34'),
(2, 2, 2, 'DELL', 'OptiPlex 7080', 'SN-DELL-2022-882', '2026-11-10 10:59:34'),
(3, 3, 1, 'Apple', 'MacBook Pro 16', 'SN-AAPL-2021-993', '2026-11-10 10:59:34'),
(4, 4, 3, 'Custom PC', 'Intel i9 / RTX 4080', 'SN-CUST-2024-004', '2026-11-10 10:59:34'),
(5, 5, 4, 'HP', 'ProLiant DL380', 'SN-HPE-2020-552', '2026-11-10 10:59:34'),
(11, 2, 1, 'HP', 'ProLiant DL380', 'РНК-65-ГО-981', '2026-11-11 12:22:47'),
(13, 11, 1, 'HP', 'ProLiant DL380', 'РНК-65-ГО-989', '2026-11-14 15:20:22'),
(14, 12, 1, 'ASUS', 'ROG Zephyrus G14', 'SN-ASUS-9011', '2026-10-02 10:20:00'),
(15, 13, 4, 'Dell', 'PowerEdge R740', 'SN-DELL-7402', '2026-10-05 14:30:00'),
(16, 14, 2, 'MSI', 'Infinite RS 13th', 'SN-MSI-3303', '2026-10-11 09:50:00'),
(17, 15, 3, 'Apple', 'Mac Studio M2 Ultra', 'SN-APPL-4404', '2026-10-18 16:15:00'),
(18, 16, 1, 'Lenovo', 'ThinkPad X1 Carbon', 'SN-LNV-5505', '2026-10-25 11:40:00'),
(19, 17, 4, 'HP', 'ProLiant DL360 Gen10', 'SN-HP-6606', '2026-11-01 13:20:00'),
(20, 18, 1, 'Acer', 'Predator Helios 300', 'SN-ACER-7707', '2026-11-06 17:50:00'),
(21, 19, 2, 'Custom', 'Ryzen 9 Render Rig', 'SN-CUST-8808', '2026-11-10 12:10:00'),
(22, 20, 4, 'Dell', 'PowerEdge R750xs', 'SN-DELL-7501', '2026-08-03 10:15:00'),
(23, 21, 1, 'Lenovo', 'Legion Pro 7i', 'SN-LNV-7701', '2026-08-19 14:40:00'),
(24, 22, 3, 'HP', 'Z4 G5 Workstation', 'SN-HP-4501', '2026-09-10 09:30:00'),
(25, 23, 1, 'Apple', 'MacBook Air M3 15', 'SN-APPL-3301', '2026-09-24 16:50:00'),
(26, 24, 2, 'ASUS', 'ROG Strix G35DX', 'SN-ASUS-3501', '2026-11-19 11:30:00'),
(27, 25, 3, 'Custom', 'Threadripper Pro 5975WX', 'SN-CUST-5975', '2026-11-21 16:00:00'),
(28, 1, 1, 'Apple', 'MacBook Pro 14 M2', 'SN-APPL-1402', '2026-09-15 12:00:00');

-- --------------------------------------------------------

--
-- Структура таблиці `device_issues`
--

CREATE TABLE `device_issues` (
  `issue_id` int(11) NOT NULL,
  `device_id` int(11) NOT NULL,
  `issue_type_id` int(11) NOT NULL,
  `registration_date` datetime DEFAULT current_timestamp(),
  `issue_description` text NOT NULL,
  `employee_id` int(11) DEFAULT NULL,
  `status` enum('Diagnosing','In_Progress','Resolved','Unresolvable') NOT NULL DEFAULT 'Diagnosing',
  `resolution_date` datetime DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Дамп даних таблиці `device_issues`
--

INSERT INTO `device_issues` (`issue_id`, `device_id`, `issue_type_id`, `registration_date`, `issue_description`, `employee_id`, `status`, `resolution_date`) VALUES
(1, 1, 1, '2026-11-10 10:59:42', 'Laptop temperature reaches 95C during gaming sessions', 1, 'In_Progress', NULL),
(2, 2, 2, '2026-11-10 10:59:42', 'Desktop shuts down unexpectedly after 5 minutes of work', 2, 'Diagnosing', NULL),
(3, 3, 3, '2026-11-10 10:59:42', 'System freezes at boot screen with critical error', 3, 'Diagnosing', NULL),
(4, 4, 1, '2026-11-10 10:59:42', 'Cooling fan noise and high CPU throttling under load', 1, 'Resolved', '2026-11-14 18:00:00'),
(5, 5, 5, '2026-11-10 10:59:42', 'RAID storage volume is corrupted and unavailable', 4, 'Resolved', '2026-09-07 17:00:00'),
(8, 1, 2, '2026-11-14 12:55:09', 'Intermittent USB-C charging port disconnect and battery drain', 5, 'In_Progress', NULL),
(9, 14, 1, '2026-10-03 12:00:00', 'CPU reaching 98C under load due to dried factory paste', 1, 'Resolved', '2026-10-04 15:00:00'),
(10, 15, 3, '2026-10-06 11:30:00', 'Degraded RAID-5 volume after controller battery failure', 11, 'Resolved', '2026-10-08 17:30:00'),
(11, 17, 2, '2026-10-19 14:00:00', 'Short circuit on main 12V power rail', 10, 'Resolved', '2026-10-22 16:00:00'),
(12, 18, 4, '2026-11-02 10:15:00', 'Cracked 14-inch matrix screen after accidental drop', 11, 'Resolved', '2026-11-03 14:00:00'),
(13, 20, 1, '2026-11-11 12:00:00', 'GPU thermal throttling in games, clogged heatsink fins', 10, 'In_Progress', NULL),
(14, 22, 3, '2026-08-04 10:30:00', 'Critical RAID-10 array desync and corrupted database blocks', 12, 'Resolved', '2026-08-07 16:00:00'),
(15, 23, 1, '2026-08-20 12:00:00', 'Pump-out effect on factory thermal paste, CPU hitting 100C', 5, 'Resolved', '2026-08-21 15:30:00'),
(16, 24, 2, '2026-09-11 11:20:00', 'Burned VRM phase MOSFETs on workstation motherboard', 4, 'Resolved', '2026-09-14 15:00:00'),
(17, 28, 1, '2026-09-15 13:00:00', 'Dust buildup in cooling fans causing acoustic noise', 9, 'Resolved', '2026-09-16 13:30:00'),
(18, 25, 4, '2026-09-25 14:30:00', 'Damaged Liquid Retina display flex cable and cracked glass', 11, 'Resolved', '2026-09-27 17:30:00'),
(19, 26, 3, '2026-11-19 13:15:00', 'Corrupted Windows bootloader and driver IRQ conflicts', 3, 'Resolved', '2026-11-20 16:00:00'),
(20, 27, 2, '2026-11-21 17:15:00', 'Unstable 12VHPWR rail under full GPU rendering load', 5, 'In_Progress', NULL);

-- --------------------------------------------------------

--
-- Структура таблиці `device_types`
--

CREATE TABLE `device_types` (
  `type_id` int(11) NOT NULL,
  `device_kind` enum('Laptop','Desktop PC','Workstation','Server') NOT NULL,
  `service_category` enum('Hardware Repair','Preventive Care','Software Tuning','Data Recovery') NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Дамп даних таблиці `device_types`
--

INSERT INTO `device_types` (`type_id`, `device_kind`, `service_category`) VALUES
(1, 'Laptop', 'Preventive Care'),
(2, 'Desktop PC', 'Hardware Repair'),
(3, 'Workstation', 'Software Tuning'),
(4, 'Server', 'Data Recovery'),
(5, 'Laptop', 'Hardware Repair'),
(6, 'Desktop PC', 'Software Tuning'),
(7, 'Workstation', 'Hardware Repair');

-- --------------------------------------------------------

--
-- Структура таблиці `employees`
--

CREATE TABLE `employees` (
  `employee_id` int(11) NOT NULL,
  `first_name` varchar(50) NOT NULL,
  `last_name` varchar(50) NOT NULL,
  `middle_name` varchar(50) DEFAULT NULL,
  `staff_type` enum('Engineer','Operator','Admin','Support','Accountant') NOT NULL,
  `position` varchar(100) NOT NULL,
  `phone_number` varchar(30) NOT NULL,
  `work_status` enum('Active','On_Leave','Sick_Leave') NOT NULL DEFAULT 'Active',
  `salary` decimal(10,2) NOT NULL DEFAULT 0.00,
  `birth_date` date NOT NULL,
  `hire_date` date NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Дамп даних таблиці `employees`
--

INSERT INTO `employees` (`employee_id`, `first_name`, `last_name`, `middle_name`, `staff_type`, `position`, `phone_number`, `work_status`, `salary`, `birth_date`, `hire_date`) VALUES
(1, 'John', 'Smith', 'Robert', 'Engineer', 'Senior Hardware Technician', '+380970001111', 'Active', 2150.00, '1990-05-14', '2020-03-01'),
(2, 'Mark', 'Vance', 'David', 'Engineer', 'Systems Engineer', '+380970002222', 'Active', 1850.00, '1993-08-22', '2021-06-15'),
(3, 'Sarah', 'Connor', 'Jane', 'Engineer', 'Junior Service Technician', '+380970003333', 'Active', 1450.00, '1998-11-03', '2022-09-10'),
(4, 'David', 'Miller', 'James', 'Engineer', 'Data Recovery Specialist', '+380970004444', 'Active', 2050.00, '1991-02-18', '2019-11-01'),
(5, 'Andrew', 'Wilson', 'Paul', 'Engineer', 'Lead Hardware Specialist', '+380970005555', 'Active', 2450.00, '1988-12-30', '2018-01-15'),
(6, 'Olena', 'Kovalenko', 'Petrivna', 'Operator', 'Service Desk Operator', '+380971112233', 'Active', 1250.00, '1998-05-14', '2024-02-01'),
(7, 'Iryna', 'Melnyk', 'Ivanivna', 'Support', 'Office & Lab Cleaner', '+380974445566', 'Active', 900.00, '1985-09-22', '2023-11-10'),
(8, 'Alex', 'Administrator', 'Olegovych', 'Admin', 'System Administrator', '+380970001122', 'Active', 2250.00, '1995-03-10', '2023-01-15'),
(9, 'Taras', 'Melnyk', 'Stepanovych', 'Engineer', 'Hardware Diagnostics Engineer', '+380973334455', 'Active', 1650.00, '1997-08-21', '2024-04-10'),
(10, 'Taras', 'Koval', 'Olehovych', 'Engineer', 'Senior Hardware Technician', '+380671112201', 'Active', 1950.00, '1994-05-12', '2022-03-15'),
(11, 'Sofiia', 'Melnyk', 'Andriivna', 'Engineer', 'Data Recovery & Board Specialist', '+380671112202', 'Active', 1750.00, '1997-09-21', '2023-06-01'),
(12, 'Maksym', 'Savchenko', 'Ihorovych', 'Engineer', 'Server & Workstation Engineer', '+380671112203', 'On_Leave', 1900.00, '1991-11-03', '2021-01-20'),
(13, 'Iryna', 'Danylko', 'Vasylivna', 'Operator', 'Service Desk Specialist', '+380671112204', 'Active', 1150.00, '1999-02-18', '2024-02-10'),
(14, 'Mykola', 'Hrytsenko', 'Petrovych', 'Support', 'Lab Logistics & Parts Keeper', '+380671112205', 'Active', 1050.00, '1988-07-30', '2020-09-05'),
(15, 'Nataliia', 'Kravets', 'Mykolaivna', 'Accountant', 'Chief Accountant', '+380979998877', 'Active', 1600.00, '1992-04-19', '2023-05-10');

-- --------------------------------------------------------

--
-- Структура таблиці `issue_types`
--

CREATE TABLE `issue_types` (
  `issue_type_id` int(11) NOT NULL,
  `type_name` varchar(100) NOT NULL,
  `description` text DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Дамп даних таблиці `issue_types`
--

INSERT INTO `issue_types` (`issue_type_id`, `type_name`, `description`) VALUES
(1, 'Overheating', 'High temperature of CPU or GPU under high workload'),
(2, 'Power Failure', 'Device does not power on, no LED light indication'),
(3, 'Software Crash', 'System freezes, blue screen errors (BSOD), system bugs'),
(4, 'Physical Damage', 'Broken screen, damaged ports, or cracked body case'),
(5, 'Data Loss', 'Corrupted drive partitions, deleted files, or RAID array error');

-- --------------------------------------------------------

--
-- Структура таблиці `orders`
--

CREATE TABLE `orders` (
  `order_id` int(11) NOT NULL,
  `client_id` int(11) NOT NULL,
  `device_id` int(11) NOT NULL,
  `initial_service_id` int(11) DEFAULT NULL,
  `lead_employee_id` int(11) DEFAULT NULL,
  `order_date` datetime DEFAULT current_timestamp(),
  `order_status` enum('Accepted','Diagnostics','In_Progress','Ready','Completed') NOT NULL DEFAULT 'Accepted',
  `estimated_hours` decimal(5,2) DEFAULT 0.00,
  `completion_date` datetime DEFAULT NULL,
  `total_cost` decimal(10,2) DEFAULT 0.00,
  `payment_status` enum('Unpaid','Paid') NOT NULL DEFAULT 'Unpaid',
  `payment_method` enum('Cash','Card','Bank_Transfer') DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Дамп даних таблиці `orders`
--

INSERT INTO `orders` (`order_id`, `client_id`, `device_id`, `initial_service_id`, `lead_employee_id`, `order_date`, `order_status`, `estimated_hours`, `completion_date`, `total_cost`, `payment_status`, `payment_method`) VALUES
(1, 1, 1, 1, 1, '2026-08-14 09:25:00', 'Completed', 5.00, '2026-08-16 14:10:00', 120.00, 'Paid', 'Cash'),
(2, 2, 2, 2, 2, '2026-11-18 11:40:00', 'In_Progress', 2.00, NULL, 50.00, 'Unpaid', NULL),
(3, 3, 3, 3, 3, '2026-11-22 15:10:00', 'Accepted', 1.00, NULL, 30.00, 'Unpaid', NULL),
(4, 4, 4, 6, 1, '2026-11-15 10:05:00', 'Ready', 2.00, NULL, 60.00, 'Paid', 'Card'),
(5, 5, 5, 4, 4, '2026-09-05 13:20:00', 'Completed', 5.00, '2026-09-07 17:45:00', 120.00, 'Paid', 'Bank_Transfer'),
(9, 11, 13, NULL, 5, '2026-11-14 15:20:22', 'In_Progress', 1.00, NULL, 45.00, 'Unpaid', NULL),
(10, 12, 14, 10, 1, '2026-10-03 11:00:00', 'Completed', 1.50, '2026-10-04 15:30:00', 45.00, 'Paid', 'Card'),
(11, 13, 15, 14, 11, '2026-10-06 10:00:00', 'Completed', 5.00, '2026-10-08 18:00:00', 220.00, 'Paid', 'Card'),
(12, 14, 16, 12, 10, '2026-10-12 14:15:00', 'Completed', 2.00, '2026-10-13 17:00:00', 60.00, 'Paid', 'Card'),
(13, 15, 17, 13, 10, '2026-10-19 12:30:00', 'Completed', 4.00, '2026-10-22 16:45:00', 140.00, 'Paid', 'Card'),
(14, 16, 18, 15, 11, '2026-11-02 09:30:00', 'Ready', 1.50, NULL, 110.00, 'Unpaid', NULL),
(15, 17, 19, 14, 11, '2026-11-07 15:00:00', 'In_Progress', 5.00, NULL, 220.00, 'Unpaid', NULL),
(16, 18, 20, 6, 10, '2026-11-11 11:20:00', 'In_Progress', 2.00, NULL, 75.00, 'Unpaid', NULL),
(17, 19, 21, NULL, 1, '2026-11-23 16:10:00', 'Diagnostics', 1.00, NULL, 35.00, 'Unpaid', NULL),
(18, 20, 22, 14, 12, '2026-08-04 09:30:00', 'Completed', 6.50, '2026-08-07 17:00:00', 360.00, 'Paid', 'Bank_Transfer'),
(19, 21, 23, 11, 5, '2026-08-20 11:15:00', 'Completed', 2.00, '2026-08-21 16:20:00', 75.00, 'Paid', 'Card'),
(20, 22, 24, 13, 4, '2026-09-11 10:00:00', 'Completed', 4.50, '2026-09-14 15:40:00', 185.00, 'Paid', 'Bank_Transfer'),
(21, 1, 28, 10, 9, '2026-09-15 12:30:00', 'Completed', 1.50, '2026-09-16 14:00:00', 45.00, 'Paid', 'Cash'),
(22, 23, 25, 15, 11, '2026-09-25 13:45:00', 'Completed', 2.50, '2026-09-27 18:10:00', 155.00, 'Paid', 'Card'),
(23, 24, 26, 12, 3, '2026-11-19 12:00:00', 'Ready', 2.00, NULL, 60.00, 'Paid', 'Cash'),
(24, 25, 27, 13, 5, '2026-11-21 16:30:00', 'In_Progress', 4.00, NULL, 140.00, 'Unpaid', NULL),
(25, 20, 22, 10, 2, '2026-11-24 10:15:00', 'Accepted', 1.50, NULL, 45.00, 'Unpaid', NULL);

-- --------------------------------------------------------

--
-- Структура таблиці `services`
--

CREATE TABLE `services` (
  `service_id` int(11) NOT NULL,
  `type_id` int(11) NOT NULL,
  `service_name` varchar(150) NOT NULL,
  `description` text DEFAULT NULL,
  `base_price` decimal(10,2) NOT NULL,
  `estimated_hours` decimal(4,2) NOT NULL,
  `is_visible_on_website` tinyint(1) DEFAULT 1
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Дамп даних таблиці `services`
--

INSERT INTO `services` (`service_id`, `type_id`, `service_name`, `description`, `base_price`, `estimated_hours`, `is_visible_on_website`) VALUES
(1, 1, 'System Cleaning & Thermal Care', 'Full dust cleaning and thermal paste replacement', 35.00, 1.50, 1),
(2, 2, 'Power Supply Unit Replacement', 'Diagnostic and replacement of faulty PSU', 50.00, 2.00, 1),
(3, 3, 'OS Optimization & Drivers Setup', 'Operating system tuning, virus cleanup, and driver update', 30.00, 1.00, 1),
(4, 4, 'RAID & SSD Data Recovery', 'Professional file recovery from damaged storage drives', 120.00, 5.00, 1),
(5, 1, 'Laptop Screen Replacement', 'Replacement of broken display panel with original screen', 85.00, 2.50, 1),
(6, 2, 'Liquid Metal Repasting', 'High-end thermal liquid metal service for gaming laptops', 60.00, 2.00, 1),
(7, 3, 'RAM & Storage Upgrade', 'Installation of new high-speed SSD drive or RAM modules', 25.00, 1.00, 1),
(8, 5, 'Internal Express Diagnostic', 'Quick internal diagnostic routine for special corporate promos', 0.00, 0.50, 0),
(10, 1, 'Deep Ultrasonic Dust Cleaning & Thermal Paste', 'Full disassembly, radiator cleaning and Arctic MX-6 thermal paste replacement', 45.00, 1.50, 1),
(11, 1, 'Liquid Metal Cooling Upgrade', 'Application of Thermal Grizzly Conductonaut with protective dielectric coating', 75.00, 2.00, 1),
(12, 2, 'Custom Gaming PC Cable Management & Tuning', 'Airflow optimization, BIOS fan curve setup and Windows OS debloating', 60.00, 2.00, 1),
(13, 3, 'Motherboard BGA & Power Rail Microsoldering', 'Component-level diagnostics and replacement of burnt MOSFETs or controllers', 140.00, 4.00, 0),
(14, 4, 'RAID Array Reconstruction & Data Recovery', 'Sector-by-sector recovery from degraded RAID-5/RAID-10 storage arrays', 220.00, 5.00, 1),
(15, 1, 'IPS/OLED Matrix Display Replacement', 'Installation of original display panel with color calibration test', 110.00, 1.50, 0);

--
-- Індекси збережених таблиць
--

--
-- Індекси таблиці `admins`
--
ALTER TABLE `admins`
  ADD PRIMARY KEY (`admin_id`),
  ADD UNIQUE KEY `username` (`username`),
  ADD UNIQUE KEY `uq_admin_employee` (`employee_id`);

--
-- Індекси таблиці `applied_services`
--
ALTER TABLE `applied_services`
  ADD PRIMARY KEY (`applied_service_id`),
  ADD KEY `device_id` (`device_id`),
  ADD KEY `issue_id` (`issue_id`),
  ADD KEY `service_id` (`service_id`);

--
-- Індекси таблиці `clients`
--
ALTER TABLE `clients`
  ADD PRIMARY KEY (`client_id`);

--
-- Індекси таблиці `devices`
--
ALTER TABLE `devices`
  ADD PRIMARY KEY (`device_id`),
  ADD UNIQUE KEY `serial_number` (`serial_number`),
  ADD KEY `client_id` (`client_id`),
  ADD KEY `type_id` (`type_id`);

--
-- Індекси таблиці `device_issues`
--
ALTER TABLE `device_issues`
  ADD PRIMARY KEY (`issue_id`),
  ADD KEY `device_id` (`device_id`),
  ADD KEY `issue_type_id` (`issue_type_id`),
  ADD KEY `engineer_id` (`employee_id`);

--
-- Індекси таблиці `device_types`
--
ALTER TABLE `device_types`
  ADD PRIMARY KEY (`type_id`);

--
-- Індекси таблиці `employees`
--
ALTER TABLE `employees`
  ADD PRIMARY KEY (`employee_id`);

--
-- Індекси таблиці `issue_types`
--
ALTER TABLE `issue_types`
  ADD PRIMARY KEY (`issue_type_id`);

--
-- Індекси таблиці `orders`
--
ALTER TABLE `orders`
  ADD PRIMARY KEY (`order_id`),
  ADD KEY `client_id` (`client_id`),
  ADD KEY `device_id` (`device_id`),
  ADD KEY `initial_service_id` (`initial_service_id`),
  ADD KEY `lead_engineer_id` (`lead_employee_id`);

--
-- Індекси таблиці `services`
--
ALTER TABLE `services`
  ADD PRIMARY KEY (`service_id`),
  ADD KEY `type_id` (`type_id`);

--
-- AUTO_INCREMENT для збережених таблиць
--

--
-- AUTO_INCREMENT для таблиці `admins`
--
ALTER TABLE `admins`
  MODIFY `admin_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=9;

--
-- AUTO_INCREMENT для таблиці `applied_services`
--
ALTER TABLE `applied_services`
  MODIFY `applied_service_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=23;

--
-- AUTO_INCREMENT для таблиці `clients`
--
ALTER TABLE `clients`
  MODIFY `client_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=26;

--
-- AUTO_INCREMENT для таблиці `devices`
--
ALTER TABLE `devices`
  MODIFY `device_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=29;

--
-- AUTO_INCREMENT для таблиці `device_issues`
--
ALTER TABLE `device_issues`
  MODIFY `issue_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=22;

--
-- AUTO_INCREMENT для таблиці `device_types`
--
ALTER TABLE `device_types`
  MODIFY `type_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=8;

--
-- AUTO_INCREMENT для таблиці `employees`
--
ALTER TABLE `employees`
  MODIFY `employee_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=16;

--
-- AUTO_INCREMENT для таблиці `issue_types`
--
ALTER TABLE `issue_types`
  MODIFY `issue_type_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;

--
-- AUTO_INCREMENT для таблиці `orders`
--
ALTER TABLE `orders`
  MODIFY `order_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=26;

--
-- AUTO_INCREMENT для таблиці `services`
--
ALTER TABLE `services`
  MODIFY `service_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=16;

--
-- Обмеження зовнішнього ключа збережених таблиць
--

--
-- Обмеження зовнішнього ключа таблиці `admins`
--
ALTER TABLE `admins`
  ADD CONSTRAINT `fk_admin_employee` FOREIGN KEY (`employee_id`) REFERENCES `employees` (`employee_id`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Обмеження зовнішнього ключа таблиці `applied_services`
--
ALTER TABLE `applied_services`
  ADD CONSTRAINT `applied_services_ibfk_1` FOREIGN KEY (`device_id`) REFERENCES `devices` (`device_id`) ON DELETE CASCADE,
  ADD CONSTRAINT `applied_services_ibfk_2` FOREIGN KEY (`issue_id`) REFERENCES `device_issues` (`issue_id`) ON DELETE CASCADE,
  ADD CONSTRAINT `applied_services_ibfk_3` FOREIGN KEY (`service_id`) REFERENCES `services` (`service_id`) ON DELETE CASCADE;

--
-- Обмеження зовнішнього ключа таблиці `devices`
--
ALTER TABLE `devices`
  ADD CONSTRAINT `devices_ibfk_1` FOREIGN KEY (`client_id`) REFERENCES `clients` (`client_id`) ON DELETE CASCADE,
  ADD CONSTRAINT `devices_ibfk_2` FOREIGN KEY (`type_id`) REFERENCES `device_types` (`type_id`) ON DELETE CASCADE;

--
-- Обмеження зовнішнього ключа таблиці `device_issues`
--
ALTER TABLE `device_issues`
  ADD CONSTRAINT `device_issues_ibfk_1` FOREIGN KEY (`device_id`) REFERENCES `devices` (`device_id`) ON DELETE CASCADE,
  ADD CONSTRAINT `device_issues_ibfk_2` FOREIGN KEY (`issue_type_id`) REFERENCES `issue_types` (`issue_type_id`) ON DELETE CASCADE,
  ADD CONSTRAINT `device_issues_ibfk_3` FOREIGN KEY (`employee_id`) REFERENCES `employees` (`employee_id`) ON DELETE CASCADE;

--
-- Обмеження зовнішнього ключа таблиці `orders`
--
ALTER TABLE `orders`
  ADD CONSTRAINT `orders_ibfk_1` FOREIGN KEY (`client_id`) REFERENCES `clients` (`client_id`) ON DELETE CASCADE,
  ADD CONSTRAINT `orders_ibfk_2` FOREIGN KEY (`device_id`) REFERENCES `devices` (`device_id`) ON DELETE CASCADE,
  ADD CONSTRAINT `orders_ibfk_3` FOREIGN KEY (`initial_service_id`) REFERENCES `services` (`service_id`) ON DELETE SET NULL,
  ADD CONSTRAINT `orders_ibfk_4` FOREIGN KEY (`lead_employee_id`) REFERENCES `employees` (`employee_id`) ON DELETE CASCADE;

--
-- Обмеження зовнішнього ключа таблиці `services`
--
ALTER TABLE `services`
  ADD CONSTRAINT `services_ibfk_1` FOREIGN KEY (`type_id`) REFERENCES `device_types` (`type_id`) ON DELETE CASCADE;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
