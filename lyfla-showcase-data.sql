-- LYFLA showcase data-only import (SMP)
-- Generated from the fictional local SMP showcase dataset.
--
-- Safety:
--   * INSERT IGNORE only; no CREATE/DROP TABLE.
--   * Back up the target database before importing.
--   * Target schema migrations must already be applied.
--   * Existing conflicting rows are preserved.
--   * Static assets must already be deployed under public/.
--   * This file also creates the only showcase table that may be missing:
--     landing_sections. It is safe to import after the normal app migrations.
--
-- Import this whole file in Wasmer DB Explorer -> Import.
-- Do NOT paste `php artisan ...` into the SQL command editor.
CREATE TABLE IF NOT EXISTS `landing_sections` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `page_key` varchar(40) NOT NULL DEFAULT 'home',
  `type` varchar(40) NOT NULL,
  `title` varchar(255) DEFAULT NULL,
  `subtitle` varchar(255) DEFAULT NULL,
  `body` text DEFAULT NULL,
  `media_id` bigint unsigned DEFAULT NULL,
  `content` json DEFAULT NULL,
  `is_enabled` tinyint(1) NOT NULL DEFAULT 1,
  `position` double NOT NULL DEFAULT 0,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `landing_sections_page_key_index` (`page_key`),
  KEY `landing_sections_render_index` (`page_key`,`is_enabled`,`position`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

SET FOREIGN_KEY_CHECKS=0;
SET UNIQUE_CHECKS=0;
SET NAMES utf8mb4;

-- MySQL dump 10.13  Distrib 8.4.11, for Linux (x86_64)
--
-- Host: localhost    Database: siswa_data
-- ------------------------------------------------------
-- Server version	8.4.11

/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!50503 SET NAMES utf8mb4 */;
/*!40103 SET @OLD_TIME_ZONE=@@TIME_ZONE */;
/*!40103 SET TIME_ZONE='+00:00' */;
/*!40014 SET @OLD_UNIQUE_CHECKS=@@UNIQUE_CHECKS, UNIQUE_CHECKS=0 */;
/*!40014 SET @OLD_FOREIGN_KEY_CHECKS=@@FOREIGN_KEY_CHECKS, FOREIGN_KEY_CHECKS=0 */;
/*!40101 SET @OLD_SQL_MODE=@@SQL_MODE, SQL_MODE='NO_AUTO_VALUE_ON_ZERO' */;
/*!40111 SET @OLD_SQL_NOTES=@@SQL_NOTES, SQL_NOTES=0 */;

--
-- Dumping data for table `permissions`
--
-- WHERE:  1=1

/*!40000 ALTER TABLE `permissions` DISABLE KEYS */;
INSERT  IGNORE INTO `permissions` (`id`, `name`, `guard_name`, `created_at`, `updated_at`) VALUES (1,'dashboard.admin.view','web','2026-09-29 17:09:00','2026-09-29 17:09:00'),(2,'dashboard.student.view','web','2026-09-29 17:09:00','2026-09-29 17:09:00'),(3,'student.view','web','2026-09-29 17:09:00','2026-09-29 17:09:00'),(4,'student.create','web','2026-09-29 17:09:00','2026-09-29 17:09:00'),(5,'student.update','web','2026-09-29 17:09:00','2026-09-29 17:09:00'),(6,'student.delete','web','2026-09-29 17:09:00','2026-09-29 17:09:00'),(7,'student.export','web','2026-09-29 17:09:01','2026-09-29 17:09:01'),(8,'academic_year.view','web','2026-09-29 17:09:01','2026-09-29 17:09:01'),(9,'academic_year.create','web','2026-09-29 17:09:01','2026-09-29 17:09:01'),(10,'academic_year.update','web','2026-09-29 17:09:01','2026-09-29 17:09:01'),(11,'academic_year.activate','web','2026-09-29 17:09:01','2026-09-29 17:09:01'),(12,'classroom.view','web','2026-09-29 17:09:01','2026-09-29 17:09:01'),(13,'classroom.view.all','web','2026-09-29 17:09:01','2026-09-29 17:09:01'),(14,'classroom.create','web','2026-09-29 17:09:01','2026-09-29 17:09:01'),(15,'classroom.update','web','2026-09-29 17:09:01','2026-09-29 17:09:01'),(16,'classroom.archive','web','2026-09-29 17:09:01','2026-09-29 17:09:01'),(17,'enrollment.view','web','2026-09-29 17:09:01','2026-09-29 17:09:01'),(18,'enrollment.assign','web','2026-09-29 17:09:02','2026-09-29 17:09:02'),(19,'enrollment.move','web','2026-09-29 17:09:02','2026-09-29 17:09:02'),(20,'enrollment.promote','web','2026-09-29 17:09:02','2026-09-29 17:09:02'),(21,'enrollment.graduate','web','2026-09-29 17:09:02','2026-09-29 17:09:02'),(22,'homeroom.assign','web','2026-09-29 17:09:02','2026-09-29 17:09:02'),(23,'homeroom.change','web','2026-09-29 17:09:02','2026-09-29 17:09:02'),(24,'classroom.student.view','web','2026-09-29 17:09:02','2026-09-29 17:09:02'),(25,'classroom.parent.view','web','2026-09-29 17:09:02','2026-09-29 17:09:02'),(26,'classroom.attendance.view','web','2026-09-29 17:09:02','2026-09-29 17:09:02'),(27,'classroom.attendance.manage','web','2026-09-29 17:09:02','2026-09-29 17:09:02'),(28,'classroom.academic.view','web','2026-09-29 17:09:02','2026-09-29 17:09:02'),(29,'classroom.academic.edit','web','2026-09-29 17:09:02','2026-09-29 17:09:02'),(30,'classroom.announcement.view','web','2026-09-29 17:09:02','2026-09-29 17:09:02'),(31,'classroom.announcement.create','web','2026-09-29 17:09:02','2026-09-29 17:09:02'),(32,'classroom.announcement.update','web','2026-09-29 17:09:03','2026-09-29 17:09:03'),(33,'classroom.announcement.delete','web','2026-09-29 17:09:03','2026-09-29 17:09:03'),(34,'classroom.report.view','web','2026-09-29 17:09:03','2026-09-29 17:09:03'),(35,'classroom.report.export','web','2026-09-29 17:09:03','2026-09-29 17:09:03'),(36,'guardian.view','web','2026-09-29 17:09:03','2026-09-29 17:09:03'),(37,'guardian.link','web','2026-09-29 17:09:03','2026-09-29 17:09:03'),(38,'guardian.unlink','web','2026-09-29 17:09:03','2026-09-29 17:09:03'),(39,'attendance.view','web','2026-09-29 17:09:03','2026-09-29 17:09:03'),(40,'attendance.manage','web','2026-09-29 17:09:03','2026-09-29 17:09:03'),(41,'grade.view','web','2026-09-29 17:09:03','2026-09-29 17:09:03'),(42,'grade.edit','web','2026-09-29 17:09:03','2026-09-29 17:09:03'),(43,'grade.publish','web','2026-09-29 17:09:03','2026-09-29 17:09:03'),(44,'alumni.view','web','2026-09-29 17:09:03','2026-09-29 17:09:03'),(45,'announcement.view','web','2026-09-29 17:09:04','2026-09-29 17:09:04'),(46,'announcement.create','web','2026-09-29 17:09:04','2026-09-29 17:09:04'),(47,'announcement.update','web','2026-09-29 17:09:04','2026-09-29 17:09:04'),(48,'announcement.delete','web','2026-09-29 17:09:04','2026-09-29 17:09:04'),(49,'registration.view','web','2026-09-29 17:09:04','2026-09-29 17:09:04'),(50,'registration.create','web','2026-09-29 17:09:04','2026-09-29 17:09:04'),(51,'registration.update','web','2026-09-29 17:09:04','2026-09-29 17:09:04'),(52,'registration.verify','web','2026-09-29 17:09:04','2026-09-29 17:09:04'),(53,'registration.delete','web','2026-09-29 17:09:04','2026-09-29 17:09:04'),(54,'document.view','web','2026-09-29 17:09:04','2026-09-29 17:09:04'),(55,'document.download','web','2026-09-29 17:09:04','2026-09-29 17:09:04'),(56,'document.verify','web','2026-09-29 17:09:04','2026-09-29 17:09:04'),(57,'verification.view','web','2026-09-29 17:09:04','2026-09-29 17:09:04'),(58,'verification.approve','web','2026-09-29 17:09:04','2026-09-29 17:09:04'),(59,'verification.request_revision','web','2026-09-29 17:09:04','2026-09-29 17:09:04'),(60,'report.view','web','2026-09-29 17:09:04','2026-09-29 17:09:04'),(61,'report.export','web','2026-09-29 17:09:05','2026-09-29 17:09:05'),(62,'user.view','web','2026-09-29 17:09:05','2026-09-29 17:09:05'),(63,'user.create','web','2026-09-29 17:09:05','2026-09-29 17:09:05'),(64,'user.update','web','2026-09-29 17:09:05','2026-09-29 17:09:05'),(65,'user.disable','web','2026-09-29 17:09:05','2026-09-29 17:09:05'),(66,'user.delete','web','2026-09-29 17:09:05','2026-09-29 17:09:05'),(67,'user.reset_password','web','2026-09-29 17:09:05','2026-09-29 17:09:05'),(68,'role.view','web','2026-09-29 17:09:05','2026-09-29 17:09:05'),(69,'role.create','web','2026-09-29 17:09:05','2026-09-29 17:09:05'),(70,'role.update','web','2026-09-29 17:09:05','2026-09-29 17:09:05'),(71,'role.delete','web','2026-09-29 17:09:05','2026-09-29 17:09:05'),(72,'role.assign','web','2026-09-29 17:09:05','2026-09-29 17:09:05'),(73,'role.assign.super_admin','web','2026-09-29 17:09:05','2026-09-29 17:09:05'),(74,'settings.view','web','2026-09-29 17:09:05','2026-09-29 17:09:05'),(75,'settings.update','web','2026-09-29 17:09:05','2026-09-29 17:09:05'),(76,'branding.view','web','2026-09-29 17:09:06','2026-09-29 17:09:06'),(77,'branding.update','web','2026-09-29 17:09:06','2026-09-29 17:09:06'),(78,'school.view','web','2026-09-29 17:09:06','2026-09-29 17:09:06'),(79,'school.update','web','2026-09-29 17:09:06','2026-09-29 17:09:06'),(80,'master.view','web','2026-09-29 17:09:06','2026-09-29 17:09:06'),(81,'master.create','web','2026-09-29 17:09:06','2026-09-29 17:09:06'),(82,'master.update','web','2026-09-29 17:09:06','2026-09-29 17:09:06'),(83,'master.delete','web','2026-09-29 17:09:06','2026-09-29 17:09:06'),(84,'activity.view','web','2026-09-29 17:09:06','2026-09-29 17:09:06'),(85,'system.view','web','2026-09-29 17:09:06','2026-09-29 17:09:06'),(86,'system.update','web','2026-09-29 17:09:06','2026-09-29 17:09:06'),(87,'cms.view','web','2026-09-30 08:30:13','2026-09-30 08:30:13'),(88,'cms.posts.create','web','2026-09-30 08:30:14','2026-09-30 08:30:14'),(89,'cms.posts.edit','web','2026-09-30 08:30:14','2026-09-30 08:30:14'),(90,'cms.posts.publish','web','2026-09-30 08:30:14','2026-09-30 08:30:14'),(91,'cms.pages.edit','web','2026-09-30 08:30:14','2026-09-30 08:30:14'),(92,'cms.pages.publish','web','2026-09-30 08:30:14','2026-09-30 08:30:14'),(93,'cms.media.manage','web','2026-09-30 08:30:14','2026-09-30 08:30:14'),(94,'cms.navigation.manage','web','2026-09-30 08:30:14','2026-09-30 08:30:14'),(95,'cms.themes.manage','web','2026-09-30 08:30:14','2026-09-30 08:30:14'),(96,'cms.settings.manage','web','2026-09-30 08:30:14','2026-09-30 08:30:14'),(97,'employee.view','web','2026-09-30 08:30:14','2026-09-30 08:30:14'),(98,'employee.create','web','2026-09-30 08:30:15','2026-09-30 08:30:15'),(99,'employee.update','web','2026-09-30 08:30:15','2026-09-30 08:30:15'),(100,'employee.resign','web','2026-09-30 08:30:15','2026-09-30 08:30:15'),(101,'employee.export','web','2026-09-30 08:30:15','2026-09-30 08:30:15'),(102,'module.view','web','2026-10-06 00:08:35','2026-10-06 00:08:35'),(103,'module.toggle','web','2026-10-06 00:08:35','2026-10-06 00:08:35');
/*!40000 ALTER TABLE `permissions` ENABLE KEYS */;
/*!40103 SET TIME_ZONE=@OLD_TIME_ZONE */;

/*!40101 SET SQL_MODE=@OLD_SQL_MODE */;
/*!40014 SET FOREIGN_KEY_CHECKS=@OLD_FOREIGN_KEY_CHECKS */;
/*!40014 SET UNIQUE_CHECKS=@OLD_UNIQUE_CHECKS */;
/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
/*!40111 SET SQL_NOTES=@OLD_SQL_NOTES */;

-- Dump completed on 2026-10-06  2:38:58
-- MySQL dump 10.13  Distrib 8.4.11, for Linux (x86_64)
--
-- Host: localhost    Database: siswa_data
-- ------------------------------------------------------
-- Server version	8.4.11

/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!50503 SET NAMES utf8mb4 */;
/*!40103 SET @OLD_TIME_ZONE=@@TIME_ZONE */;
/*!40103 SET TIME_ZONE='+00:00' */;
/*!40014 SET @OLD_UNIQUE_CHECKS=@@UNIQUE_CHECKS, UNIQUE_CHECKS=0 */;
/*!40014 SET @OLD_FOREIGN_KEY_CHECKS=@@FOREIGN_KEY_CHECKS, FOREIGN_KEY_CHECKS=0 */;
/*!40101 SET @OLD_SQL_MODE=@@SQL_MODE, SQL_MODE='NO_AUTO_VALUE_ON_ZERO' */;
/*!40111 SET @OLD_SQL_NOTES=@@SQL_NOTES, SQL_NOTES=0 */;

--
-- Dumping data for table `roles`
--
-- WHERE:  1=1

/*!40000 ALTER TABLE `roles` DISABLE KEYS */;
INSERT  IGNORE INTO `roles` (`id`, `name`, `guard_name`, `created_at`, `updated_at`) VALUES (1,'admin','web','2026-09-29 17:09:06','2026-09-29 17:09:06'),(2,'kesiswaan','web','2026-09-29 17:09:06','2026-09-29 17:09:06'),(3,'operator','web','2026-09-29 17:09:06','2026-09-29 17:09:06'),(4,'verifikator','web','2026-09-29 17:09:07','2026-09-29 17:09:07'),(5,'wali_kelas','web','2026-09-29 17:09:07','2026-09-29 17:09:07'),(6,'siswa','web','2026-09-29 17:09:07','2026-09-29 17:09:07'),(7,'super_admin','web','2026-09-29 17:09:07','2026-09-29 17:09:07');
/*!40000 ALTER TABLE `roles` ENABLE KEYS */;
/*!40103 SET TIME_ZONE=@OLD_TIME_ZONE */;

/*!40101 SET SQL_MODE=@OLD_SQL_MODE */;
/*!40014 SET FOREIGN_KEY_CHECKS=@OLD_FOREIGN_KEY_CHECKS */;
/*!40014 SET UNIQUE_CHECKS=@OLD_UNIQUE_CHECKS */;
/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
/*!40111 SET SQL_NOTES=@OLD_SQL_NOTES */;

-- Dump completed on 2026-10-06  2:38:59
-- MySQL dump 10.13  Distrib 8.4.11, for Linux (x86_64)
--
-- Host: localhost    Database: siswa_data
-- ------------------------------------------------------
-- Server version	8.4.11

/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!50503 SET NAMES utf8mb4 */;
/*!40103 SET @OLD_TIME_ZONE=@@TIME_ZONE */;
/*!40103 SET TIME_ZONE='+00:00' */;
/*!40014 SET @OLD_UNIQUE_CHECKS=@@UNIQUE_CHECKS, UNIQUE_CHECKS=0 */;
/*!40014 SET @OLD_FOREIGN_KEY_CHECKS=@@FOREIGN_KEY_CHECKS, FOREIGN_KEY_CHECKS=0 */;
/*!40101 SET @OLD_SQL_MODE=@@SQL_MODE, SQL_MODE='NO_AUTO_VALUE_ON_ZERO' */;
/*!40111 SET @OLD_SQL_NOTES=@@SQL_NOTES, SQL_NOTES=0 */;

--
-- Dumping data for table `role_has_permissions`
--
-- WHERE:  1=1

/*!40000 ALTER TABLE `role_has_permissions` DISABLE KEYS */;
INSERT  IGNORE INTO `role_has_permissions` (`permission_id`, `role_id`) VALUES (1,1),(3,1),(5,1),(7,1),(8,1),(9,1),(10,1),(11,1),(12,1),(13,1),(14,1),(15,1),(16,1),(17,1),(18,1),(19,1),(20,1),(21,1),(22,1),(23,1),(24,1),(25,1),(26,1),(27,1),(28,1),(29,1),(30,1),(31,1),(32,1),(33,1),(34,1),(35,1),(36,1),(37,1),(38,1),(39,1),(40,1),(41,1),(42,1),(43,1),(44,1),(45,1),(46,1),(47,1),(48,1),(49,1),(51,1),(54,1),(55,1),(56,1),(57,1),(58,1),(59,1),(60,1),(61,1),(74,1),(75,1),(76,1),(77,1),(78,1),(79,1),(80,1),(81,1),(82,1),(84,1),(87,1),(88,1),(89,1),(90,1),(91,1),(92,1),(93,1),(94,1),(95,1),(96,1),(97,1),(98,1),(99,1),(100,1),(101,1),(102,1),(103,1),(1,2),(3,2),(7,2),(8,2),(12,2),(13,2),(24,2),(25,2),(34,2),(35,2),(36,2),(39,2),(41,2),(44,2),(45,2),(54,2),(60,2),(61,2),(87,2),(88,2),(89,2),(91,2),(1,3),(3,3),(4,3),(5,3),(49,3),(51,3),(54,3),(55,3),(1,4),(49,4),(54,4),(55,4),(56,4),(57,4),(58,4),(59,4),(1,5),(12,5),(24,5),(25,5),(26,5),(27,5),(28,5),(30,5),(31,5),(32,5),(33,5),(34,5),(35,5),(41,5),(45,5);
/*!40000 ALTER TABLE `role_has_permissions` ENABLE KEYS */;
/*!40103 SET TIME_ZONE=@OLD_TIME_ZONE */;

/*!40101 SET SQL_MODE=@OLD_SQL_MODE */;
/*!40014 SET FOREIGN_KEY_CHECKS=@OLD_FOREIGN_KEY_CHECKS */;
/*!40014 SET UNIQUE_CHECKS=@OLD_UNIQUE_CHECKS */;
/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
/*!40111 SET SQL_NOTES=@OLD_SQL_NOTES */;

-- Dump completed on 2026-10-06  2:39:00
-- MySQL dump 10.13  Distrib 8.4.11, for Linux (x86_64)
--
-- Host: localhost    Database: siswa_data
-- ------------------------------------------------------
-- Server version	8.4.11

/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!50503 SET NAMES utf8mb4 */;
/*!40103 SET @OLD_TIME_ZONE=@@TIME_ZONE */;
/*!40103 SET TIME_ZONE='+00:00' */;
/*!40014 SET @OLD_UNIQUE_CHECKS=@@UNIQUE_CHECKS, UNIQUE_CHECKS=0 */;
/*!40014 SET @OLD_FOREIGN_KEY_CHECKS=@@FOREIGN_KEY_CHECKS, FOREIGN_KEY_CHECKS=0 */;
/*!40101 SET @OLD_SQL_MODE=@@SQL_MODE, SQL_MODE='NO_AUTO_VALUE_ON_ZERO' */;
/*!40111 SET @OLD_SQL_NOTES=@@SQL_NOTES, SQL_NOTES=0 */;

--
-- Dumping data for table `users`
--
-- WHERE:  email LIKE '%@demo.test'

/*!40000 ALTER TABLE `users` DISABLE KEYS */;
INSERT  IGNORE INTO `users` (`id`, `name`, `email`, `phone`, `email_verified_at`, `password`, `is_active`, `last_login_at`, `disabled_reason`, `created_by`, `remember_token`, `created_at`, `updated_at`) VALUES (11,'Agus Salim','guru.ipa2@demo.test',NULL,'2026-09-30 02:49:35','$2y$12$UC0ly0ftNDXkfmYpJ2OqCOBVLiXAoNrA5CUP4xJSFmE9/rZhjpExu',1,NULL,NULL,NULL,NULL,'2026-09-30 02:49:35','2026-09-30 02:49:35'),(12,'Maya Lestari','guru.ips1@demo.test',NULL,'2026-09-30 02:49:35','$2y$12$5r7nDowNHS0sbghEPpkvhuY649A01gHYow.y13PW3Yzwj0aMhIC4y',1,NULL,NULL,NULL,NULL,'2026-09-30 02:49:35','2026-09-30 02:49:35'),(13,'Fajar Nugroho','guru.xi1@demo.test',NULL,'2026-09-30 02:49:35','$2y$12$TnQAzl8eIbZ2T860.MQAp.wFnh.ViqpsVTsarTHbIMHVLM3D6bV8G',1,NULL,NULL,NULL,NULL,'2026-09-30 02:49:35','2026-09-30 02:49:35'),(71,'Rina Kartika','super.admin@demo.test',NULL,NULL,'$2y$12$JoC0aZDrJ.zEQe2ZPSApG.kjaYeauzHLEaCWk6.q3NDLjzycjdyjq',1,NULL,NULL,NULL,NULL,'2026-10-06 02:37:25','2026-10-06 02:37:25'),(72,'Doni Prasetyo','admin@demo.test',NULL,NULL,'$2y$12$P6zThopgf.8qpC8bzG5ztemCkoMmIDi0QDokVF3Sp1SzMfzE513Hi',1,NULL,NULL,NULL,NULL,'2026-10-06 02:37:25','2026-10-06 02:37:25'),(73,'Nur Aisyah','kesiswaan@demo.test',NULL,NULL,'$2y$12$Xu1Rzxwn7rEnxvngTGq8E.NlvviRrQ67DZlmBNOwbmcRO9.jDWpQe',1,NULL,NULL,NULL,NULL,'2026-10-06 02:37:25','2026-10-06 02:37:25'),(74,'Teguh Kurniawan','operator@demo.test',NULL,NULL,'$2y$12$sblr3OeBvvNDye1Do5WzDO4b74Nt5voS.Pis8ok3V1ahWAcnEIW1W',1,NULL,NULL,NULL,NULL,'2026-10-06 02:37:25','2026-10-06 02:37:25'),(75,'Vina Maharani','verifikator@demo.test',NULL,NULL,'$2y$12$9GfkoFTEpdrILivXTl3Pu.un/1LY7vmU7bTuxuNIaSTCo/spi7t3e',1,NULL,NULL,NULL,NULL,'2026-10-06 02:37:26','2026-10-06 02:37:26'),(76,'Budi Santoso','wali.kelas@demo.test',NULL,NULL,'$2y$12$exC/qfSN84/TXwftE7PEoevrBgAXzX5Co5xyAou2bEgbaeiYW5kGO',1,NULL,NULL,NULL,NULL,'2026-10-06 02:37:26','2026-10-06 02:37:26'),(77,'Andi Puspita','siswa0010000001@demo.test',NULL,NULL,'$2y$12$l2ROXRuDp3KGkOFfnUiosOVVhUHhMyhV5.cdaftbW7BLz8GI0YDxK',1,NULL,NULL,NULL,NULL,'2026-10-06 02:37:26','2026-10-06 02:37:26'),(78,'Budi Anggraini','siswa0010000002@demo.test',NULL,NULL,'$2y$12$oqYLAciWKPNcYpUPjz2LquXftPMkosDSVAoQXDnKit/Jg.9IQVsIq',1,NULL,NULL,NULL,NULL,'2026-10-06 02:37:27','2026-10-06 02:37:27'),(79,'Citra Setiawan','siswa0010000003@demo.test',NULL,NULL,'$2y$12$wdmuy9FWzcncNyaPw1D0PuJoxQA2avG5OrY5eYXv7izHIVoEw/mnu',1,NULL,NULL,NULL,NULL,'2026-10-06 02:37:27','2026-10-06 02:37:27'),(80,'Dimas Maulana','siswa0010000004@demo.test',NULL,NULL,'$2y$12$9.z0nbFjCAtGtPYVBfrRnewReyXh.BWBP4HtWJ5jOE.jPNxcjHHU2',1,NULL,NULL,NULL,NULL,'2026-10-06 02:37:27','2026-10-06 02:37:27'),(81,'Eka Ramadhani','siswa0010000005@demo.test',NULL,NULL,'$2y$12$/MtJHPL2xlZXabCmrehFbujkvyEcGI.MVLIkeTb05m5TZ4JHYYKk2',1,NULL,NULL,NULL,NULL,'2026-10-06 02:37:28','2026-10-06 02:37:28'),(82,'Fajar Saputra','siswa0010000006@demo.test',NULL,NULL,'$2y$12$.bs6wkn4OQ6ZyMPo3LU85u.cxycwN/5nqdr1p.qkODv4b/8sWSssm',1,NULL,NULL,NULL,NULL,'2026-10-06 02:37:28','2026-10-06 02:37:28'),(83,'Gita Wijaya','siswa0010000007@demo.test',NULL,NULL,'$2y$12$NSwTWz6WCFtRFAh9WbUoeOHjft5GObv6YSMENEegLrOlSneexeCQ6',1,NULL,NULL,NULL,NULL,'2026-10-06 02:37:29','2026-10-06 02:37:29'),(84,'Hendra Pratama','siswa0010000008@demo.test',NULL,NULL,'$2y$12$3eLXmfDPYtnL4rRzIk0ga.zWb4I2VkvIaxiuJKQ5psDnPc868XlbW',1,NULL,NULL,NULL,NULL,'2026-10-06 02:37:29','2026-10-06 02:37:29'),(85,'Indah Puspita','siswa0010000009@demo.test',NULL,NULL,'$2y$12$Cr03fH6/.k2fgLa/eNeRReMDf6.0S/XItySwmKag74N..yYuzQRta',1,NULL,NULL,NULL,NULL,'2026-10-06 02:37:29','2026-10-06 02:37:29'),(86,'Joko Anggraini','siswa0010000010@demo.test',NULL,NULL,'$2y$12$E62FpRjKKUUAk5WUjkTSbeBwzryS44sEkJo7enG4EUhbeXWlJ7gxK',1,NULL,NULL,NULL,NULL,'2026-10-06 02:37:30','2026-10-06 02:37:30'),(87,'Kartika Setiawan','siswa0010000011@demo.test',NULL,NULL,'$2y$12$ufjlBi6sSWjNNiJHnqwAOe8W5ne0mfCDocDPanscmK1Yj2U2X7YrO',1,NULL,NULL,NULL,NULL,'2026-10-06 02:37:30','2026-10-06 02:37:30'),(88,'Lukman Maulana','siswa0010000012@demo.test',NULL,NULL,'$2y$12$dtvWcNSeATb/0XmHvFPzdOhb2h3LyETvNZ82vUCTNeTkfHsenuOmq',1,NULL,NULL,NULL,NULL,'2026-10-06 02:37:31','2026-10-06 02:37:31'),(89,'Andi Ramadhani','siswa0010000013@demo.test',NULL,NULL,'$2y$12$8F2zbXhLja9VUkgi8NVX3.Wn41Ao4EEaHaTRjiPKxVaWPZ9B1QXqO',1,NULL,NULL,NULL,NULL,'2026-10-06 02:37:31','2026-10-06 02:37:31'),(90,'Budi Saputra','siswa0010000014@demo.test',NULL,NULL,'$2y$12$3wmvVhFH4jdDDZleKQ90b.OPmd7CI1PYE57EgUo4bJVj5y0HP.kyi',1,NULL,NULL,NULL,NULL,'2026-10-06 02:37:31','2026-10-06 02:37:31'),(91,'Citra Wijaya','siswa0010000015@demo.test',NULL,NULL,'$2y$12$8MBX.Ltw6V400v43tDn15.hw0W1DxnDMURjd3RAMzDe8HOsPSBw0S',1,NULL,NULL,NULL,NULL,'2026-10-06 02:37:32','2026-10-06 02:37:32'),(92,'Dimas Pratama','siswa0010000016@demo.test',NULL,NULL,'$2y$12$uX6/MJhentosliJIJJVauudrFsIcBpE9u89upbtwg5o.R/4fD3Wk.',1,NULL,NULL,NULL,NULL,'2026-10-06 02:37:32','2026-10-06 02:37:32'),(93,'Eka Puspita','siswa0010000017@demo.test',NULL,NULL,'$2y$12$Il7kd61TrYy9iDrlXsnofO.ql4l6L1SL6q9AgjoUSh8y9d4TfWqO6',1,NULL,NULL,NULL,NULL,'2026-10-06 02:37:32','2026-10-06 02:37:32'),(94,'Fajar Anggraini','siswa0010000018@demo.test',NULL,NULL,'$2y$12$7uQc/NzWrsVpEtwV3vrzqO9o2ndz7l9qi2hAvugbu3U9496ekT8Z.',1,NULL,NULL,NULL,NULL,'2026-10-06 02:37:33','2026-10-06 02:37:33'),(95,'Gita Setiawan','siswa0010000019@demo.test',NULL,NULL,'$2y$12$Qmn6fNmiCMKJ3D2uwP8kYOn37xQPxvOfV4eOVM3lgUNWsIjsvwEaW',1,NULL,NULL,NULL,NULL,'2026-10-06 02:37:33','2026-10-06 02:37:33'),(96,'Hendra Maulana','siswa0010000020@demo.test',NULL,NULL,'$2y$12$ckiONkStC5IgISLPqkfYzerZxog9Gk.iJSoyQsJAiMRNdVNVyNzRq',1,NULL,NULL,NULL,NULL,'2026-10-06 02:37:33','2026-10-06 02:37:33'),(97,'Indah Ramadhani','siswa0010000021@demo.test',NULL,NULL,'$2y$12$4q..JPw0kc2Lwddf/2KWLOuCFlklVEMyH1.1E51ulstbyTomiAdqG',1,NULL,NULL,NULL,NULL,'2026-10-06 02:37:34','2026-10-06 02:37:34'),(98,'Joko Saputra','siswa0010000022@demo.test',NULL,NULL,'$2y$12$j3PMtXGBFGj25uoEzcPC/uYePTVcQDswzaNyF78uacHIbf9uJnyQ6',1,NULL,NULL,NULL,NULL,'2026-10-06 02:37:34','2026-10-06 02:37:34'),(99,'Kartika Wijaya','siswa0010000023@demo.test',NULL,NULL,'$2y$12$A6Y7HMEMlyBJ61qmTb.93Oz7gxKWkwrX.G6pZZ3DDotyLpLtgkRNK',1,NULL,NULL,NULL,NULL,'2026-10-06 02:37:34','2026-10-06 02:37:34'),(100,'Lukman Pratama','siswa0010000024@demo.test',NULL,NULL,'$2y$12$BpbNsylN8EKv5oqj8xxLV.QV2cb.uE4IyvbszXEDGehejavto4z6G',1,NULL,NULL,NULL,NULL,'2026-10-06 02:37:35','2026-10-06 02:37:35'),(101,'Andi Puspita','siswa0010000025@demo.test',NULL,NULL,'$2y$12$KZFtkAV3E98Vr2fDeZu3X.odiD7gMa82NzOAOO6iykkLNu8EqxhGO',1,NULL,NULL,NULL,NULL,'2026-10-06 02:37:35','2026-10-06 02:37:35'),(102,'Budi Anggraini','siswa0010000026@demo.test',NULL,NULL,'$2y$12$OllgxxIoQ4aS1cjasCUuNOoWgSdR846FRVzCMvhYQd.i/v.Ec.75i',1,NULL,NULL,NULL,NULL,'2026-10-06 02:37:35','2026-10-06 02:37:35'),(103,'Citra Setiawan','siswa0010000027@demo.test',NULL,NULL,'$2y$12$Bv0vp74VPSaTJjDxoc.saeuUR4AWcQcx5csh53lImQenF5JkhPhoK',1,NULL,NULL,NULL,NULL,'2026-10-06 02:37:36','2026-10-06 02:37:36'),(104,'Dimas Maulana','siswa0010000028@demo.test',NULL,NULL,'$2y$12$536TbXgDPq8INh.jmiGOOuikTV49k1czOGGzpYPWHWYaJjgeMTRU6',1,NULL,NULL,NULL,NULL,'2026-10-06 02:37:36','2026-10-06 02:37:36'),(105,'Eka Ramadhani','siswa0010000029@demo.test',NULL,NULL,'$2y$12$/P2uSv0/cJ7LXCJMEkOmhun0tbFaqLi/wBT/cxGkRQjEW19iwD15y',1,NULL,NULL,NULL,NULL,'2026-10-06 02:37:36','2026-10-06 02:37:36'),(106,'Fajar Saputra','siswa0010000030@demo.test',NULL,NULL,'$2y$12$FwYS..PNQidkrCn2HEnB9uWObGxoCvEOM9cODJzwgnV45/ylIjcY6',1,NULL,NULL,NULL,NULL,'2026-10-06 02:37:37','2026-10-06 02:37:37'),(107,'Gita Wijaya','siswa0010000031@demo.test',NULL,NULL,'$2y$12$b/2RENH5OWyv8mzvDqx/Yu9RAzW0N8rkq9kQdpYSVtW8BgZjiEyJm',1,NULL,NULL,NULL,NULL,'2026-10-06 02:37:37','2026-10-06 02:37:37'),(108,'Hendra Pratama','siswa0010000032@demo.test',NULL,NULL,'$2y$12$wvgh9dlEVRZdfTVtKSntTe1JA2GdACybBKelMpSXpCQ/nnHCa.vsq',1,NULL,NULL,NULL,NULL,'2026-10-06 02:37:37','2026-10-06 02:37:37'),(109,'Indah Puspita','siswa0010000033@demo.test',NULL,NULL,'$2y$12$EcdvdJ9imJpq2tDqiValyu2HEIun0rymUKwn3YL5C8hvPcXpMyrVy',1,NULL,NULL,NULL,NULL,'2026-10-06 02:37:38','2026-10-06 02:37:38'),(110,'Joko Anggraini','siswa0010000034@demo.test',NULL,NULL,'$2y$12$bjSi9VO7Vh.N9nNaP0RtT.3Y9S775gqnuNAzkgL0oGstZLsSR73Xu',1,NULL,NULL,NULL,NULL,'2026-10-06 02:37:38','2026-10-06 02:37:38'),(111,'Kartika Setiawan','siswa0010000035@demo.test',NULL,NULL,'$2y$12$eOZ.yqtI2aj9U3W4UibFFuIoAO2Zw7CO.Q118OZCER/12GhqtDUq6',1,NULL,NULL,NULL,NULL,'2026-10-06 02:37:38','2026-10-06 02:37:38'),(112,'Lukman Maulana','siswa0010000036@demo.test',NULL,NULL,'$2y$12$ecndyRNpDVqrX7hiXkzX2u2Sybzn5LWVA/a1hArre.Z6n7ghUePMS',1,NULL,NULL,NULL,NULL,'2026-10-06 02:37:39','2026-10-06 02:37:39'),(113,'Sutrisno (Orang Tua Demo)','orang.tua@demo.test',NULL,NULL,'$2y$12$JakxzX9eAm55bmPDpth5EOKkovpIzcRFsGmh24I.g0c1Vs1GU8OCy',1,NULL,NULL,NULL,NULL,'2026-10-06 02:37:39','2026-10-06 02:37:39');
/*!40000 ALTER TABLE `users` ENABLE KEYS */;
/*!40103 SET TIME_ZONE=@OLD_TIME_ZONE */;

/*!40101 SET SQL_MODE=@OLD_SQL_MODE */;
/*!40014 SET FOREIGN_KEY_CHECKS=@OLD_FOREIGN_KEY_CHECKS */;
/*!40014 SET UNIQUE_CHECKS=@OLD_UNIQUE_CHECKS */;
/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
/*!40111 SET SQL_NOTES=@OLD_SQL_NOTES */;

-- Dump completed on 2026-10-06  2:39:01
-- MySQL dump 10.13  Distrib 8.4.11, for Linux (x86_64)
--
-- Host: localhost    Database: siswa_data
-- ------------------------------------------------------
-- Server version	8.4.11

/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!50503 SET NAMES utf8mb4 */;
/*!40103 SET @OLD_TIME_ZONE=@@TIME_ZONE */;
/*!40103 SET TIME_ZONE='+00:00' */;
/*!40014 SET @OLD_UNIQUE_CHECKS=@@UNIQUE_CHECKS, UNIQUE_CHECKS=0 */;
/*!40014 SET @OLD_FOREIGN_KEY_CHECKS=@@FOREIGN_KEY_CHECKS, FOREIGN_KEY_CHECKS=0 */;
/*!40101 SET @OLD_SQL_MODE=@@SQL_MODE, SQL_MODE='NO_AUTO_VALUE_ON_ZERO' */;
/*!40111 SET @OLD_SQL_NOTES=@@SQL_NOTES, SQL_NOTES=0 */;

--
-- Dumping data for table `model_has_roles`
--
-- WHERE:  model_type='App\Models\User' AND model_id IN (SELECT id FROM users WHERE email LIKE '%@demo.test')

-- MySQL dump 10.13  Distrib 8.4.11, for Linux (x86_64)
--
-- Host: localhost    Database: siswa_data
-- ------------------------------------------------------
-- Server version	8.4.11

/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!50503 SET NAMES utf8mb4 */;
/*!40103 SET @OLD_TIME_ZONE=@@TIME_ZONE */;
/*!40103 SET TIME_ZONE='+00:00' */;
/*!40014 SET @OLD_UNIQUE_CHECKS=@@UNIQUE_CHECKS, UNIQUE_CHECKS=0 */;
/*!40014 SET @OLD_FOREIGN_KEY_CHECKS=@@FOREIGN_KEY_CHECKS, FOREIGN_KEY_CHECKS=0 */;
/*!40101 SET @OLD_SQL_MODE=@@SQL_MODE, SQL_MODE='NO_AUTO_VALUE_ON_ZERO' */;
/*!40111 SET @OLD_SQL_NOTES=@@SQL_NOTES, SQL_NOTES=0 */;

--
-- Dumping data for table `settings`
--
-- WHERE:  1=1

/*!40000 ALTER TABLE `settings` DISABLE KEYS */;
INSERT  IGNORE INTO `settings` (`id`, `key`, `value`, `type`, `group`, `label`, `hint`, `sort_order`, `created_at`, `updated_at`) VALUES (1,'app.name','SMP 1 LYFLA','string','branding','Nama Aplikasi','Ditampilkan di sidebar, judul halaman, dan PDF.',10,'2026-10-06 00:00:28','2026-10-06 00:00:28'),(2,'app.short_name','LYFLA','string','branding','Nama Pendek','Dipakai pada PWA dan layar sempit.',20,'2026-10-06 00:00:28','2026-10-06 00:00:28'),(3,'school.name','SMP 1 LYFLA','string','school','Nama Sekolah','Tercetak di header laporan.',10,'2026-10-06 00:00:28','2026-10-06 02:36:51'),(4,'school.npsn','20219876','string','school','NPSN','Nomor Pokok Sekolah Nasional.',20,'2026-10-06 00:00:28','2026-10-06 00:00:28'),(5,'school.address','Jl. Pendidikan No. 17','text','school','Alamat','Tercetak di footer laporan.',30,'2026-10-06 00:00:28','2026-10-06 00:00:28'),(6,'school.city','Bandung','string','school','Kabupaten / Kota','',50,'2026-10-06 00:00:28','2026-10-06 00:00:28'),(7,'school.district','Coblong','string','school','Kecamatan','',60,'2026-10-06 00:00:28','2026-10-06 00:00:28'),(8,'school.postal_code','40132','string','school','Kode Pos','',70,'2026-10-06 00:00:28','2026-10-06 00:00:28'),(9,'school.province','Jawa Barat','string','school','Provinsi','',40,'2026-10-06 00:00:28','2026-10-06 00:00:28'),(10,'school.email','info@smpn1.sch.id','string','school','Email','',80,'2026-10-06 00:00:28','2026-10-06 02:36:51'),(11,'school.phone','(022) 720-1234','string','school','Nomor Telepon','',90,'2026-10-06 00:00:28','2026-10-06 00:00:28'),(12,'school.website','https://smpn1.sch.id','string','school','Website','Tanpa https:// misal: sekolah.sch.id',100,'2026-10-06 00:00:28','2026-10-06 02:36:51'),(13,'school.headmaster','Lucky Noor Fadilla','string','school','Nama Kepala Sekolah','Tercetak di laporan.',110,'2026-10-06 00:00:28','2026-10-06 00:00:28'),(14,'app.tagline','Pendaftaran, akademik, dan informasi sekolah dalam satu portal.','string','branding','Tagline','Kalimat singkat di bawah nama aplikasi.',30,'2026-10-06 02:36:51','2026-10-06 02:36:51');
/*!40000 ALTER TABLE `settings` ENABLE KEYS */;
UPDATE `settings` SET `value`='SMP 1 LYFLA', `updated_at`=CURRENT_TIMESTAMP WHERE `key`='app.name';
UPDATE `settings` SET `value`='LYFLA', `updated_at`=CURRENT_TIMESTAMP WHERE `key`='app.short_name';
UPDATE `settings` SET `value`='SMP 1 LYFLA', `updated_at`=CURRENT_TIMESTAMP WHERE `key`='school.name';
/*!40103 SET TIME_ZONE=@OLD_TIME_ZONE */;

/*!40101 SET SQL_MODE=@OLD_SQL_MODE */;
/*!40014 SET FOREIGN_KEY_CHECKS=@OLD_FOREIGN_KEY_CHECKS */;
/*!40014 SET UNIQUE_CHECKS=@OLD_UNIQUE_CHECKS */;
/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
/*!40111 SET SQL_NOTES=@OLD_SQL_NOTES */;

-- Dump completed on 2026-10-06  2:39:02
-- MySQL dump 10.13  Distrib 8.4.11, for Linux (x86_64)
--
-- Host: localhost    Database: siswa_data
-- ------------------------------------------------------
-- Server version	8.4.11

/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!50503 SET NAMES utf8mb4 */;
/*!40103 SET @OLD_TIME_ZONE=@@TIME_ZONE */;
/*!40103 SET TIME_ZONE='+00:00' */;
/*!40014 SET @OLD_UNIQUE_CHECKS=@@UNIQUE_CHECKS, UNIQUE_CHECKS=0 */;
/*!40014 SET @OLD_FOREIGN_KEY_CHECKS=@@FOREIGN_KEY_CHECKS, FOREIGN_KEY_CHECKS=0 */;
/*!40101 SET @OLD_SQL_MODE=@@SQL_MODE, SQL_MODE='NO_AUTO_VALUE_ON_ZERO' */;
/*!40111 SET @OLD_SQL_NOTES=@@SQL_NOTES, SQL_NOTES=0 */;

--
-- Dumping data for table `academic_years`
--
-- WHERE:  name IN ('2026/2027','2027/2028')

/*!40000 ALTER TABLE `academic_years` DISABLE KEYS */;
INSERT  IGNORE INTO `academic_years` (`id`, `name`, `status`, `start_date`, `end_date`, `is_active`, `is_default`, `notes`, `created_at`, `updated_at`) VALUES (1,'2026/2027','active','2026-07-01','2027-06-30',1,1,NULL,'2026-09-30 02:49:33','2026-10-05 10:17:27'),(3,'2027/2028','upcoming','2027-07-01','2028-06-30',0,0,NULL,'2026-10-05 10:17:27','2026-10-05 10:17:27');
/*!40000 ALTER TABLE `academic_years` ENABLE KEYS */;
/*!40103 SET TIME_ZONE=@OLD_TIME_ZONE */;

/*!40101 SET SQL_MODE=@OLD_SQL_MODE */;
/*!40014 SET FOREIGN_KEY_CHECKS=@OLD_FOREIGN_KEY_CHECKS */;
/*!40014 SET UNIQUE_CHECKS=@OLD_UNIQUE_CHECKS */;
/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
/*!40111 SET SQL_NOTES=@OLD_SQL_NOTES */;

-- Dump completed on 2026-10-06  2:39:02
-- MySQL dump 10.13  Distrib 8.4.11, for Linux (x86_64)
--
-- Host: localhost    Database: siswa_data
-- ------------------------------------------------------
-- Server version	8.4.11

/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!50503 SET NAMES utf8mb4 */;
/*!40103 SET @OLD_TIME_ZONE=@@TIME_ZONE */;
/*!40103 SET TIME_ZONE='+00:00' */;
/*!40014 SET @OLD_UNIQUE_CHECKS=@@UNIQUE_CHECKS, UNIQUE_CHECKS=0 */;
/*!40014 SET @OLD_FOREIGN_KEY_CHECKS=@@FOREIGN_KEY_CHECKS, FOREIGN_KEY_CHECKS=0 */;
/*!40101 SET @OLD_SQL_MODE=@@SQL_MODE, SQL_MODE='NO_AUTO_VALUE_ON_ZERO' */;
/*!40111 SET @OLD_SQL_NOTES=@@SQL_NOTES, SQL_NOTES=0 */;

--
-- Dumping data for table `departments`
--
-- WHERE:  code IN ('RPL','TKJ','DKV','ADMIN','ILMUA','ILMUS','BAHAS')

/*!40000 ALTER TABLE `departments` DISABLE KEYS */;
INSERT  IGNORE INTO `departments` (`id`, `name`, `code`, `created_at`, `updated_at`) VALUES (3,'Rekayasa Perangkat Lunak','RPL','2026-10-05 10:17:27','2026-10-05 10:17:27'),(4,'Teknik Komputer dan Jaringan','TKJ','2026-10-05 10:17:27','2026-10-05 10:17:27'),(5,'Desain Komunikasi Visual','DKV','2026-10-05 10:17:27','2026-10-05 10:17:27'),(6,'Ilmu Alam','ILMUA','2026-10-06 00:00:28','2026-10-06 00:00:28'),(7,'Ilmu Sosial','ILMUS','2026-10-06 00:00:28','2026-10-06 00:00:28'),(8,'Bahasa','BAHAS','2026-10-06 00:00:28','2026-10-06 00:00:28'),(9,'Administrasi','ADMIN','2026-10-06 00:00:32','2026-10-06 00:00:32');
/*!40000 ALTER TABLE `departments` ENABLE KEYS */;
/*!40103 SET TIME_ZONE=@OLD_TIME_ZONE */;

/*!40101 SET SQL_MODE=@OLD_SQL_MODE */;
/*!40014 SET FOREIGN_KEY_CHECKS=@OLD_FOREIGN_KEY_CHECKS */;
/*!40014 SET UNIQUE_CHECKS=@OLD_UNIQUE_CHECKS */;
/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
/*!40111 SET SQL_NOTES=@OLD_SQL_NOTES */;

-- Dump completed on 2026-10-06  2:39:03
-- MySQL dump 10.13  Distrib 8.4.11, for Linux (x86_64)
--
-- Host: localhost    Database: siswa_data
-- ------------------------------------------------------
-- Server version	8.4.11

/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!50503 SET NAMES utf8mb4 */;
/*!40103 SET @OLD_TIME_ZONE=@@TIME_ZONE */;
/*!40103 SET TIME_ZONE='+00:00' */;
/*!40014 SET @OLD_UNIQUE_CHECKS=@@UNIQUE_CHECKS, UNIQUE_CHECKS=0 */;
/*!40014 SET @OLD_FOREIGN_KEY_CHECKS=@@FOREIGN_KEY_CHECKS, FOREIGN_KEY_CHECKS=0 */;
/*!40101 SET @OLD_SQL_MODE=@@SQL_MODE, SQL_MODE='NO_AUTO_VALUE_ON_ZERO' */;
/*!40111 SET @OLD_SQL_NOTES=@@SQL_NOTES, SQL_NOTES=0 */;

--
-- Dumping data for table `classes`
--
-- WHERE:  code LIKE 'RPL-%' OR code LIKE 'TKJ-%' OR code LIKE 'DKV-%'

/*!40000 ALTER TABLE `classes` DISABLE KEYS */;
INSERT  IGNORE INTO `classes` (`id`, `academic_year_id`, `department_id`, `name`, `code`, `level`, `capacity`, `room`, `status`, `notes`, `created_at`, `updated_at`) VALUES (5,1,3,'X RPL 1','RPL-10-1-A','X',32,'R-201','active',NULL,'2026-10-05 10:17:27','2026-10-05 10:17:27'),(6,1,3,'X RPL 2','RPL-10-2-A','X',32,'R-202','active',NULL,'2026-10-05 10:17:27','2026-10-05 10:17:27'),(7,1,3,'XI RPL 1','RPL-11-1-A','XI',32,'R-203','active',NULL,'2026-10-05 10:17:27','2026-10-05 10:17:27'),(8,1,3,'XII RPL 1','RPL-12-1-A','XII',32,'R-204','active',NULL,'2026-10-05 10:17:27','2026-10-05 10:17:27'),(9,1,4,'X TKJ 1','TKJ-10-1-A','X',28,'L-101','active',NULL,'2026-10-05 10:17:27','2026-10-05 10:17:27'),(10,1,5,'X DKV 1','DKV-10-1-A','X',24,'D-102','active',NULL,'2026-10-05 10:17:27','2026-10-05 10:17:27'),(11,3,3,'XI RPL 1','RPL-11-1-B','XI',32,'R-203','active',NULL,'2026-10-05 10:17:27','2026-10-05 10:17:27'),(12,3,3,'XII RPL 1','RPL-12-1-B','XII',32,'R-204','active',NULL,'2026-10-05 10:17:27','2026-10-05 10:17:27'),(13,1,3,'VII RPL 1','RPL-7-1-A','VII',32,'R-201','active',NULL,'2026-10-06 02:37:24','2026-10-06 02:37:24'),(14,1,3,'VII RPL 2','RPL-7-2-A','VII',32,'R-202','active',NULL,'2026-10-06 02:37:24','2026-10-06 02:37:24'),(15,1,3,'VIII RPL 1','RPL-8-1-A','VIII',32,'R-203','active',NULL,'2026-10-06 02:37:24','2026-10-06 02:37:24'),(16,1,3,'IX RPL 1','RPL-9-1-A','IX',32,'R-204','active',NULL,'2026-10-06 02:37:24','2026-10-06 02:37:24'),(17,1,4,'VII TKJ 1','TKJ-7-1-A','VII',28,'L-101','active',NULL,'2026-10-06 02:37:24','2026-10-06 02:37:24'),(18,1,5,'VII DKV 1','DKV-7-1-A','VII',24,'D-102','active',NULL,'2026-10-06 02:37:24','2026-10-06 02:37:24');
/*!40000 ALTER TABLE `classes` ENABLE KEYS */;
/*!40103 SET TIME_ZONE=@OLD_TIME_ZONE */;

/*!40101 SET SQL_MODE=@OLD_SQL_MODE */;
/*!40014 SET FOREIGN_KEY_CHECKS=@OLD_FOREIGN_KEY_CHECKS */;
/*!40014 SET UNIQUE_CHECKS=@OLD_UNIQUE_CHECKS */;
/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
/*!40111 SET SQL_NOTES=@OLD_SQL_NOTES */;

-- Dump completed on 2026-10-06  2:39:04
-- MySQL dump 10.13  Distrib 8.4.11, for Linux (x86_64)
--
-- Host: localhost    Database: siswa_data
-- ------------------------------------------------------
-- Server version	8.4.11

/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!50503 SET NAMES utf8mb4 */;
/*!40103 SET @OLD_TIME_ZONE=@@TIME_ZONE */;
/*!40103 SET TIME_ZONE='+00:00' */;
/*!40014 SET @OLD_UNIQUE_CHECKS=@@UNIQUE_CHECKS, UNIQUE_CHECKS=0 */;
/*!40014 SET @OLD_FOREIGN_KEY_CHECKS=@@FOREIGN_KEY_CHECKS, FOREIGN_KEY_CHECKS=0 */;
/*!40101 SET @OLD_SQL_MODE=@@SQL_MODE, SQL_MODE='NO_AUTO_VALUE_ON_ZERO' */;
/*!40111 SET @OLD_SQL_NOTES=@@SQL_NOTES, SQL_NOTES=0 */;

--
-- Dumping data for table `subjects`
--
-- WHERE:  1=1

/*!40000 ALTER TABLE `subjects` DISABLE KEYS */;
INSERT  IGNORE INTO `subjects` (`id`, `name`, `code`, `grade_level`, `created_at`, `updated_at`) VALUES (1,'Matematika Wajib','MTK-W','X','2026-09-30 02:49:33','2026-09-30 02:49:33'),(2,'Bahasa Indonesia','BINDO','X','2026-09-30 02:49:33','2026-09-30 02:49:33'),(3,'Bahasa Inggris','BING','X','2026-09-30 02:49:33','2026-09-30 02:49:33'),(4,'Fisika','FIS','X','2026-09-30 02:49:33','2026-09-30 02:49:33'),(5,'Kimia','KIM','X','2026-09-30 02:49:33','2026-09-30 02:49:33'),(6,'Biologi','BIO','X','2026-09-30 02:49:33','2026-09-30 02:49:33'),(7,'Sejarah Indonesia','SEJARAH','X','2026-09-30 02:49:33','2026-09-30 02:49:33'),(8,'Informatika','INF','X','2026-09-30 02:49:33','2026-09-30 02:49:33'),(9,'Matematika','MTK','XII','2026-10-05 10:17:43','2026-10-05 10:17:43'),(10,'Bahasa Indonesia','BID','XII','2026-10-05 10:17:43','2026-10-05 10:17:43'),(11,'Bahasa Inggris','BIG','XII','2026-10-05 10:17:43','2026-10-05 10:17:43'),(12,'Pendidikan Pancasila','PANC','XII','2026-10-05 10:17:43','2026-10-05 10:17:43'),(13,'Pendidikan Jasmani','PJOK','XII','2026-10-05 10:17:43','2026-10-05 10:17:43'),(14,'Ekonomi','EKONOMI',NULL,'2026-10-06 00:00:28','2026-10-06 00:00:28'),(15,'Sosiologi','SOSIOLOG',NULL,'2026-10-06 00:00:28','2026-10-06 00:00:28'),(16,'Geografi','GEOGRAFI',NULL,'2026-10-06 00:00:28','2026-10-06 00:00:28');
/*!40000 ALTER TABLE `subjects` ENABLE KEYS */;
/*!40103 SET TIME_ZONE=@OLD_TIME_ZONE */;

/*!40101 SET SQL_MODE=@OLD_SQL_MODE */;
/*!40014 SET FOREIGN_KEY_CHECKS=@OLD_FOREIGN_KEY_CHECKS */;
/*!40014 SET UNIQUE_CHECKS=@OLD_UNIQUE_CHECKS */;
/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
/*!40111 SET SQL_NOTES=@OLD_SQL_NOTES */;

-- Dump completed on 2026-10-06  2:39:04
-- MySQL dump 10.13  Distrib 8.4.11, for Linux (x86_64)
--
-- Host: localhost    Database: siswa_data
-- ------------------------------------------------------
-- Server version	8.4.11

/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!50503 SET NAMES utf8mb4 */;
/*!40103 SET @OLD_TIME_ZONE=@@TIME_ZONE */;
/*!40103 SET TIME_ZONE='+00:00' */;
/*!40014 SET @OLD_UNIQUE_CHECKS=@@UNIQUE_CHECKS, UNIQUE_CHECKS=0 */;
/*!40014 SET @OLD_FOREIGN_KEY_CHECKS=@@FOREIGN_KEY_CHECKS, FOREIGN_KEY_CHECKS=0 */;
/*!40101 SET @OLD_SQL_MODE=@@SQL_MODE, SQL_MODE='NO_AUTO_VALUE_ON_ZERO' */;
/*!40111 SET @OLD_SQL_NOTES=@@SQL_NOTES, SQL_NOTES=0 */;

--
-- Dumping data for table `semesters`
--
-- WHERE:  academic_year_id IN (SELECT id FROM academic_years WHERE name IN ('2026/2027','2027/2028'))

-- MySQL dump 10.13  Distrib 8.4.11, for Linux (x86_64)
--
-- Host: localhost    Database: siswa_data
-- ------------------------------------------------------
-- Server version	8.4.11

/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!50503 SET NAMES utf8mb4 */;
/*!40103 SET @OLD_TIME_ZONE=@@TIME_ZONE */;
/*!40103 SET TIME_ZONE='+00:00' */;
/*!40014 SET @OLD_UNIQUE_CHECKS=@@UNIQUE_CHECKS, UNIQUE_CHECKS=0 */;
/*!40014 SET @OLD_FOREIGN_KEY_CHECKS=@@FOREIGN_KEY_CHECKS, FOREIGN_KEY_CHECKS=0 */;
/*!40101 SET @OLD_SQL_MODE=@@SQL_MODE, SQL_MODE='NO_AUTO_VALUE_ON_ZERO' */;
/*!40111 SET @OLD_SQL_NOTES=@@SQL_NOTES, SQL_NOTES=0 */;

--
-- Dumping data for table `grade_categories`
--
-- WHERE:  academic_year_id IN (SELECT id FROM academic_years WHERE name IN ('2026/2027','2028/2028','2027/2028'))

-- MySQL dump 10.13  Distrib 8.4.11, for Linux (x86_64)
--
-- Host: localhost    Database: siswa_data
-- ------------------------------------------------------
-- Server version	8.4.11

/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!50503 SET NAMES utf8mb4 */;
/*!40103 SET @OLD_TIME_ZONE=@@TIME_ZONE */;
/*!40103 SET TIME_ZONE='+00:00' */;
/*!40014 SET @OLD_UNIQUE_CHECKS=@@UNIQUE_CHECKS, UNIQUE_CHECKS=0 */;
/*!40014 SET @OLD_FOREIGN_KEY_CHECKS=@@FOREIGN_KEY_CHECKS, FOREIGN_KEY_CHECKS=0 */;
/*!40101 SET @OLD_SQL_MODE=@@SQL_MODE, SQL_MODE='NO_AUTO_VALUE_ON_ZERO' */;
/*!40111 SET @OLD_SQL_NOTES=@@SQL_NOTES, SQL_NOTES=0 */;

--
-- Dumping data for table `students`
--
-- WHERE:  user_id IN (SELECT id FROM users WHERE email LIKE '%@demo.test')

-- MySQL dump 10.13  Distrib 8.4.11, for Linux (x86_64)
--
-- Host: localhost    Database: siswa_data
-- ------------------------------------------------------
-- Server version	8.4.11

/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!50503 SET NAMES utf8mb4 */;
/*!40103 SET @OLD_TIME_ZONE=@@TIME_ZONE */;
/*!40103 SET TIME_ZONE='+00:00' */;
/*!40014 SET @OLD_UNIQUE_CHECKS=@@UNIQUE_CHECKS, UNIQUE_CHECKS=0 */;
/*!40014 SET @OLD_FOREIGN_KEY_CHECKS=@@FOREIGN_KEY_CHECKS, FOREIGN_KEY_CHECKS=0 */;
/*!40101 SET @OLD_SQL_MODE=@@SQL_MODE, SQL_MODE='NO_AUTO_VALUE_ON_ZERO' */;
/*!40111 SET @OLD_SQL_NOTES=@@SQL_NOTES, SQL_NOTES=0 */;

--
-- Dumping data for table `enrollments`
--
-- WHERE:  student_id IN (SELECT id FROM students WHERE user_id IN (SELECT id FROM users WHERE email LIKE '%@demo.test')) OR classroom_id IN (SELECT id FROM classes WHERE code LIKE 'RPL-%' OR code LIKE 'TKJ-%' OR code LIKE 'DKV-%')

-- MySQL dump 10.13  Distrib 8.4.11, for Linux (x86_64)
--
-- Host: localhost    Database: siswa_data
-- ------------------------------------------------------
-- Server version	8.4.11

/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!50503 SET NAMES utf8mb4 */;
/*!40103 SET @OLD_TIME_ZONE=@@TIME_ZONE */;
/*!40103 SET TIME_ZONE='+00:00' */;
/*!40014 SET @OLD_UNIQUE_CHECKS=@@UNIQUE_CHECKS, UNIQUE_CHECKS=0 */;
/*!40014 SET @OLD_FOREIGN_KEY_CHECKS=@@FOREIGN_KEY_CHECKS, FOREIGN_KEY_CHECKS=0 */;
/*!40101 SET @OLD_SQL_MODE=@@SQL_MODE, SQL_MODE='NO_AUTO_VALUE_ON_ZERO' */;
/*!40111 SET @OLD_SQL_NOTES=@@SQL_NOTES, SQL_NOTES=0 */;

--
-- Dumping data for table `homeroom_assignments`
--
-- WHERE:  classroom_id IN (SELECT id FROM classes WHERE code LIKE 'RPL-%' OR code LIKE 'TKJ-%' OR code LIKE 'DKV-%')

-- MySQL dump 10.13  Distrib 8.4.11, for Linux (x86_64)
--
-- Host: localhost    Database: siswa_data
-- ------------------------------------------------------
-- Server version	8.4.11

/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!50503 SET NAMES utf8mb4 */;
/*!40103 SET @OLD_TIME_ZONE=@@TIME_ZONE */;
/*!40103 SET TIME_ZONE='+00:00' */;
/*!40014 SET @OLD_UNIQUE_CHECKS=@@UNIQUE_CHECKS, UNIQUE_CHECKS=0 */;
/*!40014 SET @OLD_FOREIGN_KEY_CHECKS=@@FOREIGN_KEY_CHECKS, FOREIGN_KEY_CHECKS=0 */;
/*!40101 SET @OLD_SQL_MODE=@@SQL_MODE, SQL_MODE='NO_AUTO_VALUE_ON_ZERO' */;
/*!40111 SET @OLD_SQL_NOTES=@@SQL_NOTES, SQL_NOTES=0 */;

--
-- Dumping data for table `parents`
--
-- WHERE:  id IN (SELECT parent_id FROM guardian_relationships WHERE student_id IN (SELECT id FROM students WHERE user_id IN (SELECT id FROM users WHERE email LIKE '%@demo.test')))

-- MySQL dump 10.13  Distrib 8.4.11, for Linux (x86_64)
--
-- Host: localhost    Database: siswa_data
-- ------------------------------------------------------
-- Server version	8.4.11

/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!50503 SET NAMES utf8mb4 */;
/*!40103 SET @OLD_TIME_ZONE=@@TIME_ZONE */;
/*!40103 SET TIME_ZONE='+00:00' */;
/*!40014 SET @OLD_UNIQUE_CHECKS=@@UNIQUE_CHECKS, UNIQUE_CHECKS=0 */;
/*!40014 SET @OLD_FOREIGN_KEY_CHECKS=@@FOREIGN_KEY_CHECKS, FOREIGN_KEY_CHECKS=0 */;
/*!40101 SET @OLD_SQL_MODE=@@SQL_MODE, SQL_MODE='NO_AUTO_VALUE_ON_ZERO' */;
/*!40111 SET @OLD_SQL_NOTES=@@SQL_NOTES, SQL_NOTES=0 */;

--
-- Dumping data for table `guardian_relationships`
--
-- WHERE:  student_id IN (SELECT id FROM students WHERE user_id IN (SELECT id FROM users WHERE email LIKE '%@demo.test')) OR user_id IN (SELECT id FROM users WHERE email LIKE '%@demo.test')

-- MySQL dump 10.13  Distrib 8.4.11, for Linux (x86_64)
--
-- Host: localhost    Database: siswa_data
-- ------------------------------------------------------
-- Server version	8.4.11

/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!50503 SET NAMES utf8mb4 */;
/*!40103 SET @OLD_TIME_ZONE=@@TIME_ZONE */;
/*!40103 SET TIME_ZONE='+00:00' */;
/*!40014 SET @OLD_UNIQUE_CHECKS=@@UNIQUE_CHECKS, UNIQUE_CHECKS=0 */;
/*!40014 SET @OLD_FOREIGN_KEY_CHECKS=@@FOREIGN_KEY_CHECKS, FOREIGN_KEY_CHECKS=0 */;
/*!40101 SET @OLD_SQL_MODE=@@SQL_MODE, SQL_MODE='NO_AUTO_VALUE_ON_ZERO' */;
/*!40111 SET @OLD_SQL_NOTES=@@SQL_NOTES, SQL_NOTES=0 */;

--
-- Dumping data for table `registrations`
--
-- WHERE:  student_id IN (SELECT id FROM students WHERE user_id IN (SELECT id FROM users WHERE email LIKE '%@demo.test'))

-- MySQL dump 10.13  Distrib 8.4.11, for Linux (x86_64)
--
-- Host: localhost    Database: siswa_data
-- ------------------------------------------------------
-- Server version	8.4.11

/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!50503 SET NAMES utf8mb4 */;
/*!40103 SET @OLD_TIME_ZONE=@@TIME_ZONE */;
/*!40103 SET TIME_ZONE='+00:00' */;
/*!40014 SET @OLD_UNIQUE_CHECKS=@@UNIQUE_CHECKS, UNIQUE_CHECKS=0 */;
/*!40014 SET @OLD_FOREIGN_KEY_CHECKS=@@FOREIGN_KEY_CHECKS, FOREIGN_KEY_CHECKS=0 */;
/*!40101 SET @OLD_SQL_MODE=@@SQL_MODE, SQL_MODE='NO_AUTO_VALUE_ON_ZERO' */;
/*!40111 SET @OLD_SQL_NOTES=@@SQL_NOTES, SQL_NOTES=0 */;

--
-- Dumping data for table `grades`
--
-- WHERE:  enrollment_id IN (SELECT id FROM enrollments WHERE student_id IN (SELECT id FROM students WHERE user_id IN (SELECT id FROM users WHERE email LIKE '%@demo.test')))

-- MySQL dump 10.13  Distrib 8.4.11, for Linux (x86_64)
--
-- Host: localhost    Database: siswa_data
-- ------------------------------------------------------
-- Server version	8.4.11

/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!50503 SET NAMES utf8mb4 */;
/*!40103 SET @OLD_TIME_ZONE=@@TIME_ZONE */;
/*!40103 SET TIME_ZONE='+00:00' */;
/*!40014 SET @OLD_UNIQUE_CHECKS=@@UNIQUE_CHECKS, UNIQUE_CHECKS=0 */;
/*!40014 SET @OLD_FOREIGN_KEY_CHECKS=@@FOREIGN_KEY_CHECKS, FOREIGN_KEY_CHECKS=0 */;
/*!40101 SET @OLD_SQL_MODE=@@SQL_MODE, SQL_MODE='NO_AUTO_VALUE_ON_ZERO' */;
/*!40111 SET @OLD_SQL_NOTES=@@SQL_NOTES, SQL_NOTES=0 */;

--
-- Dumping data for table `attendance_sessions`
--
-- WHERE:  classroom_id IN (SELECT id FROM classes WHERE code LIKE 'RPL-%' OR code LIKE 'TKJ-%' OR code LIKE 'DKV-%')

-- MySQL dump 10.13  Distrib 8.4.11, for Linux (x86_64)
--
-- Host: localhost    Database: siswa_data
-- ------------------------------------------------------
-- Server version	8.4.11

/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!50503 SET NAMES utf8mb4 */;
/*!40103 SET @OLD_TIME_ZONE=@@TIME_ZONE */;
/*!40103 SET TIME_ZONE='+00:00' */;
/*!40014 SET @OLD_UNIQUE_CHECKS=@@UNIQUE_CHECKS, UNIQUE_CHECKS=0 */;
/*!40014 SET @OLD_FOREIGN_KEY_CHECKS=@@FOREIGN_KEY_CHECKS, FOREIGN_KEY_CHECKS=0 */;
/*!40101 SET @OLD_SQL_MODE=@@SQL_MODE, SQL_MODE='NO_AUTO_VALUE_ON_ZERO' */;
/*!40111 SET @OLD_SQL_NOTES=@@SQL_NOTES, SQL_NOTES=0 */;

--
-- Dumping data for table `attendance_records`
--
-- WHERE:  session_id IN (SELECT id FROM attendance_sessions WHERE classroom_id IN (SELECT id FROM classes WHERE code LIKE 'RPL-%' OR code LIKE 'TKJ-%' OR code LIKE 'DKV-%'))

-- MySQL dump 10.13  Distrib 8.4.11, for Linux (x86_64)
--
-- Host: localhost    Database: siswa_data
-- ------------------------------------------------------
-- Server version	8.4.11

/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!50503 SET NAMES utf8mb4 */;
/*!40103 SET @OLD_TIME_ZONE=@@TIME_ZONE */;
/*!40103 SET TIME_ZONE='+00:00' */;
/*!40014 SET @OLD_UNIQUE_CHECKS=@@UNIQUE_CHECKS, UNIQUE_CHECKS=0 */;
/*!40014 SET @OLD_FOREIGN_KEY_CHECKS=@@FOREIGN_KEY_CHECKS, FOREIGN_KEY_CHECKS=0 */;
/*!40101 SET @OLD_SQL_MODE=@@SQL_MODE, SQL_MODE='NO_AUTO_VALUE_ON_ZERO' */;
/*!40111 SET @OLD_SQL_NOTES=@@SQL_NOTES, SQL_NOTES=0 */;

--
-- Dumping data for table `classroom_announcements`
--
-- WHERE:  classroom_id IN (SELECT id FROM classes WHERE code LIKE 'RPL-%' OR code LIKE 'TKJ-%' OR code LIKE 'DKV-%')

-- MySQL dump 10.13  Distrib 8.4.11, for Linux (x86_64)
--
-- Host: localhost    Database: siswa_data
-- ------------------------------------------------------
-- Server version	8.4.11

/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!50503 SET NAMES utf8mb4 */;
/*!40103 SET @OLD_TIME_ZONE=@@TIME_ZONE */;
/*!40103 SET TIME_ZONE='+00:00' */;
/*!40014 SET @OLD_UNIQUE_CHECKS=@@UNIQUE_CHECKS, UNIQUE_CHECKS=0 */;
/*!40014 SET @OLD_FOREIGN_KEY_CHECKS=@@FOREIGN_KEY_CHECKS, FOREIGN_KEY_CHECKS=0 */;
/*!40101 SET @OLD_SQL_MODE=@@SQL_MODE, SQL_MODE='NO_AUTO_VALUE_ON_ZERO' */;
/*!40111 SET @OLD_SQL_NOTES=@@SQL_NOTES, SQL_NOTES=0 */;

--
-- Dumping data for table `employees`
--
-- WHERE:  user_id IN (SELECT id FROM users WHERE email LIKE '%@demo.test')

-- MySQL dump 10.13  Distrib 8.4.11, for Linux (x86_64)
--
-- Host: localhost    Database: siswa_data
-- ------------------------------------------------------
-- Server version	8.4.11

/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!50503 SET NAMES utf8mb4 */;
/*!40103 SET @OLD_TIME_ZONE=@@TIME_ZONE */;
/*!40103 SET TIME_ZONE='+00:00' */;
/*!40014 SET @OLD_UNIQUE_CHECKS=@@UNIQUE_CHECKS, UNIQUE_CHECKS=0 */;
/*!40014 SET @OLD_FOREIGN_KEY_CHECKS=@@FOREIGN_KEY_CHECKS, FOREIGN_KEY_CHECKS=0 */;
/*!40101 SET @OLD_SQL_MODE=@@SQL_MODE, SQL_MODE='NO_AUTO_VALUE_ON_ZERO' */;
/*!40111 SET @OLD_SQL_NOTES=@@SQL_NOTES, SQL_NOTES=0 */;

--
-- Dumping data for table `cms_categories`
--
-- WHERE:  slug IN ('kegiatan','pengumuman')

/*!40000 ALTER TABLE `cms_categories` DISABLE KEYS */;
INSERT  IGNORE INTO `cms_categories` (`id`, `name`, `slug`, `description`, `sort_order`, `created_at`, `updated_at`) VALUES (1,'Kegiatan','kegiatan','Kegiatan dan jejak sekolah',0,'2026-10-05 13:20:11','2026-10-05 13:20:11'),(2,'Pengumuman','pengumuman',NULL,0,'2026-10-06 00:00:28','2026-10-06 00:00:28');
/*!40000 ALTER TABLE `cms_categories` ENABLE KEYS */;
/*!40103 SET TIME_ZONE=@OLD_TIME_ZONE */;

/*!40101 SET SQL_MODE=@OLD_SQL_MODE */;
/*!40014 SET FOREIGN_KEY_CHECKS=@OLD_FOREIGN_KEY_CHECKS */;
/*!40014 SET UNIQUE_CHECKS=@OLD_UNIQUE_CHECKS */;
/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
/*!40111 SET SQL_NOTES=@OLD_SQL_NOTES */;

-- Dump completed on 2026-10-06  2:39:14
-- MySQL dump 10.13  Distrib 8.4.11, for Linux (x86_64)
--
-- Host: localhost    Database: siswa_data
-- ------------------------------------------------------
-- Server version	8.4.11

/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!50503 SET NAMES utf8mb4 */;
/*!40103 SET @OLD_TIME_ZONE=@@TIME_ZONE */;
/*!40103 SET TIME_ZONE='+00:00' */;
/*!40014 SET @OLD_UNIQUE_CHECKS=@@UNIQUE_CHECKS, UNIQUE_CHECKS=0 */;
/*!40014 SET @OLD_FOREIGN_KEY_CHECKS=@@FOREIGN_KEY_CHECKS, FOREIGN_KEY_CHECKS=0 */;
/*!40101 SET @OLD_SQL_MODE=@@SQL_MODE, SQL_MODE='NO_AUTO_VALUE_ON_ZERO' */;
/*!40111 SET @OLD_SQL_NOTES=@@SQL_NOTES, SQL_NOTES=0 */;

--
-- Dumping data for table `cms_posts`
--
-- WHERE:  slug IN ('ekstrakurikuler-robotika-tambah-kelas-baru','kegiatan-literasi-digital-untuk-orang-tua','penerimaan-peserta-didik-baru-20262027-resmi-dibuka','penerimaan-peserta-didik-baru-tahun-20262027','tim-sains-raih-juara-tingkat-provinsi')

/*!40000 ALTER TABLE `cms_posts` DISABLE KEYS */;
INSERT  IGNORE INTO `cms_posts` (`id`, `kind`, `title`, `slug`, `body`, `excerpt`, `status`, `author_id`, `category_id`, `published_at`, `scheduled_for`, `parent_id`, `sort_order`, `blocks`, `meta_title`, `meta_description`, `meta_image`, `is_public`, `public_from`, `public_until`, `created_at`, `updated_at`, `deleted_at`) VALUES (1,'post','Penerimaan Peserta Didik Baru 2026/2027 Resmi Dibuka','penerimaan-peserta-didik-baru-20262027-resmi-dibuka','Sekolah membuka pendaftaran peserta didik baru untuk tahun ajaran 2026/2027. Pendaftaran dilakukan secara online dan berkas diverifikasi oleh tim admisi.','Sekolah membuka pendaftaran peserta didik baru untuk tahun ajaran 2026/2027. Pendaftaran dilakukan secara online dan berkas diverifikasi ole...','published',5,1,'2026-10-05 13:20:11',NULL,NULL,0,NULL,NULL,NULL,NULL,1,NULL,NULL,'2026-10-05 13:20:11','2026-10-05 13:20:11',NULL),(2,'post','Tim Sains Raih Juara Tingkat Provinsi','tim-sains-raih-juara-tingkat-provinsi','Tim sains sekolah meraih medali pada lomba tingkat provinsi cabang mata pelajaran yang diikuti sekolah-sekolah dari seluruh daerah.','Tim sains sekolah meraih medali pada lomba tingkat provinsi cabang mata pelajaran yang diikuti sekolah-sekolah dari seluruh daerah.','published',5,1,'2026-10-01 13:20:11',NULL,NULL,0,NULL,NULL,NULL,NULL,1,NULL,NULL,'2026-10-05 13:20:11','2026-10-05 13:20:11',NULL),(3,'post','Ekstrakurikuler Robotika Tambah Kelas Baru','ekstrakurikuler-robotika-tambah-kelas-baru','Minat siswa terhadap robotika terus meningkat. Kelas baru dibuka untuk siswa kelas X dan XI dengan pembimbing dari alumni Teknik Elektro.','Minat siswa terhadap robotika terus meningkat. Kelas baru dibuka untuk siswa kelas X dan XI dengan pembimbing dari alumni Teknik Elektro.','published',5,1,'2026-09-27 13:20:11',NULL,NULL,0,NULL,NULL,NULL,NULL,1,NULL,NULL,'2026-10-05 13:20:11','2026-10-05 13:20:11',NULL),(4,'post','Kegiatan Literasi Digital untuk Orang Tua','kegiatan-literasi-digital-untuk-orang-tua','Sekolah mengadakan kegiatan literasi digital bagi orang tua guna mendampingi anak belajar di rumah.','Sekolah mengadakan kegiatan literasi digital bagi orang tua guna mendampingi anak belajar di rumah.','published',5,1,'2026-09-23 13:20:11',NULL,NULL,0,NULL,NULL,NULL,NULL,1,NULL,NULL,'2026-10-05 13:20:11','2026-10-05 13:20:11',NULL),(6,'post','Penerimaan Peserta Didik Baru Tahun 2026/2027','penerimaan-peserta-didik-baru-tahun-20262027','<p>Pendaftaran peserta didik baru dibuka mulai 1 Juni hingga 30 Juni. Calon peserta didik dapat mendaftar melalui portal sekolah atau langsung ke ruang tata usaha pada jam kerja.</p><p>Berkas yang disiapkan: akta kelahiran, kartu keluarga, ijazah atau SKL, dan pas foto.</p>','Pendaftaran peserta didik baru dibuka mulai 1 Juni hingga 30 Juni. Calon peserta didik dapat mendaftar melalui portal sekolah atau langsung ke ruang tata usaha pada jam…','published',NULL,2,'2026-10-06 00:00:28',NULL,NULL,0,NULL,NULL,NULL,NULL,1,NULL,NULL,'2026-10-06 00:00:28','2026-10-06 00:00:28',NULL);
/*!40000 ALTER TABLE `cms_posts` ENABLE KEYS */;
/*!40103 SET TIME_ZONE=@OLD_TIME_ZONE */;

/*!40101 SET SQL_MODE=@OLD_SQL_MODE */;
/*!40014 SET FOREIGN_KEY_CHECKS=@OLD_FOREIGN_KEY_CHECKS */;
/*!40014 SET UNIQUE_CHECKS=@OLD_UNIQUE_CHECKS */;
/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
/*!40111 SET SQL_NOTES=@OLD_SQL_NOTES */;

-- Dump completed on 2026-10-06  2:39:15
-- MySQL dump 10.13  Distrib 8.4.11, for Linux (x86_64)
--
-- Host: localhost    Database: siswa_data
-- ------------------------------------------------------
-- Server version	8.4.11

/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!50503 SET NAMES utf8mb4 */;
/*!40103 SET @OLD_TIME_ZONE=@@TIME_ZONE */;
/*!40103 SET TIME_ZONE='+00:00' */;
/*!40014 SET @OLD_UNIQUE_CHECKS=@@UNIQUE_CHECKS, UNIQUE_CHECKS=0 */;
/*!40014 SET @OLD_FOREIGN_KEY_CHECKS=@@FOREIGN_KEY_CHECKS, FOREIGN_KEY_CHECKS=0 */;
/*!40101 SET @OLD_SQL_MODE=@@SQL_MODE, SQL_MODE='NO_AUTO_VALUE_ON_ZERO' */;
/*!40111 SET @OLD_SQL_NOTES=@@SQL_NOTES, SQL_NOTES=0 */;

--
-- Dumping data for table `cms_revisions`
--
-- WHERE:  post_id IN (SELECT id FROM cms_posts WHERE slug IN ('ekstrakurikuler-robotika-tambah-kelas-baru','kegiatan-literasi-digital-untuk-orang-tua','penerimaan-peserta-didik-baru-20262027-resmi-dibuka','penerimaan-peserta-didik-baru-tahun-20262027','tim-sains-raih-juara-tingkat-provinsi'))

-- MySQL dump 10.13  Distrib 8.4.11, for Linux (x86_64)
--
-- Host: localhost    Database: siswa_data
-- ------------------------------------------------------
-- Server version	8.4.11

/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!50503 SET NAMES utf8mb4 */;
/*!40103 SET @OLD_TIME_ZONE=@@TIME_ZONE */;
/*!40103 SET TIME_ZONE='+00:00' */;
/*!40014 SET @OLD_UNIQUE_CHECKS=@@UNIQUE_CHECKS, UNIQUE_CHECKS=0 */;
/*!40014 SET @OLD_FOREIGN_KEY_CHECKS=@@FOREIGN_KEY_CHECKS, FOREIGN_KEY_CHECKS=0 */;
/*!40101 SET @OLD_SQL_MODE=@@SQL_MODE, SQL_MODE='NO_AUTO_VALUE_ON_ZERO' */;
/*!40111 SET @OLD_SQL_NOTES=@@SQL_NOTES, SQL_NOTES=0 */;

--
-- Dumping data for table `cms_media`
--
-- WHERE:  1=1

/*!40000 ALTER TABLE `cms_media` DISABLE KEYS */;
INSERT  IGNORE INTO `cms_media` (`id`, `disk`, `path`, `original_name`, `mime`, `size`, `width`, `height`, `alt_text`, `caption`, `uploaded_by`, `created_at`, `updated_at`, `deleted_at`) VALUES (1,'public','images/school/students-walking-courtyard.webp','students-walking-courtyard.webp','image/webp',118408,NULL,NULL,'Empat siswa berseragam berjalan bersama di halaman sekolah, tersenyum','Siswa LYFLA di halaman sekolah',NULL,'2026-10-05 12:23:21','2026-10-05 12:23:21',NULL),(2,'public','branding/lyfla-building.png','lyfla-building.png','image/png',1897062,NULL,NULL,'Gedung sekolah LYFLA',NULL,NULL,'2026-10-05 12:25:38','2026-10-05 12:25:38',NULL);
/*!40000 ALTER TABLE `cms_media` ENABLE KEYS */;
/*!40103 SET TIME_ZONE=@OLD_TIME_ZONE */;

/*!40101 SET SQL_MODE=@OLD_SQL_MODE */;
/*!40014 SET FOREIGN_KEY_CHECKS=@OLD_FOREIGN_KEY_CHECKS */;
/*!40014 SET UNIQUE_CHECKS=@OLD_UNIQUE_CHECKS */;
/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
/*!40111 SET SQL_NOTES=@OLD_SQL_NOTES */;

-- Dump completed on 2026-10-06  2:39:16
-- MySQL dump 10.13  Distrib 8.4.11, for Linux (x86_64)
--
-- Host: localhost    Database: siswa_data
-- ------------------------------------------------------
-- Server version	8.4.11

/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!50503 SET NAMES utf8mb4 */;
/*!40103 SET @OLD_TIME_ZONE=@@TIME_ZONE */;
/*!40103 SET TIME_ZONE='+00:00' */;
/*!40014 SET @OLD_UNIQUE_CHECKS=@@UNIQUE_CHECKS, UNIQUE_CHECKS=0 */;
/*!40014 SET @OLD_FOREIGN_KEY_CHECKS=@@FOREIGN_KEY_CHECKS, FOREIGN_KEY_CHECKS=0 */;
/*!40101 SET @OLD_SQL_MODE=@@SQL_MODE, SQL_MODE='NO_AUTO_VALUE_ON_ZERO' */;
/*!40111 SET @OLD_SQL_NOTES=@@SQL_NOTES, SQL_NOTES=0 */;

--
-- Dumping data for table `cms_post_media`
--
-- WHERE:  post_id IN (SELECT id FROM cms_posts WHERE slug IN ('ekstrakurikuler-robotika-tambah-kelas-baru','kegiatan-literasi-digital-untuk-orang-tua','penerimaan-peserta-didik-baru-20262027-resmi-dibuka','penerimaan-peserta-didik-baru-tahun-20262027','tim-sains-raih-juara-tingkat-provinsi'))

-- MySQL dump 10.13  Distrib 8.4.11, for Linux (x86_64)
--
-- Host: localhost    Database: siswa_data
-- ------------------------------------------------------
-- Server version	8.4.11

/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!50503 SET NAMES utf8mb4 */;
/*!40103 SET @OLD_TIME_ZONE=@@TIME_ZONE */;
/*!40103 SET TIME_ZONE='+00:00' */;
/*!40014 SET @OLD_UNIQUE_CHECKS=@@UNIQUE_CHECKS, UNIQUE_CHECKS=0 */;
/*!40014 SET @OLD_FOREIGN_KEY_CHECKS=@@FOREIGN_KEY_CHECKS, FOREIGN_KEY_CHECKS=0 */;
/*!40101 SET @OLD_SQL_MODE=@@SQL_MODE, SQL_MODE='NO_AUTO_VALUE_ON_ZERO' */;
/*!40111 SET @OLD_SQL_NOTES=@@SQL_NOTES, SQL_NOTES=0 */;

--
-- Dumping data for table `cms_post_tag`
--
-- WHERE:  post_id IN (SELECT id FROM cms_posts WHERE slug IN ('ekstrakurikuler-robotika-tambah-kelas-baru','kegiatan-literasi-digital-untuk-orang-tua','penerimaan-peserta-didik-baru-20262027-resmi-dibuka','penerimaan-peserta-didik-baru-tahun-20262027','tim-sains-raih-juara-tingkat-provinsi'))

-- MySQL dump 10.13  Distrib 8.4.11, for Linux (x86_64)
--
-- Host: localhost    Database: siswa_data
-- ------------------------------------------------------
-- Server version	8.4.11

/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!50503 SET NAMES utf8mb4 */;
/*!40103 SET @OLD_TIME_ZONE=@@TIME_ZONE */;
/*!40103 SET TIME_ZONE='+00:00' */;
/*!40014 SET @OLD_UNIQUE_CHECKS=@@UNIQUE_CHECKS, UNIQUE_CHECKS=0 */;
/*!40014 SET @OLD_FOREIGN_KEY_CHECKS=@@FOREIGN_KEY_CHECKS, FOREIGN_KEY_CHECKS=0 */;
/*!40101 SET @OLD_SQL_MODE=@@SQL_MODE, SQL_MODE='NO_AUTO_VALUE_ON_ZERO' */;
/*!40111 SET @OLD_SQL_NOTES=@@SQL_NOTES, SQL_NOTES=0 */;

--
-- Dumping data for table `landing_sections`
--
-- WHERE:  1=1

/*!40000 ALTER TABLE `landing_sections` DISABLE KEYS */;
INSERT  IGNORE INTO `landing_sections` (`id`, `page_key`, `type`, `title`, `subtitle`, `body`, `media_id`, `content`, `is_enabled`, `position`, `created_at`, `updated_at`) VALUES (1,'home','hero','Belajar Hari Ini. Memimpin Esok Hari.','Penerimaan Peserta Didik Baru 2026/2027','Lingkungan belajar yang mendukung siswa berkembang secara akademik, kreatif, dan berkarakter — dengan pendampingan yang individual dan berkelanjutan.',1,'{\"stats\": [{\"label\": \"Siswa Aktif\", \"value\": \"412\"}, {\"label\": \"Guru & Tenaga Pendidik\", \"value\": \"48\", \"suffix\": \"+\"}, {\"label\": \"Ekstrakurikuler\", \"value\": \"12\", \"suffix\": \"+\"}, {\"label\": \"Prestasi\", \"value\": \"27\", \"suffix\": \"+\"}], \"cta_url\": \"/ppdb\", \"cta_label\": \"Daftar Sekarang\", \"secondary_cta_url\": \"/tentang\", \"secondary_cta_label\": \"Jelajahi Sekolah\"}',1,0,'2026-10-05 08:23:55','2026-10-06 02:38:24'),(2,'home','trust',NULL,NULL,NULL,NULL,'{\"items\": [{\"icon\": \"badge-check\", \"label\": \"Terakreditasi A\"}, {\"icon\": \"book-open\", \"label\": \"Kurikulum Nasional\"}, {\"icon\": \"shield-check\", \"label\": \"Lingkungan Aman\"}, {\"icon\": \"sparkles\", \"label\": \"Program Unggulan\"}, {\"icon\": \"trophy\", \"label\": \"Prestasi Akademik & Non-Akademik\"}]}',1,1,'2026-10-05 08:23:55','2026-10-05 08:23:55'),(3,'home','about','Lebih dari Sekadar Tempat Belajar','Tentang Sekolah','Kami percaya pendidikan yang baik tidak hanya mengejar nilai. Setiap siswa dikenal secara pribadi, dibimbing sesuai kekuatannya, dan diberi ruang untuk tumbuh.\n\nSekolah kami menggabungkan kurikulum nasional dengan program unggulan yang relevan dengan kebutuhan abad ke-21.',NULL,'{\"cta_url\": \"/tentang\", \"cta_label\": \"Kenali Sekolah Kami\", \"highlight_label\": \"Prestasi sekolah\", \"highlight_value\": \"27+\"}',1,2,'2026-10-05 08:23:55','2026-10-05 08:23:55'),(4,'home','features','Kenapa Memilih Kami?','Keunggulan',NULL,NULL,'{\"items\": [{\"body\": \"Pembelajaran terarah dengan dukungan guru berpengalaman.\", \"icon\": \"graduation-cap\", \"title\": \"Akademik Unggul\"}, {\"body\": \"Pembinaan siswa tidak hanya fokus pada nilai.\", \"icon\": \"heart-handshake\", \"title\": \"Pengembangan Karakter\"}, {\"body\": \"Kreativitas, teknologi, komunikasi, dan kolaborasi.\", \"icon\": \"lightbulb\", \"title\": \"Keterampilan Masa Depan\"}, {\"body\": \"Ruang belajar aman dan suportif.\", \"icon\": \"users\", \"title\": \"Lingkungan Positif\"}, {\"body\": \"Fasilitas untuk mendukung proses belajar dan eksplorasi.\", \"icon\": \"building-2\", \"title\": \"Fasilitas Lengkap\"}, {\"body\": \"Siswa didorong berkembang melalui organisasi dan kegiatan.\", \"icon\": \"handshake\", \"title\": \"Komunitas Aktif\"}]}',1,3,'2026-10-05 08:23:55','2026-10-05 08:23:55'),(5,'home','programs','Program Unggulan','Pilihan Program',NULL,NULL,'{\"items\": [{\"body\": \"Pembelajaran berbasis riset sejak kelas SMP, dengan pembimbing dari praktisi dan Akademi.\", \"icon\": \"flask-conical\", \"title\": \"Science & Research\", \"cta_url\": \"/program\", \"eyebrow\": \"Program Unggulan\", \"cta_label\": \"Pelajari Program\"}, {\"body\": \"Literasi digital dan pemrograman sebagai keterampilan dasar.\", \"icon\": \"monitor\", \"title\": \"Digital Learning\"}, {\"body\": \"Penguatan bahasa Inggris melalui pendekatan imersif dan communicative.\", \"icon\": \"languages\", \"title\": \"Language Program\"}, {\"body\": \"Kaderisasi untuk organisasi siswa dan komunikasi.\", \"icon\": \"crown\", \"title\": \"Leadership\"}, {\"body\": \"Musik, seni visual, dan panggung sebagai ruang ekspresi.\", \"icon\": \"palette\", \"title\": \"Creative Arts\"}, {\"body\": \"Pembinaan atlet berprestasi dengan program latihan terstruktur.\", \"icon\": \"trophy\", \"title\": \"Sports Development\"}], \"cta_url\": \"/program\", \"cta_label\": \"Lihat Semua Program\"}',1,4,'2026-10-05 08:23:55','2026-10-05 08:23:55'),(6,'home','experience','Lebih Banyak Hal untuk Ditemukan','Kehidupan Siswa','Sekolah bukan hanya jadwal pelajaran. Ini tempat siswa menemukan minatnya, berbagi dengan teman, dan belajar bersama.',NULL,'{\"items\": [{\"body\": \"Panggung, musik, dan seni visual.\", \"icon\": \"palette\", \"image\": \"/images/school/staff-meeting-tablet.webp\", \"title\": \"Seni & Kreativitas\", \"image_alt\": \"Tiga guru berdiskusi di ruang rapat\"}, {\"body\": \"Belajar membangun dan memprogram.\", \"icon\": \"bot\", \"image\": \"/images/school/computer-lab-class.webp\", \"title\": \"Robotik & Teknologi\", \"image_alt\": \"Siswa belajar di laboratorium komputer bersama guru\"}, {\"body\": \" tim dan kompetisi dan latihan rutin terstruktur.\", \"icon\": \"dumbbell\", \"image\": \"/images/school/students-walking-courtyard.webp\", \"title\": \"Olahraga\", \"image_alt\": \"Empat siswa berseragam berjalan bersama di halaman sekolah, tersenyum\"}, {\"body\": \"English Day dan pertukaran budaya.\", \"icon\": \"globe-2\", \"image\": \"/images/school/students-library-tablet.webp\", \"title\": \"Bahasa & Budaya\", \"image_alt\": \"Siswa berdiskusi di perpustakaan dengan laptop dan tablet\"}, {\"body\": \"OSIS, pramuka, dan majalah siswa.\", \"icon\": \"users\", \"image\": \"/images/school/campus-entry-checkpoint.webp\", \"title\": \"Organisasi Siswa\", \"image_alt\": \"Siswa absen di pintu masuk sekolah dengan petugas\"}, {\"body\": \"Bakti sosial dan kegiatan peduli sosial.\", \"icon\": \"heart\", \"image\": \"/images/school/teacher-guidance-classroom.webp\", \"title\": \"Kegiatan Sosial\", \"image_alt\": \"Guru mendampingi tiga siswa di kelas\"}]}',1,5,'2026-10-05 08:23:55','2026-10-05 12:25:38'),(7,'home','achievements','Prestasi yang Membanggakan','Capaian',NULL,NULL,'{\"cards\": [], \"stats\": [{\"label\": \"Prestasi\", \"value\": \"27\", \"suffix\": \"+\"}, {\"label\": \"Tingkat Kabupaten\", \"value\": \"14\"}, {\"label\": \"Tingkat Provinsi\", \"value\": \"9\"}, {\"label\": \"Tingkat Nasional\", \"value\": \"4\"}]}',1,6,'2026-10-05 08:23:55','2026-10-05 08:23:55'),(8,'home','facilities','Ruang untuk Belajar dan Berkembang','Fasilitas',NULL,NULL,'{\"items\": [{\"body\": \"Peralatan praktikum untuk eksperimen biologi, fisika, dan kimia.\", \"icon\": \"flask-conical\", \"image\": \"/images/school/computer-lab-class.webp\", \"title\": \"Laboratorium Sains\", \"image_alt\": \"Siswa belajar di laboratorium komputer bersama guru\"}, {\"body\": \"Koleksi cetak dan digital dengan ruang baca tenang.\", \"icon\": \"book-open\", \"image\": \"/images/school/students-library-tablet.webp\", \"title\": \"Perpustakaan\", \"image_alt\": \"Siswa berdiskusi di perpustakaan dengan laptop dan tablet\"}, {\"body\": \"Lab komputer dengan koneksi berkecepatan tinggi.\", \"icon\": \"monitor\", \"image\": \"/images/school/computer-lab-class.webp\", \"title\": \"Laboratorium Komputer\", \"image_alt\": \"Siswa belajar di laboratorium komputer bersama guru\"}, {\"body\": \"Ruang untuk rapat, pentas, dan kegiatan sekolah.\", \"icon\": \"presentation\", \"image\": \"/images/school/teacher-guidance-classroom.webp\", \"title\": \"Aula Serbaguna\", \"image_alt\": \"Guru mendampingi tiga siswa di kelas\"}, {\"body\": \"Lapangan basket, futsal, dan voli.\", \"icon\": \"dumbbell\", \"image\": \"/images/school/students-walking-courtyard.webp\", \"title\": \"Lapangan Olahraga\", \"image_alt\": \"Empat siswa berseragam berjalan bersama di halaman sekolah, tersenyum\"}, {\"body\": \"Ruang latihan dan perekaman untuk siswa.\", \"icon\": \"music\", \"image\": \"/images/school/students-library-tablet.webp\", \"title\": \"Studio Musik\", \"image_alt\": \"Siswa berdiskusi di perpustakaan dengan laptop dan tablet\"}]}',1,7,'2026-10-05 08:23:55','2026-10-05 12:21:45'),(9,'home','testimonials','Cerita dari Mereka','Testimoni',NULL,NULL,'{\"items\": [{\"name\": \"Nama Siswa\", \"role\": \"Siswa Kelas XI\", \"quote\": \"Di sini saya belajar bahwa sukses bukan soal tercepat, tapi tentang kesediaan untuk berusaha.\"}, {\"name\": \"Nama Orang Tua\", \"role\": \"Orang Tua Siswa\", \"quote\": \"Guru-gurunya sabar dan tidak pernah bosan menjelaskan sampai kita paham.\"}, {\"name\": \"Nama Alumni\", \"role\": \"Alumni Jurusan 2021\", \"quote\": \"Ilmu yang saya dapat di sini menjadi berguna untuk kuliah.\"}], \"is_sample\": true}',1,8,'2026-10-05 08:23:55','2026-10-05 08:23:55'),(10,'home','news','Berita & Kegiatan','Kabar Terbaru',NULL,NULL,'{\"items\": [], \"cta_url\": \"/berita\", \"cta_label\": \"Lihat Semua Berita\"}',1,9,'2026-10-05 08:23:55','2026-10-05 08:23:55'),(11,'home','journey','Perjalanan Bersama Kami','Alur Pendidikan',NULL,NULL,'{\"items\": [{\"body\": \"Pendaftaran dan orientasi\", \"icon\": \"log-in\", \"title\": \"Masuk\"}, {\"body\": \"Pembelajaran terarah\", \"icon\": \"book-open\", \"title\": \"Belajar\"}, {\"body\": \"Karakter dan keterampilan\", \"icon\": \"trending-up\", \"title\": \"Berkembang\"}, {\"body\": \"Kompetisi dan pengakuan\", \"icon\": \"trophy\", \"title\": \"Berprestasi\"}, {\"body\": \"Kuliah dan karier\", \"icon\": \"rocket\", \"title\": \"Melanjutkan\"}]}',1,10,'2026-10-05 08:23:55','2026-10-05 08:23:55'),(12,'home','ppdb','Siap Memulai Perjalananmu Bersama Kami?','PPDB 2026/2027','Penerimaan Peserta Didik Baru kini dibuka. Ini tempat belajar anak yang suportif dan berkarakter.',2,'{\"period\": \"Periode pendaftaran: 1 November – 31 Desember 2026\", \"cta_url\": \"/ppdb\", \"cta_label\": \"Daftar Sekarang\", \"highlights\": [{\"icon\": \"clipboard-list\", \"label\": \"Syarat utama: ijazah SKL, akta kelahiran, dan rapor\"}, {\"icon\": \"wallet\", \"label\": \"Tersedia jalur beasiswa prestasi dan bantuan sosial\"}, {\"icon\": \"info\", \"label\": \"Jadwal tes dan pengumuman dikirim via surel terdaftar\"}], \"secondary_cta_url\": \"/ppdb\", \"secondary_cta_label\": \"Lihat Informasi PPDB\"}',1,11,'2026-10-05 08:23:55','2026-10-06 02:38:25'),(13,'home','faq','Pertanyaan yang Sering Diajukan','FAQ',NULL,NULL,'{\"items\": [{\"answer\": \"Isi formulir pendaftaran secara online melalui halaman PPDB, unggah dokumen yang diminta, lalu lakukan verifikasi berkas.\", \"question\": \"Bagaimana cara mendaftar?\"}, {\"answer\": \"Kami menawarkan program akademik reguler, Science & Research, Digital Learning, dan Language Program.\", \"question\": \"Apa saja program yang tersedia?\"}, {\"answer\": \"Ada jalur beasiswa prestasi akademik maupun non-akademik. Informasi lengkap tersedia di halaman PPDB.\", \"question\": \"Apakah ada beasiswa?\"}, {\"answer\": \"Organisasi siswa, pramuka, robotik, seni, olahraga, dan kegiatan keagamaan. Pendaftaran dilakukan tiap awal tahun ajaran.\", \"question\": \"Apa saja ekstrakurikulernya?\"}, {\"answer\": \"Jadwal lengkap dan pengumuman setiap tahap diumumkan pada halaman PPDB dan dikirim ke surel pendaftar.\", \"question\": \"Bagaimana melihat jadwal PPDB?\"}]}',1,12,'2026-10-05 08:23:55','2026-10-05 08:23:55'),(14,'home','cta','Masa Depan Dimulai dari Pilihan Hari Ini.',NULL,'Banyak keluarga sudah mempercayakan tempat belajar anak mereka di sini. Giliran Anda.',NULL,'{\"cta_url\": \"/ppdb\", \"cta_label\": \"Daftar Sekarang\", \"secondary_cta_url\": \"/kontak\", \"secondary_cta_label\": \"Hubungi Kami\"}',1,13,'2026-10-05 08:23:55','2026-10-05 08:23:55');
/*!40000 ALTER TABLE `landing_sections` ENABLE KEYS */;
/*!40103 SET TIME_ZONE=@OLD_TIME_ZONE */;

/*!40101 SET SQL_MODE=@OLD_SQL_MODE */;
/*!40014 SET FOREIGN_KEY_CHECKS=@OLD_FOREIGN_KEY_CHECKS */;
/*!40014 SET UNIQUE_CHECKS=@OLD_UNIQUE_CHECKS */;
/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
/*!40111 SET SQL_NOTES=@OLD_SQL_NOTES */;

-- Dump completed on 2026-10-06  2:39:18
-- MySQL dump 10.13  Distrib 8.4.11, for Linux (x86_64)
--
-- Host: localhost    Database: siswa_data
-- ------------------------------------------------------
-- Server version	8.4.11

/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!50503 SET NAMES utf8mb4 */;
/*!40103 SET @OLD_TIME_ZONE=@@TIME_ZONE */;
/*!40103 SET TIME_ZONE='+00:00' */;
/*!40014 SET @OLD_UNIQUE_CHECKS=@@UNIQUE_CHECKS, UNIQUE_CHECKS=0 */;
/*!40014 SET @OLD_FOREIGN_KEY_CHECKS=@@FOREIGN_KEY_CHECKS, FOREIGN_KEY_CHECKS=0 */;
/*!40101 SET @OLD_SQL_MODE=@@SQL_MODE, SQL_MODE='NO_AUTO_VALUE_ON_ZERO' */;
/*!40111 SET @OLD_SQL_NOTES=@@SQL_NOTES, SQL_NOTES=0 */;

--
-- Dumping data for table `alumni`
--
-- WHERE:  student_id IN (SELECT id FROM students WHERE user_id IN (SELECT id FROM users WHERE email LIKE '%@demo.test'))


SET UNIQUE_CHECKS=1;
SET FOREIGN_KEY_CHECKS=1;
