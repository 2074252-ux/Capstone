-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Generation Time: Nov 02, 2025 at 04:59 PM
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
-- Database: `capstoneproject`
--

-- --------------------------------------------------------

--
-- Table structure for table `categories`
--

CREATE TABLE `categories` (
  `id` int(11) NOT NULL,
  `name` varchar(100) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `categories`
--

INSERT INTO `categories` (`id`, `name`) VALUES
(1, 'Soft Drinks'),
(2, 'Bottled Water'),
(3, 'Energy Drinks'),
(4, 'Juices'),
(5, 'Coffee & Tea'),
(6, 'Chips & Crackers'),
(7, 'Biscuits & Cookies'),
(8, 'Candies & Chocolates'),
(9, 'Instant Noodles'),
(10, 'Bread & Pastries'),
(11, 'Canned Goods'),
(12, 'Condiments & Sauces'),
(13, 'Cooking Oil & Seasonings'),
(14, 'Rice & Grains'),
(15, 'Instant Meals'),
(16, 'Soap & Body Wash'),
(17, 'Shampoo & Conditioner'),
(18, 'Toothpaste & Oral Care'),
(19, 'Deodorants & Perfumes'),
(20, 'Feminine Care'),
(21, 'Laundry Supplies'),
(22, 'Cleaning Supplies'),
(23, 'Air Fresheners'),
(24, 'Kitchen Essentials'),
(25, 'Paper Products'),
(26, 'Frozen Foods'),
(27, 'Ice Cream & Desserts'),
(28, 'Dairy Products'),
(29, 'Baby Food & Formula'),
(30, 'Diapers & Wipes'),
(31, 'Pet Food'),
(32, 'Pet Care Items'),
(33, 'Vitamins & Supplements'),
(34, 'First Aid & Medicines');

-- --------------------------------------------------------

--
-- Table structure for table `custom_barcodes`
--

CREATE TABLE `custom_barcodes` (
  `id` int(11) NOT NULL,
  `name` varchar(255) NOT NULL,
  `description` text DEFAULT NULL,
  `unit` varchar(100) DEFAULT NULL,
  `price` decimal(10,2) DEFAULT 0.00,
  `wholesale_price` decimal(10,2) DEFAULT 0.00,
  `retail_price` decimal(10,2) DEFAULT 0.00,
  `category_id` int(11) DEFAULT NULL,
  `barcode` varchar(100) DEFAULT NULL,
  `date_created` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `custom_barcodes`
--

INSERT INTO `custom_barcodes` (`id`, `name`, `description`, `unit`, `price`, `wholesale_price`, `retail_price`, `category_id`, `barcode`, `date_created`) VALUES
(1, 'MAM GINA RICE', 'RICE', '1 KG', 50.00, 0.00, 50.00, 10, 'CB0150801997', '2025-10-09 07:18:22'),
(2, 'Brown Sugar', 'sugar', '1 KG', 10.00, 0.00, 10.00, 12, 'CB7589125846', '2025-10-09 07:39:15'),
(3, 'Brown Sugar', 'sugar', '1 KG', 10.00, 0.00, 10.00, 12, 'CB3992710963', '2025-10-09 07:44:39'),
(4, 'White Sugar', '1/4kg of white sugar', '1/4kg', 0.00, 0.00, 0.00, NULL, 'CB2862802271', '2025-10-10 03:23:33'),
(5, 'White Sugar', '1 KG of white sugar', '1 KG', 0.00, 0.00, 0.00, NULL, 'CB1882854490', '2025-10-10 03:28:53'),
(6, 'Brown Sugar', '1/2 brown sugar', '1/2', 0.00, 0.00, 0.00, NULL, 'CB9937765293', '2025-10-15 15:56:42'),
(7, 'White Sugar', 'sufgar', '3/4', 0.00, 0.00, 0.00, NULL, 'CB5928959746', '2025-10-23 11:36:26'),
(8, 'Sukang Pinikurat', 'fss', '100ml', 0.00, 0.00, 0.00, NULL, 'CC-00001', '2025-10-23 11:38:32'),
(9, 'Peanut', 'plastic peanut', '1/2kg', 0.00, 0.00, 0.00, NULL, 'CC-00002', '2025-10-24 04:42:48'),
(10, 'Peanut', 'eaew', '1/4', 0.00, 0.00, 0.00, NULL, 'CB5103695232', '2025-10-24 04:43:16');

-- --------------------------------------------------------

--
-- Table structure for table `damage_items`
--

CREATE TABLE `damage_items` (
  `damage_id` int(11) NOT NULL,
  `item_id` int(11) DEFAULT NULL,
  `barcode` varchar(100) NOT NULL,
  `name` varchar(255) NOT NULL,
  `category_id` int(11) DEFAULT NULL,
  `retail_price` decimal(10,2) DEFAULT NULL,
  `current_stock` int(11) DEFAULT NULL,
  `damage_quantity` int(11) DEFAULT NULL,
  `remarks` text DEFAULT NULL,
  `stock_remained` int(11) DEFAULT NULL,
  `date_reported` datetime DEFAULT current_timestamp(),
  `user_id` int(11) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `damage_items`
--

INSERT INTO `damage_items` (`damage_id`, `item_id`, `barcode`, `name`, `category_id`, `retail_price`, `current_stock`, `damage_quantity`, `remarks`, `stock_remained`, `date_reported`, `user_id`) VALUES
(4, 13, '4800047820502', 'Green Cross', 22, 33.00, 25, 5, '5 item has holes', 20, '2025-10-25 17:19:31', NULL),
(5, 2, '4804888900089', 'ZESTO BIG Mango', 4, 17.00, 8, 3, '3 items is damage', 5, '2025-10-26 15:24:43', NULL),
(6, 1, '4806502163917', 'Sanicare Wipes', 25, 30.00, 6, 3, '3 damages', 3, '2025-10-26 15:35:31', NULL),
(7, 14, '4801981110001', 'Cocola large', 1, 35.00, 48, 8, '8 has broken cap', 40, '2025-10-26 23:08:59', NULL),
(8, 12, '4800361410816', 'BEAR BRAND SWAK 33G', 28, 35.00, 3, 1, 'bite damage from rats', 2, '2025-10-27 00:12:02', NULL),
(9, 1, '4806502163917', 'Sanicare Wipes', 25, 30.00, 7, 3, '3 item has holes', 4, '2025-10-27 09:32:02', NULL),
(10, 1, '4806502163917', 'Sanicare Wipes', 25, 30.00, 4, 2, '2 sanicare has holes', 2, '2025-10-28 17:47:50', NULL),
(11, 5, '8888002076009', 'Coca-Cola Can 320ml', 1, 34.00, 64, 4, '4 coca cola has cracks', 60, '2025-10-28 18:14:46', NULL),
(12, 3, '750515018402', 'SkyFlakes Crackers', 6, 10.00, 10, 5, '5 has holes from mouse bites', 5, '2025-10-31 23:34:45', NULL),
(13, 13, '4800047820502', 'Green Cross', 22, 35.00, 27, 7, 'damage 7', 20, '2025-11-01 00:30:43', NULL),
(14, 34, '987654321', 'number2test', 9, 2.00, 21, 3, 'eee', 18, '2025-11-02 23:16:14', 1),
(15, 34, '987654321', 'number2test', 9, 2.00, 18, 2, 'easd', 16, '2025-11-02 23:21:14', 1),
(16, 34, '987654321', 'number2test', 9, 2.00, 19, 5, '5 dasdw', 14, '2025-11-02 23:27:12', 2);

-- --------------------------------------------------------

--
-- Table structure for table `expired_items`
--

CREATE TABLE `expired_items` (
  `expired_id` int(11) NOT NULL,
  `item_id` int(11) DEFAULT NULL,
  `barcode` varchar(100) NOT NULL,
  `name` varchar(255) NOT NULL,
  `category_id` int(11) DEFAULT NULL,
  `retail_price` decimal(10,2) DEFAULT NULL,
  `current_stock` int(11) DEFAULT NULL,
  `expired_quantity` int(11) DEFAULT NULL,
  `expiration_date` date DEFAULT NULL,
  `stock_remained` int(11) DEFAULT NULL,
  `date_reported` datetime DEFAULT current_timestamp(),
  `user_id` int(11) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `expired_items`
--

INSERT INTO `expired_items` (`expired_id`, `item_id`, `barcode`, `name`, `category_id`, `retail_price`, `current_stock`, `expired_quantity`, `expiration_date`, `stock_remained`, `date_reported`, `user_id`) VALUES
(1, 7, '4800016114601', 'Dewberry Yogurt Cake', 10, 18.00, 13, 13, '2025-10-02', 0, '2025-10-25 17:31:19', NULL),
(2, 7, '4800016114601', 'Dewberry Yogurt Cake', 10, 36.00, 5, 5, '2025-10-03', 0, '2025-10-25 17:47:17', NULL),
(3, NULL, '978158882020', 'Shape', 18, 30.00, 10, 10, '2002-02-02', 0, '2025-10-27 11:57:22', NULL),
(4, 31, '4804888900096', 'zesto pineapple', 4, 40.00, 10, 10, '2022-12-23', 0, '2025-10-28 17:41:06', NULL),
(5, 27, '4894819890169', 'Watson', 16, 30.00, 9, 9, '2025-10-29', 0, '2025-11-01 00:26:04', NULL),
(6, 27, '4894819890169', 'Watson', 16, 22.00, 5, 5, '2025-10-30', 0, '2025-11-01 00:46:33', NULL),
(7, 27, '4894819890169', 'Watson', 16, 33.00, 10, 10, '2025-10-30', 0, '2025-11-01 00:48:13', NULL);

-- --------------------------------------------------------

--
-- Table structure for table `items`
--

CREATE TABLE `items` (
  `id` int(11) NOT NULL,
  `barcode` varchar(50) NOT NULL,
  `name` varchar(100) NOT NULL,
  `description` text NOT NULL DEFAULT '',
  `category_id` int(11) DEFAULT NULL,
  `quantity` int(11) DEFAULT 0,
  `wholesale_price` decimal(10,2) NOT NULL DEFAULT 0.00,
  `retail_price` decimal(10,2) NOT NULL DEFAULT 0.00,
  `expiration_date` date DEFAULT NULL,
  `date_added` timestamp NOT NULL DEFAULT current_timestamp(),
  `markup_percentage` decimal(5,2) DEFAULT 10.00,
  `low_stock_threshold` int(11) DEFAULT 5
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `items`
--

INSERT INTO `items` (`id`, `barcode`, `name`, `description`, `category_id`, `quantity`, `wholesale_price`, `retail_price`, `expiration_date`, `date_added`, `markup_percentage`, `low_stock_threshold`) VALUES
(1, '4806502163917', 'Sanicare Wipes', 'Sanicare wipes', 25, 7, 25.00, 30.00, '2026-02-02', '2025-10-26 05:21:03', 15.00, 5),
(2, '4804888900089', 'ZESTO BIG Mango', 'ZESTO BIG Mango drink', 4, 11, 30.00, 37.50, '2026-03-03', '2025-10-26 05:21:03', 25.00, 5),
(3, '750515018402', 'SkyFlakes Crackers', 'SkyFlakes crackers', 6, 15, 10.00, 11.50, '2026-06-01', '2025-10-26 05:21:03', 15.00, 5),
(4, '4800024558305', 'Del Monte Four Season 230ml', 'Del Monte Four Seasons juice drink 230ml', 4, 53, 30.00, 35.00, '2025-12-04', '2025-10-26 05:21:03', 10.00, 5),
(5, '8888002076009', 'Coca-Cola Can 320ml', 'Coca-Cola in can 320ml', 1, 60, 30.00, 34.00, '2026-06-01', '2025-10-26 05:21:03', 10.00, 5),
(7, '4800016114601', 'Dewberry Yogurt Cake', '', 10, 4, 33.00, 36.00, '2026-03-04', '2025-10-26 05:21:03', 10.00, 5),
(8, 'CB1882854490', 'White Sugar', '', 12, 30, 3.00, 5.00, '2026-09-02', '2025-10-26 05:21:03', 10.00, 5),
(10, '4800016652035', 'Mr Chips', 'Mr chips 24 g', 6, 15, 30.00, 37.50, '2026-02-03', '2025-10-26 05:21:03', 25.00, 5),
(11, '48037433', 'Star margarine', 'star', 12, 8, 0.00, 50.00, '2026-12-06', '2025-10-26 05:21:03', 10.00, 5),
(12, '4800361410816', 'BEAR BRAND SWAK 33G', 'Brear Brand swak', 28, 2, 30.00, 35.00, '2026-03-03', '2025-10-26 05:21:03', 10.00, 5),
(13, '4800047820502', 'Green Cross', 'green', 22, 19, 30.00, 35.00, '2027-02-02', '2025-10-26 05:21:03', 10.00, 5),
(14, '4801981110001', 'Cocola large', 'coke', 1, 40, 30.00, 35.00, '2026-02-02', '2025-10-26 05:21:03', 10.00, 5),
(17, '8992696526662', 'nescafe black', 'wea', 5, 27, 30.00, 35.00, '2026-06-02', '2025-10-26 05:21:03', 10.00, 5),
(22, '4800049720121', 'Nature Spring', '1000ml', 2, 0, 0.00, 0.00, NULL, '2025-10-26 05:21:03', 10.00, 5),
(25, 'CB0150801997', 'MAM GINA RICE', '', NULL, 5, 34.00, 50.00, '2026-03-03', '2025-10-26 05:21:03', 10.00, 5),
(26, 'CC-00001', 'Sukang Pinikurat', '', NULL, 10, 42.00, 50.00, '2026-02-03', '2025-10-26 05:21:03', 10.00, 5),
(27, '4894819890169', 'Watson', 'esadw', 16, 6, 30.00, 33.00, NULL, '2025-10-27 03:48:15', 10.00, 5),
(31, '4804888900096', 'zesto pineapple', '250ml', 4, 10, 12.00, 13.80, '2025-11-03', '2025-10-28 09:36:08', 15.00, 5),
(33, '12345678', 'testinglowstock', 'test', 13, 47, 3.00, 3.30, '2026-02-02', '2025-10-31 17:48:20', 10.00, 25),
(34, '987654321', 'number2test', '3', 9, 17, 2.00, 2.20, '2026-02-02', '2025-10-31 17:58:45', 10.00, 20);

--
-- Triggers `items`
--
DELIMITER $$
CREATE TRIGGER `update_retail_price` BEFORE UPDATE ON `items` FOR EACH ROW BEGIN
    IF NEW.wholesale_price != OLD.wholesale_price OR NEW.markup_percentage != OLD.markup_percentage THEN
        SET NEW.retail_price = NEW.wholesale_price + (NEW.wholesale_price * (NEW.markup_percentage / 100));
    END IF;
END
$$
DELIMITER ;

-- --------------------------------------------------------

--
-- Table structure for table `sales`
--

CREATE TABLE `sales` (
  `id` int(11) NOT NULL,
  `transaction_id` varchar(50) DEFAULT NULL,
  `barcode` varchar(100) NOT NULL,
  `wholesale_price` decimal(10,2) DEFAULT 0.00,
  `name` varchar(255) NOT NULL,
  `retail_price` decimal(10,2) NOT NULL,
  `quantity` int(11) NOT NULL,
  `total_amount` decimal(10,2) NOT NULL,
  `sale_date` datetime DEFAULT current_timestamp(),
  `user_id` int(11) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `sales`
--

INSERT INTO `sales` (`id`, `transaction_id`, `barcode`, `wholesale_price`, `name`, `retail_price`, `quantity`, `total_amount`, `sale_date`, `user_id`) VALUES
(1, NULL, '4804888900089', 0.00, 'ZESTO BIG Mango', 17.00, 2, 34.00, '2025-08-13 14:30:45', NULL),
(2, NULL, '4804888900089', 0.00, 'ZESTO BIG Mango', 17.00, 2, 34.00, '2025-08-13 14:34:29', NULL),
(3, NULL, '4804888900089', 0.00, 'ZESTO BIG Mango', 17.00, 2, 34.00, '2025-08-13 14:39:23', NULL),
(4, NULL, '4804888900089', 0.00, 'ZESTO BIG Mango', 17.00, 1, 17.00, '2025-08-13 14:41:28', NULL),
(5, NULL, '8888002076009', 0.00, 'Coca-Cola Can 320ml', 25.00, 1, 25.00, '2025-08-13 17:51:04', NULL),
(6, NULL, '8888002076009', 0.00, 'Coca-Cola Can 320ml', 25.00, 2, 50.00, '2025-08-21 18:21:12', NULL),
(7, NULL, '8888002076009', 0.00, 'Coca-Cola Can 320ml', 25.00, 1, 25.00, '2025-09-18 03:22:11', NULL),
(8, 'TXN68cc3c7c35e57', '750515018402', 0.00, 'SkyFlakes Crackers', 10.00, 1, 10.00, '2025-09-19 01:08:12', NULL),
(9, 'TXN68cc3c7c35e57', '8888002076009', 0.00, 'Coca-Cola Can 320ml', 25.00, 1, 25.00, '2025-09-19 01:08:12', NULL),
(10, 'TXN68cc3c7c35e57', '4804888900089', 0.00, 'ZESTO BIG Mango', 17.00, 1, 17.00, '2025-09-19 01:08:12', NULL),
(11, 'TXN68cc448c13f3c', '4804888900089', 0.00, 'ZESTO BIG Mango', 17.00, 1, 17.00, '2025-09-19 01:42:36', NULL),
(12, 'TXN68cc448c13f3c', '4804888900089', 0.00, 'ZESTO BIG Mango', 17.00, 1, 17.00, '2025-09-19 01:42:36', NULL),
(13, 'TXN68ccf66a363cc', '8888002076009', 0.00, 'Coca-Cola Can 320ml', 25.00, 2, 50.00, '2025-09-19 14:21:30', NULL),
(14, 'TXN68ccf66a363cc', '750515018402', 0.00, 'SkyFlakes Crackers', 10.00, 1, 10.00, '2025-09-19 14:21:30', NULL),
(15, 'TXN68ccfb5061978', '4800024558305', 0.00, 'Del Monte Four Season 230ml', 30.00, 1, 30.00, '2025-09-19 14:42:24', NULL),
(16, 'TXN68cd4c216db89', '4804888900089', 0.00, 'ZESTO BIG Mango', 17.00, 1, 17.00, '2025-09-19 20:27:13', NULL),
(17, 'TXN68cd4c216db89', '750515018402', 0.00, 'SkyFlakes Crackers', 10.00, 1, 10.00, '2025-09-19 20:27:13', NULL),
(18, 'TXN68cd4c216db89', '4806502163917', 0.00, 'Sanicare Wipes', 30.00, 1, 30.00, '2025-09-19 20:27:13', NULL),
(19, 'TXN68e74a1a2d014', '8888002076009', 0.00, 'Coca-Cola Can 320ml', 25.00, 1, 25.00, '2025-10-09 13:37:30', NULL),
(21, 'TXN68fa12b4d71b8', '4801981110001', 0.00, '0', 25.00, 2, 50.00, '2025-10-23 19:34:12', NULL),
(22, 'TXN68fb05c58f063', '8888002076009', 0.00, '0', 28.00, 1, 28.00, '2025-10-24 12:51:17', NULL),
(23, 'TXN68fb0617a4304', '4804888900089', 0.00, '0', 17.00, 1, 17.00, '2025-10-24 12:52:39', NULL),
(24, 'TXN68fb0617a4304', '4800016114601', 0.00, '0', 18.00, 1, 18.00, '2025-10-24 12:52:39', NULL),
(25, 'TXN68fb0617a4304', '4806502163917', 0.00, '0', 30.00, 1, 30.00, '2025-10-24 12:52:39', NULL),
(26, 'TXN68fb06728818c', '8992696526662', 0.00, '0', 0.00, 3, 0.00, '2025-10-24 12:54:10', NULL),
(27, 'TXN68fb06728818c', '4800016114601', 0.00, '0', 18.00, 1, 18.00, '2025-10-24 12:54:10', NULL),
(28, 'TXN68fca6937a0ff', '4806502163917', 0.00, 'Sanicare Wipes', 0.00, 3, 90.00, '2025-10-25 18:29:39', NULL),
(29, 'TXN68fca6b731ee3', '233423423', 0.00, 'test now', 0.00, 3, 120.00, '2025-10-25 18:30:15', NULL),
(30, 'TXN68fca89f33f36', '233423423', 0.00, 'test now', 0.00, 1, 40.00, '2025-10-25 18:38:23', NULL),
(31, 'TXN68fca99810137', '4800047820502', 0.00, 'Green Cross', 0.00, 3, 99.00, '2025-10-25 18:42:32', NULL),
(32, 'TXN68fcac69b6dcc', '4806502163917', 25.00, 'Sanicare Wipes', 30.00, 1, 30.00, '2025-10-25 18:54:33', NULL),
(33, 'TXN68fdcf86e1a90', '4806502163917', 25.00, 'Sanicare Wipes', 30.00, 1, 30.00, '2025-10-26 15:36:38', NULL),
(34, 'TXN68fe3a4959ca1', '4806502163917', 25.00, 'Sanicare Wipes', 30.00, 2, 60.00, '2025-10-26 23:12:09', NULL),
(35, 'TXN68fe488d6a64a', '4806502163917', 25.00, 'Sanicare Wipes', 30.00, 1, 30.00, '2025-10-27 00:13:01', NULL),
(36, 'TXN68fe52017bad5', '8888002076009', 30.00, 'Coca-Cola Can 320ml', 34.00, 2, 68.00, '2025-10-27 00:53:21', NULL),
(37, 'TXN68fe55cc5434b', '8888002076009', 30.00, 'Coca-Cola Can 320ml', 34.00, 3, 102.00, '2025-10-27 01:09:32', NULL),
(38, 'TXN68fef0b72efec', '4894819890169', 20.00, 'Watson', 30.00, 2, 60.00, '2025-10-27 12:10:31', NULL),
(39, 'TXN69008e3c037fa', '8888002076009', 30.00, 'Coca-Cola Can 320ml', 34.00, 2, 68.00, '2025-10-28 17:34:52', NULL),
(40, 'TXN6905082275a5a', '4800047820502', 30.00, 'Green Cross', 35.00, 1, 35.00, '2025-11-01 03:04:02', NULL),
(41, 'TXN690508e359bf8', '987654321', 2.00, 'number2test', 2.20, 4, 8.80, '2025-11-01 03:07:15', NULL),
(42, 'TXN6907786485327', '987654321', 2.00, 'number2test', 2.20, 1, 2.20, '2025-11-02 23:27:32', NULL),
(43, 'TXN69077b13c947c', '987654321', 2.00, 'number2test', 2.20, 1, 2.20, '2025-11-02 23:38:59', NULL);

-- --------------------------------------------------------

--
-- Table structure for table `stock_transactions`
--

CREATE TABLE `stock_transactions` (
  `id` int(11) NOT NULL,
  `item_id` int(11) NOT NULL,
  `type` enum('in','out') NOT NULL,
  `quantity` int(11) NOT NULL,
  `date` timestamp NOT NULL DEFAULT current_timestamp(),
  `user_id` int(11) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `stock_transactions`
--

INSERT INTO `stock_transactions` (`id`, `item_id`, `type`, `quantity`, `date`, `user_id`) VALUES
(1, 1, 'in', 5, '2025-08-13 03:20:13', NULL),
(2, 1, 'out', 2, '2025-08-13 03:43:56', NULL),
(3, 2, 'in', 10, '2025-08-13 05:49:50', NULL),
(4, 3, 'in', 20, '2025-08-13 05:52:36', NULL),
(5, 2, 'in', 2, '2025-08-13 05:53:29', NULL),
(6, 2, 'in', 5, '2025-08-13 06:45:35', NULL),
(7, 2, 'in', 3, '2025-08-13 08:35:34', NULL),
(8, 5, 'in', 19, '2025-08-13 09:50:23', NULL),
(9, 5, 'in', 3, '2025-08-21 10:21:48', NULL),
(10, 3, 'out', 3, '2025-09-17 19:14:38', NULL),
(11, 5, 'in', 5, '2025-09-19 06:20:41', NULL),
(12, 4, 'in', 3, '2025-09-19 06:42:58', NULL),
(13, 5, 'in', 1, '2025-09-20 13:19:02', NULL),
(14, 5, 'in', 5, '2025-10-01 06:59:37', NULL),
(15, 5, 'in', 20, '2025-10-09 05:55:41', NULL),
(16, 5, 'in', 20, '2025-10-09 05:56:11', NULL),
(18, 7, 'in', 3, '2025-10-09 06:54:04', NULL),
(19, 7, 'in', 8, '2025-10-09 06:58:02', NULL),
(20, 7, 'in', 8, '2025-10-09 06:59:08', NULL),
(22, 4, 'in', 50, '2025-10-20 13:53:02', NULL),
(23, 7, 'in', 3, '2025-10-21 02:54:01', NULL),
(24, 11, 'in', 20, '2025-10-21 09:20:28', NULL),
(25, 11, 'out', 12, '2025-10-21 09:21:22', NULL),
(26, 5, 'in', 2, '2025-10-22 05:26:18', NULL),
(27, 5, 'in', 2, '2025-10-22 08:13:16', NULL),
(28, 13, 'in', 10, '2025-10-23 11:27:23', NULL),
(29, 14, 'in', 49, '2025-10-23 11:33:02', NULL),
(30, 1, 'in', 3, '2025-10-24 00:44:29', NULL),
(31, 1, 'in', 3, '2025-10-24 01:06:36', NULL),
(32, 13, 'in', 5, '2025-10-24 01:50:01', NULL),
(33, 13, 'in', 10, '2025-10-24 01:50:41', NULL),
(35, 17, 'in', 20, '2025-10-24 04:39:13', NULL),
(36, 17, 'in', 10, '2025-10-24 04:46:07', NULL),
(37, 5, 'in', 2, '2025-10-24 05:08:05', NULL),
(38, 8, 'in', 30, '2025-10-24 05:25:14', NULL),
(40, 14, 'in', 1, '2025-10-24 08:11:33', NULL),
(41, 4, 'in', 1, '2025-10-24 08:53:44', NULL),
(42, 12, 'in', 3, '2025-10-25 07:05:16', NULL),
(44, 25, 'in', 5, '2025-10-25 08:02:08', NULL),
(45, 26, 'in', 10, '2025-10-25 08:03:23', NULL),
(47, 7, 'in', 5, '2025-10-25 09:47:01', NULL),
(49, 13, 'in', 10, '2025-10-25 14:34:10', NULL),
(50, 1, 'in', 3, '2025-10-26 07:25:57', NULL),
(51, 1, 'in', 4, '2025-10-26 15:09:43', NULL),
(52, 1, 'in', 3, '2025-10-26 16:10:59', NULL),
(53, 5, 'in', 1, '2025-10-26 16:50:55', NULL),
(54, 1, 'in', 1, '2025-10-26 17:13:23', NULL),
(55, 7, 'in', 4, '2025-10-27 02:49:13', NULL),
(56, 3, 'in', 10, '2025-10-27 03:51:02', NULL),
(57, 27, 'in', 10, '2025-10-27 03:52:15', NULL),
(59, 27, 'in', 1, '2025-10-28 06:06:18', NULL),
(60, 31, 'in', 10, '2025-10-28 09:37:04', NULL),
(61, 1, 'in', 5, '2025-10-31 15:49:52', NULL),
(62, 10, 'in', 10, '2025-10-31 15:51:14', NULL),
(63, 3, 'in', 10, '2025-10-31 16:22:50', NULL),
(64, 27, 'in', 5, '2025-10-31 16:26:57', NULL),
(65, 27, 'in', 10, '2025-10-31 16:47:14', NULL),
(66, 34, 'in', 18, '2025-10-31 17:59:21', NULL),
(67, 33, 'in', 9, '2025-10-31 18:00:01', NULL),
(68, 34, 'in', 3, '2025-10-31 18:02:42', NULL),
(69, 33, 'in', 12, '2025-10-31 18:37:45', NULL),
(70, 27, 'in', 6, '2025-10-31 18:38:18', NULL),
(71, 33, 'in', 26, '2025-10-31 18:38:56', NULL),
(72, 34, 'in', 2, '2025-11-02 14:55:20', NULL),
(73, 34, 'in', 2, '2025-11-02 15:15:13', 1),
(74, 34, 'in', 3, '2025-11-02 15:21:45', 2),
(75, 34, 'in', 5, '2025-11-02 15:39:55', 2);

-- --------------------------------------------------------

--
-- Table structure for table `users`
--

CREATE TABLE `users` (
  `id` int(11) NOT NULL,
  `username` varchar(50) NOT NULL,
  `password` varchar(255) NOT NULL,
  `role` enum('owner','employee') NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `users`
--

INSERT INTO `users` (`id`, `username`, `password`, `role`) VALUES
(1, 'owner1', '81dc9bdb52d04dc20036dbd8313ed055', 'owner'),
(2, 'employee1', '81dc9bdb52d04dc20036dbd8313ed055', 'employee');

--
-- Indexes for dumped tables
--

--
-- Indexes for table `categories`
--
ALTER TABLE `categories`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `custom_barcodes`
--
ALTER TABLE `custom_barcodes`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `barcode` (`barcode`),
  ADD KEY `category_id` (`category_id`);

--
-- Indexes for table `damage_items`
--
ALTER TABLE `damage_items`
  ADD PRIMARY KEY (`damage_id`),
  ADD KEY `category_id` (`category_id`),
  ADD KEY `item_id` (`item_id`),
  ADD KEY `user_id` (`user_id`);

--
-- Indexes for table `expired_items`
--
ALTER TABLE `expired_items`
  ADD PRIMARY KEY (`expired_id`),
  ADD KEY `category_id` (`category_id`),
  ADD KEY `item_id` (`item_id`),
  ADD KEY `user_id` (`user_id`);

--
-- Indexes for table `items`
--
ALTER TABLE `items`
  ADD PRIMARY KEY (`id`),
  ADD KEY `fk_items_category` (`category_id`);

--
-- Indexes for table `sales`
--
ALTER TABLE `sales`
  ADD PRIMARY KEY (`id`),
  ADD KEY `user_id` (`user_id`);

--
-- Indexes for table `stock_transactions`
--
ALTER TABLE `stock_transactions`
  ADD PRIMARY KEY (`id`),
  ADD KEY `item_id` (`item_id`),
  ADD KEY `user_id` (`user_id`);

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
-- AUTO_INCREMENT for table `categories`
--
ALTER TABLE `categories`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=35;

--
-- AUTO_INCREMENT for table `custom_barcodes`
--
ALTER TABLE `custom_barcodes`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=11;

--
-- AUTO_INCREMENT for table `damage_items`
--
ALTER TABLE `damage_items`
  MODIFY `damage_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=17;

--
-- AUTO_INCREMENT for table `expired_items`
--
ALTER TABLE `expired_items`
  MODIFY `expired_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=8;

--
-- AUTO_INCREMENT for table `items`
--
ALTER TABLE `items`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=35;

--
-- AUTO_INCREMENT for table `sales`
--
ALTER TABLE `sales`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=44;

--
-- AUTO_INCREMENT for table `stock_transactions`
--
ALTER TABLE `stock_transactions`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=76;

--
-- AUTO_INCREMENT for table `users`
--
ALTER TABLE `users`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- Constraints for dumped tables
--

--
-- Constraints for table `custom_barcodes`
--
ALTER TABLE `custom_barcodes`
  ADD CONSTRAINT `custom_barcodes_ibfk_1` FOREIGN KEY (`category_id`) REFERENCES `categories` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `damage_items`
--
ALTER TABLE `damage_items`
  ADD CONSTRAINT `damage_items_ibfk_1` FOREIGN KEY (`category_id`) REFERENCES `categories` (`id`),
  ADD CONSTRAINT `damage_items_ibfk_2` FOREIGN KEY (`item_id`) REFERENCES `items` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `damage_items_ibfk_3` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`);

--
-- Constraints for table `expired_items`
--
ALTER TABLE `expired_items`
  ADD CONSTRAINT `expired_items_ibfk_1` FOREIGN KEY (`category_id`) REFERENCES `categories` (`id`),
  ADD CONSTRAINT `expired_items_ibfk_2` FOREIGN KEY (`item_id`) REFERENCES `items` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `expired_items_ibfk_3` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`);

--
-- Constraints for table `items`
--
ALTER TABLE `items`
  ADD CONSTRAINT `fk_items_category` FOREIGN KEY (`category_id`) REFERENCES `categories` (`id`) ON DELETE SET NULL ON UPDATE CASCADE;

--
-- Constraints for table `sales`
--
ALTER TABLE `sales`
  ADD CONSTRAINT `sales_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`);

--
-- Constraints for table `stock_transactions`
--
ALTER TABLE `stock_transactions`
  ADD CONSTRAINT `stock_transactions_ibfk_1` FOREIGN KEY (`item_id`) REFERENCES `items` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `stock_transactions_ibfk_2` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`);
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
