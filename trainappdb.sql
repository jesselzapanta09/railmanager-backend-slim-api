-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Generation Time: Apr 02, 2026 at 05:59 AM
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
-- Database: `trainappdb`
--

-- --------------------------------------------------------

--
-- Table structure for table `token_blacklist`
--

CREATE TABLE `token_blacklist` (
  `id` int(11) NOT NULL,
  `token` text NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `trains`
--

CREATE TABLE `trains` (
  `id` int(11) NOT NULL,
  `train_name` varchar(150) NOT NULL,
  `price` decimal(10,2) NOT NULL,
  `route` varchar(255) NOT NULL,
  `image` varchar(500) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `trains`
--

INSERT INTO `trains` (`id`, `train_name`, `price`, `route`, `image`, `created_at`, `updated_at`) VALUES
(1, 'LRT Line 1', 20.00, 'Baclaran - Fernando Poe Jr. Station', '/uploads/trains/train-1774270237420.jpg', '2026-03-16 13:56:58', '2026-03-23 12:50:37'),
(2, 'LRT Line 2', 25.00, 'Recto - Antipolo', '/uploads/trains/train-1774270227489.jpg', '2026-03-16 13:56:58', '2026-03-23 12:50:27'),
(3, 'MRT Line 3', 24.00, 'North Avenue - Taft Avenue', '/uploads/trains/train-1774270219573.jpg', '2026-03-16 13:56:58', '2026-03-23 12:50:19'),
(4, 'PNR Metro Commuter Line', 30.00, 'Tutuban - Alabang', '/uploads/trains/train-1774270206481.jpg', '2026-03-16 13:56:58', '2026-03-23 12:50:06'),
(5, 'PNR Bicol Express', 450.00, 'Manila - Naga', '/uploads/trains/train-1774270199745.jpg', '2026-03-16 13:56:58', '2026-03-23 12:49:59'),
(6, 'PNR Mayon Limited', 500.00, 'Manila - Legazpi', '/uploads/trains/train-1774270189200.jpg', '2026-03-16 13:56:58', '2026-03-23 12:49:49'),
(7, 'LRT Cavite Extension', 35.00, 'Baclaran - Niog', '/uploads/trains/train-1774270184964.jpg', '2026-03-16 13:56:58', '2026-03-23 12:49:44'),
(8, 'MRT Line 7', 28.00, 'North Avenue - San Jose del Monte', '/uploads/trains/train-1774270174367.jpg', '2026-03-16 13:56:58', '2026-03-23 12:49:34'),
(9, 'North–South Commuter Railway', 60.00, 'Clark - Calamba', '/uploads/trains/train-1774270137013.jpg', '2026-03-16 13:56:58', '2026-03-23 12:48:57'),
(10, 'Metro Manila Subway', 35.00, 'Valenzuela - NAIA Terminal 3', '/uploads/trains/train-1774270131095.jpg', '2026-03-16 13:56:58', '2026-03-23 12:48:51'),
(11, 'PNR South Long Haul', 800.00, 'Manila - Matnog', '/uploads/trains/train-1774270120521.jpg', '2026-03-16 13:56:58', '2026-03-23 12:48:40'),
(12, 'Clark Airport Express', 120.00, 'Clark Airport - Manila', '/uploads/trains/train-1774270108739.jpg', '2026-03-16 13:56:58', '2026-03-23 12:48:28'),
(13, 'Mindanao Railway Phase 1', 50.00, 'Tagum - Davao - Digos', '/uploads/trains/train-1774270094561.jpg', '2026-03-16 13:56:58', '2026-03-23 12:48:14'),
(14, 'Panay Rail Revival', 40.00, 'Iloilo - Roxas City', '/uploads/trains/train-1774270083595.jpg', '2026-03-16 13:56:58', '2026-03-23 12:48:03'),
(15, 'Cebu Monorail', 25.00, 'Cebu City - Mactan Airport', '/uploads/trains/train-1774270068006.jpg', '2026-03-16 13:56:58', '2026-03-23 12:47:48'),
(16, 'OZ-TRAIN', 50.00, 'Ozamiz - Tangub', '/uploads/trains/train-1774103794536.jpg', '2026-03-21 14:36:34', '2026-03-23 12:47:27');

-- --------------------------------------------------------

--
-- Table structure for table `users`
--

CREATE TABLE `users` (
  `id` int(11) NOT NULL,
  `username` varchar(100) NOT NULL,
  `email` varchar(150) NOT NULL,
  `password` varchar(255) NOT NULL,
  `role` enum('admin','user') NOT NULL DEFAULT 'user',
  `avatar` varchar(500) DEFAULT NULL,
  `email_verified_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `users`
--

INSERT INTO `users` (`id`, `username`, `email`, `password`, `role`, `avatar`, `email_verified_at`, `created_at`) VALUES
(1, 'Jessel Zapanta', 'jesselzapanta@gmail.com', '$2b$10$zb5.7jUvR39pgOqILkodouhXa9aeP3ZB1RNMeXuN/JjZvNHUa9Ir6', 'admin', '/uploads/avatars/avatar-1774791747009.jpg', '2026-03-22 08:13:48', '2026-03-16 13:56:58'),
(3, 'jesselzapanta09', 'jesselzapanta09@gmail.com', '$2b$10$OUf9vBUPpl.TUIZKvV4GEOGoTVIyMbSJa.YeH.fEL5slSAPhjqPPS', 'user', '/uploads/avatars/avatar-1774265341394.png', NULL, '2026-03-17 08:07:19'),
(28, 'Juan Dela Cruz', 'juandelacruz9@gmail.com', '$2b$10$BSJLo5vdyDVuovqdOuCdYOwsRC7TXWFhDiPxbxGw7cH8XBFwR.Uke', 'admin', '/uploads/avatars/avatar-1774269433539.jpg', '2026-03-23 12:39:09', '2026-03-23 12:31:24'),
(30, 'John Doe', 'johndoe@gmail.com', '$2b$10$x10Uji5HF2qNJn9N3f6v1u52Dt/p4RAu85zAgR0lUG/PgG7XgdZfO', 'admin', NULL, NULL, '2026-03-23 12:41:37'),
(31, 'Raiden Shogun', 'raidenshogun@gmail.com', '$2b$10$HZGVOSSsuU4C03.9dnVs9OKzlHPYXd8odLdROPjbRHPm8.3r65JRO', 'admin', '/uploads/avatars/avatar-1774269758313.jpg', '2026-03-23 12:53:22', '2026-03-23 12:42:38'),
(32, 'Eren Yeager', 'erenyeager@gmail.com', '$2b$10$lgFV/r2oowLgjUfvgbjUuOeeHsFAeT9dSZJR3S81651OthjZaHFkK', 'user', NULL, '2026-03-23 12:54:09', '2026-03-23 12:43:50'),
(33, 'Gabimaru', 'gabimaru@gmail.com', '$2b$10$9YGdUCKkpPt97SkjGVNl.OsI8Rrknwx6VwOmBmwQ4ollNOMZg/Gq2', 'admin', NULL, NULL, '2026-03-23 12:45:57');

-- --------------------------------------------------------

--
-- Table structure for table `user_tokens`
--

CREATE TABLE `user_tokens` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `token` varchar(255) NOT NULL,
  `type` enum('email_verify','password_reset') NOT NULL,
  `expires_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Indexes for dumped tables
--

--
-- Indexes for table `token_blacklist`
--
ALTER TABLE `token_blacklist`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `trains`
--
ALTER TABLE `trains`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `users`
--
ALTER TABLE `users`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `username` (`username`),
  ADD UNIQUE KEY `email` (`email`);

--
-- Indexes for table `user_tokens`
--
ALTER TABLE `user_tokens`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `token` (`token`),
  ADD KEY `user_id` (`user_id`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `token_blacklist`
--
ALTER TABLE `token_blacklist`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=69;

--
-- AUTO_INCREMENT for table `trains`
--
ALTER TABLE `trains`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=20;

--
-- AUTO_INCREMENT for table `users`
--
ALTER TABLE `users`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=34;

--
-- AUTO_INCREMENT for table `user_tokens`
--
ALTER TABLE `user_tokens`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=50;

--
-- Constraints for dumped tables
--

--
-- Constraints for table `user_tokens`
--
ALTER TABLE `user_tokens`
  ADD CONSTRAINT `user_tokens_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
