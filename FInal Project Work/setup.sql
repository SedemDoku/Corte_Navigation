-- Create the Database
CREATE DATABASE IF NOT EXISTS corte_db;
USE corte_db;

-- 1. Users Table
CREATE TABLE `users` (
  `id` int NOT NULL AUTO_INCREMENT,
  `username` varchar(50) NOT NULL,
  `email` varchar(100) NOT NULL,
  `password` varchar(255) NOT NULL,
  `role` enum('user','admin') NOT NULL DEFAULT 'user',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `reset_token_hash` varchar(64) DEFAULT NULL,
  `reset_token_expires_at` datetime DEFAULT NULL,
  `remember_token` varchar(255) DEFAULT NULL,
  `remember_token_expires` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `username` (`username`),
  UNIQUE KEY `email` (`email`),
  KEY `idx_reset_token` (`reset_token_hash`),
  KEY `idx_remember_token` (`remember_token`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- Data for `users`
INSERT INTO `users` (`id`, `username`, `email`, `password`, `role`, `created_at`, `reset_token_hash`, `reset_token_expires_at`, `remember_token`, `remember_token_expires`) VALUES
(1, 'rosebud', 'mpawenacarl@gmail.com', '$2y$10$Y4ltxIP32sMgq7Kg4qlUrOkcrMTv7nqXk/PUZ/MU4iWQFx/rZgObG', 'admin', '2025-11-30 18:12:10', NULL, NULL, '4b706478b9fd29da85eb6dfc64a79cda7bda3dbcbc6237a677eb8106ea0aabc4', '2026-01-01 00:47:22'),
(2, 'Domino', 'sedemdoku12@gmail.com', '$2y$10$o5gnAsJzUBcRh84efHIJF.bNjgQCk8Pcyg8JmgkXwhCSewn9ki2kS', 'user', '2025-11-30 20:58:59', NULL, NULL, '2689be23046e86d674a607e8b1e22f65ca09fe50fc4c8d11347b7650857462b5', '2025-12-30 23:29:12'),
(3, 'gacucu', 'gacuti@gmail.com', '$2y$10$Fb9X2/a2H8x2Ql/XfIapZ.7jerZofuOLiJjmi/5MJ5k8f/Wf8dLIK', 'user', '2025-11-30 22:08:41', NULL, NULL, '33d96beea270c33aa0f5a783fc0936ebb48fb51c46a027a673619c4d845e31be', '2026-01-01 01:13:22');

-- 2. route_results Table
CREATE TABLE `route_results` (
  `id` int NOT NULL AUTO_INCREMENT,
  `user_id` int DEFAULT NULL,
  `request_time` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `json_data` json DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- Data for `route_results`
INSERT INTO `route_results` (`id`, `user_id`, `request_time`, `json_data`) VALUES
(1, NULL, '2025-12-01 10:14:21', 'null'),
(2, NULL, '2025-12-01 10:18:39', '{\"result\": {\"journey\": [{\"stops\": [...]}, ...], \"status\": \"success\"}}'),
(3, 3, '2025-12-01 10:45:43', '{\"result\": {\"journey\": [{\"stops\": [...]}, ...], \"status\": \"success\"}}'),
(4, 3, '2025-12-01 12:50:19', '{\"result\": {\"journey\": [{\"stops\": [...]}, ...], \"status\": \"success\"}}');

--3. saved_routes Table

CREATE TABLE IF NOT EXISTS saved_routes (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    route_result_id INT NOT NULL,
    saved_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    
    INDEX (user_id),
    INDEX (route_result_id),

    FOREIGN KEY (user_id) REFERENCES users(id)
        ON DELETE CASCADE ON UPDATE CASCADE,

    FOREIGN KEY (route_result_id) REFERENCES route_results(id)
        ON DELETE CASCADE ON UPDATE CASCADE
);

COMMIT;
