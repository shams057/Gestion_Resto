-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: localhost
-- Generation Time: Dec 11, 2025 at 06:08 PM
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
-- Database: `gestion_resto`
--

-- --------------------------------------------------------

--
-- Table structure for table `categories`
--

CREATE TABLE `categories` (
  `id` int(10) UNSIGNED NOT NULL,
  `nom` varchar(100) NOT NULL,
  `description` text DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `categories`
--

INSERT INTO `categories` (`id`, `nom`, `description`, `created_at`) VALUES
(1, 'Entrées', 'Entrées et amuse-bouches', '2025-11-21 10:40:11'),
(2, 'Plats principaux', 'Plats principaux chauds', '2025-11-21 10:40:11'),
(3, 'Desserts', 'Gâteaux, glaces et desserts', '2025-11-21 10:40:11');

-- --------------------------------------------------------

--
-- Table structure for table `clients`
--

CREATE TABLE `clients` (
  `id` int(10) UNSIGNED NOT NULL,
  `nom` varchar(120) NOT NULL,
  `telephone` varchar(30) DEFAULT NULL,
  `email` varchar(150) DEFAULT NULL,
  `note` text DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `password_hash` varchar(255) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `clients`
--

INSERT INTO `clients` (`id`, `nom`, `telephone`, `email`, `note`, `created_at`, `password_hash`) VALUES
(1, 'Ahmed', '21690000001', 'ahmed@mail.local', NULL, '2025-11-21 10:40:11', '01470258369'),
(2, 'Maryem', '21690000002', 'maryem@mail.local', NULL, '2025-11-21 10:40:11', '01470258/36');

-- --------------------------------------------------------

--
-- Table structure for table `commandes`
--

CREATE TABLE `commandes` (
  `id` int(10) UNSIGNED NOT NULL,
  `reference` varchar(50) NOT NULL,
  `id_client` int(10) UNSIGNED DEFAULT NULL,
  `id_serveur` int(10) UNSIGNED DEFAULT NULL,
  `date_commande` datetime NOT NULL DEFAULT current_timestamp(),
  `total` decimal(10,2) NOT NULL DEFAULT 0.00,
  `statut` enum('en_attente','preparation','en_livraison','livree','annulee') NOT NULL DEFAULT 'en_attente',
  `mode_paiement` enum('espece','carte','mobile') DEFAULT 'espece',
  `remarque` text DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `commandes`
--

INSERT INTO `commandes` (`id`, `reference`, `id_client`, `id_serveur`, `date_commande`, `total`, `statut`, `mode_paiement`, `remarque`, `created_at`, `updated_at`) VALUES
(1, 'CMD-20251121-0001', 1, 2, '2025-11-21 10:40:11', 27.50, 'en_attente', 'espece', NULL, '2025-11-21 10:40:11', '2025-11-21 10:40:11');

-- --------------------------------------------------------

--
-- Table structure for table `ligne_commandes`
--

CREATE TABLE `ligne_commandes` (
  `id` int(10) UNSIGNED NOT NULL,
  `id_commande` int(10) UNSIGNED NOT NULL,
  `id_plat` int(10) UNSIGNED NOT NULL,
  `quantite` int(10) UNSIGNED NOT NULL DEFAULT 1,
  `prix_unitaire` decimal(10,2) NOT NULL DEFAULT 0.00,
  `total_ligne` decimal(10,2) GENERATED ALWAYS AS (`prix_unitaire` * `quantite`) STORED,
  `remarque` varchar(255) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `ligne_commandes`
--

INSERT INTO `ligne_commandes` (`id`, `id_commande`, `id_plat`, `quantite`, `prix_unitaire`, `remarque`) VALUES
(1, 1, 2, 1, 14.50, NULL),
(2, 1, 4, 2, 6.50, NULL);

--
-- Triggers `ligne_commandes`
--
DELIMITER $$
CREATE TRIGGER `trg_update_total_after_delete` AFTER DELETE ON `ligne_commandes` FOR EACH ROW BEGIN
    UPDATE commandes
    SET total = (SELECT COALESCE(SUM(total_ligne),0) FROM ligne_commandes WHERE id_commande = OLD.id_commande)
    WHERE id = OLD.id_commande;
END
$$
DELIMITER ;
DELIMITER $$
CREATE TRIGGER `trg_update_total_after_insert` AFTER INSERT ON `ligne_commandes` FOR EACH ROW BEGIN
    UPDATE commandes
    SET total = (SELECT COALESCE(SUM(total_ligne),0) FROM ligne_commandes WHERE id_commande = NEW.id_commande)
    WHERE id = NEW.id_commande;
END
$$
DELIMITER ;

-- --------------------------------------------------------

--
-- Table structure for table `logs`
--

CREATE TABLE `logs` (
  `id` int(10) UNSIGNED NOT NULL,
  `utilisateur_id` int(10) UNSIGNED DEFAULT NULL,
  `action` varchar(150) NOT NULL,
  `details` text DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `logs`
--

INSERT INTO `logs` (`id`, `utilisateur_id`, `action`, `details`, `created_at`) VALUES
(1, 1, 'Creation BD', 'Base de données initialisée et tables créées', '2025-11-21 10:40:11');

-- --------------------------------------------------------

--
-- Table structure for table `plats`
--

CREATE TABLE `plats` (
  `id` int(10) UNSIGNED NOT NULL,
  `nom` varchar(150) NOT NULL,
  `description` text DEFAULT NULL,
  `prix` decimal(10,2) NOT NULL DEFAULT 0.00,
  `id_categorie` int(10) UNSIGNED DEFAULT NULL,
  `image_url` varchar(999) DEFAULT NULL,
  `allergies` varchar(255) DEFAULT NULL,
  `disponible` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `plats`
--

INSERT INTO `plats` (`id`, `nom`, `description`, `prix`, `id_categorie`, `image_url`, `allergies`, `disponible`, `created_at`, `updated_at`) VALUES
(1, 'Salade César', 'Salade avec poulet, parmesan et croutons', 12.00, 1, 'https://www.allrecipes.com/thmb/GKJL13Wb8TZ9hpJ9c70v0aNXsyQ=/750x0/filters:no_upscale():max_bytes(150000):strip_icc():format(webp)/229063-Classic-Restaurant-Caesar-Salad-ddmfs-4x3-231-89bafa5e54dd4a8c933cf2a5f9f12a6f.jpg', 'gluten-free,lactose-free', 1, '2025-11-21 10:40:11', '2025-12-08 11:18:14'),
(2, 'Pâtes carbonara', 'Pâtes à la crème et pancetta', 14.50, 2, 'https://upload.wikimedia.org/wikipedia/commons/3/33/Espaguetis_carbonara.jpg', 'nut-free', 1, '2025-11-21 10:40:11', '2025-12-08 11:22:42'),
(3, 'Couscous', 'Couscous traditionnel', 10.00, 2, 'https://upload.wikimedia.org/wikipedia/commons/thumb/0/0c/Moroccan_cuscus%2C_from_Casablanca%2C_September_2018.jpg/960px-Moroccan_cuscus%2C_from_Casablanca%2C_September_2018.jpg', 'gluten-free', 1, '2025-11-21 10:40:11', '2025-12-08 11:22:42'),
(4, 'Tiramisu', 'Dessert italien à base de mascarpone', 6.50, 3, 'https://upload.wikimedia.org/wikipedia/commons/thumb/5/58/Tiramisu_-_Raffaele_Diomede.jpg/1280px-Tiramisu_-_Raffaele_Diomede.jpg', 'gluten-free,lactose-free', 1, '2025-11-21 10:40:11', '2025-12-08 11:22:42'),
(5, 'Loubya', 'Plat tunisien culte', 14.00, 2, 'https://images.squarespace-cdn.com/content/v1/580bb690d1758e509eb28292/1549000436086-84CVXCCRN1JXYJISO2VZ/RUUKmSUaRYODKWRl1nVSRA.jpg?format=1500w', 'gluten-free,lactose-free,nut-free', 1, '2025-11-21 10:40:11', '2025-12-08 11:22:42'),
(6, 'Lasagne', 'Lasagne italienne, béchamel et viande hachée', 18.00, 2, 'https://imgs.search.brave.com/Ez-XtYlqQxPX_IP9XsSZ4tEtPjYiBTS0rj7sMTmIzqU/rs:fit:860:0:0:0/g:ce/aHR0cHM6Ly9pbWcu/ZnJlZXBpay5jb20v/ZnJlZS1waG90by9p/dGFsaWFuLWxhc2Fn/bmUtc2VydmVkLXdp/dGgtcm9ja2V0LXNh/bGFkXzE0MTc5My0x/Nzg4LmpwZz9zZW10/PWFpc19oeWJyaWQm/dz03NDAmcT04MA', 'nut-free', 1, '2025-11-21 10:40:11', '2025-12-08 11:22:42'),
(7, 'Glace', 'Glace sorbet naturel', 6.50, 3, 'https://imgs.search.brave.com/H-6d3WcvtSjN9-rJc-3zmFciwQZJc0V9ScTBHCms9fo/rs:fit:860:0:0:0/g:ce/aHR0cHM6Ly9zdGF0/aWMudmVjdGVlenku/Y29tL3RpL3Bob3Rv/cy1ncmF0dWl0ZS90/Mi81MjcxNjkxMS1h/c3NvcnRpbWVudC1k/ZS1jb2xvcmUtc29y/YmV0LWxhLWdsYWNl/LWNyZW1lLWJvdWxl/cy1kYW5zLWVuLWJv/aXMtYm91bGVzLWdy/YXR1aXQtcGhvdG8u/anBn', 'gluten-free,lactose-free,nut-free', 1, '2025-11-21 10:40:11', '2025-12-08 11:50:16');

-- --------------------------------------------------------

--
-- Table structure for table `reservations`
--

CREATE TABLE `reservations` (
  `id` int(10) UNSIGNED NOT NULL,
  `id_client` int(10) UNSIGNED DEFAULT NULL,
  `date_reservation` datetime NOT NULL,
  `nombre_personnes` int(10) UNSIGNED NOT NULL DEFAULT 1,
  `statut` enum('confirmee','annulee','terminee','en_attente') DEFAULT 'en_attente',
  `remarque` text DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `reservations`
--

INSERT INTO `reservations` (`id`, `id_client`, `date_reservation`, `nombre_personnes`, `statut`, `remarque`, `created_at`) VALUES
(1, 2, '2025-11-25 20:00:00', 4, 'confirmee', NULL, '2025-11-21 10:40:11');

-- --------------------------------------------------------

--
-- Table structure for table `stocks`
--

CREATE TABLE `stocks` (
  `id` int(10) UNSIGNED NOT NULL,
  `article` varchar(150) NOT NULL,
  `quantite` int(11) NOT NULL DEFAULT 0,
  `unite` varchar(30) DEFAULT 'unit',
  `seuil_alerte` int(11) DEFAULT 0,
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `stocks`
--

INSERT INTO `stocks` (`id`, `article`, `quantite`, `unite`, `seuil_alerte`, `updated_at`) VALUES
(1, 'Farine', 25, 'kg', 5, '2025-11-21 10:40:11'),
(2, 'Tomates', 10, 'kg', 3, '2025-11-21 10:40:11');

-- --------------------------------------------------------

--
-- Table structure for table `users`
--

CREATE TABLE `users` (
  `id` int(10) UNSIGNED NOT NULL,
  `nom` varchar(100) NOT NULL,
  `email` varchar(150) DEFAULT NULL,
  `telephone` varchar(30) DEFAULT NULL,
  `role` enum('admin','gerant','serveur','cuisinier') NOT NULL DEFAULT 'serveur',
  `password_hash` varchar(255) DEFAULT NULL,
  `actif` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `users`
--

INSERT INTO `users` (`id`, `nom`, `email`, `telephone`, `role`, `password_hash`, `actif`, `created_at`, `updated_at`) VALUES
(1, 'Admin Principal', 'admin@resto.local', '21650000000', 'admin', '74108520963', 1, '2025-11-21 10:40:11', '2025-12-09 19:02:43'),
(2, 'Sami Serveur', 'sami@resto.local', '21650000001', 'serveur', '7410852096', 1, '2025-11-21 10:40:11', '2025-12-09 19:02:58'),
(3, 'Fatma Cuisiniere', 'fatma@resto.local', '21650000002', 'cuisinier', '741085209', 1, '2025-11-21 10:40:11', '2025-12-09 19:03:02');

--
-- Indexes for dumped tables
--

--
-- Indexes for table `categories`
--
ALTER TABLE `categories`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `clients`
--
ALTER TABLE `clients`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `commandes`
--
ALTER TABLE `commandes`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `reference` (`reference`),
  ADD KEY `id_client` (`id_client`),
  ADD KEY `id_serveur` (`id_serveur`),
  ADD KEY `idx_commandes_date` (`date_commande`),
  ADD KEY `idx_commandes_statut` (`statut`);

--
-- Indexes for table `ligne_commandes`
--
ALTER TABLE `ligne_commandes`
  ADD PRIMARY KEY (`id`),
  ADD KEY `id_plat` (`id_plat`),
  ADD KEY `idx_lignes_commande_commande` (`id_commande`);

--
-- Indexes for table `logs`
--
ALTER TABLE `logs`
  ADD PRIMARY KEY (`id`),
  ADD KEY `utilisateur_id` (`utilisateur_id`);

--
-- Indexes for table `plats`
--
ALTER TABLE `plats`
  ADD PRIMARY KEY (`id`),
  ADD KEY `id_categorie` (`id_categorie`),
  ADD KEY `idx_plats_nom` (`nom`);

--
-- Indexes for table `reservations`
--
ALTER TABLE `reservations`
  ADD PRIMARY KEY (`id`),
  ADD KEY `id_client` (`id_client`);

--
-- Indexes for table `stocks`
--
ALTER TABLE `stocks`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `users`
--
ALTER TABLE `users`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `email` (`email`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `categories`
--
ALTER TABLE `categories`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT for table `clients`
--
ALTER TABLE `clients`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT for table `commandes`
--
ALTER TABLE `commandes`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `ligne_commandes`
--
ALTER TABLE `ligne_commandes`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT for table `logs`
--
ALTER TABLE `logs`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `plats`
--
ALTER TABLE `plats`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=8;

--
-- AUTO_INCREMENT for table `reservations`
--
ALTER TABLE `reservations`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `stocks`
--
ALTER TABLE `stocks`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT for table `users`
--
ALTER TABLE `users`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- Constraints for dumped tables
--

--
-- Constraints for table `commandes`
--
ALTER TABLE `commandes`
  ADD CONSTRAINT `commandes_ibfk_1` FOREIGN KEY (`id_client`) REFERENCES `clients` (`id`) ON DELETE SET NULL ON UPDATE CASCADE,
  ADD CONSTRAINT `commandes_ibfk_2` FOREIGN KEY (`id_serveur`) REFERENCES `users` (`id`) ON DELETE SET NULL ON UPDATE CASCADE;

--
-- Constraints for table `ligne_commandes`
--
ALTER TABLE `ligne_commandes`
  ADD CONSTRAINT `ligne_commandes_ibfk_1` FOREIGN KEY (`id_commande`) REFERENCES `commandes` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT `ligne_commandes_ibfk_2` FOREIGN KEY (`id_plat`) REFERENCES `plats` (`id`) ON UPDATE CASCADE;

--
-- Constraints for table `logs`
--
ALTER TABLE `logs`
  ADD CONSTRAINT `logs_ibfk_1` FOREIGN KEY (`utilisateur_id`) REFERENCES `users` (`id`) ON DELETE SET NULL ON UPDATE CASCADE;

--
-- Constraints for table `plats`
--
ALTER TABLE `plats`
  ADD CONSTRAINT `plats_ibfk_1` FOREIGN KEY (`id_categorie`) REFERENCES `categories` (`id`) ON DELETE SET NULL ON UPDATE CASCADE;

--
-- Constraints for table `reservations`
--
ALTER TABLE `reservations`
  ADD CONSTRAINT `reservations_ibfk_1` FOREIGN KEY (`id_client`) REFERENCES `clients` (`id`) ON DELETE SET NULL ON UPDATE CASCADE;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
